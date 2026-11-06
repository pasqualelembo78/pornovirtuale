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
			<?php if ( false === stripos( (string) get_post_field( 'post_content', get_the_ID() ), '<h1' ) ) : ?>
				<h1 class="pv-page-title"><?php the_title(); ?></h1>
			<?php endif; ?>
			<?php pv_updated_date(); ?>
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

			<div style="max-width:880px;margin-top:36px;">
				<h2 class="pv-section-title pv-section-title--left">Pornovirtuale.com in breve</h2>
				<p style="color:var(--pv-muted);">
					Pornovirtuale.com è una guida italiana al porno virtuale e alle cam VR: raccoglie stanze live,
					video a 180 e 360 gradi, profili di modelli e guide pratiche, con accesso gratuito e senza installazioni.
					Le stanze si aprono nel browser, su desktop e smartphone, e molte sono ottimizzate per il visore.
				</p>
				<p style="color:var(--pv-muted);">
					I contenuti live arrivano da reti partner selezionate, mentre video e schede dei modelli si consultano
					liberamente. La sezione VIP raccoglie show personalizzati e materiale esclusivo, con formule di abbonamento
					trasparenti. Per orientarti usa il menu principale: trovi le categorie delle stanze, le anteprime live,
					il catalogo video e il blog con tutorial e recensioni.
				</p>
			</div>
		</div>
	</section>

	<?php
endwhile;

get_footer();
