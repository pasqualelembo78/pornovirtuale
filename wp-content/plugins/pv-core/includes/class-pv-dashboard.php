<?php
defined( 'ABSPATH' ) || exit;

class PV_Dashboard {
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
	}

	public static function admin_menu() {
		add_menu_page(
			__( 'PV Core', 'pornovirtuale' ),
			__( 'PV Core', 'pornovirtuale' ),
			'manage_options',
			'pv-core-dashboard',
			array( __CLASS__, 'render_page' ),
			'dashicons-video-alt3',
			30
		);
		add_submenu_page(
			'pv-core-dashboard',
			__( 'Dashboard', 'pornovirtuale' ),
			__( 'Dashboard', 'pornovirtuale' ),
			'manage_options',
			'pv-core-dashboard',
			array( __CLASS__, 'render_page' )
		);
		add_submenu_page(
			'pv-core-dashboard',
			__( 'Automazioni', 'pornovirtuale' ),
			__( 'Automazioni', 'pornovirtuale' ),
			'manage_options',
			'pv-automation',
			array( 'PV_Automation', 'render_page' )
		);
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$opts = get_option( PV_Automation::OPTION, PV_Automation::defaults() );
		if ( ! is_array( $opts ) ) {
			$opts = PV_Automation::defaults();
		}
		$last = get_option( 'pv_automation_last_run', array() );
		$log  = get_option( PV_Automation::LOG_OPTION, array() );
		$next = wp_next_scheduled( PV_Automation::CRON_HOOK );
		$videos = wp_count_posts( 'video' );
		$stanze = wp_count_posts( 'stanza' );
		$modelli = wp_count_posts( 'modello' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'PV Core Dashboard', 'pornovirtuale' ); ?></h1>
			<div class="card" style="max-width:800px;padding:20px;margin-top:20px;">
				<h2><?php esc_html_e( 'Statistiche contenuti', 'pornovirtuale' ); ?></h2>
				<p><?php echo sprintf( esc_html__( 'Video: %d pubblicati, %d in bozza', 'pornovirtuale' ), (int)$videos->publish, (int)$videos->draft ); ?></p>
				<p><?php echo sprintf( esc_html__( 'Stanze: %d pubblicati, %d in bozza', 'pornovirtuale' ), (int)$stanze->publish, (int)$stanze->draft ); ?></p>
				<p><?php echo sprintf( esc_html__( 'Modelli: %d pubblicati, %d in bozza', 'pornovirtuale' ), (int)$modelli->publish, (int)$modelli->draft ); ?></p>
			</div>
			<div class="card" style="max-width:800px;padding:20px;margin-top:20px;">
				<h2><?php esc_html_e( 'Automazioni', 'pornovirtuale' ); ?></h2>
				<p><strong><?php esc_html_e( 'Stato:', 'pornovirtuale' ); ?></strong> <?php echo ! empty($opts['enabled']) ? esc_html__('Attiva','pornovirtuale') : esc_html__('Disattivata','pornovirtuale'); ?></p>
				<p><strong><?php esc_html_e( 'Pubblicazione automatica:', 'pornovirtuale' ); ?></strong> <?php echo ! empty($opts['auto_publish']) ? esc_html__('Attiva','pornovirtuale') : esc_html__('Disattivata (sicuro)','pornovirtuale'); ?></p>
				<p><strong><?php esc_html_e( 'Frequenza:', 'pornovirtuale' ); ?></strong> <?php echo esc_html($opts['frequency']); ?></p>
				<p><strong><?php esc_html_e( 'Max per run:', 'pornovirtuale' ); ?></strong> <?php echo (int)$opts['max_per_run']; ?></p>
				<p><strong><?php esc_html_e( 'Ultima esecuzione:', 'pornovirtuale' ); ?></strong> <?php echo ! empty($last['time']) ? esc_html($last['time']) : esc_html__('Mai','pornovirtuale'); ?></p>
				<p><strong><?php esc_html_e( 'Prossima esecuzione:', 'pornovirtuale' ); ?></strong> <?php echo $next ? date_i18n('Y-m-d H:i:s',$next) : esc_html__('Non pianificata','pornovirtuale'); ?></p>
				<p>
					<a href="<?php echo admin_url('admin.php?page=pv-automation'); ?>" class="button button-primary"><?php esc_html_e( 'Gestisci automazioni', 'pornovirtuale' ); ?></a>
					<a href="<?php echo admin_url('options-general.php?page=pv-settings'); ?>" class="button"><?php esc_html_e( 'Impostazioni PV Core', 'pornovirtuale' ); ?></a>
				</p>
			</div>
			<div class="card" style="max-width:800px;padding:20px;margin-top:20px;">
				<h2><?php esc_html_e( 'Log recente', 'pornovirtuale' ); ?></h2>
				<?php if (empty($log)) : ?><p><?php esc_html_e( 'Nessun log.', 'pornovirtuale' ); ?></p><?php else : ?><pre style="background:#f9f9f9;padding:10px;overflow:auto;max-height:300px;"><?php echo esc_html(print_r(array_slice($log,0,5),true)); ?></pre><?php endif; ?>
			</div>
		</div>
		<?php
	}
}
PV_Dashboard::init();
