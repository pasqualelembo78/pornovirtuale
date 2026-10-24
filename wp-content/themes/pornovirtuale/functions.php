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
function pv_enqueue_assets() {
	wp_enqueue_style( 'pornovirtuale', get_stylesheet_uri(), array(), PV_VERSION );
	wp_enqueue_style( 'pornovirtuale-fonts', 'https://fonts.googleapis.com/css2?family=Orbitron:wght@500;700&family=Roboto:wght@400;500&display=swap', array(), null );

	wp_enqueue_script( 'pv-theme', PV_THEME_URI . '/assets/js/theme.js', array(), PV_VERSION, true );
}
add_action( 'wp_enqueue_scripts', 'pv_enqueue_assets' );

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
		$description = __( 'La raccolta di video in realta virtuale: contenuti 180 e 360, formati per visori e browser.', 'pornovirtuale' );
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
