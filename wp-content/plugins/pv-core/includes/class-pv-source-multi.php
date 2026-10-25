<?php
defined( 'ABSPATH' ) || exit;

/**
 * Adapter di ricerca best-effort per provider diversi da XVideos.
 * Non aggira login, CAPTCHA, paywall o limitazioni d'accesso.
 */
class PV_Source_Multi {
	public static function search_and_import( $site, $query, $limit = 5, $category = '', $page = 0 ) {
		$configs = array(
			'youporn' => array(
				'search' => 'https://www.youporn.com/search/?query=%s&page=%d',
				'host' => 'www.youporn.com',
				'pattern' => '~^/watch/([0-9]+)(?:/|$)~',
				'embed' => 'https://www.youporn.com/embed/%s',
				'id_meta' => '_pv_youporn_id',
			),
			'pornhub' => array(
				'search' => 'https://www.pornhub.com/video/search?search=%s&page=%d',
				'host' => 'www.pornhub.com',
				'pattern' => '~^/view_video\.php\?viewkey=([a-zA-Z0-9]+)~',
				'embed' => 'https://www.pornhub.com/embed/%s',
				'id_meta' => '_pv_pornhub_id',
			),
			'redtube' => array(
				'search' => 'https://www.redtube.com/?search=%s&page=%d',
				'host' => 'www.redtube.com',
				'pattern' => '~^/([0-9]{5,})(?:/|$|\?)~',
				'embed' => 'https://embed.redtube.com/?id=%s',
				'id_meta' => '_pv_redtube_id',
			),
		);
		if ( ! isset( $configs[ $site ] ) ) {
			return self::result( 'error', 'Provider non supportato.' );
		}
		$query = trim( sanitize_text_field( $query ) );
		$limit = max( 1, min( 100, absint( $limit ) ) );
		if ( '' === $query ) {
			return self::result( 'error', 'Inserisci una parola chiave.' );
		}
		$c = $configs[ $site ];
		$url = sprintf( $c['search'], rawurlencode( $query ), max( 1, absint( $page ) + 1 ) );
		$response = wp_remote_get( $url, array(
			'timeout' => 15,
			'redirection' => 3,
			'headers' => array(
				'User-Agent' => 'Mozilla/5.0 (compatible; PVCoreImporter/1.0; +https://pornovirtuale.com)',
				'Accept' => 'text/html,application/xhtml+xml',
			),
		) );
		if ( is_wp_error( $response ) ) {
			return self::result( 'error', sprintf( '%1$s: %2$s', $site, $response->get_error_message() ) );
		}
		$status = (int) wp_remote_retrieve_response_code( $response );
		$html = (string) wp_remote_retrieve_body( $response );
		if ( $status < 200 || $status >= 300 || '' === trim( $html ) ) {
			return self::result( 'error', sprintf( '%1$s ha restituito HTTP %2$d o una pagina vuota.', $site, $status ) );
		}
		if ( preg_match( '/captcha|verify you are human|access denied|unusual traffic|enable javascript to continue/i', substr( $html, 0, 50000 ) ) ) {
			return self::result( 'error', sprintf( '%s sembra bloccare la richiesta o richiedere una verifica; passo oltre senza aggirare la protezione.', $site ) );
		}
		if ( ! class_exists( 'DOMDocument' ) ) {
			return self::result( 'error', 'Estensione PHP DOM non disponibile.' );
		}
		$previous = libxml_use_internal_errors( true );
		$dom = new DOMDocument();
		$loaded = $dom->loadHTML( '<?xml encoding="utf-8" ?>' . $html );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		if ( ! $loaded ) {
			return self::result( 'error', sprintf( 'Impossibile leggere i risultati di %s.', $site ) );
		}
		$xpath = new DOMXPath( $dom );
		$nodes = $xpath->query( '//a[@href]' );
		$found = array();
		if ( $nodes ) {
			foreach ( $nodes as $node ) {
				$href = html_entity_decode( trim( $node->getAttribute( 'href' ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
				$match_href = $href;
				if ( 0 === strpos( $match_href, 'http' ) ) {
					$parts = wp_parse_url( $match_href );
					$match_href = ( $parts['path'] ?? '/' ) . ( isset( $parts['query'] ) ? '?' . $parts['query'] : '' );
				}
				if ( ! preg_match( $c['pattern'], $match_href, $m ) ) {
					continue;
				}
				$id = $m[1];
				if ( isset( $found[ $id ] ) ) {
					continue;
				}
				$title = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $node->getAttribute( 'title' ) ?: $node->textContent ) ) );
				$title = sanitize_text_field( html_entity_decode( $title, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
				if ( '' === $title || ! preg_match( '/[\p{L}\p{N}]{2,}/u', $title ) ) {
					continue;
				}
				$full_url = ( 0 === strpos( $href, 'http' ) ) ? strtok( $href, '?' ) : 'https://' . $c['host'] . strtok( $href, '?' );
				$thumb = '';
				$images = $xpath->query( './/img', $node );
				if ( $images && $images->length ) {
					$img = $images->item( 0 );
					foreach ( array( 'data-src', 'data-original', 'src' ) as $attr ) {
						$src = trim( $img->getAttribute( $attr ) );
						if ( 0 === strpos( $src, 'http' ) ) {
							$thumb = esc_url_raw( $src );
							break;
						}
					}
				}
				$found[ $id ] = array( 'id' => $id, 'title' => $title, 'url' => esc_url_raw( $full_url ), 'embed' => sprintf( $c['embed'], rawurlencode( $id ) ), 'thumbnail' => $thumb );
				if ( count( $found ) >= $limit * 3 ) {
					break;
				}
			}
		}
		if ( empty( $found ) ) {
			return self::result( 'empty', sprintf( 'Nessun risultato leggibile da %s; provo il provider successivo.', $site ) );
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
				'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ),
				'posts_per_page' => 1,
				'fields' => 'ids',
				'no_found_rows' => true,
				'meta_query' => array( 'relation' => 'OR',
					array( 'key' => $c['id_meta'], 'value' => $item['id'], 'compare' => '=' ),
					array( 'key' => '_pv_source_url', 'value' => $item['url'], 'compare' => '=' ),
					array( 'key' => '_pv_embed_url', 'value' => $item['embed'], 'compare' => '=' ),
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
				'post_content' => sprintf( '<p>%s</p><iframe src="%s" title="%s" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>', esc_html( 'Video importato da ' . ucfirst( $site ) . '.' ), esc_url( $item['embed'] ), esc_attr( $item['title'] ) ),
				'post_excerpt' => sprintf( 'Video trovato tramite ricerca: %s', $query ),
			), true );
			if ( is_wp_error( $post_id ) || ! $post_id ) {
				continue;
			}
			update_post_meta( $post_id, '_pv_source', $site );
			update_post_meta( $post_id, $c['id_meta'], $item['id'] );
			update_post_meta( $post_id, '_pv_source_url', $item['url'] );
			update_post_meta( $post_id, '_pv_external_url', $item['url'] );
			update_post_meta( $post_id, '_pv_embed_url', $item['embed'] );
			update_post_meta( $post_id, '_pv_search_keyword', $query );
			if ( '' !== $item['thumbnail'] ) {
				update_post_meta( $post_id, '_pv_thumbnail_url', $item['thumbnail'] );
			}
			if ( '' !== $category && taxonomy_exists( 'pv_categoria' ) ) {
				$term = term_exists( $category, 'pv_categoria' );
				if ( ! $term ) { $term = wp_insert_term( $category, 'pv_categoria' ); }
				if ( ! is_wp_error( $term ) && $term ) {
					$term_id = is_array( $term ) ? (int) $term['term_id'] : (int) $term;
					wp_set_object_terms( $post_id, array( $term_id ), 'pv_categoria', false );
				}
			}
			$items[] = array( 'id' => $post_id, 'title' => $item['title'], 'url' => $item['url'] );
			$imported++;
		}
		$result = self::result( 'success', sprintf( '%s: %d video programmati, %d duplicati ignorati.', ucfirst( $site ), $imported, $duplicates ) );
		$result['imported'] = $imported;
		$result['duplicates'] = $duplicates;
		$result['scanned'] = count( $found );
		$result['items'] = $items;
		return $result;
	}

	private static function next_publication_datetime() {
		$timezone = wp_timezone();
		$next = current_datetime()->modify( '+5 minutes' );
		$latest_ids = get_posts( array( 'post_type' => 'video', 'post_status' => array( 'future' ), 'posts_per_page' => 1, 'orderby' => 'date', 'order' => 'DESC', 'fields' => 'ids', 'no_found_rows' => true ) );
		if ( ! empty( $latest_ids ) ) {
			$latest = get_post_datetime( (int) $latest_ids[0], 'date', 'local' );
			if ( $latest instanceof DateTimeImmutable && $latest >= $next ) {
				$next = $latest->setTimezone( $timezone )->modify( '+5 minutes' );
			}
		}
		return $next->setTimezone( $timezone );
	}

	private static function result( $status, $message ) {
		return array( 'status' => $status, 'message' => $message, 'imported' => 0, 'duplicates' => 0, 'scanned' => 0, 'items' => array() );
	}
}
