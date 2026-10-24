<?php
/**
 * Archivio stanze live.
 *
 * @package Pornovirtuale
 */

get_header();

$pv_tipologie = get_terms(
	array(
		'taxonomy'   => 'pv_tipologia',
		'hide_empty' => true,
	)
);
?>

<section class="pv-page-head">
	<div class="container">
		<h1 class="pv-page-title"><?php echo esc_html( get_the_archive_title() ); ?></h1>
		<?php if ( get_the_archive_description() ) : ?>
			<p style="color:var(--pv-muted);max-width:760px;"><?php echo esc_html( get_the_archive_description() ); ?></p>
		<?php endif; ?>

		<?php if ( ! is_wp_error( $pv_tipologie ) && $pv_tipologie ) : ?>
			<div class="page-links">
				<?php foreach ( $pv_tipologie as $pv_tip ) : ?>
					<a class="internal-links" style="margin:0;" href="<?php echo esc_url( get_term_link( $pv_tip ) ); ?>"><?php echo esc_html( $pv_tip->name ); ?></a>
				<?php endforeach; ?>
			</div>
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
					echo PV_Embed::room_card( get_the_ID() );
				endwhile;
				?>
			</div>
			<div class="pagination"><?php the_posts_pagination(); ?></div>
		<?php else : ?>
			<p class="pv-empty"><?php esc_html_e( 'Non ci sono ancora stanze pubblicate. Aggiungile dalla sezione Stanze del pannello di controllo.', 'pornovirtuale' ); ?></p>
		<?php endif; ?>
	</div>
</section>

<?php
get_footer();
