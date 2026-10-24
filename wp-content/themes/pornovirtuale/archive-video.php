<?php
/**
 * Archivio video VR.
 *
 * @package Pornovirtuale
 */

get_header();
?>

<section class="pv-page-head">
	<div class="container">
		<h1 class="pv-page-title"><?php echo esc_html( get_the_archive_title() ); ?></h1>
		<?php if ( get_the_archive_description() ) : ?>
			<p style="color:var(--pv-muted);max-width:760px;"><?php echo esc_html( get_the_archive_description() ); ?></p>
		<?php endif; ?>
	</div>
</section>

<section class="section" style="padding-top:20px;">
	<div class="container">
		<?php if ( have_posts() ) : ?>
			<div class="pv-grid">
				<?php
				while ( have_posts() ) :
					the_post();
					$pv_id       = get_the_ID();
					$pv_poster   = get_the_post_thumbnail_url( $pv_id, 'pv-card' );
					$pv_duration = get_post_meta( $pv_id, '_pv_duration', true );
					$pv_res      = get_post_meta( $pv_id, '_pv_resolution', true );
					$pv_vr       = (int) get_post_meta( $pv_id, '_pv_vr_mode', true );
					?>
					<article class="pv-card pv-card--video">
						<a class="pv-card__media" href="<?php the_permalink(); ?>" title="<?php echo esc_attr( get_the_title() ); ?>">
							<?php if ( $pv_poster ) : ?>
								<img src="<?php echo esc_url( $pv_poster ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>" loading="lazy" decoding="async">
							<?php else : ?>
								<span class="pv-card__placeholder" aria-hidden="true"></span>
							<?php endif; ?>
							<span class="pv-card__badge">VR</span>
							<?php if ( $pv_duration ) : ?>
								<span class="pv-card__duration"><?php echo esc_html( $pv_duration ); ?></span>
							<?php endif; ?>
						</a>
						<div class="pv-card__body">
							<h3 class="pv-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
							<?php if ( $pv_res ) : ?>
								<p class="pv-card__meta"><?php echo esc_html( $pv_res ); ?><?php echo $pv_vr ? ' · ' . esc_html__( 'Visione VR', 'pornovirtuale' ) : ''; ?></p>
							<?php endif; ?>
						</div>
					</article>
					<?php
				endwhile;
				?>
			</div>
			<div class="pagination"><?php the_posts_pagination(); ?></div>
		<?php else : ?>
			<p class="pv-empty"><?php esc_html_e( 'Non ci sono ancora video pubblicati.', 'pornovirtuale' ); ?></p>
		<?php endif; ?>
	</div>
</section>

<?php
get_footer();
