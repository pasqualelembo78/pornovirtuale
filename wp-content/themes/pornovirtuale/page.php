<?php
/**
 * Pagina interna.
 *
 * @package Pornovirtuale
 */

get_header();

while ( have_posts() ) :
	the_post();
	?>

	<section class="pv-page-head">
		<div class="container">
			<nav class="pv-breadcrumb"><a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a> / <?php echo esc_html( get_the_title() ); ?></nav>
			<h1 class="pv-page-title"><?php the_title(); ?></h1>
		</div>
	</section>

	<section class="section" style="padding-top:16px;">
		<div class="container">
			<div class="pv-content" style="max-width:880px;">
				<?php the_content(); ?>
			</div>

			<?php if ( is_page( 'supporto' ) && ! has_shortcode( get_post_field( 'post_content' ), 'pv_contact_form' ) ) : ?>
				<div style="max-width:880px;margin-top:26px;">
					<?php echo do_shortcode( '[pv_contact_form]' ); ?>
				</div>
			<?php endif; ?>
		</div>
	</section>

	<?php
endwhile;

get_footer();
