<?php
/**
 * Profilo modello.
 *
 * @package Pornovirtuale
 */

get_header();

while ( have_posts() ) :
	the_post();

	$pv_id     = get_the_ID();
	$pv_url    = get_post_meta( $pv_id, '_pv_affiliate_url', true );
	$pv_source = get_post_meta( $pv_id, '_pv_source', true );
	?>

	<section class="pv-page-head">
		<div class="container">
			<h1 class="pv-page-title"><?php the_title(); ?></h1>
			<?php if ( $pv_source ) : ?>
				<div class="pv-entry-meta"><?php echo esc_html( $pv_source ); ?></div>
			<?php endif; ?>
		</div>
	</section>

	<section class="section" style="padding-top:16px;">
		<div class="container">
			<div style="display:grid;grid-template-columns:minmax(0,320px) minmax(0,1fr);gap:30px;align-items:start;">
				<div>
					<?php if ( has_post_thumbnail() ) : ?>
						<?php the_post_thumbnail( 'pv-card' ); ?>
					<?php endif; ?>
					<?php if ( $pv_url ) : ?>
						<a class="btn" style="margin-top:14px;width:100%;" href="<?php echo esc_url( $pv_url ); ?>" rel="nofollow sponsored noopener" target="_blank"><?php esc_html_e( 'Vai al profilo', 'pornovirtuale' ); ?></a>
					<?php endif; ?>
				</div>
				<div class="pv-content">
					<?php the_content(); ?>
				</div>
			</div>

			<?php
			$pv_rooms = new WP_Query(
				array(
					'post_type'      => 'stanza',
					'posts_per_page' => 6,
					'meta_query'     => array(
						array(
							'key'   => '_pv_model_name',
							'value' => get_the_title(),
						),
					),
				)
			);

			if ( $pv_rooms->have_posts() ) :
				?>
				<div class="pv-related">
					<h2 class="pv-section-title pv-section-title--left"><?php esc_html_e( 'Stanze con questa modello', 'pornovirtuale' ); ?></h2>
					<div class="pv-grid">
						<?php
						while ( $pv_rooms->have_posts() ) :
							$pv_rooms->the_post();
							echo PV_Embed::room_card( get_the_ID() );
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
