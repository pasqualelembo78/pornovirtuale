<?php
defined( 'ABSPATH' ) || exit;

/**
 * Ricerca pubblica XVideos e importazione dei risultati come bozze.
 *
 * Nota: non aggira CAPTCHA, login, rate limit o altre misure di accesso.
 */
class PV_Source_XVideos {
	const SOURCE = 'xvideos';

	public static function search_and_import( $query, $limit = 5, $category = '' ) {
		$query = trim( sanitize_text_field( $query ) );
		$limit = max( 1, min( 20, absint( $limit ) ) );
		if ( '' === $query ) {
			return self::result( 'error', __( 'Inserisci una parola chiave.', 'pornovirtuale' ) );
		}

		$url = add_query_arg( 'k', $query, 'https://www.xvideos.com/' );
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
				$title = trim( $node->getAttribute( 'title' ) );
				if ( '' === $title ) {
					$title = trim( preg_replace( '/\s+/', ' ', $node->textContent ) );
				}
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
					'title' => sanitize_text_field( $title ),
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
			return self::result( 'error', __( 'Nessun risultato leggibile. La struttura della pagina potrebbe essere cambiata o il sito potrebbe non consentire la richiesta.', 'pornovirtuale' ) );
		}

		$imported = 0;
		$duplicates = 0;
		$items = array();
		foreach ( $found as $item ) {
			if ( $imported >= $limit ) {
				break;
			}
			$existing = get_posts( array(
				'post_type' => 'video',
				'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => 1,
				'fields' => 'ids',
				'meta_key' => '_pv_source_url',
				'meta_value' => $item['url'],
				'no_found_rows' => true,
			) );
			if ( ! empty( $existing ) ) {
				$duplicates++;
				continue;
			}
			$post_id = wp_insert_post( array(
				'post_type' => 'video',
				'post_status' => 'draft',
				'post_title' => wp_strip_all_tags( $item['title'] ),
				'post_content' => sprintf(
					'<p>%s</p>\n<iframe src="%s" title="%s" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>',
					esc_html__( 'Video importato da XVideos. Verifica titolo, contenuto e autorizzazioni prima della pubblicazione.', 'pornovirtuale' ),
					esc_url( $item['embed'] ),
					esc_attr( $item['title'] )
				),
				'post_excerpt' => sprintf( __( 'Video trovato tramite ricerca: %s', 'pornovirtuale' ), $query ),
			), true );
			if ( is_wp_error( $post_id ) ) {
				continue;
			}
			update_post_meta( $post_id, '_pv_source', self::SOURCE );
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
			$items[] = array( 'id' => $post_id, 'title' => $item['title'], 'url' => $item['url'] );
			$imported++;
		}
		$message = sprintf(
			__( 'Ricerca completata: %1$d bozze create, %2$d duplicati ignorati.', 'pornovirtuale' ),
			$imported,
			$duplicates
		);
		$result = self::result( 'success', $message );
		$result['imported'] = $imported;
		$result['duplicates'] = $duplicates;
		$result['items'] = $items;
		self::log_result( $query, $result );
		return $result;
	}

	private static function result( $status, $message ) {
		$result = array( 'status' => $status, 'message' => $message, 'imported' => 0, 'items' => array() );
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
