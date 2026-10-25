<?php
defined( 'ABSPATH' ) || exit;

/**
 * Ricerca pubblica XVideos e pubblicazione dei risultati importati.
 *
 * Nota: non aggira CAPTCHA, login, rate limit o altre misure di accesso.
 */
class PV_Source_XVideos {
	const SOURCE = 'xvideos';

	/**
	 * Elimina etichette tecniche di qualità dal titolo.
	 * Restituisce una stringa vuota se resta soltanto un'etichetta tecnica.
	 */
	private static function clean_video_title( $title ) {
		$title = html_entity_decode( (string) $title, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$title = wp_strip_all_tags( $title );
		$title = preg_replace( '/[\x{00A0}\s]+/u', ' ', $title );
		$title = trim( $title );

		if ( '' === $title ) {
			return '';
		}

		// Rimuove risoluzioni e sigle video, anche tra parentesi o separate da trattini.
		$title = preg_replace( '/\b(?:2160p|1440p|1080p|720p|576p|480p|360p|240p|144p|4k|8k|uhd|fhd|full\s*hd|hd|sd)\b/iu', ' ', $title );
		$title = preg_replace( '/\s*[-|–—:;,]+\s*/u', ' ', $title );
		$title = preg_replace( '/[\x{00A0}\s]+/u', ' ', $title );
		$title = trim( $title, " \t\n\r\0\x0B-_|–—:;,." );

		// Non pubblicare titoli composti esclusivamente da etichette tecniche o simboli.
		if ( '' === $title || ! preg_match( '/[\p{L}\p{N}]{2,}/u', $title ) ) {
			return '';
		}

		return sanitize_text_field( $title );
	}

	public static function search_and_import( $query, $limit = 5, $category = '', $page = 0, $tags = array() ) {
		$query = trim( sanitize_text_field( $query ) );
		$limit = max( 1, min( 100, absint( $limit ) ) );
		if ( '' === $query ) {
			return self::result( 'error', __( 'Inserisci una parola chiave.', 'pornovirtuale' ) );
		}

		$url = add_query_arg( array( 'k' => $query, 'p' => max( 0, absint( $page ) ) ), 'https://www.xvideos.com/' );
		$response = wp_remote_get( $url, array(
			'timeout' => 20,
			'redirection' => 3,
			'headers' => array(
				'User-Agent' => 'Mozilla/5.0 (compatible; PVCoreImporter/1.0; +https://pornovirtuale.com)',
				'Accept' => 'text/html,application/xhtml+xml',
			),
		) );

		if ( is_wp_error( $response ) ) {
			return self::result( 'error', sprintf( __( 'Richiesta XVideos non riuscita: %s', 'pornovirtuale' ), $response->get_error_message() ) );
		}
		$status = (int) wp_remote_retrieve_response_code( $response );
		$html = (string) wp_remote_retrieve_body( $response );
		if ( $status < 200 || $status >= 300 ) {
			return self::result( 'error', sprintf( __( 'XVideos ha restituito HTTP %d. Nessun tentativo di aggirare eventuali restrizioni è stato eseguito.', 'pornovirtuale' ), $status ) );
		}
		if ( '' === trim( $html ) ) {
			return self::result( 'error', __( 'La pagina di ricerca XVideos è vuota.', 'pornovirtuale' ) );
		}
		if ( preg_match( '/captcha|verify you are human|access denied|unusual traffic/i', substr( $html, 0, 50000 ) ) ) {
			return self::result( 'error', __( 'XVideos sembra richiedere una verifica o limitare l’accesso. Importazione interrotta senza aggirare la protezione.', 'pornovirtuale' ) );
		}
		if ( ! class_exists( 'DOMDocument' ) ) {
			return self::result( 'error', __( 'Estensione PHP DOM non disponibile sul server.', 'pornovirtuale' ) );
		}

		$previous = libxml_use_internal_errors( true );
		$dom = new DOMDocument();
		$loaded = $dom->loadHTML( '<?xml encoding="utf-8" ?>' . $html );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		if ( ! $loaded ) {
			return self::result( 'error', __( 'Impossibile leggere la pagina dei risultati XVideos.', 'pornovirtuale' ) );
		}
		$xpath = new DOMXPath( $dom );
		$nodes = $xpath->query( '//a[contains(@href, "/video.") and @href]' );
		$found = array();
		if ( $nodes ) {
			foreach ( $nodes as $node ) {
				$href = trim( $node->getAttribute( 'href' ) );
				if ( ! preg_match( '~^/video\.([a-zA-Z0-9]+)~', $href, $match ) ) {
					continue;
				}
				$id = $match[1];
				$full_url = 'https://www.xvideos.com' . strtok( $href, '?' );
				if ( isset( $found[ $id ] ) ) {
					continue;
				}

				// Prova prima l'attributo title, poi il testo visibile dell'elemento.
				$title = self::clean_video_title( $node->getAttribute( 'title' ) );
				if ( '' === $title ) {
					$title = self::clean_video_title( $node->textContent );
				}
				// Se questa occorrenza contiene solo 1080p/720p ecc., prova eventuali
				// altre occorrenze dello stesso video nella pagina invece di importarla.
				if ( '' === $title ) {
					continue;
				}

				$thumb = '';
				$images = $xpath->query( './/img', $node );
				if ( $images && $images->length ) {
					$img = $images->item( 0 );
					foreach ( array( 'data-src', 'data-original', 'src' ) as $attr ) {
						$candidate = trim( $img->getAttribute( $attr ) );
						if ( '' !== $candidate && 0 === strpos( $candidate, 'http' ) ) {
							$thumb = esc_url_raw( $candidate );
							break;
						}
					}
				}
				$found[ $id ] = array(
					'id' => $id,
					'title' => $title,
					'url' => esc_url_raw( $full_url ),
					'embed' => 'https://www.xvideos.com/embedframe/' . rawurlencode( $id ),
					'thumbnail' => $thumb,
				);
				if ( count( $found ) >= $limit * 3 ) {
					break;
				}
			}
		}

		if ( empty( $found ) ) {
			return self::result( 'error', __( 'Nessun risultato con un titolo valido. La struttura della pagina potrebbe essere cambiata o i risultati potrebbero contenere soltanto etichette tecniche.', 'pornovirtuale' ) );
		}

		$imported = 0;
		$duplicates = 0;
		$items = array();
		foreach ( $found as $item ) {
			if ( $imported >= $limit ) {
				break;
			}
			// Deduplica per ID XVideos e per tutti gli URL noti, anche se cambia lo slug.
			// Include anche il cestino: un video cancellato non viene reimportato automaticamente.
			$video_path = '/video.' . $item['id'];
			$embed_path = '/embedframe/' . $item['id'];
			$existing = get_posts( array(
				'post_type' => 'video',
				'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ),
				'posts_per_page' => 1,
				'fields' => 'ids',
				'no_found_rows' => true,
				'meta_query' => array(
					'relation' => 'OR',
					array( 'key' => '_pv_xvideos_id', 'value' => $item['id'], 'compare' => '=' ),
					array( 'key' => '_pv_source_url', 'value' => $item['url'], 'compare' => '=' ),
					array( 'key' => '_pv_external_url', 'value' => $item['url'], 'compare' => '=' ),
					array( 'key' => '_pv_embed_url', 'value' => $item['embed'], 'compare' => '=' ),
					array( 'key' => '_pv_source_url', 'value' => $video_path, 'compare' => 'LIKE' ),
					array( 'key' => '_pv_external_url', 'value' => $video_path, 'compare' => 'LIKE' ),
					array( 'key' => '_pv_embed_url', 'value' => $embed_path, 'compare' => 'LIKE' ),
				),
			) );
			if ( ! empty( $existing ) ) {
				$duplicates++;
				continue;
			}
			$publish_at = self::next_publication_datetime();
			$post_id = wp_insert_post( array(
				'post_type' => 'video',
				'post_status' => 'future',
				'post_date' => $publish_at->format( 'Y-m-d H:i:s' ),
				'post_date_gmt' => get_gmt_from_date( $publish_at->format( 'Y-m-d H:i:s' ) ),
				'post_title' => $item['title'],
				'post_content' => sprintf(
					'<p>%s</p>\n<iframe src="%s" title="%s" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>',
					esc_html__( 'Video importato da XVideos.', 'pornovirtuale' ),
					esc_url( $item['embed'] ),
					esc_attr( $item['title'] )
				),
				'post_excerpt' => sprintf( __( 'Video trovato tramite ricerca: %s', 'pornovirtuale' ), $query ),
			), true );
			if ( is_wp_error( $post_id ) ) {
				continue;
			}
			update_post_meta( $post_id, '_pv_source', self::SOURCE );
			update_post_meta( $post_id, '_pv_xvideos_id', $item['id'] );
			update_post_meta( $post_id, '_pv_source_url', $item['url'] );
			update_post_meta( $post_id, '_pv_external_url', $item['url'] );
			update_post_meta( $post_id, '_pv_embed_url', $item['embed'] );
			update_post_meta( $post_id, '_pv_search_keyword', $query );
			if ( '' !== $item['thumbnail'] ) {
				update_post_meta( $post_id, '_pv_thumbnail_url', $item['thumbnail'] );
			}
			if ( '' !== $category && taxonomy_exists( 'pv_categoria' ) ) {
				$term = term_exists( $category, 'pv_categoria' );
				if ( ! $term ) {
					$term = wp_insert_term( $category, 'pv_categoria' );
				}
				if ( ! is_wp_error( $term ) ) {
					$term_id = is_array( $term ) ? (int) $term['term_id'] : (int) $term;
					wp_set_object_terms( $post_id, array( $term_id ), 'pv_categoria', false );
				}
			}
			if ( ! empty( $tags ) && taxonomy_exists( 'post_tag' ) ) {
				$clean_tags = array();
				foreach ( $tags as $t ) {
					$t = sanitize_text_field( is_scalar( $t ) ? $t : '' );
					if ( '' !== $t ) {
						$clean_tags[] = $t;
					}
				}
				if ( ! empty( $clean_tags ) ) {
					wp_set_object_terms( $post_id, $clean_tags, 'post_tag', false );
				}
			}
			$items[] = array( 'id' => $post_id, 'title' => $item['title'], 'url' => $item['url'] );
			$imported++;
		}
		$message = sprintf(
			__( 'Ricerca completata: %1$d video programmati (uno ogni 5 minuti), %2$d duplicati ignorati.', 'pornovirtuale' ),
			$imported,
			$duplicates
		);
		$result = self::result( 'success', $message );
		$result['imported'] = $imported;
		$result['duplicates'] = $duplicates;
		$result['scanned'] = count( $found );
		$result['items'] = $items;
		self::log_result( $query, $result );
		return $result;
	}

	/**
	 * Restituisce il prossimo slot di pubblicazione, distanziato di almeno 5 minuti
	 * dall'ultimo video già programmato. Usa il fuso orario configurato in WordPress.
	 */
	private static function next_publication_datetime() {
		$timezone = wp_timezone();
		$next = current_datetime()->modify( '+5 minutes' );

		$latest_ids = get_posts( array(
			'post_type' => 'video',
			'post_status' => array( 'future' ),
			'posts_per_page' => 1,
			'orderby' => 'date',
			'order' => 'DESC',
			'fields' => 'ids',
			'no_found_rows' => true,
		) );

		if ( ! empty( $latest_ids ) ) {
			$latest = get_post_datetime( (int) $latest_ids[0], 'date', 'local' );
			if ( $latest instanceof DateTimeImmutable && $latest >= $next ) {
				$next = $latest->setTimezone( $timezone )->modify( '+5 minutes' );
			}
		}

		$daily_limit = (int) ( get_option( 'pv_search_import', array() )['daily_limit'] ?? 2 );
		if ( $daily_limit > 0 ) {
			$today_count = self::get_today_publish_count();
			if ( $today_count >= $daily_limit ) {
				return current_datetime()->modify( '+1 day' )->setTimezone( $timezone );
			}
		}

		return $next->setTimezone( $timezone );
	}

	/**
	 * Conta i video programmati per oggi (future o publish).
	 */
	private static function get_today_publish_count() {
		$now = current_time( 'mysql' );
		$today = substr( $now, 0, 10 );
		$ids = get_posts( array(
			'post_type' => 'video',
			'post_status' => array( 'future', 'publish' ),
			'posts_per_page' => -1,
			'fields' => 'ids',
			'no_found_rows' => true,
			'date_query' => array(
				array(
					'after'    => $today . ' 00:00:00',
					'before'   => $today . ' 23:59:59',
					'compare'  => '>=',
					'relation' => 'OR',
				),
			),
		) );
		return count( (array) $ids );
	}

	private static function result( $status, $message ) {
		$result = array( 'status' => $status, 'message' => $message, 'imported' => 0, 'duplicates' => 0, 'scanned' => 0, 'items' => array() );
		self::log_result( '', $result );
		return $result;
	}

	private static function log_result( $query, $result ) {
		if ( ! class_exists( 'PV_Automation' ) ) {
			return;
		}
		$log = get_option( PV_Automation::LOG_OPTION, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}
		array_unshift( $log, array(
			'time' => current_time( 'mysql' ),
			'manual' => true,
			'results' => array(
				'type' => 'xvideos_search_import',
				'query' => sanitize_text_field( $query ),
				'status' => sanitize_key( $result['status'] ),
				'message' => sanitize_text_field( $result['message'] ),
				'imported' => absint( $result['imported'] ),
			),
		) );
		update_option( PV_Automation::LOG_OPTION, array_slice( $log, 0, 20 ), false );
	}
}
