<?php
/**
 * Risultati di ricerca.
 *
 * @package Pornovirtuale
 */

get_header();
?>

<section class="pv-page-head">
	<div class="container">
		<h1 class="pv-page-title"><?php printf( esc_html__( 'Risultati per: %s', 'pornovirtuale' ), '<span>' . esc_html( get_search_query() ) . '</span>' ); ?></h1>
		<p style="color:var(--pv-muted);"><?php echo esc_html( sprintf( __( '%s risultati trovati', 'pornovirtuale' ), number_format_i18n( $GLOBALS['wp_query']->found_posts ) ) ); ?></p>
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
							<p class="pv-card__meta"><?php echo esc_html( get_post_type_object( get_post_type() )->labels->singular_name ); ?></p>
							<h3 class="pv-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
							<p class="pv-card__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 22, '...' ) ); ?></p>
						</div>
					</article>
					<?php
				endwhile;
				?>
			</div>
			<div class="pagination"><?php the_posts_pagination(); ?></div>
		<?php else : ?>
			<p class="pv-empty"><?php esc_html_e( 'Nessun risultato. Prova con un termine diverso.', 'pornovirtuale' ); ?></p>
		<?php endif; ?>
	</div>
</section>

<?php
get_footer();
