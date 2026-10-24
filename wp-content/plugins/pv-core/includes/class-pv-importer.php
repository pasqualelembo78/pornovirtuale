<?php
defined( 'ABSPATH' ) || exit;

interface PV_Import_Source {
	public function get_slug();
	public function get_name();
	public function is_available();
	public function get_status_message();
	public function fetch( $limit );
}

class PV_Importer {
	const META_EXTERNAL_ID = '_pv_external_id';
	const META_SOURCE      = '_pv_import_source';
	const META_SOURCE_URL  = '_pv_source_url';

	public static function get_sources() {
		$sources = array();
		$sources['youporn'] = new PV_Import_Source_YouPorn();
		return apply_filters( 'pv_import_sources', $sources );
	}

	public static function run( $args = array() ) {
		$args = wp_parse_args( $args, array( 'manual' => false, 'limit' => 0, 'force' => false ) );
		$opts = get_option( PV_Automation::OPTION, PV_Automation::defaults() );
		if ( ! is_array( $opts ) ) {
			$opts = PV_Automation::defaults();
		}
		if ( ! $args['manual'] && empty( $opts['enabled'] ) ) {
			return array( 'status' => 'skipped', 'message' => __( 'Automazione disabilitata.', 'pornovirtuale' ) );
		}
		$limit = (int) $args['limit'];
		if ( $limit <= 0 ) {
			$limit = (int) ( $opts['max_per_run'] ?? 10 );
		}
		if ( $limit <= 0 ) {
			$limit = 10;
		}
		$sources = self::get_sources();
		$results = array( 'imported' => 0, 'duplicates' => 0, 'errors' => 0, 'items' => array() );
		foreach ( $sources as $slug => $source ) {
			if ( empty( $opts['sources'][ $slug ]['enabled'] ) ) {
				continue;
			}
			if ( ! $source->is_available() ) {
				$results['errors']++;
				$results['items'][] = array( 'source' => $slug, 'status' => 'error', 'message' => $source->get_status_message() );
				continue;
			}
			$items = array();
			try {
				$items = $source->fetch( $limit );
			} catch ( Exception $e ) {
				$results['errors']++;
				$results['items'][] = array( 'source' => $slug, 'status' => 'error', 'message' => __( 'Errore durante il fetch.', 'pornovirtuale' ) );
				continue;
			}
			if ( ! is_array( $items ) ) {
				$items = array();
			}
			foreach ( $items as $item ) {
				if ( $results['imported'] >= $limit ) {
					break;
				}
				$res = self::import_item( $item, $opts, $slug );
				if ( 'imported' === $res['status'] ) {
					$results['imported']++;
				} elseif ( 'duplicate' === $res['status'] ) {
					$results['duplicates']++;
				} else {
					$results['errors']++;
				}
				$results['items'][] = array_merge( array( 'source' => $slug ), $res );
			}
			if ( $results['imported'] >= $limit ) {
				break;
			}
		}
		self::log_run( $results, $opts, $args['manual'] );
		PV_Automation::update_last_run( $results );
		return array( 'status' => 'ok', 'results' => $results );
	}

	public static function import_item( $item, $opts, $source_slug ) {
		$item = wp_parse_args( $item, array( 'external_id' => '', 'title' => '', 'description' => '', 'thumbnail' => '', 'category' => '', 'embed_url' => '', 'embed_code' => '', 'source_url' => '' ) );
		$title = sanitize_text_field( $item['title'] );
		if ( '' === $title ) {
			return array( 'status' => 'error', 'message' => __( 'Titolo mancante.', 'pornovirtuale' ) );
		}
		$external_id = sanitize_text_field( $item['external_id'] );
		$source_url  = esc_url_raw( $item['source_url'] );
		if ( '' === $source_url && ! empty( $item['embed_url'] ) ) {
			$source_url = esc_url_raw( $item['embed_url'] );
		}
		if ( $external_id ) {
			$existing = get_posts( array( 'post_type' => 'video', 'post_status' => 'any', 'numberposts' => 1, 'meta_key' => self::META_EXTERNAL_ID, 'meta_value' => $external_id, 'fields' => 'ids' ) );
			if ( ! empty( $existing ) ) {
				return array( 'status' => 'duplicate', 'message' => __( 'Duplicato per ID esterno.', 'pornovirtuale' ) );
			}
		}
		if ( $source_url ) {
			$existing = get_posts( array( 'post_type' => 'video', 'post_status' => 'any', 'numberposts' => 1, 'meta_key' => self::META_SOURCE_URL, 'meta_value' => $source_url, 'fields' => 'ids' ) );
			if ( ! empty( $existing ) ) {
				return array( 'status' => 'duplicate', 'message' => __( 'Duplicato per URL sorgente.', 'pornovirtuale' ) );
			}
		}
		$post_status = 'draft';
		if ( ! empty( $opts['auto_publish'] ) ) {
			$post_status = 'publish';
		}
		$post_id = wp_insert_post( array( 'post_type' => 'video', 'post_title' => $title, 'post_content' => wp_kses_post( $item['description'] ), 'post_status' => $post_status ), true );
		if ( is_wp_error( $post_id ) ) {
			return array( 'status' => 'error', 'message' => __( 'Errore creazione post.', 'pornovirtuale' ) );
		}
		if ( $external_id ) {
			update_post_meta( $post_id, self::META_EXTERNAL_ID, $external_id );
		}
		if ( $source_url ) {
			update_post_meta( $post_id, self::META_SOURCE_URL, $source_url );
		}
		update_post_meta( $post_id, self::META_SOURCE, sanitize_key( $source_slug ) );
		if ( ! empty( $item['embed_url'] ) ) {
			update_post_meta( $post_id, '_pv_embed_url', esc_url_raw( $item['embed_url'] ) );
			update_post_meta( $post_id, '_pv_source', 'embed' );
		}
		if ( ! empty( $item['embed_code'] ) ) {
			update_post_meta( $post_id, '_pv_embed_code', wp_kses_post( $item['embed_code'] ) );
			update_post_meta( $post_id, '_pv_source', 'custom' );
		}
		$cat = $opts['default_category'] ?? '';
		if ( ! empty( $cat ) ) {
			$term = get_term_by( 'name', $cat, 'pv_categoria' );
			if ( ! $term ) {
				$term = get_term_by( 'slug', sanitize_title( $cat ), 'pv_categoria' );
			}
			if ( $term && ! is_wp_error( $term ) ) {
				wp_set_object_terms( $post_id, (int) $term->term_id, 'pv_categoria' );
			}
		}
		if ( ! empty( $item['thumbnail'] ) ) {
			do_action( 'pv_import_after_thumbnail_check', $post_id, esc_url_raw( $item['thumbnail'] ), $item );
		}
		return array( 'status' => 'imported', 'post_id' => $post_id, 'status_post' => $post_status );
	}

	public static function log_run( $results, $opts, $manual ) {
		$log = array( 'time' => current_time( 'mysql' ), 'manual' => (bool) $manual, 'results' => $results );
		$existing = get_option( PV_Automation::LOG_OPTION, array() );
		if ( ! is_array( $existing ) ) {
			$existing = array();
		}
		array_unshift( $existing, $log );
		if ( count( $existing ) > 20 ) {
			$existing = array_slice( $existing, 0, 20 );
		}
		update_option( PV_Automation::LOG_OPTION, $existing, false );
	}
}

class PV_Import_Source_YouPorn implements PV_Import_Source {
	public function get_slug() { return 'youporn'; }
	public function get_name() { return 'YouPorn'; }
	public function is_available() { return false; }
	public function get_status_message() { return __( "Disabilitata: nessun metodo ufficiale verificabile per import automatico autorizzato. Verificare documentazione ufficiale YouPorn/termini d'uso prima di attivare.", 'pornovirtuale' ); }
	public function fetch( $limit ) { return array(); }
}
