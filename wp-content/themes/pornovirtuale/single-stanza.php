<?php
/**
 * Singola stanza live.
 *
 * @package Pornovirtuale
 */

get_header();

while ( have_posts() ) :
	the_post();

	$pv_id       = get_the_ID();
	$pv_model    = get_post_meta( $pv_id, '_pv_model_name', true );
	$pv_viewers  = (int) get_post_meta( $pv_id, '_pv_viewers', true );
	$pv_chat     = get_post_meta( $pv_id, '_pv_chat_url', true );
	$pv_is_vip   = (int) get_post_meta( $pv_id, '_pv_is_vip', true );
	$pv_terms    = wp_get_post_terms( $pv_id, 'pv_categoria' );
	$pv_tipologie = wp_get_post_terms( $pv_id, 'pv_tipologia' );
	?>

	<section class="pv-page-head">
		<div class="container">
			<nav class="pv-breadcrumb"><a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a> / <a href="<?php echo esc_url( get_post_type_archive_link( 'stanza' ) ); ?>">Stanze</a> / <?php echo esc_html( get_the_title() ); ?></nav>
			<h1 class="pv-page-title"><?php the_title(); ?></h1>
			<div class="pv-entry-meta">
				<?php if ( $pv_model ) : ?>
					<span><?php echo esc_html( $pv_model ); ?></span> &middot;
				<?php endif; ?>
				<?php if ( $pv_viewers ) : ?>
					<span><?php echo esc_html( number_format_i18n( $pv_viewers ) ); ?> <?php esc_html_e( 'spettatori', 'pornovirtuale' ); ?></span> &middot;
				<?php endif; ?>
				<span class="rta-label"><strong>18+</strong> <?php esc_html_e( 'Live', 'pornovirtuale' ); ?></span>
			</div>
		</div>
	</section>

	<section class="section" style="padding-top:16px;">
		<div class="container">
			<?php echo do_shortcode( '[pv_stanza id="' . (int) $pv_id . '"]' ); ?>

			<div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:16px;">
				<?php if ( $pv_chat ) : ?>
					<a class="btn" href="<?php echo esc_url( $pv_chat ); ?>" rel="nofollow sponsored noopener" target="_blank"><?php esc_html_e( 'Apri la chat', 'pornovirtuale' ); ?></a>
				<?php endif; ?>
				<?php if ( $pv_is_vip ) : ?>
					<span class="btn btn--gold"><?php esc_html_e( 'Contenuto VIP', 'pornovirtuale' ); ?></span>
				<?php endif; ?>
			</div>

			<?php if ( ( ! is_wp_error( $pv_terms ) && $pv_terms ) || ( ! is_wp_error( $pv_tipologie ) && $pv_tipologie ) ) : ?>
				<div class="page-links" style="margin-top:22px;">
					<?php
					foreach ( array( $pv_terms, $pv_tipologie ) as $group ) {
						if ( is_wp_error( $group ) || ! $group ) {
							continue;
						}
						foreach ( $group as $term ) {
							printf(
								'<a class="internal-links" style="margin:0;" href="%s">%s</a>',
								esc_url( get_term_link( $term ) ),
								esc_html( $term->name )
							);
						}
					}
					?>
				</div>
			<?php endif; ?>

			<?php if ( has_post_thumbnail() ) : ?>
				<div style="margin-top:26px;"><?php the_post_thumbnail( 'pv-hero' ); ?></div>
			<?php endif; ?>

			<div class="pv-content" style="max-width:820px;">
				<?php the_content(); ?>
			</div>

			<?php
			$pv_related = new WP_Query(
				array(
					'post_type'      => 'stanza',
					'posts_per_page' => 6,
					'post__not_in'   => array( $pv_id ),
					'tax_query'      => $pv_terms && ! is_wp_error( $pv_terms ) ? array(
						array(
							'taxonomy' => 'pv_categoria',
							'field'    => 'term_id',
							'terms'    => wp_list_pluck( $pv_terms, 'term_id' ),
						),
					) : array(),
				)
			);

			if ( $pv_related->have_posts() ) :
				?>
				<div class="pv-related">
					<h2 class="pv-section-title pv-section-title--left"><?php esc_html_e( 'Stanze simili', 'pornovirtuale' ); ?></h2>
					<div class="pv-grid">
						<?php
						while ( $pv_related->have_posts() ) :
							$pv_related->the_post();
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
