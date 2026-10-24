<?php
/**
 * Template generico per tassonomie PV (categorie, tipologie, fonti).
 *
 * @package Pornovirtuale
 */

get_header();

$pv_term  = get_queried_object();
$pv_types = ( $pv_term instanceof WP_Term ) ? get_object_taxonomies( $pv_term->object_type ) : array();
$pv_main  = ( $pv_term instanceof WP_Term && in_array( 'stanza', (array) $pv_term->object_type, true ) ) ? 'stanza' : 'video';
?>

<section class="pv-page-head">
	<div class="container">
		<nav class="pv-breadcrumb"><a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a> / <?php echo esc_html( $pv_main === 'stanza' ? get_post_type_archive_link( 'stanza' ) : get_post_type_archive_link( 'video' ) ); ?></nav>
		<h1 class="pv-page-title"><?php echo esc_html( $pv_term->name ); ?></h1>
		<?php if ( $pv_term->description ) : ?>
			<p style="color:var(--pv-muted);max-width:760px;"><?php echo esc_html( $pv_term->description ); ?></p>
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
					if ( 'stanza' === get_post_type() ) {
						echo PV_Embed::room_card( get_the_ID() );
					} else {
						$pv_id     = get_the_ID();
						$pv_poster = get_the_post_thumbnail_url( $pv_id, 'pv-card' );
						$pv_dur    = get_post_meta( $pv_id, '_pv_duration', true );
						?>
						<article class="pv-card pv-card--video">
							<a class="pv-card__media" href="<?php the_permalink(); ?>">
								<?php if ( $pv_poster ) : ?>
									<img src="<?php echo esc_url( $pv_poster ); ?>" alt="" loading="lazy" decoding="async">
								<?php else : ?>
									<span class="pv-card__placeholder" aria-hidden="true"></span>
								<?php endif; ?>
								<span class="pv-card__badge">VR</span>
								<?php if ( $pv_dur ) : ?>
									<span class="pv-card__duration"><?php echo esc_html( $pv_dur ); ?></span>
								<?php endif; ?>
							</a>
							<div class="pv-card__body">
								<h3 class="pv-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
							</div>
						</article>
						<?php
					}
				endwhile;
				?>
			</div>
			<div class="pagination"><?php the_posts_pagination(); ?></div>
		<?php else : ?>
			<p class="pv-empty"><?php esc_html_e( 'Nessun contenuto in questa categoria.', 'pornovirtuale' ); ?></p>
		<?php endif; ?>
	</div>
</section>

<?php
get_footer();
