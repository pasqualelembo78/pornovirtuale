<?php
/**
 * Singolo video VR.
 *
 * @package Pornovirtuale
 */

get_header();

while ( have_posts() ) :
	the_post();

	$pv_id       = get_the_ID();
	$pv_duration = get_post_meta( $pv_id, '_pv_duration', true );
	$pv_res      = get_post_meta( $pv_id, '_pv_resolution', true );
	$pv_vr       = (int) get_post_meta( $pv_id, '_pv_vr_mode', true );
	$pv_source   = get_post_meta( $pv_id, '_pv_source', true );
	?>

	<section class="pv-page-head">
		<div class="container">
			<nav class="pv-breadcrumb"><a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a> / <a href="<?php echo esc_url( get_post_type_archive_link( 'video' ) ); ?>">Video VR</a> / <?php echo esc_html( get_the_title() ); ?></nav>
			<h1 class="pv-page-title"><?php the_title(); ?></h1>
			<div class="pv-entry-meta">
				<?php if ( $pv_duration ) : ?>
					<span><?php echo esc_html( $pv_duration ); ?></span> &middot;
				<?php endif; ?>
				<?php if ( $pv_res ) : ?>
					<span><?php echo esc_html( $pv_res ); ?></span> &middot;
				<?php endif; ?>
				<span><?php echo esc_html( get_the_date( 'j F Y' ) ); ?></span>
			</div>
		</div>
	</section>

	<section class="section" style="padding-top:16px;">
		<div class="container">
			<?php echo do_shortcode( '[pv_video id="' . (int) $pv_id . '"]' ); ?>

			<div class="pv-content" style="max-width:820px;margin-top:24px;">
				<?php the_content(); ?>
			</div>

			<?php
			$pv_related = new WP_Query(
				array(
					'post_type'      => 'video',
					'posts_per_page' => 6,
					'post__not_in'   => array( $pv_id ),
				)
			);

			if ( $pv_related->have_posts() ) :
				?>
				<div class="pv-related">
					<h2 class="pv-section-title pv-section-title--left"><?php esc_html_e( 'Altri video VR', 'pornovirtuale' ); ?></h2>
					<div class="pv-grid">
						<?php
						while ( $pv_related->have_posts() ) :
							$pv_related->the_post();
							$pv_rid    = get_the_ID();
							$pv_rpost  = get_the_post_thumbnail_url( $pv_rid, 'pv-card' );
							$pv_rdur   = get_post_meta( $pv_rid, '_pv_duration', true );
							?>
							<article class="pv-card pv-card--video">
								<a class="pv-card__media" href="<?php the_permalink(); ?>">
									<?php if ( $pv_rpost ) : ?>
										<img src="<?php echo esc_url( $pv_rpost ); ?>" alt="" loading="lazy" decoding="async">
									<?php else : ?>
										<span class="pv-card__placeholder" aria-hidden="true"></span>
									<?php endif; ?>
									<span class="pv-card__badge">VR</span>
									<?php if ( $pv_rdur ) : ?>
										<span class="pv-card__duration"><?php echo esc_html( $pv_rdur ); ?></span>
									<?php endif; ?>
								</a>
								<div class="pv-card__body">
									<h3 class="pv-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
								</div>
							</article>
							<?php
						endwhile;
						?>
					</div>
				</div>
				<?php
				wp_reset_postdata();
			endif;
			?>
		</div>
	</section>

	<?php
endwhile;

get_footer();
