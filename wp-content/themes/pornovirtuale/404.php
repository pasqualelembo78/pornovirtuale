<?php
/**
 * 404.
 *
 * @package Pornovirtuale
 */

get_header();
?>

<section class="pv-page-head">
	<div class="container">
		<h1 class="pv-page-title"><?php esc_html_e( 'Pagina non trovata', 'pornovirtuale' ); ?></h1>
		<p style="color:var(--pv-muted);max-width:700px;"><?php esc_html_e( 'La pagina che cerchi non esiste piu o e stata spostata. Ecco dove andare.', 'pornovirtuale' ); ?></p>
		<div class="page-links">
			<a class="btn" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Torna alla home', 'pornovirtuale' ); ?></a>
			<a class="btn btn--ghost" href="<?php echo esc_url( get_post_type_archive_link( 'stanza' ) ); ?>"><?php esc_html_e( 'Stanze live', 'pornovirtuale' ); ?></a>
			<a class="btn btn--ghost" href="<?php echo esc_url( get_post_type_archive_link( 'video' ) ); ?>"><?php esc_html_e( 'Video VR', 'pornovirtuale' ); ?></a>
		</div>
	</div>
</section>

<?php
get_footer();
