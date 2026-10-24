<?php
/**
 * Home: hero, stanze live, video VR, categorie, cam live, articoli.
 *
 * @package Pornovirtuale
 */

get_header();

$pv_featured = get_posts(
	array(
		'post_type'      => 'stanza',
		'posts_per_page' => 1,
		'meta_key'       => '_pv_viewers',
		'orderby'        => 'meta_value_num',
		'order'          => 'DESC',
	)
);
?>

<section class="hero">
	<div class="hero-content">
		<h1><?php esc_html_e( 'Il porno virtuale, dal vivo, in realta virtuale', 'pornovirtuale' ); ?></h1>
		<p><?php esc_html_e( 'Stanze live gratuite con modelli in VR, anteprime 360 e video immersivi: entra subito, dal browser o dal tuo visore.', 'pornovirtuale' ); ?></p>
		<a href="<?php echo esc_url( get_post_type_archive_link( 'stanza' ) ); ?>"><span class="btn"><?php esc_html_e( 'Entra nelle stanze live', 'pornovirtuale' ); ?></span></a>
		<a href="<?php echo esc_url( get_post_type_archive_link( 'video' ) ); ?>"><span class="btn btn--ghost"><?php esc_html_e( 'Scopri i video VR', 'pornovirtuale' ); ?></span></a>
		<div class="hero__badges">
			<span class="hero__badge"><?php esc_html_e( 'Gratuito', 'pornovirtuale' ); ?></span>
			<span class="hero__badge"><?php esc_html_e( 'Live 24/7', 'pornovirtuale' ); ?></span>
			<span class="hero__badge"><?php esc_html_e( 'Oculus / Quest / mobile', 'pornovirtuale' ); ?></span>
			<span class="hero__badge"><?php esc_html_e( 'Contenuti 180 e 360', 'pornovirtuale' ); ?></span>
		</div>
	</div>
</section>

<?php if ( $pv_featured ) : ?>
	<?php $pv_feat = $pv_featured[0]; ?>
	<section class="section">
		<div class="container">
			<div class="pv-section-head">
				<h2 class="pv-section-title pv-section-title--left"><?php esc_html_e( 'In evidenza', 'pornovirtuale' ); ?></h2>
				<a class="pv-more" href="<?php echo esc_url( get_permalink( $pv_feat ) ); ?>"><?php esc_html_e( 'Vedi la stanza', 'pornovirtuale' ); ?> &rarr;</a>
			</div>
			<?php echo do_shortcode( '[pv_stanza id="' . (int) $pv_feat->ID . '"]' ); ?>
		</div>
	</section>
<?php endif; ?>

<section class="section" style="background:var(--pv-bg-2);">
	<div class="container">
		<div class="pv-section-head">
			<h2 class="pv-section-title pv-section-title--left"><?php esc_html_e( 'Stanze live ora', 'pornovirtuale' ); ?></h2>
			<a class="pv-more" href="<?php echo esc_url( get_post_type_archive_link( 'stanza' ) ); ?>"><?php esc_html_e( 'Tutte le stanze', 'pornovirtuale' ); ?> &rarr;</a>
		</div>
		<?php echo do_shortcode( '[pv_rooms number="12"]' ); ?>
	</div>
</section>

<section class="section">
	<div class="container">
		<div class="pv-section-head">
			<h2 class="pv-section-title pv-section-title--left"><?php esc_html_e( 'Video VR gratuiti', 'pornovirtuale' ); ?></h2>
			<a class="pv-more" href="<?php echo esc_url( get_post_type_archive_link( 'video' ) ); ?>"><?php esc_html_e( 'Catalogo completo', 'pornovirtuale' ); ?> &rarr;</a>
		</div>
		<?php echo do_shortcode( '[pv_videos number="8"]' ); ?>
	</div>
</section>

<section class="section" style="background:var(--pv-bg-2);">
	<div class="container">
		<?php echo do_shortcode( '[pv_cam_live title="' . esc_attr__( 'Cam live in diretta', 'pornovirtuale' ) . '"]' ); ?>
	</div>
</section>

<section class="section">
	<div class="container">
		<div class="pv-section-head">
			<h2 class="pv-section-title pv-section-title--left"><?php esc_html_e( 'Guide e articoli', 'pornovirtuale' ); ?></h2>
			<a class="pv-more" href="<?php echo esc_url( pv_page_url( 'blog' ) ); ?>"><?php esc_html_e( 'Tutto il blog', 'pornovirtuale' ); ?> &rarr;</a>
		</div>
		<?php echo do_shortcode( '[pv_articles number="6"]' ); ?>
	</div>
</section>

<section class="seo-text">
	<div class="container">
		<h2 class="pv-section-title"><?php esc_html_e( 'Pornovirtuale.com: cam VR e porno virtuale', 'pornovirtuale' ); ?></h2>
		<p><?php esc_html_e( 'Pornovirtuale.com e la piattaforma italiana dedicata al porno virtuale: stanze live in realta virtuale, show VIP, anteprime 360 e video immersivi, disponibili gratuitamente e senza registrazione per la navigazione.', 'pornovirtuale' ); ?></p>
		<p><?php esc_html_e( 'Ogni stanza e ottimizzata per la riproduzione fluida su desktop, smartphone e visori VR. Trovi cam con performer di tutta Europa, contenuti 180 e 360 gradi e la sezione VIP con show esclusivi e abbonamenti.', 'pornovirtuale' ); ?></p>
		<p><?php esc_html_e( 'I contenuti mostrati nelle stanze live provengono da reti partner: navigando e iscrivendoti a una di queste piattaforme possiamo ricevere una commissione, senza alcun costo aggiuntivo per te.', 'pornovirtuale' ); ?></p>
	</div>
</section>

<?php
get_footer();
