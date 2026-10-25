<?php
defined( 'ABSPATH' ) || exit;

class PV_Search_Importer {
	const OPTION = 'pv_search_import';
	const QUEUE_OPTION = 'pv_search_import_queue';
	const CRON_HOOK = 'pv_search_import_batch';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_actions' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'run_batch' ) );
	}

	public static function defaults() {
		return array(
			'enabled' => 0,
			'sites' => array(
				'xvideos' => array( 'enabled' => 0 ),
				'youporn' => array( 'enabled' => 0 ),
				'pornhub' => array( 'enabled' => 0 ),
				'redtube' => array( 'enabled' => 0 ),
			),
		);
	}

	public static function admin_menu() {
		add_submenu_page(
			'pv-core-dashboard',
			__( 'Importa per ricerca', 'pornovirtuale' ),
			__( 'Importa per ricerca', 'pornovirtuale' ),
			'manage_options',
			'pv-search-import',
			array( __CLASS__, 'render_page' )
		);
	}

	public static function handle_actions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( isset( $_POST['pv_search_import_save'] ) ) {
			check_admin_referer( 'pv_search_import_save' );
			update_option( self::OPTION, self::defaults() );
			add_settings_error( 'pv_search_import', 'saved', __( 'Impostazioni salvate.', 'pornovirtuale' ), 'updated' );
		}
		if ( isset( $_POST['pv_search_import_stop'] ) ) {
			check_admin_referer( 'pv_search_import_stop' );
			wp_clear_scheduled_hook( self::CRON_HOOK );
			$queue = get_option( self::QUEUE_OPTION, array() );
			if ( is_array( $queue ) && ! empty( $queue ) ) {
				$queue['status'] = 'paused';
				update_option( self::QUEUE_OPTION, $queue, false );
			}
			wp_safe_redirect( add_query_arg( 'page', 'pv-search-import', admin_url( 'admin.php' ) ) );
			exit;
		}
		if ( ! isset( $_POST['pv_search_import_run'] ) ) {
			return;
		}
		check_admin_referer( 'pv_search_import_run' );
		$query = sanitize_text_field( wp_unslash( $_POST['search_query'] ?? '' ) );
		$site  = sanitize_key( wp_unslash( $_POST['search_site'] ?? 'xvideos' ) );
		$cat   = sanitize_text_field( wp_unslash( $_POST['search_category'] ?? '' ) );
		$limit = max( 1, min( 1000000, absint( $_POST['search_limit'] ?? 100 ) ) );

		if ( '' === $query ) {
			add_settings_error( 'pv_search_import', 'err', __( 'Inserisci una parola chiave.', 'pornovirtuale' ), 'error' );
			return;
		}
		if ( 'xvideos' !== $site ) {
			add_settings_error( 'pv_search_import', 'err', __( 'In questa versione è attiva la ricerca XVideos; gli altri provider non sono collegati.', 'pornovirtuale' ), 'error' );
			return;
		}
		if ( ! class_exists( 'PV_Source_XVideos' ) ) {
			add_settings_error( 'pv_search_import', 'err', __( 'Modulo XVideos non disponibile.', 'pornovirtuale' ), 'error' );
			return;
		}

		wp_clear_scheduled_hook( self::CRON_HOOK );
		$queue = array(
			'status' => 'running',
			'query' => $query,
			'site' => $site,
			'category' => $cat,
			'target' => $limit,
			'imported' => 0,
			'duplicates' => 0,
			'errors' => 0,
			'page' => 0,
			'started_at' => current_time( 'mysql' ),
			'updated_at' => current_time( 'mysql' ),
			'message' => __( 'Importazione avviata; il primo blocco verrà elaborato automaticamente.', 'pornovirtuale' ),
		);
		update_option( self::QUEUE_OPTION, $queue, false );
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_single_event( time() + 2, self::CRON_HOOK );
		}
		wp_safe_redirect( add_query_arg( 'page', 'pv-search-import', admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Elabora fino a 100 risultati per evento e pianifica rapidamente il blocco successivo.
	 */
	public static function run_batch() {
		$queue = get_option( self::QUEUE_OPTION, array() );
		if ( ! is_array( $queue ) || 'running' !== ( $queue['status'] ?? '' ) ) {
			return;
		}
		$remaining = max( 0, (int) $queue['target'] - (int) $queue['imported'] );
		if ( 0 === $remaining ) {
			$queue['status'] = 'completed';
			$queue['message'] = __( 'Obiettivo raggiunto.', 'pornovirtuale' );
			$queue['updated_at'] = current_time( 'mysql' );
			update_option( self::QUEUE_OPTION, $queue, false );
			return;
		}
		if ( ! class_exists( 'PV_Source_XVideos' ) ) {
			$queue['status'] = 'error';
			$queue['message'] = __( 'Modulo XVideos non disponibile; importazione interrotta.', 'pornovirtuale' );
			update_option( self::QUEUE_OPTION, $queue, false );
			return;
		}

		$batch_limit = min( 100, $remaining );
		$result = PV_Source_XVideos::search_and_import( $queue['query'], $batch_limit, $queue['category'], (int) $queue['page'] );
		$queue['updated_at'] = current_time( 'mysql' );
		if ( 'error' === ( $result['status'] ?? '' ) ) {
			$queue['status'] = 'error';
			$queue['message'] = sanitize_text_field( $result['message'] ?? __( 'Errore durante la ricerca.', 'pornovirtuale' ) );
			$queue['errors'] = (int) $queue['errors'] + 1;
			update_option( self::QUEUE_OPTION, $queue, false );
			return;
		}

		$batch_imported = (int) ( $result['imported'] ?? 0 );
		$batch_duplicates = (int) ( $result['duplicates'] ?? 0 );
		$scanned = (int) ( $result['scanned'] ?? 0 );
		$queue['imported'] = (int) $queue['imported'] + $batch_imported;
		$queue['duplicates'] = (int) $queue['duplicates'] + $batch_duplicates;
		$queue['page'] = (int) $queue['page'] + 1;

		if ( 0 === $scanned || ( 0 === $batch_imported && 0 === $batch_duplicates ) ) {
			$queue['status'] = 'completed';
			$queue['message'] = __( 'Non risultano altri video disponibili per questa ricerca; operazione terminata.', 'pornovirtuale' );
		} elseif ( (int) $queue['imported'] >= (int) $queue['target'] ) {
			$queue['status'] = 'completed';
			$queue['message'] = __( 'Obiettivo richiesto raggiunto.', 'pornovirtuale' );
		} else {
			$queue['status'] = 'running';
			$queue['message'] = sprintf(
				__( 'Elaborazione in corso: %1$d di %2$d video importati; %3$d duplicati ignorati.', 'pornovirtuale' ),
				(int) $queue['imported'],
				(int) $queue['target'],
				(int) $queue['duplicates']
			);
		}
		update_option( self::QUEUE_OPTION, $queue, false );
		if ( 'running' === $queue['status'] && ! wp_next_scheduled( self::CRON_HOOK ) ) {
			// Breve pausa per evitare richieste continue alla fonte e carico eccessivo sul server.
			wp_schedule_single_event( time() + 3, self::CRON_HOOK );
		}
	}

	public static function run_search( $args ) {
		$args = wp_parse_args( $args, array( 'query' => '', 'site' => 'xvideos', 'category' => '', 'limit' => 5 ) );
		$query = sanitize_text_field( $args['query'] );
		$site  = sanitize_key( $args['site'] );
		$limit = max( 1, min( 100, absint( $args['limit'] ) ) );
		$cat   = sanitize_text_field( $args['category'] );

		if ( 'xvideos' !== $site ) {
			return array(
				'status'     => 'error',
				'message'    => __( 'In questa versione è attiva la ricerca XVideos. Gli altri provider non sono ancora collegati.', 'pornovirtuale' ),
				'imported'   => 0,
				'duplicates' => 0,
				'errors'     => 1,
				'items'      => array(),
			);
		}

		if ( ! class_exists( 'PV_Source_XVideos' ) ) {
			return array(
				'status'     => 'error',
				'message'    => __( 'Classe XVideos non caricata. Verifica il file class-pv-source-xvideos.php e il require_once in pv-core.php.', 'pornovirtuale' ),
				'imported'   => 0,
				'duplicates' => 0,
				'errors'     => 1,
				'items'      => array(),
			);
		}

		$res = PV_Source_XVideos::search_and_import( $query, $limit, $cat );

		$log = get_option( PV_Automation::LOG_OPTION, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}
		array_unshift( $log, array(
			'time'   => current_time( 'mysql' ),
			'manual' => true,
			'results' => array(
				'type'       => 'xvideos_keyword_import',
				'query'      => $query,
				'site'       => $site,
				'imported'   => (int) ( $res['imported'] ?? 0 ),
				'duplicates' => (int) ( $res['duplicates'] ?? 0 ),
				'errors'     => (int) ( $res['errors'] ?? 0 ),
			),
		) );
		$log = array_slice( $log, 0, 20 );
		update_option( PV_Automation::LOG_OPTION, $log, false );

		return $res;
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		settings_errors( 'pv_search_import' );
		$res = get_transient( 'pv_search_import_result' );
		delete_transient( 'pv_search_import_result' );
		$queue = get_option( self::QUEUE_OPTION, array() );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Importa per ricerca', 'pornovirtuale' ); ?></h1>
			<?php if ( is_array( $queue ) && ! empty( $queue ) ) : ?>
				<?php
				$progress_target = max( 1, (int) ( $queue['target'] ?? 1 ) );
				$progress_imported = max( 0, (int) ( $queue['imported'] ?? 0 ) );
				$progress_percent = min( 100, (int) floor( 100 * $progress_imported / $progress_target ) );
				$progress_status = (string) ( $queue['status'] ?? '' );
				?>
				<div class="notice notice-info">
					<p><strong><?php echo esc_html( sprintf( __( 'Stato: %1$s — importati %2$d di %3$d; duplicati ignorati: %4$d; pagina: %5$d. %6$s', 'pornovirtuale' ), $queue['status'] ?? '—', $progress_imported, $progress_target, (int) ( $queue['duplicates'] ?? 0 ), (int) ( $queue['page'] ?? 0 ), $queue['message'] ?? '' ) ); ?></strong></p>
					<div style="max-width:700px;margin:12px 0 16px">
						<div style="height:22px;background:#e5e7eb;border-radius:4px;overflow:hidden;border:1px solid #c3c4c7" role="progressbar" aria-label="<?php esc_attr_e( 'Avanzamento importazione', 'pornovirtuale' ); ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr( $progress_percent ); ?>">
							<div style="height:100%;width:<?php echo esc_attr( $progress_percent ); ?>%;background:#2271b1;transition:width .3s ease"></div>
						</div>
						<p style="margin:5px 0"><strong><?php echo esc_html( $progress_percent . '%' ); ?></strong> — <?php echo esc_html( sprintf( __( '%1$d video importati su %2$d richiesti', 'pornovirtuale' ), $progress_imported, $progress_target ) ); ?></p>
					</div>
					<?php if ( 'running' === $progress_status ) : ?><form method="post" action=""><?php wp_nonce_field( 'pv_search_import_stop' ); ?><?php submit_button( __( 'Interrompi importazione', 'pornovirtuale' ), 'secondary', 'pv_search_import_stop', false ); ?></form><?php endif; ?>
				</div>
				<?php if ( 'running' === $progress_status ) : ?>
					<script>(function(){window.setTimeout(function(){if(document.visibilityState!=='hidden'){window.location.reload();}},5000);}());</script>
				<?php endif; ?>
			<?php endif; ?>
			<?php if ( $res ) : ?>
				<div class="notice notice-info"><p><strong><?php echo esc_html( $res['message'] ); ?></strong></p></div>
			<?php endif; ?>
			<form method="post" action="">
				<?php wp_nonce_field( 'pv_search_import_run' ); ?>
				<h2><?php esc_html_e( 'Parametri importazione', 'pornovirtuale' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Chiave di ricerca', 'pornovirtuale' ); ?></th>
						<td><input type="text" name="search_query" class="regular-text" required></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Sito', 'pornovirtuale' ); ?></th>
						<td>
							<select name="search_site">
								<option value="xvideos">XVideos</option>
								<option value="youporn">YouPorn</option>
								<option value="pornhub">Pornhub</option>
								<option value="redtube">RedTube</option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Categoria del sito', 'pornovirtuale' ); ?></th>
						<td><input type="text" name="search_category" class="regular-text" placeholder="es. vr, adult"></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Numero video', 'pornovirtuale' ); ?></th>
						<td><input type="number" name="search_limit" value="100" min="1" max="1000000"><p class="description"><?php esc_html_e( 'Obiettivo totale da 1 a 1.000.000 di video. Il sistema elabora automaticamente blocchi di massimo 100 video; i risultati effettivamente disponibili dipendono dalla ricerca e dal sito sorgente.', 'pornovirtuale' ); ?></p></td>
					</tr>
				</table>
				<?php submit_button( __( 'Avvia importazione automatica', 'pornovirtuale' ), 'primary', 'pv_search_import_run' ); ?>
			</form>
			<p class="description"><?php esc_html_e( 'Avvia una volta sola: l’importazione procede in blocchi di massimo 100 risultati. I video nuovi vengono programmati a partire da 5 minuti da adesso, poi ogni 5 minuti. I duplicati vengono ignorati. La continuazione usa WP-Cron e richiede visite al sito, salvo configurazione di un cron di sistema.', 'pornovirtuale' ); ?></p>
		</div>
		<?php
	}
}
PV_Search_Importer::init();
