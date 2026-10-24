<?php
/**
 * Articolo del blog.
 *
 * @package Pornovirtuale
 */

get_header();

while ( have_posts() ) :
	the_post();
	?>

	<section class="pv-page-head">
		<div class="container">
			<nav class="pv-breadcrumb"><a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a> / <a href="<?php echo esc_url( pv_page_url( 'blog' ) ); ?>">Blog</a> / <?php echo esc_html( get_the_title() ); ?></nav>
			<h1 class="pv-page-title"><?php the_title(); ?></h1>
			<div class="pv-entry-meta">
				<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date( 'j F Y' ) ); ?></time>
			</div>
		</div>
	</section>

	<section class="section" style="padding-top:16px;">
		<div class="container">
			<?php if ( has_post_thumbnail() ) : ?>
				<div style="max-width:900px;margin-bottom:24px;"><?php the_post_thumbnail( 'pv-hero' ); ?></div>
			<?php endif; ?>

			<div class="pv-content" style="max-width:820px;">
				<?php the_content(); ?>
			</div>
		</div>
	</section>

	<?php
endwhile;

get_footer();
