<?php
/**
 * Footer.
 *
 * @package Pornovirtuale
 */

$pv_categorie = get_terms(
	array(
		'taxonomy'   => 'pv_categoria',
		'hide_empty' => false,
		'number'     => 6,
	)
);
?>
</main>

<footer class="site-footer">
	<div class="container">
		<div class="site-footer__grid">
			<div>
				<h4>Pornovirtuale</h4>
				<p><?php esc_html_e( 'Cam VR, stanze live e video in realta virtuale per adulti. Contenuti gratuiti, accessibili da browser o visore.', 'pornovirtuale' ); ?></p>
				<p class="rta-label" style="margin-top:12px;"><strong>18+</strong> <?php esc_html_e( 'Solo per adulti', 'pornovirtuale' ); ?></p>
			</div>

			<div>
				<h4><?php esc_html_e( 'Naviga', 'pornovirtuale' ); ?></h4>
				<ul>
					<li><a href="<?php echo esc_url( get_post_type_archive_link( 'stanza' ) ); ?>"><?php esc_html_e( 'Stanze live', 'pornovirtuale' ); ?></a></li>
					<li><a href="<?php echo esc_url( get_post_type_archive_link( 'video' ) ); ?>"><?php esc_html_e( 'Video VR', 'pornovirtuale' ); ?></a></li>
					<li><a href="<?php echo esc_url( get_post_type_archive_link( 'modello' ) ); ?>"><?php esc_html_e( 'Modelli', 'pornovirtuale' ); ?></a></li>
					<li><a href="<?php echo esc_url( pv_page_url( 'blog' ) ); ?>"><?php esc_html_e( 'Blog e guide', 'pornovirtuale' ); ?></a></li>
				</ul>
			</div>

			<div>
				<h4><?php esc_html_e( 'Categorie', 'pornovirtuale' ); ?></h4>
				<ul>
					<?php if ( ! is_wp_error( $pv_categorie ) && $pv_categorie ) : ?>
						<?php foreach ( $pv_categorie as $pv_cat ) : ?>
							<li><a href="<?php echo esc_url( get_term_link( $pv_cat ) ); ?>"><?php echo esc_html( $pv_cat->name ); ?></a></li>
						<?php endforeach; ?>
					<?php endif; ?>
				</ul>
			</div>

			<div>
				<h4><?php esc_html_e( 'Legale', 'pornovirtuale' ); ?></h4>
				<ul>
					<li><a href="<?php echo esc_url( pv_page_url( 'privacy' ) ); ?>"><?php esc_html_e( 'Privacy Policy', 'pornovirtuale' ); ?></a></li>
					<li><a href="<?php echo esc_url( pv_page_url( 'termini' ) ); ?>"><?php esc_html_e( 'Termini e Condizioni', 'pornovirtuale' ); ?></a></li>
					<li><a href="<?php echo esc_url( pv_page_url( 'supporto' ) ); ?>"><?php esc_html_e( 'Supporto e contatti', 'pornovirtuale' ); ?></a></li>
					<li><a href="<?php echo esc_url( pv_page_url( 'dmca' ) ); ?>"><?php esc_html_e( 'DMCA / Copyright', 'pornovirtuale' ); ?></a></li>
				</ul>
			</div>
		</div>

		<div class="site-footer__bottom">
			<span>&copy; <?php echo esc_html( date( 'Y' ) ); ?> Pornovirtuale.com — <?php esc_html_e( 'tutti i diritti riservati', 'pornovirtuale' ); ?></span>
			<span><?php esc_html_e( 'Questo sito contiene link affiliati a reti di cam e piattaforme partner: possiamo ricevere una commissione senza costi aggiuntivi per te.', 'pornovirtuale' ); ?></span>
		</div>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
