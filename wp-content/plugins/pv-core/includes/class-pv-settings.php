<?php
/**
 * Impostazioni di PV Core.
 *
 * @package Pornovirtuale
 */

defined( 'ABSPATH' ) || exit;

class PV_Settings {

	const OPTION = 'pv_settings';

	/**
	 * Impostazioni di default.
	 */
	public static function defaults() {
		return array(
			// Player
			'player_autoplay'      => 0,
			'player_facade'        => 1,
			'player_vr_mode'       => 0,
			'player_allow_fullscreen' => 1,
			'player_sandbox'       => 'allow-scripts allow-same-origin allow-popups allow-presentation',
			// Affiliate
			'aff_chaturbate'       => '',
			'aff_stripchat'        => '',
			'aff_xcams'            => '',
			'aff_hubtraffic'       => '',
			'aff_default_room'     => '',
			'aff_cam_widget'       => '',
			// Verifica eta
			'age_mode'             => 'declaration', // off | declaration | provider
			'age_provider_url'     => '',
			'age_provider_key'     => '',
			'age_cookie_days'      => 30,
			'age_block_media'      => 1,
			'age_log'              => 1,
			// GDPR
			'gdpr_enabled'         => 1,
			'gdpr_analytics'       => 0,
			'gdpr_marketing'       => 0,
			// SEO
			'seo_noindex_pages'    => 'login,registrati,supporto',
			'seo_schema'           => 1,
			// Contatti
			'contact_email'        => 'webmaster@pornovirtuale.com',
			// Varie
			'live_feed_provider'   => 'chaturbate',
			'enable_xmlrpc'        => 0,
		);
	}

	/**
	 * Legge tutte le impostazioni.
	 */
	public static function all() {
		$saved = get_option( self::OPTION, array() );

		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		return wp_parse_args( $saved, self::defaults() );
	}

	/**
	 * Legge una singola impostazione.
	 */
	public static function get( $key, $default = null ) {
		$all = self::all();

		if ( null !== $default && ! isset( $all[ $key ] ) ) {
			return $default;
		}

		return isset( $all[ $key ] ) ? $all[ $key ] : $default;
	}

	/**
	 * Salva le impostazioni.
	 */
	public static function update( $values ) {
		$all = self::all();
		$all = array_merge( $all, (array) $values );
		update_option( self::OPTION, $all );
	}

	/**
	 * Installazione: crea la tabella del log di verifica eta.
	 */
	public static function install() {
		self::create_age_log_table();
		self::update( self::defaults() );
		flush_rewrite_rules();
	}

	/**
	 * Tabella del log delle verifiche d'eta.
	 */
	public static function create_age_log_table() {
		global $wpdb;

		$table = $wpdb->prefix . 'pv_age_log';
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			created_at DATETIME NOT NULL,
			ip_hash VARCHAR(64) NOT NULL DEFAULT '',
			user_agent VARCHAR(255) NOT NULL DEFAULT '',
			method VARCHAR(32) NOT NULL DEFAULT '',
			result VARCHAR(32) NOT NULL DEFAULT '',
			provider VARCHAR(64) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY created_at (created_at),
			KEY result (result)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Registra la pagina di impostazioni.
	 */
	public static function admin_menu() {
		add_options_page(
			__( 'Pornovirtuale', 'pornovirtuale' ),
			__( 'Pornovirtuale', 'pornovirtuale' ),
			'manage_options',
			'pv-settings',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Rendering pagina impostazioni.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( isset( $_POST['pv_save_settings'] ) ) {
			check_admin_referer( 'pv_save_settings' );

			$values = array(
				'player_autoplay'         => isset( $_POST['player_autoplay'] ) ? 1 : 0,
				'player_facade'           => isset( $_POST['player_facade'] ) ? 1 : 0,
				'player_vr_mode'          => isset( $_POST['player_vr_mode'] ) ? 1 : 0,
				'player_allow_fullscreen' => isset( $_POST['player_allow_fullscreen'] ) ? 1 : 0,
				'aff_chaturbate'          => sanitize_text_field( wp_unslash( $_POST['aff_chaturbate'] ?? '' ) ),
				'aff_stripchat'           => sanitize_text_field( wp_unslash( $_POST['aff_stripchat'] ?? '' ) ),
				'aff_xcams'               => sanitize_text_field( wp_unslash( $_POST['aff_xcams'] ?? '' ) ),
				'aff_hubtraffic'          => sanitize_text_field( wp_unslash( $_POST['aff_hubtraffic'] ?? '' ) ),
				'aff_default_room'        => sanitize_text_field( wp_unslash( $_POST['aff_default_room'] ?? '' ) ),
				'aff_cam_widget'          => esc_url_raw( wp_unslash( $_POST['aff_cam_widget'] ?? '' ) ),
				'age_mode'                => in_array( $_POST['age_mode'] ?? '', array( 'off', 'declaration', 'provider' ), true ) ? sanitize_key( $_POST['age_mode'] ) : 'declaration',
				'age_provider_url'        => esc_url_raw( wp_unslash( $_POST['age_provider_url'] ?? '' ) ),
				'age_provider_key'        => sanitize_text_field( wp_unslash( $_POST['age_provider_key'] ?? '' ) ),
				'age_cookie_days'         => max( 1, (int) ( $_POST['age_cookie_days'] ?? 30 ) ),
				'age_block_media'         => isset( $_POST['age_block_media'] ) ? 1 : 0,
				'age_log'                 => isset( $_POST['age_log'] ) ? 1 : 0,
				'gdpr_enabled'            => isset( $_POST['gdpr_enabled'] ) ? 1 : 0,
				'gdpr_analytics'          => isset( $_POST['gdpr_analytics'] ) ? 1 : 0,
				'gdpr_marketing'          => isset( $_POST['gdpr_marketing'] ) ? 1 : 0,
				'seo_noindex_pages'       => sanitize_text_field( wp_unslash( $_POST['seo_noindex_pages'] ?? '' ) ),
				'seo_schema'              => isset( $_POST['seo_schema'] ) ? 1 : 0,
				'contact_email'           => sanitize_email( wp_unslash( $_POST['contact_email'] ?? '' ) ),
				'live_feed_provider'      => sanitize_key( wp_unslash( $_POST['live_feed_provider'] ?? 'chaturbate' ) ),
				'enable_xmlrpc'           => isset( $_POST['enable_xmlrpc'] ) ? 1 : 0,
			);

			self::update( $values );
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Impostazioni salvate.', 'pornovirtuale' ) . '</p></div>';
		}

		$s = self::all();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Impostazioni Pornovirtuale', 'pornovirtuale' ); ?></h1>

			<form method="post" action="">
				<?php wp_nonce_field( 'pv_save_settings' ); ?>

				<h2><?php esc_html_e( 'Player', 'pornovirtuale' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Facade (clic per caricare)', 'pornovirtuale' ); ?></th>
						<td><label><input type="checkbox" name="player_facade" value="1" <?php checked( $s['player_facade'], 1 ); ?>> <?php esc_html_e( 'Carica l iframe solo al clic (consigliato: veloce e non viola le regole delle reti affiliate)', 'pornovirtuale' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Modalita VR', 'pornovirtuale' ); ?></th>
						<td><label><input type="checkbox" name="player_vr_mode" value="1" <?php checked( $s['player_vr_mode'], 1 ); ?>> <?php esc_html_e( 'Mostra il toggle vista stereo/split per i feed 180/360', 'pornovirtuale' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Autoplay', 'pornovirtuale' ); ?></th>
						<td><label><input type="checkbox" name="player_autoplay" value="1" <?php checked( $s['player_autoplay'], 1 ); ?>> <?php esc_html_e( 'Avvio automatico (sconsigliato, consuma banda)', 'pornovirtuale' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Fullscreen', 'pornovirtuale' ); ?></th>
						<td><label><input type="checkbox" name="player_allow_fullscreen" value="1" <?php checked( $s['player_allow_fullscreen'], 1 ); ?>> <?php esc_html_e( 'Consenti schermo intero', 'pornovirtuale' ); ?></label></td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Reti affiliate (ID e room)', 'pornovirtuale' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Gli ID si ottengono iscrivendosi al programma affiliate della rete. Lascia vuoto se non ancora registrato.', 'pornovirtuale' ); ?></p>
				<table class="form-table" role="presentation">
					<tr><th scope="row">Chaturbate (ID)</th><td><input type="text" class="regular-text" name="aff_chaturbate" value="<?php echo esc_attr( $s['aff_chaturbate'] ); ?>"></td></tr>
					<tr><th scope="row">Stripchat (ID)</th><td><input type="text" class="regular-text" name="aff_stripchat" value="<?php echo esc_attr( $s['aff_stripchat'] ); ?>"></td></tr>
					<tr><th scope="row">Xcams / XcamsPower (ID)</th><td><input type="text" class="regular-text" name="aff_xcams" value="<?php echo esc_attr( $s['aff_xcams'] ); ?>"></td></tr>
					<tr><th scope="row">HubTraffic (PornHub/YouPorn/RedTube)</th><td><input type="text" class="regular-text" name="aff_hubtraffic" value="<?php echo esc_attr( $s['aff_hubtraffic'] ); ?>"></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Room predefinita', 'pornovirtuale' ); ?></th><td><input type="text" class="regular-text" name="aff_default_room" value="<?php echo esc_attr( $s['aff_default_room'] ); ?>"><p class="description"><?php esc_html_e( 'Es. chaturbate:modelname | stripchat:modelname | xcams:modelname', 'pornovirtuale' ); ?></p></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Widget cam (iframe)', 'pornovirtuale' ); ?></th><td><input type="url" class="regular-text" name="aff_cam_widget" value="<?php echo esc_attr( $s['aff_cam_widget'] ); ?>"></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Provider feed live', 'pornovirtuale' ); ?></th><td>
						<select name="live_feed_provider">
							<?php foreach ( array( 'chaturbate', 'stripchat', 'xcams', 'none' ) as $p ) : ?>
								<option value="<?php echo esc_attr( $p ); ?>" <?php selected( $s['live_feed_provider'], $p ); ?>><?php echo esc_html( $p ); ?></option>
							<?php endforeach; ?>
						</select>
					</td></tr>
				</table>

				<h2><?php esc_html_e( 'Verifica d\'eta (art. 13-bis D.L. Caivano / AGCOM 96/25)', 'pornovirtuale' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Modalita', 'pornovirtuale' ); ?></th>
						<td>
							<select name="age_mode">
								<option value="off" <?php selected( $s['age_mode'], 'off' ); ?>><?php esc_html_e( 'Disattivata', 'pornovirtuale' ); ?></option>
								<option value="declaration" <?php selected( $s['age_mode'], 'declaration' ); ?>><?php esc_html_e( 'Autodichiarazione (solo maggioreenne)', 'pornovirtuale' ); ?></option>
								<option value="provider" <?php selected( $s['age_mode'], 'provider' ); ?>><?php esc_html_e( 'Provider terzo certificato (conforme AGCOM)', 'pornovirtuale' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'La modalita "provider" richiede un contratto con un fornitore certificato (es. Intesa, Veriff, provider EUDI Wallet).', 'pornovirtuale' ); ?></p>
						</td>
					</tr>
					<tr><th scope="row"><?php esc_html_e( 'URL provider', 'pornovirtuale' ); ?></th><td><input type="url" class="regular-text" name="age_provider_url" value="<?php echo esc_attr( $s['age_provider_url'] ); ?>"></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Chiave callback', 'pornovirtuale' ); ?></th><td><input type="text" class="regular-text" name="age_provider_key" value="<?php echo esc_attr( $s['age_provider_key'] ); ?>"><p class="description"><?php esc_html_e( 'Chiave segreta per firmare la callback di ritorno del provider.', 'pornovirtuale' ); ?></p></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Durata cookie (giorni)', 'pornovirtuale' ); ?></th><td><input type="number" name="age_cookie_days" value="<?php echo esc_attr( $s['age_cookie_days'] ); ?>" min="1" max="365"></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Blocca media prima del consenso', 'pornovirtuale' ); ?></th><td><label><input type="checkbox" name="age_block_media" value="1" <?php checked( $s['age_block_media'], 1 ); ?>> <?php esc_html_e( 'Nasconde video e iframe finche non si accetta', 'pornovirtuale' ); ?></label></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Log verifiche', 'pornovirtuale' ); ?></th><td><label><input type="checkbox" name="age_log" value="1" <?php checked( $s['age_log'], 1 ); ?>> <?php esc_html_e( 'Registra esito delle verifiche (audit trail)', 'pornovirtuale' ); ?></label></td></tr>
				</table>

				<h2><?php esc_html_e( 'GDPR e SEO', 'pornovirtuale' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr><th scope="row"><?php esc_html_e( 'Cookie banner', 'pornovirtuale' ); ?></th><td><label><input type="checkbox" name="gdpr_enabled" value="1" <?php checked( $s['gdpr_enabled'], 1 ); ?>> <?php esc_html_e( 'Attiva banner cookie + age gate', 'pornovirtuale' ); ?></label></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Cookie analitici di default', 'pornovirtuale' ); ?></th><td><label><input type="checkbox" name="gdpr_analytics" value="1" <?php checked( $s['gdpr_analytics'], 1 ); ?>> <?php esc_html_e( 'Pre-selezionati', 'pornovirtuale' ); ?></label></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Cookie marketing di default', 'pornovirtuale' ); ?></th><td><label><input type="checkbox" name="gdpr_marketing" value="1" <?php checked( $s['gdpr_marketing'], 1 ); ?>> <?php esc_html_e( 'Pre-selezionati', 'pornovirtuale' ); ?></label></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Pagine noindex', 'pornovirtuale' ); ?></th><td><input type="text" class="regular-text" name="seo_noindex_pages" value="<?php echo esc_attr( $s['seo_noindex_pages'] ); ?>"><p class="description"><?php esc_html_e( 'Slug separati da virgola.', 'pornovirtuale' ); ?></p></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Schema.org JSON-LD', 'pornovirtuale' ); ?></th><td><label><input type="checkbox" name="seo_schema" value="1" <?php checked( $s['seo_schema'], 1 ); ?>> <?php esc_html_e( 'Genera VideoObject / BroadcastEvent / ItemList', 'pornovirtuale' ); ?></label></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Email contatti', 'pornovirtuale' ); ?></th><td><input type="email" name="contact_email" value="<?php echo esc_attr( $s['contact_email'] ); ?>"></td></tr>
				</table>

				<h2><?php esc_html_e( 'Sicurezza', 'pornovirtuale' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr><th scope="row">XML-RPC</th><td><label><input type="checkbox" name="enable_xmlrpc" value="1" <?php checked( $s['enable_xmlrpc'], 1 ); ?>> <?php esc_html_e( 'Consenti XML-RPC (sconsigliato disattivato)', 'pornovirtuale' ); ?></label></td></tr>
				</table>

				<?php submit_button( __( 'Salva impostazioni', 'pornovirtuale' ), 'primary', 'pv_save_settings' ); ?>
			</form>
		</div>
		<?php
	}
}

add_action( 'admin_menu', array( 'PV_Settings', 'admin_menu' ) );
