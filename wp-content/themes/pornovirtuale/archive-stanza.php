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

<section class="section" style="padding-top:10px;">
	<div class="container" style="max-width:860px;">
		<p style="color:var(--pv-muted);margin:0 0 10px;">
			Ventiquattro stanze a tema, ognuna con i suoi personaggi: qui non guardi un video, <strong>scrivi</strong>.
			Scegli una stanza, apri la card di un personaggio e la chat parte con lui o lei. Gli incontri restano legati al tuo account,
			così puoi riprenderli quando vuoi.
		</p>
		<p style="color:var(--pv-muted);margin:0 0 10px;">
			Serve solo un login gratuito: niente installazioni, nessun visore. Le stanze vanno dai temi classici
			(amanti segreti, tradimenti, incontri bollenti) a quelli più specifici come BDSM, feticismi o roleplay erotico.
		</p>
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

		<?php
		// Indice descrittivo delle stanze: è la parte che si legge (e si cita) davvero
		if ( function_exists( 'aria_room_seo_data' ) ) :
			?>
			<div style="max-width:860px;margin:34px 0 10px;">
				<h2 class="pv-section-title pv-section-title--left">Le stanze, una per una</h2>
				<?php foreach ( aria_room_seo_data() as $pv_slug => $pv_dati ) :
					$pv_stanza = get_page_by_path( $pv_slug, OBJECT, 'stanza' );
					if ( ! $pv_stanza ) {
						continue;
					}
					?>
					<p style="margin:0 0 14px;">
						<strong><a href="<?php echo esc_url( get_permalink( $pv_stanza ) ); ?>"><?php echo esc_html( get_the_title( $pv_stanza ) ); ?></a></strong> —
						<?php echo esc_html( $pv_dati['desc'] ); ?>
					</p>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</section>

<?php
get_footer();
