<?php
defined( 'ABSPATH' ) || exit;

class PV_Bulk_Importer {
	const OPTION = 'pv_bulk_import';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_actions' ) );
	}

	public static function admin_menu() {
		add_submenu_page(
			'pv-core-dashboard',
			__( 'Importa URL / CSV', 'pornovirtuale' ),
			__( 'Importa URL / CSV', 'pornovirtuale' ),
			'manage_options',
			'pv-bulk-import',
			array( __CLASS__, 'render_page' )
		);
	}

	public static function handle_actions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( isset( $_POST['pv_bulk_import_urls'] ) ) {
			check_admin_referer( 'pv_bulk_import_urls' );
			$urls_raw = sanitize_textarea_field( wp_unslash( $_POST['import_urls'] ?? '' ) );
			$category = sanitize_text_field( wp_unslash( $_POST['import_category'] ?? '' ) );
			$urls = array_filter( array_map( 'trim', explode( "\n", $urls_raw ) ) );
			if ( empty( $urls ) ) {
				add_settings_error( 'pv_bulk_import', 'err', __( 'Inserisci almeno un URL.', 'pornovirtuale' ), 'error' );
				return;
			}
			$res = self::import_from_urls( $urls, $category );
			set_transient( 'pv_bulk_import_result', $res, 120 );
			wp_safe_redirect( add_query_arg( 'page', 'pv-bulk-import', admin_url( 'admin.php' ) ) );
			exit;
		}
		if ( isset( $_POST['pv_bulk_import_csv'] ) ) {
			check_admin_referer( 'pv_bulk_import_csv' );
			if ( ! isset( $_FILES['import_csv'] ) || $_FILES['import_csv']['error'] !== UPLOAD_ERR_OK ) {
				add_settings_error( 'pv_bulk_import', 'err', __( 'File CSV richiesto.', 'pornovirtuale' ), 'error' );
				return;
			}
			$category = sanitize_text_field( wp_unslash( $_POST['import_category_csv'] ?? '' ) );
			$res = self::import_from_csv( $_FILES['import_csv'], $category );
			set_transient( 'pv_bulk_import_result', $res, 120 );
			wp_safe_redirect( add_query_arg( 'page', 'pv-bulk-import', admin_url( 'admin.php' ) ) );
			exit;
		}
	}

	/**
	 * Importa da lista URL (uno per riga).
	 */
	public static function import_from_urls( $urls, $category = '' ) {
		$results = array( 'imported' => 0, 'duplicates' => 0, 'errors' => 0, 'items' => array() );
		foreach ( $urls as $url ) {
			$url = esc_url_raw( $url );
			if ( '' === $url ) { continue; }
			$item = self::extract_from_url( $url );
			if ( is_wp_error( $item ) ) {
				$results['errors']++;
				$results['items'][] = array( 'url' => $url, 'status' => 'error', 'message' => $item->get_error_message() );
				continue;
			}
			$res = PV_Importer::import_item( $item, get_option( PV_Automation::OPTION, PV_Automation::defaults() ), 'manual' );
			if ( 'imported' === $res['status'] ) {
				$results['imported']++;
				if ( $category ) {
					$term = get_term_by( 'name', $category, 'pv_categoria' );
					if ( ! $term ) { $term = get_term_by( 'slug', sanitize_title( $category ), 'pv_categoria' ); }
					if ( $term && ! is_wp_error( $term ) ) {
						wp_set_object_terms( $res['post_id'], (int) $term->term_id, 'pv_categoria' );
					}
				}
			} elseif ( 'duplicate' === $res['status'] ) {
				$results['duplicates']++;
			} else {
				$results['errors']++;
			}
			$results['items'][] = array_merge( array( 'url' => $url ), $res );
		}
		$log = get_option( PV_Automation::LOG_OPTION, array() );
		if ( ! is_array( $log ) ) { $log = array(); }
		array_unshift( $log, array( 'time' => current_time( 'mysql' ), 'manual' => true, 'results' => array( 'type' => 'bulk_urls', 'summary' => $results ) ) );
		if ( count( $log ) > 20 ) { $log = array_slice( $log, 0, 20 ); }
		update_option( PV_Automation::LOG_OPTION, $log, false );
		return $results;
	}

	/**
	 * Importa da file CSV (colonne: url,title,category).
	 */
	public static function import_from_csv( $file, $default_category = '' ) {
		$handle = fopen( $file['tmp_name'], 'r' );
		if ( ! $handle ) {
			return array( 'imported' => 0, 'duplicates' => 0, 'errors' => 1, 'items' => array( array( 'status' => 'error', 'message' => 'Impossibile aprire CSV.' ) ) );
		}
		$header = fgetcsv( $handle );
		if ( ! $header ) { fclose( $handle ); return array( 'imported' => 0, 'duplicates' => 0, 'errors' => 1, 'items' => array() ); }
		$header = array_map( 'strtolower', array_map( 'trim', $header ) );
		$url_idx = array_search( 'url', $header );
		$title_idx = array_search( 'title', $header );
		$cat_idx = array_search( 'category', $header );
		if ( $url_idx === false ) { fclose( $handle ); return array( 'imported' => 0, 'duplicates' => 0, 'errors' => 1, 'items' => array( array( 'status' => 'error', 'message' => 'Colonna "url" mancante.' ) ) ); }
		$results = array( 'imported' => 0, 'duplicates' => 0, 'errors' => 0, 'items' => array() );
		while ( ( $row = fgetcsv( $handle ) ) !== false ) {
			$url = isset( $row[ $url_idx ] ) ? esc_url_raw( trim( $row[ $url_idx ] ) ) : '';
			if ( '' === $url ) { continue; }
			$title = ( $title_idx !== false && isset( $row[ $title_idx ] ) ) ? sanitize_text_field( $row[ $title_idx ] ) : '';
			$cat   = ( $cat_idx !== false && isset( $row[ $cat_idx ] ) ) ? sanitize_text_field( $row[ $cat_idx ] ) : $default_category;
			$item = self::extract_from_url( $url );
			if ( $title ) { $item['title'] = $title; }
			if ( is_wp_error( $item ) ) {
				$results['errors']++;
				$results['items'][] = array( 'url' => $url, 'status' => 'error', 'message' => $item->get_error_message() );
				continue;
			}
			$res = PV_Importer::import_item( $item, get_option( PV_Automation::OPTION, PV_Automation::defaults() ), 'manual' );
			if ( 'imported' === $res['status'] ) {
				$results['imported']++;
				$use_cat = $cat ?: $default_category;
				if ( $use_cat ) {
					$term = get_term_by( 'name', $use_cat, 'pv_categoria' );
					if ( ! $term ) { $term = get_term_by( 'slug', sanitize_title( $use_cat ), 'pv_categoria' ); }
					if ( $term && ! is_wp_error( $term ) ) {
						wp_set_object_terms( $res['post_id'], (int) $term->term_id, 'pv_categoria' );
					}
				}
			} elseif ( 'duplicate' === $res['status'] ) {
				$results['duplicates']++;
			} else {
				$results['errors']++;
			}
			$results['items'][] = array_merge( array( 'url' => $url ), $res );
		}
		fclose( $handle );
		$log = get_option( PV_Automation::LOG_OPTION, array() );
		if ( ! is_array( $log ) ) { $log = array(); }
		array_unshift( $log, array( 'time' => current_time( 'mysql' ), 'manual' => true, 'results' => array( 'type' => 'bulk_csv', 'summary' => $results ) ) );
		if ( count( $log ) > 20 ) { $log = array_slice( $log, 0, 20 ); }
		update_option( PV_Automation::LOG_OPTION, $log, false );
		return $results;
	}

	/**
	 * Tenta di dedurre provider e embed da URL noto.
	 * Non fa scraping: usa solo pattern noti per comporre embed URL.
	 */
	private static function extract_from_url( $url ) {
		$host = strtolower( parse_url( $url, PHP_URL_HOST ) );
		$host = preg_replace( '/^www\./', '', $host );
		$provider = '';
		$embed_url = '';
		$embed_code = '';
		$title = '';
		$external_id = '';
		$source_url = $url;

		// Pattern noti per embed diretti (pubblicamente documentati)
		if ( false !== strpos( $host, 'pornhub.com' ) ) {
			$provider = 'pornhub';
			if ( preg_match( '/\/view_video\.php\?viewkey=([a-z0-9]+)/', $url, $m ) ) {
				$external_id = 'ph_' . $m[1];
				$embed_url = 'https://www.pornhub.com/embed/' . $m[1];
			}
		} elseif ( false !== strpos( $host, 'youporn.com' ) ) {
			$provider = 'youporn';
			if ( preg_match( '/\/video\/([a-z0-9-]+)\/(\d+)/', $url, $m ) ) {
				$external_id = 'yp_' . $m[2];
				$embed_url = 'https://www.youporn.com/embed/' . $m[2];
			}
		} elseif ( false !== strpos( $host, 'redtube.com' ) ) {
			$provider = 'redtube';
			if ( preg_match( '/\/(\d+)/', $url, $m ) ) {
				$external_id = 'rt_' . $m[1];
				$embed_url = 'https://embed.redtube.com/?id=' . $m[1];
			}
		} elseif ( false !== strpos( $host, 'xvideos.com' ) ) {
			$provider = 'xvideos';
			if ( preg_match( '/\/video(\d+)\//', $url, $m ) ) {
				$external_id = 'xv_' . $m[1];
				$embed_url = 'https://www.xvideos.com/embedframe/' . $m[1];
			}
		} elseif ( false !== strpos( $host, 'xhamster.com' ) ) {
			$provider = 'xhamster';
			if ( preg_match( '/\/videos\/([^\/]+)-(\d+)/', $url, $m ) ) {
				$external_id = 'xh_' . $m[2];
				$embed_url = 'https://xhamster.com/embed/' . $m[2];
			}
		} elseif ( false !== strpos( $host, 'tnaflix.com' ) ) {
			$provider = 'tnaflix';
			if ( preg_match( '/\/video(\d+)\//', $url, $m ) ) {
				$external_id = 'tn_' . $m[1];
				$embed_url = 'https://www.tnaflix.com/embed/' . $m[1];
			}
		} else {
			// URL generico: salva come custom, l'utente dovrà completare embed manualmente
			$provider = 'custom';
		}

		if ( '' === $title ) {
			$title = 'Video importato ' . date( 'Y-m-d H:i' );
		}

		return array(
			'external_id' => $external_id,
			'title'       => $title,
			'description' => '',
			'thumbnail'   => '',
			'category'    => '',
			'embed_url'   => $embed_url,
			'embed_code'  => $embed_code,
			'source_url'  => $source_url,
		);
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		settings_errors( 'pv_bulk_import' );
		$res = get_transient( 'pv_bulk_import_result' );
		delete_transient( 'pv_bulk_import_result' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Importa URL / CSV', 'pornovirtuale' ); ?></h1>
			<div class="notice notice-info"><p><?php esc_html_e( 'Crea post <strong>video</strong> in <strong>bozza</strong> (pubblicazione automatica disattivata). Riconosce automaticamente embed per Pornhub, YouPorn, RedTube, XVideos, xHamster, TNAFlix. Per altri siti salva come custom da completare.', 'pornovirtuale' ); ?></p></div>
			<?php if ( $res ) : ?>
				<div class="notice notice-info"><p><strong><?php esc_html_e( 'Risultato:', 'pornovirtuale' ); ?></strong> Importati: <?php echo (int)$res['imported']; ?> | Duplicati: <?php echo (int)$res['duplicates']; ?> | Errori: <?php echo (int)$res['errors']; ?></p><pre style="background:#f9f9f9;padding:10px;overflow:auto;max-height:300px;"><?php echo esc_html( print_r( $res['items'], true ) ); ?></pre></div>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Importa da URL (uno per riga)', 'pornovirtuale' ); ?></h2>
			<form method="post" action="">
				<?php wp_nonce_field( 'pv_bulk_import_urls' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'URL video', 'pornovirtuale' ); ?></th>
						<td><textarea name="import_urls" rows="10" class="large-text code" placeholder="https://www.pornhub.com/view_video.php?viewkey=...
https://www.xvideos.com/video12345/...
https://www.youporn.com/video/title/12345"></textarea></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Categoria (opzionale)', 'pornovirtuale' ); ?></th>
						<td><input type="text" class="regular-text" name="import_category" placeholder="es. VR"></td>
					</tr>
				</table>
				<?php submit_button( __( 'Importa URL', 'pornovirtuale' ), 'primary', 'pv_bulk_import_urls' ); ?>
			</form>

			<h2><?php esc_html_e( 'Importa da CSV', 'pornovirtuale' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Colonne CSV: <code>url,title,category</code> (solo url obbligatorio).', 'pornovirtuale' ); ?></p>
			<form method="post" action="" enctype="multipart/form-data">
				<?php wp_nonce_field( 'pv_bulk_import_csv' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'File CSV', 'pornovirtuale' ); ?></th>
						<td><input type="file" name="import_csv" accept=".csv"></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Categoria di default', 'pornovirtuale' ); ?></th>
						<td><input type="text" class="regular-text" name="import_category_csv" placeholder="es. VR"></td>
					</tr>
				</table>
				<?php submit_button( __( 'Importa CSV', 'pornovirtuale' ), 'primary', 'pv_bulk_import_csv' ); ?>
			</form>
		</div>
		<?php
	}
}
PV_Bulk_Importer::init();
