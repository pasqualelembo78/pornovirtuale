<?php
/**
 * Header.
 *
 * @package Pornovirtuale
 */

?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="theme-color" content="#12121d">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="site-header" id="site-header">
	<div class="container site-header__inner">
		<a class="site-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" title="Pornovirtuale — Cam VR e Porno Virtuale">
			Pornovirtuale <span>18+</span>
		</a>

		<nav class="site-nav" id="site-nav" aria-label="<?php esc_attr_e( 'Menu principale', 'pornovirtuale' ); ?>">
			<?php
			if ( has_nav_menu( 'primary' ) ) {
				wp_nav_menu(
					array(
						'theme_location' => 'primary',
						'menu_class'     => '',
						'container'      => false,
						'depth'          => 2,
						'fallback_cb'    => 'pv_menu_fallback',
					)
				);
			} else {
				pv_menu_fallback();
			}
			?>
		</nav>

		<div class="site-header__actions">
			<form class="site-search" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
				<label class="visually-hidden" for="s"><?php esc_html_e( 'Cerca', 'pornovirtuale' ); ?></label>
				<input type="search" id="s" name="s" placeholder="<?php esc_attr_e( 'Cerca stanze, video...', 'pornovirtuale' ); ?>" value="<?php echo esc_attr( get_search_query() ); ?>">
				<button type="submit" aria-label="<?php esc_attr_e( 'Cerca', 'pornovirtuale' ); ?>">
					<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path></svg>
				</button>
			</form>

			<a class="btn btn--sm" href="<?php echo esc_url( pv_page_url( 'login' ) ); ?>"><?php esc_html_e( 'Accedi', 'pornovirtuale' ); ?></a>

			<button class="nav-toggle" id="nav-toggle" aria-label="<?php esc_attr_e( 'Apri il menu', 'pornovirtuale' ); ?>" aria-expanded="false">
				<span></span><span></span><span></span>
			</button>
		</div>
	</div>
</header>

<main class="site-main">
