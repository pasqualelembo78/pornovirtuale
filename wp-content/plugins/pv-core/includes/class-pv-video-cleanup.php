<?php
defined( 'ABSPATH' ) || exit;

/**
 * Strumenti amministrativi per la pulizia dei video.
 *
 * I contenuti vengono spostati nel cestino, mai eliminati definitivamente.
 */
class PV_Video_Cleanup {
	const PAGE_SLUG = 'pv-video-cleanup';
	const BATCH_SIZE = 100;

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_actions' ) );
	}

	public static function admin_menu() {
		add_submenu_page(
			'pv-core-dashboard',
			__( 'Pulizia video', 'pornovirtuale' ),
			__( 'Pulizia video', 'pornovirtuale' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Stati modificabili: comprende anche i video programmati e le bozze.
	 */
	private static function active_statuses() {
		return array( 'publish', 'future', 'draft', 'pending', 'private', 'auto-draft' );
	}

	private static function count_videos() {
		$counts = wp_count_posts( 'video' );
		$total = 0;
		foreach ( self::active_statuses() as $status ) {
			if ( isset( $counts->{$status} ) ) {
				$total += (int) $counts->{$status};
			}
		}
		return $total;
	}

	public static function handle_actions() {
		if ( ! isset( $_POST['pv_video_cleanup_run'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Non hai i permessi per eseguire questa operazione.', 'pornovirtuale' ) );
		}
		check_admin_referer( 'pv_video_cleanup_run' );

		if ( 'ELIMINA VIDEO' !== sanitize_text_field( wp_unslash( $_POST['pv_video_cleanup_confirm'] ?? '' ) ) ) {
			set_transient( 'pv_video_cleanup_notice', array(
				'type' => 'error',
				'message' => __( 'Conferma non valida. Nessun video è stato modificato.', 'pornovirtuale' ),
			), 120 );
			self::redirect_back();
		}

		$ids = get_posts( array(
			'post_type'              => 'video',
			'post_status'            => self::active_statuses(),
			'posts_per_page'          => self::BATCH_SIZE,
			'orderby'                 => 'ID',
			'order'                   => 'ASC',
			'fields'                  => 'ids',
			'no_found_rows'           => true,
			'update_post_meta_cache'  => false,
			'update_post_term_cache'  => false,
			'suppress_filters'        => true,
		) );

		$moved = 0;
		foreach ( $ids as $post_id ) {
			if ( wp_trash_post( (int) $post_id ) ) {
				$moved++;
			}
		}

		$remaining = self::count_videos();
		if ( $remaining > 0 ) {
			$message = sprintf(
				/* translators: 1: videos moved to trash, 2: videos remaining */
				__( 'Spostati nel cestino %1$d video. Ne restano %2$d: premi nuovamente il pulsante per continuare con il lotto successivo.', 'pornovirtuale' ),
				$moved,
				$remaining
			);
		} else {
			$message = sprintf(
				/* translators: %d: videos moved to trash */
				__( 'Pulizia completata: %d video spostati nel cestino. Puoi ripristinarli da Video > Cestino.', 'pornovirtuale' ),
				$moved
			);
		}

		set_transient( 'pv_video_cleanup_notice', array(
			'type' => 'success',
			'message' => $message,
		), 120 );
		self::redirect_back();
	}

	private static function redirect_back() {
		wp_safe_redirect( add_query_arg( 'page', self::PAGE_SLUG, admin_url( 'admin.php' ) ) );
		exit;
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$notice = get_transient( 'pv_video_cleanup_notice' );
		delete_transient( 'pv_video_cleanup_notice' );
		$total = self::count_videos();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Pulizia video', 'pornovirtuale' ); ?></h1>
			<?php if ( $notice && ! empty( $notice['message'] ) ) : ?>
				<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible"><p><?php echo esc_html( $notice['message'] ); ?></p></div>
			<?php endif; ?>

			<div class="card" style="max-width:850px;padding:20px;margin-top:20px;">
				<h2><?php esc_html_e( 'Spostare tutti i video nel cestino?', 'pornovirtuale' ); ?></h2>
				<p><?php echo esc_html( sprintf( __( 'Sono presenti %d video fuori dal cestino.', 'pornovirtuale' ), $total ) ); ?></p>
				<p><?php esc_html_e( 'L’operazione include video pubblicati, programmati, bozze, in attesa di revisione e privati. Per sicurezza, i video vengono spostati nel cestino e non eliminati definitivamente. I contenuti vengono elaborati in gruppi di 100 per evitare timeout: se ne restano, potrai premere nuovamente il pulsante.', 'pornovirtuale' ); ?></p>
				<p><strong><?php esc_html_e( 'Attenzione: questa operazione riguarda tutti i post del tipo “video”, non soltanto quelli importati da XVideos.', 'pornovirtuale' ); ?></strong></p>

				<?php if ( $total > 0 ) : ?>
					<form method="post" action="" onsubmit="return confirm('Confermi di spostare nel cestino fino a 100 video?');">
						<?php wp_nonce_field( 'pv_video_cleanup_run' ); ?>
						<p>
							<label for="pv_video_cleanup_confirm"><?php esc_html_e( 'Per confermare, scrivi esattamente ELIMINA VIDEO:', 'pornovirtuale' ); ?></label><br>
							<input type="text" id="pv_video_cleanup_confirm" name="pv_video_cleanup_confirm" class="regular-text" required autocomplete="off">
						</p>
						<?php submit_button( __( 'Sposta i video nel cestino', 'pornovirtuale' ), 'delete', 'pv_video_cleanup_run' ); ?>
					</form>
				<?php else : ?>
					<p><strong><?php esc_html_e( 'Non ci sono video da pulire.', 'pornovirtuale' ); ?></strong></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
}

PV_Video_Cleanup::init();
