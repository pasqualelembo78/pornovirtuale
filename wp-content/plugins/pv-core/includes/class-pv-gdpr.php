<?php
/**
 * Overlay di compliance: age gate + cookie banner.
 *
 * @package Pornovirtuale
 */

defined( 'ABSPATH' ) || exit;

class PV_GDPR {

	/**
	 * Caricamento asset.
	 */
	public static function enqueue() {
		if ( ! PV_Settings::get( 'gdpr_enabled', 1 ) ) {
			return;
		}

		wp_enqueue_style( 'pv-compliance', PV_CORE_URL . 'assets/css/compliance.css', array(), PV_CORE_VERSION );
		wp_enqueue_script( 'pv-compliance', PV_CORE_URL . 'assets/js/compliance.js', array(), PV_CORE_VERSION, true );

		wp_localize_script(
			'pv-compliance',
			'pvCompliance',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'pv_age_verify' ),
				'mode'    => PV_Age::mode(),
				'exitUrl' => 'https://www.google.com',
			)
		);
	}

	/**
	 * Markup dell'overlay.
	 */
	public static function render_overlay() {
		if ( ! PV_Settings::get( 'gdpr_enabled', 1 ) ) {
			return;
		}

		$mode     = PV_Age::mode();
		$privacy  = pv_page_url( 'privacy' );
		$termini  = pv_page_url( 'termini' );
		$verified = PV_Age::is_verified();
		?>
		<div id="pv-compliance-overlay" class="compliance-overlay" aria-label="<?php esc_attr_e( 'Verifica eta e cookie', 'pornovirtuale' ); ?>" style="display:none;">
			<div class="compliance-box" role="dialog" aria-modal="true">
				<h2><?php bloginfo( 'name' ); ?></h2>
				<p><strong><?php esc_html_e( 'Questo sito contiene contenuti per adulti.', 'pornovirtuale' ); ?></strong> <?php esc_html_e( 'Devi avere almeno 18 anni per proseguire.', 'pornovirtuale' ); ?></p>

				<?php if ( 'provider' === $mode && ! $verified ) : ?>
					<p><?php esc_html_e( 'Per accedere ai contenuti verifichiamo la tua eta tramite un fornitore certificato, nel rispetto della privacy.', 'pornovirtuale' ); ?></p>
				<?php endif; ?>

				<div class="compliance-actions">
					<?php if ( 'provider' === $mode && ! $verified ) : ?>
						<button id="pv-verify-id" class="btn"><?php esc_html_e( 'Verifica la mia eta', 'pornovirtuale' ); ?></button>
						<button id="pv-accept-age" class="btn ghost"><?php esc_html_e( 'Ho 18 anni o piu', 'pornovirtuale' ); ?></button>
					<?php else : ?>
						<button id="pv-accept-age" class="btn"><?php esc_html_e( 'Ho 18 anni o piu', 'pornovirtuale' ); ?></button>
					<?php endif; ?>
					<button id="pv-decline-age" class="btn ghost"><?php esc_html_e( 'Esci', 'pornovirtuale' ); ?></button>
				</div>

				<hr style="border-color:rgba(255,255,255,.06);margin:.8rem 0;">

				<p><?php esc_html_e( 'Il sito utilizza cookie per il funzionamento, per le statistiche e per la pubblicita. Puoi accettare tutto o scegliere le preferenze.', 'pornovirtuale' ); ?></p>

				<div class="compliance-cookies">
					<button id="pv-accept-all" class="btn"><?php esc_html_e( 'Accetto tutti', 'pornovirtuale' ); ?></button>
					<button id="pv-accept-necessary" class="btn ghost"><?php esc_html_e( 'Solo cookie necessari', 'pornovirtuale' ); ?></button>
					<button id="pv-decline-all" class="btn ghost"><?php esc_html_e( 'Rifiuto tutti', 'pornovirtuale' ); ?></button>
					<button id="pv-cookie-settings" class="btn ghost"><?php esc_html_e( 'Impostazioni cookie', 'pornovirtuale' ); ?></button>
				</div>

				<div id="pv-cookie-settings-panel" style="display:none;">
					<label><input type="checkbox" id="pv-analytics-cookie" <?php checked( (int) PV_Settings::get( 'gdpr_analytics', 0 ), 1 ); ?>> <?php esc_html_e( 'Analitici (statistiche)', 'pornovirtuale' ); ?></label><br>
					<label><input type="checkbox" id="pv-marketing-cookie" <?php checked( (int) PV_Settings::get( 'gdpr_marketing', 0 ), 1 ); ?>> <?php esc_html_e( 'Marketing / Pubblicita', 'pornovirtuale' ); ?></label><br>
					<div style="margin-top:.6rem;">
						<button id="pv-save-cookie-settings" class="btn"><?php esc_html_e( 'Salva preferenze', 'pornovirtuale' ); ?></button>
					</div>
					<p style="font-size:.85rem;color:#bbb;margin-top:.6rem;">
						<?php esc_html_e( 'Leggi la', 'pornovirtuale' ); ?> <a href="<?php echo esc_url( $privacy ); ?>" rel="noopener"><?php esc_html_e( 'Privacy Policy', 'pornovirtuale' ); ?></a>
						<?php esc_html_e( 'e i', 'pornovirtuale' ); ?> <a href="<?php echo esc_url( $termini ); ?>" rel="noopener"><?php esc_html_e( 'Termini', 'pornovirtuale' ); ?></a>.
					</p>
				</div>

				<div class="compliance-footer">
					<small><?php esc_html_e( 'Puoi rivedere le tue preferenze in qualsiasi momento.', 'pornovirtuale' ); ?></small>
					<small><a href="<?php echo esc_url( $privacy ); ?>"><?php esc_html_e( 'Maggiori informazioni', 'pornovirtuale' ); ?></a></small>
				</div>

				<noscript>
					<p style="color:#ffb3b3;margin-top:.6rem;"><strong><?php esc_html_e( 'JavaScript e richiesto', 'pornovirtuale' ); ?></strong>: <?php esc_html_e( 'abilitalo per motivi di sicurezza e compliance.', 'pornovirtuale' ); ?></p>
				</noscript>
			</div>
		</div>
		<?php
	}
}

add_action( 'wp_enqueue_scripts', array( 'PV_GDPR', 'enqueue' ) );
add_action( 'wp_footer', array( 'PV_GDPR', 'render_overlay' ), 5 );
