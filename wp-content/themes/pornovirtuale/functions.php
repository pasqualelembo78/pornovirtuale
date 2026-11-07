<?php
/**
 * Pornovirtuale v2 - functions and definitions.
 *
 * @package Pornovirtuale
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PV_VERSION', '2.0.0' );
define( 'PV_THEME_DIR', get_template_directory() );
define( 'PV_THEME_URI', get_template_directory_uri() );

/**
 * Theme setup.
 */
function pv_theme_setup() {
	load_theme_textdomain( 'pornovirtuale', PV_THEME_DIR . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'customize-selective-refresh-widgets' );

	register_nav_menus(
		array(
			'primary' => __( 'Menu Principale', 'pornovirtuale' ),
			'footer'  => __( 'Menu Footer', 'pornovirtuale' ),
			'legal'   => __( 'Menu Legale', 'pornovirtuale' ),
		)
	);

	add_image_size( 'pv-card', 640, 400, true );
	add_image_size( 'pv-hero', 1600, 900, true );
	add_image_size( 'pv-thumb', 320, 180, true );

	// Gli header di sicurezza e il robots restano a Yoast.
	remove_action( 'wp_head', 'wp_generator' );
}
add_action( 'after_setup_theme', 'pv_theme_setup' );

/**
 * Content width.
 */
function pv_content_width() {
	$GLOBALS['content_width'] = apply_filters( 'pv_content_width', 1200 );
}
add_action( 'after_setup_theme', 'pv_content_width', 0 );

/**
 * Front-end assets.
 */
function pv_asset_version( $relative ) {
	// Versiona gli asset con la data del file: dopo una correzione il browser
	// non puo' piu' rieseguire una vecchia copia in cache (es. theme.js rotto).
	$file = get_theme_file_path( $relative );
	return file_exists( $file ) ? filemtime( $file ) : PV_VERSION;
}

function pv_enqueue_assets() {
	wp_enqueue_style( 'pornovirtuale', get_stylesheet_uri(), array(), pv_asset_version( 'style.css' ) );
	wp_enqueue_style( 'pornovirtuale-fonts', 'https://fonts.googleapis.com/css2?family=Orbitron:wght@500;700&family=Roboto:wght@400;500&display=swap', array(), null );

	wp_enqueue_script( 'pv-theme', PV_THEME_URI . '/assets/js/theme.js', array(), pv_asset_version( 'assets/js/theme.js' ), true );
}
add_action( 'wp_enqueue_scripts', 'pv_enqueue_assets' );

/**
 * WooCommerce carica gli script su ogni pagina, anche dove non serve.
 * Il tema non usa carrelli/widget: li togliamo fuori da shop, carrello,
 * checkout e area personale (con essi spariscono anche jQuery e migrate).
 */
function pv_dequeue_woocommerce_assets() {
	if ( ! function_exists( 'is_woocommerce' ) ) {
		return;
	}

	if ( is_woocommerce() || is_cart() || is_checkout() || is_account_page() ) {
		return;
	}

	foreach ( array( 'woocommerce', 'wc-add-to-cart', 'wc-order-attribution', 'sourcebuster-js', 'jquery-blockui', 'js-cookie', 'wc-cart-fragments', 'wc-geolocation', 'googlesitekit-events-provider-woocommerce' ) as $handle ) {
		wp_dequeue_script( $handle );
	}
}
add_action( 'wp_enqueue_scripts', 'pv_dequeue_woocommerce_assets', 99 );
add_action( 'wp_print_scripts', 'pv_dequeue_woocommerce_assets', 0 );
add_action( 'wp_print_footer_scripts', 'pv_dequeue_woocommerce_assets', 0 );

/**
 * Fuori da carrello/checkout/area personale il tema non usa blocchi:
 * il CSS di wp-block-library e i global styles (oltre 13 KB inline)
 * sono peso morto che abbassa il rapporto testo/HTML.
 */
function pv_dequeue_block_styles() {
	if ( ! function_exists( 'is_woocommerce' ) ) {
		return;
	}

	if ( is_woocommerce() || is_cart() || is_checkout() || is_account_page() ) {
		return;
	}

	foreach ( array( 'wp-block-library', 'global-styles', 'wp-global-styles-placeholder', 'wp-block-styles-placeholder', 'classic-theme-styles', 'wp-emoji-styles', 'wp-img-auto-sizes-contain', 'woocommerce-inline' ) as $handle ) {
		wp_dequeue_style( $handle );
	}
}
/**
 * Disattiva del tutto i global styles del core fuori dalle pagine a blocchi:
 * vanno rimossi prima che `wp_enqueue_scripts` venga eseguito, perché il core
 * li riaggiunge nel footer e li "hoista" comunque nell'head.
 */
function pv_disable_block_styles_actions() {
	if ( ! function_exists( 'is_woocommerce' ) ) {
		return;
	}

	if ( is_woocommerce() || is_cart() || is_checkout() || is_account_page() ) {
		return;
	}

	remove_action( 'wp_enqueue_scripts', 'wp_enqueue_global_styles' );
	remove_action( 'wp_footer', 'wp_enqueue_global_styles', 1 );

	// WP 6.9 sposta comunque i global styles nell'head: li togliamo dal buffer.
	add_filter( 'wp_template_enhancement_output_buffer', 'pv_strip_global_styles', 99 );
}

function pv_strip_global_styles( $buffer ) {
	return preg_replace( '#<style id="global-styles-inline-css">.*?</style>#s', '', $buffer );
}
add_action( 'wp', 'pv_disable_block_styles_actions' );

add_action( 'wp_enqueue_scripts', 'pv_dequeue_block_styles', 99 );
add_action( 'wp_print_styles', 'pv_dequeue_block_styles', 0 );
add_action( 'wp_footer', 'pv_dequeue_block_styles', 2 );
add_action( 'wp_print_footer_scripts', 'pv_dequeue_block_styles', 0 );

/**
 * Gli emoji nativi di WordPress pesano oltre 3 KB di script e CSS
 * su ogni pagina: qui non servono.
 */
function pv_disable_emojis() {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );

	add_filter(
		'wp_resource_hints',
		function ( $urls, $relation ) {
			if ( 'dns-prefetch' === $relation ) {
				$urls = array_filter( $urls, function ( $url ) {
					return false === strpos( $url, 's.w.org' );
				} );
			}
			return $urls;
		},
		10,
		2
	);
}
add_action( 'init', 'pv_disable_emojis' );

/** Schema Organization: segnali di brand per motori e knowledge graph. */
/** Scoperta dell'indice macchina per agenti AI. */
function pv_ai_index_link() {
	printf( '<link rel="alternate" type="application/json" href="%s" />' . "\n", esc_url( home_url( '/ai-index.json' ) ) );
	printf( '<link rel="alternate" type="application/json" href="%s" />' . "\n", esc_url( home_url( '/ai-characters.json' ) ) );
	printf( '<link rel="alternate" type="text/plain" href="%s" />' . "\n", esc_url( home_url( '/llms.txt' ) ) );
	printf( '<link rel="alternate" type="text/plain" href="%s" />' . "\n", esc_url( home_url( '/llms-full.txt' ) ) );
}
add_action( 'wp_head', 'pv_ai_index_link', 5 );

function pv_organization_schema() {
	if ( is_admin() || is_feed() ) {
		return;
	}

	$logo_id  = (int) get_option( 'site_icon' );
	$logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'full' ) : '';

	$schema = array(
		'@context'    => 'https://schema.org',
		'@type'       => 'Organization',
		'@id'         => home_url( '/#organization' ),
		'name'        => get_bloginfo( 'name' ),
		'url'         => home_url( '/' ),
		'description' => get_bloginfo( 'description' ),
		'areaServed'  => 'IT',
		'knowsAbout'  => array(
			'porno virtuale',
			'cam VR per adulti',
			'chat erotiche',
			'realtà virtuale 180 e 360 gradi',
		),
		'contactPoint' => array(
			'@type'             => 'ContactPoint',
			'contactType'       => 'customer support',
			'url'               => home_url( '/supporto/' ),
			'availableLanguage' => 'Italian',
		),
	);

	if ( $logo_url ) {
		$schema['logo'] = array(
			'@type'  => 'ImageObject',
			'url'    => $logo_url,
			'width'  => 512,
			'height' => 512,
		);
	}

	echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n";
}
add_action( 'wp_head', 'pv_organization_schema', 6 );

/** Dati aggiuntivi per la guida sulle risoluzioni VR. */
function pv_guide_article_schema( $data, $context = null ) {
	$post = get_post();
	if ( ! $post || 'video-vr-720p-1080p-4k-differenze' !== $post->post_name ) {
		return $data;
	}

	$data['about'] = array(
		array( '@type' => 'Thing', 'name' => 'Realtà virtuale' ),
		array( '@type' => 'Thing', 'name' => 'Risoluzione video' ),
	);
	$data['mentions'] = array(
		array( '@type' => 'Thing', 'name' => '1080p' ),
		array( '@type' => 'Thing', 'name' => '4K UHD' ),
		array( '@type' => 'Thing', 'name' => 'Bitrate' ),
		array( '@type' => 'Thing', 'name' => 'Visore VR' ),
	);
	$data['speakable'] = array(
		'@type'       => 'SpeakableSpecification',
		'cssSelector' => array( 'h1', '.pv-content > p:first-of-type' ),
	);
	$data['citation'] = array(
		'https://it.wikipedia.org/wiki/Realt%C3%A0_virtuale',
		'https://it.wikipedia.org/wiki/4K',
		'https://developer.mozilla.org/en-US/docs/Web/API/WebXR_Device_API',
	);

	return $data;
}
add_filter( 'wpseo_schema_article', 'pv_guide_article_schema', 20, 2 );

/** Schema CollectionPage: aggiunge l'elenco alla pagina WebPage di Yoast. */
function pv_collection_schema_graph( $graph ) {
	if ( ! ( is_home() || is_post_type_archive( 'stanza' ) || is_post_type_archive( 'video' ) || is_category() ) ) {
		return $graph;
	}

	global $wp_query;
	if ( empty( $wp_query->posts ) || ! is_array( $graph ) ) {
		return $graph;
	}

	$items = array();
	foreach ( array_slice( $wp_query->posts, 0, 20 ) as $item ) {
		$items[] = array(
			'@type'    => 'ListItem',
			'position' => count( $items ) + 1,
			'url'      => get_permalink( $item ),
			'name'     => get_the_title( $item ),
		);
	}

	if ( is_home() ) {
		$url = get_permalink( (int) get_option( 'page_for_posts' ) );
	} elseif ( is_category() ) {
		$url = get_category_link( get_queried_object_id() );
	} else {
		$url = get_post_type_archive_link( get_post_type() );
	}

	foreach ( $graph as $i => $node ) {
		$id = (string) ( $node['@id'] ?? '' );
		if ( '' === $id || 0 !== strpos( $id, $url ) ) {
			continue;
		}
		$type    = $node['@type'] ?? '';
		$tipi    = is_array( $type ) ? $type : array( $type );
		$ammessi = array_intersect( $tipi, array( 'WebPage', 'CollectionPage' ) );
		if ( ! $ammessi ) {
			continue;
		}
		$graph[ $i ]['@type']      = 'CollectionPage';
		$graph[ $i ]['mainEntity'] = array(
			'@type'           => 'ItemList',
			'numberOfItems'   => count( $items ),
			'itemListElement' => $items,
		);
		break;
	}

	return $graph;
}
add_filter( 'wpseo_schema_graph', 'pv_collection_schema_graph', 20 );

/** Schema AboutPage per la pagina redazione. */
function pv_about_page_schema() {
	if ( ! is_page( 'chi-siamo' ) ) {
		return;
	}

	$schema = array(
		'@context'     => 'https://schema.org',
		'@type'        => 'AboutPage',
		'name'         => get_the_title(),
		'url'          => get_permalink(),
		'dateModified' => get_post_modified_time( 'c' ),
		'mainEntity'   => array(
			'@type'       => 'Organization',
			'@id'         => home_url( '/#organization' ),
			'name'        => get_bloginfo( 'name' ),
			'url'         => home_url( '/' ),
			'description' => get_bloginfo( 'description' ),
		),
	);

	echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n";
}
add_action( 'wp_head', 'pv_about_page_schema', 6 );

/**
 * Data di ultimo aggiornamento visibile: segnale di freschezza per
 * utenti e crawler (Amazonbot compreso).
 */
function pv_updated_date() {
	$id = get_the_ID();
	if ( ! $id ) {
		return;
	}

	$modified = (int) get_post_modified_time( 'U', false, $id, true );
	$published = (int) get_post_time( 'U', false, $id, true );

	if ( ! $modified || $modified <= $published ) {
		return;
	}

	printf(
		'<p class="pv-updated" style="color:var(--pv-muted);font-size:.9em;">%s %s</p>',
		esc_html__( 'Aggiornato il', 'pornovirtuale' ),
		esc_html( date_i18n( 'j F Y', $modified ) )
	);
}

/**
 * Internet Explorer e il vecchio Edge non gestiscono più (o male) XSLT:
 * per loro serve il sitemap in XML puro, senza foglio di stile, così
 * non compare l'avviso di funzionalità in dismissione.
 */
function pv_sitemap_stylesheet( $stylesheet ) {
	$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';

	if ( preg_match( '/MSIE|Trident|Edge\/\d/i', $ua ) ) {
		return '';
	}

	return $stylesheet;
}
add_filter( 'wpseo_stylesheet_url', 'pv_sitemap_stylesheet' );

/**
 * Pagine di servizio (carrello, checkout, area personale) e residui vuoti
 * non hanno valore di ricerca: fuori dal sitemap.
 */
function pv_exclude_service_pages_from_sitemap( $ids ) {
	$escludi = array( 3129, 312, 315, 316, 317, 3124, 169, 171 );
	$prodotti = wp_count_posts( 'product' );
	if ( empty( $prodotti->publish ) ) {
		$escludi[] = 314; // shop vuoto: torna in sitemap al primo prodotto
	}
	return array_merge( (array) $ids, $escludi );
}
add_filter( 'wpseo_exclude_from_sitemap_by_post_ids', 'pv_exclude_service_pages_from_sitemap' );

/**
 * L'archivio autore è disattivato e reindirizza alla home: tenerlo nella
 * sitemap significa dichiarare a Google un URL che risponde 301.
 */
function pv_remove_author_sitemap( $links ) {
	return array_values(
		array_filter(
			(array) $links,
			static function ( $link ) {
				return empty( $link['loc'] ) || false === strpos( $link['loc'], 'author-sitemap' );
			}
		)
	);
}
add_filter( 'wpseo_sitemap_index_links', 'pv_remove_author_sitemap', 30 );

/**
 * Meta description di riserva per video, stanze senza personaggio e categorie
 * del blog: evita pagine indicizzabili con description vuota o troppo corta.
 */
function pv_meta_description_fallback( $desc ) {
	$desc = is_string( $desc ) ? trim( $desc ) : '';
	if ( mb_strlen( $desc ) >= 70 ) {
		return $desc;
	}

	if ( is_singular( 'video' ) ) {
		$testo = 'Video VR per adulti: ' . get_the_title() . '. Dettagli, formato e accesso nella scheda. Guarda la scena in realtà virtuale, da browser o visore. Solo 18+.';
	} elseif ( is_singular( 'stanza' ) && ! get_query_var( 'personaggio' ) ) {
		$slug  = get_post_field( 'post_name', get_queried_object_id() );
		$pezzo = '';
		if ( function_exists( 'aria_room_seo' ) ) {
			$seo   = aria_room_seo( $slug );
			$pezzo = wp_strip_all_tags( (string) ( $seo['desc'] ?? '' ) );
		}
		if ( ! $pezzo ) {
			$pezzo = 'personaggi, chat dal vivo e incontri a tema.';
		}
		$testo = 'Stanza ' . get_the_title() . ': ' . rtrim( $pezzo, '.' ) . '. Chat gratis, 4 messaggi senza registrarti. Solo 18+.';
	} elseif ( is_category() ) {
		$term  = get_queried_object();
		$testo = term_description( $term );
		if ( ! $testo ) {
			$testo = 'Articoli e guide su ' . $term->name . ': cam VR, porno virtuale e realtà immersiva spiegati in modo chiaro.';
		}
	} else {
		return $desc;
	}

	$testo = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $testo ) ) );
	if ( mb_strlen( $testo ) > 155 ) {
		$testo = mb_substr( $testo, 0, 155 );
		$sp    = mb_strrpos( $testo, ' ' );
		if ( false !== $sp && $sp > 80 ) {
			$testo = mb_substr( $testo, 0, $sp );
		}
	}

	return rtrim( $testo, " \t\n\r\0\x0B.,;:!?" );
}
add_filter( 'wpseo_metadesc', 'pv_meta_description_fallback', 12 );

/**
 * Pagine vuote o di servizio: noindex finché non hanno contenuto reale.
 */
function pv_noindex_empty_pages( $robots ) {
	if ( is_page( 3124 ) || is_page( 312 ) || is_page( 169 ) || is_page( 171 ) ) {
		$robots['index']  = 'noindex';
		$robots['follow'] = 'follow';
	}

	if ( is_page( 314 ) ) {
		$prodotti = wp_count_posts( 'product' );
		if ( empty( $prodotti->publish ) ) {
			$robots['index']  = 'noindex';
			$robots['follow'] = 'follow';
		}
	}

	if ( is_category() ) {
		$termine = get_queried_object();
		if ( $termine instanceof WP_Term && 0 === (int) $termine->count ) {
			$robots['index']  = 'noindex';
			$robots['follow'] = 'follow';
		}
	}

	return $robots;
}
add_filter( 'wpseo_robots_array', 'pv_noindex_empty_pages', 20 );

/** Vecchi URL video non più esistenti: 301 al catalogo conservando gli UTM. */
function pv_redirect_old_video_urls() {
	if ( ! is_404() ) {
		return;
	}
	$path = (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH );
	if ( 0 !== strpos( $path, '/video/' ) ) {
		return;
	}
	$target = home_url( '/video-vr/' );
	$utm    = array_intersect_key(
		wp_unslash( $_GET ),
		array_flip( array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term' ) )
	);
	if ( $utm ) {
		$target = add_query_arg( $utm, $target );
	}
	wp_safe_redirect( $target, 301 );
	exit;
}
add_action( 'template_redirect', 'pv_redirect_old_video_urls', 1 );

/** Immagine social dell'indice del blog. */
function pv_blog_social_image() {
	if ( ! is_home() ) {
		return;
	}
	$url = content_url( '/uploads/brand/og-blog.jpg' );
	printf( '<meta property="og:image" content="%s" />' . "\n", esc_url( $url ) );
	printf( '<meta property="og:image:width" content="1200" />' . "\n" );
	printf( '<meta property="og:image:height" content="630" />' . "\n" );
	printf( '<meta property="og:image:alt" content="%s" />' . "\n", esc_attr__( 'Blog di Pornovirtuale: guide e novità su porno virtuale e VR', 'pornovirtuale' ) );
	printf( '<meta name="twitter:image" content="%s" />' . "\n", esc_url( $url ) );
	printf( '<meta name="twitter:image:alt" content="%s" />' . "\n", esc_attr__( 'Blog di Pornovirtuale: guide e novità su porno virtuale e VR', 'pornovirtuale' ) );
}
add_action( 'wp_head', 'pv_blog_social_image', 6 );

/** Immagine social dalla featured image, per articoli e pagine. */
function pv_featured_social_image() {
	if ( is_home() || is_singular( 'stanza' ) || ! is_singular() || ! has_post_thumbnail() ) {
		return;
	}
	$url = get_the_post_thumbnail_url( null, 'full' );
	if ( ! $url ) {
		return;
	}
	printf( '<meta property="og:image" content="%s" />' . "\n", esc_url( $url ) );
	printf( '<meta property="og:image:alt" content="%s" />' . "\n", esc_attr( get_the_title() ) );
	printf( '<meta name="twitter:image" content="%s" />' . "\n", esc_url( $url ) );
	printf( '<meta name="twitter:image:alt" content="%s" />' . "\n", esc_attr( get_the_title() ) );
}
add_action( 'wp_head', 'pv_featured_social_image', 6 );

/** Immagine social di riserva per le pagine senza immagine in evidenza. */
function pv_default_social_image() {
	if ( is_home() || is_singular( 'stanza' ) || has_post_thumbnail() ) {
		return;
	}
	if ( ! ( is_singular() || is_category() || is_post_type_archive() ) ) {
		return;
	}
	$url = is_front_page() ? content_url( '/uploads/brand/og-home.jpg' ) : content_url( '/uploads/brand/og-default.jpg' );
	printf( '<meta property="og:image" content="%s" />' . "\n", esc_url( $url ) );
	printf( '<meta property="og:image:width" content="1200" />' . "\n" );
	printf( '<meta property="og:image:height" content="630" />' . "\n" );
	printf( '<meta property="og:image:alt" content="%s" />' . "\n", esc_attr__( 'Pornovirtuale: cam VR, porno virtuale e stanze live', 'pornovirtuale' ) );
	printf( '<meta name="twitter:image" content="%s" />' . "\n", esc_url( $url ) );
	printf( '<meta name="twitter:image:alt" content="%s" />' . "\n", esc_attr__( 'Pornovirtuale: cam VR, porno virtuale e stanze live', 'pornovirtuale' ) );
}
add_action( 'wp_head', 'pv_default_social_image', 6 );

/** La vecchia pagina "Articoli" non è più l'indice del blog. */
function pv_redirect_old_posts_page() {
	if ( is_page( 312 ) ) {
		wp_safe_redirect( home_url( '/blog/' ), 301 );
		exit;
	}
}
add_action( 'template_redirect', 'pv_redirect_old_posts_page', 1 );

/**
 * Title SEO: toglie il nome sito ripetuto, distingue i personaggi omonimi
 * e limita la lunghezza a 60 caratteri.
 */
function pv_limit_seo_title( $title ) {
	if ( is_front_page() ) {
		return 'Pornovirtuale: cam VR, porno virtuale e stanze live';
	}

	$title = trim( wp_strip_all_tags( $title ) );

	// Rimuove le ripetizioni finali del nome sito (anche concatenate).
	for ( $i = 0; $i < 3; $i++ ) {
		$clean = preg_replace( '/\s*[|\-–—]\s*Pornovirtuale(?:\.com)?(?:\s*[—–-]\s*Cam VR.*)?$/iu', '', $title );
		if ( $clean === $title ) {
			break;
		}
		$title = $clean;
	}

	// Yoast aggiunge "Archives" agli archivi: in italiano è rumore.
	if ( is_category() || is_tag() || is_author() || is_post_type_archive() ) {
		$title = preg_replace( '/\s+Archives$/iu', '', $title );
		$title = preg_replace( '/^Archivi\s+/iu', '', $title );
	}

	// Personaggi omonimi: aggiunge il cognome per non avere title identici.
	// Lo slug del personaggio è l'ultimo segmento dell'URL, non il post_name.
	if ( is_singular( 'stanza' ) ) {
		$path  = trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
		$segs  = explode( '/', $path );
		$slug  = (string) end( $segs );
		$parts = explode( '-', $slug );
		if ( count( $parts ) >= 2 && $parts[0] && $parts[1] ) {
			$nome = ucfirst( $parts[0] );
			$cognome = ucfirst( $parts[1] );
			if ( ! preg_match( '/^Chat con ' . preg_quote( $nome . ' ' . $cognome, '/' ) . '\b/u', $title ) ) {
				$title = preg_replace(
				'/^Chat con ' . preg_quote( $nome, '/' ) . '\b/u',
				'Chat con ' . $nome . ' ' . $cognome,
				$title,
				1
			);
			}
		}
	}

	$title = trim( $title, " \t\n\r\0\x0B-–—|,.;:" );

	if ( mb_strlen( $title ) > 60 ) {
		$title = mb_substr( $title, 0, 60 );
		$space = mb_strrpos( $title, ' ' );
		if ( false !== $space && $space > 35 ) {
			$title = mb_substr( $title, 0, $space );
		}
		$title = rtrim( $title, " \t\n\r\0\x0B-–—|,.;:" );
	}

	// Un title troppo corto non dice nulla: aggiunge il nome sito.
	if ( mb_strlen( $title ) < 30 ) {
		$suffix = ' | Pornovirtuale.com — Cam VR e Porno Virtuale';
		$room   = 60 - mb_strlen( $title );
		if ( mb_strlen( $suffix ) > $room ) {
			$suffix  = mb_substr( $suffix, 0, $room );
			$last_sp = mb_strrpos( $suffix, ' ' );
			if ( false !== $last_sp && $last_sp >= 17 ) {
				$suffix = mb_substr( $suffix, 0, $last_sp );
			}
			$suffix = rtrim( $suffix, " \t\n\r\0\x0B-–—|,.;:" );
		}
		$title .= $suffix;
	}

	return $title;
}
add_filter( 'wpseo_title', 'pv_limit_seo_title', 99 );
add_filter( 'pre_get_document_title', 'pv_limit_seo_title', 99 );

/**
 * Menu: via gli ID progressivi e le classi ridondanti, restano solo
 * quelle usate da CSS e JS.
 */
add_filter( 'nav_menu_item_id', '__return_empty_string' );

function pv_nav_menu_classes( $classes ) {
	$keep = array(
		'menu-item',
		'menu-item-has-children',
		'current-menu-item',
		'current-menu-parent',
		'current-menu-ancestor',
		'current_page_item',
		'current_page_parent',
		'current_page_ancestor',
	);

	return array_values( array_intersect( $classes, $keep ) );
}
add_filter( 'nav_menu_css_class', 'pv_nav_menu_classes' );

/**
 * Utility: permalink di una pagina per slug.
 */
function pv_page_url( $slug ) {
	$page = get_page_by_path( $slug );

	if ( $page ) {
		return get_permalink( $page );
	}

	return home_url( '/' . trim( $slug, '/' ) . '/' );
}

/**
 * Classi del body.
 */
function pv_body_classes( $classes ) {
	$classes[] = 'pv-theme';

	if ( is_front_page() ) {
		$classes[] = 'pv-home';
	}

	return $classes;
}
add_filter( 'body_class', 'pv_body_classes' );

/**
 * Sidebar / widget.
 */
function pv_widgets_init() {
	register_sidebar(
		array(
			'name'          => __( 'Sidebar', 'pornovirtuale' ),
			'id'            => 'pv-sidebar',
			'description'   => __( 'Widget mostrati nella sidebar delle pagine interne.', 'pornovirtuale' ),
			'before_widget' => '<div id="%1$s" class="widget %2$s">',
			'after_widget'  => '</div>',
			'before_title'  => '<h3 class="widget-title">',
			'after_title'   => '</h3>',
		)
	);
}
add_action( 'widgets_init', 'pv_widgets_init' );

/**
 * Sottotitolo dei menu di fallback.
 */
function pv_menu_fallback() {
	echo '<ul>';
	wp_list_pages( array( 'title_li' => '', 'depth' => 1 ) );
	echo '</ul>';
}

/**
 * Pagine di archivio per i nuovi tipi di contenuto.
 */
function pv_archive_titles( $title ) {
	if ( is_category() ) {
		return single_cat_title( '', false );
	}
	if ( is_tag() ) {
		return single_tag_title( '', false );
	}
	if ( is_author() ) {
		return get_the_author();
	}

	if ( is_post_type_archive( 'stanza' ) ) {
		$title = __( 'Stanze live in realta virtuale', 'pornovirtuale' );
	} elseif ( is_post_type_archive( 'video' ) ) {
		$title = __( 'Video VR e porno in realta virtuale', 'pornovirtuale' );
	} elseif ( is_post_type_archive( 'modello' ) ) {
		$title = __( 'Modelli e performer', 'pornovirtuale' );
	}

	return $title;
}
add_filter( 'get_the_archive_title', 'pv_archive_titles' );

/**
 * Descrizione archivio.
 */
function pv_archive_description( $description ) {
	if ( is_post_type_archive( 'stanza' ) ) {
		$description = __( 'Stanze live gratuiti con modelli in realta virtuale: entra, guarda e interagisci in diretta.', 'pornovirtuale' );
	} elseif ( is_post_type_archive( 'video' ) ) {
		$description = __( 'La raccolta di video in realta virtuale: contenuti 180 e 360, formati per visori e browser. Risoluzioni da 720p a 1080p e oltre, da scegliere in base al dispositivo.', 'pornovirtuale' );
	}

	return $description;
}
add_filter( 'get_the_archive_description', 'pv_archive_description' );

/**
 * Excerpt.
 */
function pv_excerpt_length() {
	return 30;
}
add_filter( 'excerpt_length', 'pv_excerpt_length' );

/**
 * Le pagine non indicizzate usano il robots di Yoast: niente output duplicato.
 */
function pv_clean_head() {
	remove_action( 'wp_head', 'wp_robots', 1 );
}
add_action( 'wp_head', 'pv_clean_head', 1 );
