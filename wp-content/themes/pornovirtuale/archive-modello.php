<?php
/**
 * Archivio modelli.
 *
 * @package Pornovirtuale
 */

get_header();
?>

<section class="pv-page-head">
	<div class="container">
		<h1 class="pv-page-title"><?php echo esc_html( get_the_archive_title() ); ?></h1>
		<p style="color:var(--pv-muted);max-width:760px;"><?php esc_html_e( 'Le performer e i modelli che trovi nelle stanze live e nei video VR di Pornovirtuale.com.', 'pornovirtuale' ); ?></p>
	</div>
</section>

<section class="section" style="padding-top:20px;">
	<div class="container">
		<?php if ( have_posts() ) : ?>
			<div class="pv-grid pv-grid--models">
				<?php
				while ( have_posts() ) :
					the_post();
					$pv_id     = get_the_ID();
					$pv_poster = get_the_post_thumbnail_url( $pv_id, 'pv-card' );
					$pv_url    = get_post_meta( $pv_id, '_pv_affiliate_url', true );
					?>
					<article class="pv-card pv-card--model">
						<a class="pv-card__media" href="<?php echo esc_url( $pv_url ? $pv_url : get_permalink( $pv_id ) ); ?>"<?php echo $pv_url ? ' rel="nofollow sponsored noopener" target="_blank"' : ''; ?>>
							<?php if ( $pv_poster ) : ?>
								<img src="<?php echo esc_url( $pv_poster ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>" loading="lazy" decoding="async">
							<?php else : ?>
								<span class="pv-card__placeholder" aria-hidden="true"></span>
							<?php endif; ?>
						</a>
						<div class="pv-card__body">
							<h3 class="pv-card__title"><a href="<?php echo esc_url( $pv_url ? $pv_url : get_permalink( $pv_id ) ); ?>"><?php the_title(); ?></a></h3>
						</div>
					</article>
					<?php
				endwhile;
				?>
			</div>
			<div class="pagination"><?php the_posts_pagination(); ?></div>
		<?php else : ?>
			<p class="pv-empty"><?php esc_html_e( 'Non ci sono ancora profili modello.', 'pornovirtuale' ); ?></p>
		<?php endif; ?>
	</div>
</section>

<?php
get_footer();
