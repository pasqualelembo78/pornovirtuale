<?php
defined( 'ABSPATH' ) || exit;

class PV_Automation {
	const OPTION     = 'pv_automation';
	const LOG_OPTION = 'pv_automation_log';
	const CRON_HOOK  = 'pv_core_run_import';

	public static function defaults() {
		return array(
			'enabled'           => 0,
			'frequency'         => 'hourly',
			'max_per_run'       => 10,
			'default_category'  => '',
			'auto_publish'      => 0,
			'sources'           => array(
				'youporn' => array( 'enabled' => 0 ),
			),
		);
	}

	public static function init() {
		add_action( self::CRON_HOOK, array( __CLASS__, 'cron_run' ) );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_actions' ) );
		add_action( 'init', array( __CLASS__, 'schedule' ) );
	}

	public static function schedule() {
		$opts = get_option( self::OPTION, self::defaults() );
		if ( ! is_array( $opts ) ) {
			$opts = self::defaults();
		}
		$next = wp_next_scheduled( self::CRON_HOOK );
		if ( ! empty( $opts['enabled'] ) ) {
			if ( ! $next ) {
				$recurrence = in_array( $opts['frequency'], array( 'hourly', 'twicedaily', 'daily' ), true ) ? $opts['frequency'] : 'hourly';
				wp_schedule_event( time() + 300, $recurrence, self::CRON_HOOK );
			}
		} else {
			if ( $next ) {
				wp_clear_scheduled_hook( self::CRON_HOOK );
			}
		}
	}

	public static function cron_run() {
		if ( ! class_exists( 'PV_Importer' ) ) {
			return;
		}
		PV_Importer::run( array( 'manual' => false ) );
	}

	public static function admin_menu() {
		add_submenu_page(
			'pv-core-dashboard',
			__( 'Automazioni', 'pornovirtuale' ),
			__( 'Automazioni', 'pornovirtuale' ),
			'manage_options',
			'pv-automation',
			array( __CLASS__, 'render_page' )
		);
	}

	public static function handle_actions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( isset( $_POST['pv_save_automation'] ) ) {
			check_admin_referer( 'pv_save_automation' );
			$opts = self::defaults();
			$opts['enabled']          = isset( $_POST['enabled'] ) ? 1 : 0;
			$opts['frequency']        = in_array( $_POST['frequency'] ?? '', array( 'hourly', 'twicedaily', 'daily' ), true ) ? sanitize_key( $_POST['frequency'] ) : 'hourly';
			$opts['max_per_run']      = max( 1, (int) ( $_POST['max_per_run'] ?? 10 ) );
			$opts['default_category'] = sanitize_text_field( wp_unslash( $_POST['default_category'] ?? '' ) );
			$opts['auto_publish']     = isset( $_POST['auto_publish'] ) ? 1 : 0;
			$sources = array();
			if ( isset( $_POST['sources'] ) && is_array( $_POST['sources'] ) ) {
				foreach ( $_POST['sources'] as $slug => $v ) {
					$sources[ sanitize_key( $slug ) ] = array( 'enabled' => isset( $v['enabled'] ) ? 1 : 0 );
				}
			}
			$opts['sources'] = wp_parse_args( $sources, self::defaults()['sources'] );
			update_option( self::OPTION, $opts );
			self::schedule();
			add_settings_error( 'pv_automation', 'saved', __( 'Impostazioni automazioni salvate.', 'pornovirtuale' ), 'updated' );
		}
		if ( isset( $_POST['pv_test_import'] ) ) {
			check_admin_referer( 'pv_test_import' );
			if ( class_exists( 'PV_Importer' ) ) {
				$res = PV_Importer::run( array( 'manual' => true, 'limit' => (int) ( $_POST['test_limit'] ?? 5 ) ) );
				set_transient( 'pv_import_test_result', $res, 60 );
			}
			wp_safe_redirect( add_query_arg( 'page', 'pv-automation', admin_url( 'admin.php' ) ) );
			exit;
		}
		if ( isset( $_POST['pv_run_now'] ) ) {
			check_admin_referer( 'pv_run_now' );
			if ( class_exists( 'PV_Importer' ) ) {
				$res = PV_Importer::run( array( 'manual' => true, 'limit' => (int) ( $_POST['run_now_limit'] ?? 10 ) ) );
				set_transient( 'pv_import_run_result', $res, 60 );
			}
			wp_safe_redirect( add_query_arg( 'page', 'pv-automation', admin_url( 'admin.php' ) ) );
			exit;
		}
	}

	public static function update_last_run( $results ) {
		update_option( 'pv_automation_last_run', array( 'time' => current_time( 'mysql' ), 'results' => $results ), false );
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		settings_errors( 'pv_automation' );
		$opts = get_option( self::OPTION, self::defaults() );
		if ( ! is_array( $opts ) ) {
			$opts = self::defaults();
		}
		$last = get_option( 'pv_automation_last_run', array() );
		$log  = get_option( self::LOG_OPTION, array() );
		$next = wp_next_scheduled( self::CRON_HOOK );
		$test = get_transient( 'pv_import_test_result' );
		$runr = get_transient( 'pv_import_run_result' );
		delete_transient( 'pv_import_test_result' );
		delete_transient( 'pv_import_run_result' );
		$sources = class_exists( 'PV_Importer' ) ? PV_Importer::get_sources() : array();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Automazioni PV Core', 'pornovirtuale' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Importazione automatica video. Pubblicazione automatica disattivata per impostazione predefinita.', 'pornovirtuale' ); ?></p>
			<?php if ( $test ) : ?><div class="notice notice-info"><p><strong><?php esc_html_e( 'Test manuale eseguito', 'pornovirtuale' ); ?></strong></p><pre><?php echo esc_html( print_r( $test, true ) ); ?></pre></div><?php endif; ?>
			<?php if ( $runr ) : ?><div class="notice notice-info"><p><strong><?php esc_html_e( 'Esecuzione immediata', 'pornovirtuale' ); ?></strong></p><pre><?php echo esc_html( print_r( $runr, true ) ); ?></pre></div><?php endif; ?>
			<form method="post" action="">
				<?php wp_nonce_field( 'pv_save_automation' ); ?>
				<h2><?php esc_html_e( 'Stato automazione', 'pornovirtuale' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr><th scope="row"><?php esc_html_e( 'Abilita automazione', 'pornovirtuale' ); ?></th><td><label><input type="checkbox" name="enabled" value="1" <?php checked( ! empty( $opts['enabled'] ), true ); ?>> <?php esc_html_e( 'Attiva schedulazione', 'pornovirtuale' ); ?></label></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Frequenza', 'pornovirtuale' ); ?></th><td><select name="frequency"><option value="hourly" <?php selected( $opts['frequency'], 'hourly' ); ?>>Oraria</option><option value="twicedaily" <?php selected( $opts['frequency'], 'twicedaily' ); ?>>Due volte al giorno</option><option value="daily" <?php selected( $opts['frequency'], 'daily' ); ?>>Giornaliera</option></select></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Massimo video per esecuzione', 'pornovirtuale' ); ?></th><td><input type="number" name="max_per_run" value="<?php echo esc_attr( $opts['max_per_run'] ); ?>" min="1" max="100"></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Categoria predefinita', 'pornovirtuale' ); ?></th><td><input type="text" class="regular-text" name="default_category" value="<?php echo esc_attr( $opts['default_category'] ); ?>"></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Pubblicazione automatica', 'pornovirtuale' ); ?></th><td><label><input type="checkbox" name="auto_publish" value="1" <?php checked( ! empty( $opts['auto_publish'] ), true ); ?>> <?php esc_html_e( 'Pubblica automaticamente (DISATTIVATO per default)', 'pornovirtuale' ); ?></label></td></tr>
				</table>
				<h2><?php esc_html_e( 'Fonti video', 'pornovirtuale' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php foreach ( $sources as $slug => $source ) : $en = ! empty( $opts['sources'][ $slug ]['enabled'] ); ?>
					<tr><th scope="row"><?php echo esc_html( $source->get_name() ); ?></th><td><label><input type="checkbox" name="sources[<?php echo esc_attr( $slug ); ?>][enabled]" value="1" <?php checked( $en, true ); ?> <?php disabled( ! $source->is_available() ); ?>> Abilita</label><p class="description"><?php echo esc_html( $source->get_status_message() ); ?></p></td></tr>
					<?php endforeach; ?>
				</table>
				<?php submit_button( __( 'Salva impostazioni', 'pornovirtuale' ), 'primary', 'pv_save_automation' ); ?>
			</form>
			<h2><?php esc_html_e( 'Esecuzione', 'pornovirtuale' ); ?></h2>
			<table class="form-table"><tr><th><?php esc_html_e( 'Ultima esecuzione', 'pornovirtuale' ); ?></th><td><?php echo ! empty( $last['time'] ) ? esc_html( $last['time'] ) : 'Mai'; ?></td></tr><tr><th><?php esc_html_e( 'Prossima esecuzione', 'pornovirtuale' ); ?></th><td><?php echo $next ? date_i18n( 'Y-m-d H:i:s', $next ) : 'Non pianificata'; ?></td></tr></table>
			<form method="post" action=""><?php wp_nonce_field( 'pv_test_import' ); ?><p><label>Limite test <input type="number" name="test_limit" value="5" min="1" max="20"></label> <?php submit_button( 'Test manuale (senza pubblicazione)', 'secondary', 'pv_test_import', false ); ?></p></form>
			<form method="post" action=""><?php wp_nonce_field( 'pv_run_now' ); ?><p><label>Limite esecuzione <input type="number" name="run_now_limit" value="10" min="1" max="100"></label> <?php submit_button( 'Esegui ora', 'secondary', 'pv_run_now', false ); ?></p></form>
			<h2><?php esc_html_e( 'Log recente', 'pornovirtuale' ); ?></h2>
			<?php if ( empty( $log ) ) : ?><p>Nessun log.</p><?php else : ?><pre style="background:#fff;padding:10px;overflow:auto;max-height:400px;"><?php echo esc_html( print_r( $log, true ) ); ?></pre><?php endif; ?>
		</div>
		<?php
	}
}
PV_Automation::init();
