<?php
defined( 'ABSPATH' ) || exit;

class PV_Search_Importer {
	const OPTION = 'pv_search_import';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_actions' ) );
		add_action( 'wp_ajax_pv_search_import_test', array( __CLASS__, 'ajax_test' ) );
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
		if ( isset( $_POST['pv_search_import_run'] ) ) {
			check_admin_referer( 'pv_search_import_run' );
			$query = sanitize_text_field( wp_unslash( $_POST['search_query'] ?? '' ) );
			$site  = sanitize_key( wp_unslash( $_POST['search_site'] ?? 'xvideos' ) );
			$cat   = sanitize_text_field( wp_unslash( $_POST['search_category'] ?? '' ) );
			$limit = max( 1, min( 20, (int) ( $_POST['search_limit'] ?? 5 ) ) );
			
			if ( '' === $query ) {
				add_settings_error( 'pv_search_import', 'err', __( 'Inserisci una parola chiave.', 'pornovirtuale' ), 'error' );
				return;
			}
			
			$res = self::run_search( array( 'query' => $query, 'site' => $site, 'category' => $cat, 'limit' => $limit ) );
			set_transient( 'pv_search_import_result', $res, 120 );
			wp_safe_redirect( add_query_arg( 'page', 'pv-search-import', admin_url( 'admin.php' ) ) );
			exit;
		}
	}

	public static function run_search( $args ) {
		$args = wp_parse_args( $args, array( 'query' => '', 'site' => 'xvideos', 'category' => '', 'limit' => 5 ) );
		$query = sanitize_text_field( $args['query'] );
		$site  = sanitize_key( $args['site'] );
		$limit = max( 1, min( 20, absint( $args['limit'] ) ) );
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
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Importa per ricerca', 'pornovirtuale' ); ?></h1>
			<div class="notice notice-warning">
				<p><strong><?php esc_html_e( 'Importante:', 'pornovirtuale' ); ?></strong> <?php esc_html_e( 'Per rispetto dei termini d\'uso di XVideos/YouPorn/Pornhub/RedTube e delle restrizioni tecniche, l\'importazione automatica via scraping è disabilitata. Puoi importare manualmente con URL/CSV oppure fornire un feed ufficiale autorizzato.', 'pornovirtuale' ); ?></p>
			</div>
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
						<td><input type="text" name="search_category" class="regular-text" placeholder="es. vr, teen"></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Numero video', 'pornovirtuale' ); ?></th>
						<td><input type="number" name="search_limit" value="5" min="1" max="20"></td>
					</tr>
				</table>
				<?php submit_button( __( 'Avvia importazione automatica', 'pornovirtuale' ), 'primary', 'pv_search_import_run' ); ?>
			</form>
			<p class="description"><?php esc_html_e( 'I video nuovi verranno programmati per la pubblicazione a partire da 5 minuti da adesso, poi a intervalli di 5 minuti. I duplicati vengono ignorati. La pubblicazione effettiva dipende dal corretto funzionamento della pianificazione WordPress/cron.', 'pornovirtuale' ); ?></p>
		</div>
		<?php
	}
}
PV_Search_Importer::init();
