<?php
/**
 * Blog / indice articoli.
 *
 * @package Pornovirtuale
 */

get_header();
?>

<section class="pv-page-head">
	<div class="container">
		<h1 class="pv-page-title"><?php echo esc_html( wp_get_document_title() ); ?></h1>
		<p style="color:var(--pv-muted);max-width:760px;"><?php esc_html_e( 'Guide, tutorial, recensioni e novita su porno virtuale e realta virtuale.', 'pornovirtuale' ); ?></p>
	</div>
</section>

<section class="section" style="padding-top:20px;">
	<div class="container">
		<?php if ( have_posts() ) : ?>
			<div class="pv-grid pv-grid--articles">
				<?php
				while ( have_posts() ) :
					the_post();
					?>
					<article class="pv-card pv-card--article">
						<div class="pv-card__body">
							<p class="pv-card__meta"><?php echo esc_html( get_the_date( 'j F Y' ) ); ?></p>
							<h3 class="pv-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
							<p class="pv-card__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 24, '...' ) ); ?></p>
						</div>
					</article>
					<?php
				endwhile;
				?>
			</div>
			<div class="pagination"><?php the_posts_pagination(); ?></div>
		<?php else : ?>
			<p class="pv-empty"><?php esc_html_e( 'Nessun articolo pubblicato al momento.', 'pornovirtuale' ); ?></p>
		<?php endif; ?>
	</div>
</section>

<?php
get_footer();
