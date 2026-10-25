<?php
defined( 'ABSPATH' ) || exit;

/**
 * Pulizia definitiva dei video con avanzamento AJAX a lotti.
 */
class PV_Video_Cleanup {
	const PAGE_SLUG  = 'pv-video-cleanup';
	const BATCH_SIZE = 100;

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'wp_ajax_pv_video_cleanup_start', array( __CLASS__, 'ajax_start' ) );
		add_action( 'wp_ajax_pv_video_cleanup_batch', array( __CLASS__, 'ajax_batch' ) );
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
	 * Conta ogni post di tipo video, indipendentemente dallo stato,
	 * compresi gli elementi già nel cestino.
	 */
	private static function count_videos() {
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = %s",
				'video'
			)
		);
	}

	private static function progress_key() {
		return 'pv_video_cleanup_progress_' . get_current_user_id();
	}

	public static function ajax_start() {
		check_ajax_referer( 'pv_video_cleanup_ajax', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Non hai i permessi per eseguire questa operazione.', 'pornovirtuale' ) ), 403 );
		}

		$total = self::count_videos();
		$state = array(
			'total'     => $total,
			'processed' => 0,
			'deleted'   => 0,
			'last_id'   => 0,
			'status'    => $total > 0 ? 'running' : 'completed',
			'message'   => $total > 0 ? __( 'Cancellazione avviata.', 'pornovirtuale' ) : __( 'Non ci sono video da eliminare.', 'pornovirtuale' ),
		);
		set_transient( self::progress_key(), $state, 2 * HOUR_IN_SECONDS );
		wp_send_json_success( $state );
	}

	public static function ajax_batch() {
		check_ajax_referer( 'pv_video_cleanup_ajax', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Non hai i permessi per eseguire questa operazione.', 'pornovirtuale' ) ), 403 );
		}

		$state = get_transient( self::progress_key() );
		if ( ! is_array( $state ) ) {
			wp_send_json_error( array( 'message' => __( 'Sessione di cancellazione non trovata. Avvia nuovamente la procedura.', 'pornovirtuale' ) ), 400 );
		}
		if ( 'running' !== $state['status'] ) {
			wp_send_json_success( $state );
		}

		global $wpdb;
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND ID > %d ORDER BY ID ASC LIMIT %d",
				'video',
				(int) $state['last_id'],
				self::BATCH_SIZE
			)
		);

		if ( empty( $ids ) ) {
			$state['status']  = 'completed';
			$state['message'] = sprintf(
				/* translators: 1: deleted videos, 2: items that could not be deleted */
				__( 'Operazione terminata: %1$d video eliminati definitivamente. Elementi non eliminati: %2$d.', 'pornovirtuale' ),
				(int) $state['deleted'],
				max( 0, (int) $state['total'] - (int) $state['deleted'] )
			);
			set_transient( self::progress_key(), $state, 2 * HOUR_IN_SECONDS );
			wp_send_json_success( $state );
		}

		foreach ( $ids as $post_id ) {
			$post_id = (int) $post_id;
			$state['last_id'] = $post_id;
			$state['processed']++;
			if ( wp_delete_post( $post_id, true ) ) {
				$state['deleted']++;
			}
		}

		$remaining = self::count_videos();
		if ( 0 === $remaining || (int) $state['last_id'] >= (int) end( $ids ) && count( $ids ) < self::BATCH_SIZE ) {
			$state['status'] = 'completed';
			$state['message'] = sprintf(
				/* translators: 1: deleted videos, 2: remaining videos */
				__( 'Operazione terminata: %1$d video eliminati definitivamente. Video ancora presenti: %2$d.', 'pornovirtuale' ),
				(int) $state['deleted'],
				$remaining
			);
		} else {
			$state['message'] = sprintf(
				/* translators: 1: deleted videos, 2: total videos */
				__( 'Eliminati %1$d video su %2$d. Cancellazione in corso…', 'pornovirtuale' ),
				(int) $state['deleted'],
				(int) $state['total']
			);
		}

		set_transient( self::progress_key(), $state, 2 * HOUR_IN_SECONDS );
		wp_send_json_success( $state );
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$total = self::count_videos();
		$nonce = wp_create_nonce( 'pv_video_cleanup_ajax' );
		$ajax_url = admin_url( 'admin-ajax.php' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Pulizia video', 'pornovirtuale' ); ?></h1>
			<div class="card" style="max-width:850px;padding:20px;margin-top:20px;">
				<h2><?php esc_html_e( 'Eliminazione definitiva di tutti i video', 'pornovirtuale' ); ?></h2>
				<p id="pv-cleanup-total"><?php echo esc_html( sprintf( __( 'Video presenti (incluso il cestino): %d', 'pornovirtuale' ), $total ) ); ?></p>
				<p><?php esc_html_e( 'La procedura elimina definitivamente tutti i post del tipo “video”, compresi pubblicati, programmati, bozze, privati e quelli già nel cestino. I video vengono elaborati automaticamente in gruppi da 100; non è necessario premere di nuovo il pulsante.', 'pornovirtuale' ); ?></p>
				<p><strong><?php esc_html_e( 'Attenzione: l’eliminazione è irreversibile e riguarda tutti i video, non soltanto quelli importati da una fonte specifica.', 'pornovirtuale' ); ?></strong></p>

				<div id="pv-cleanup-progress-wrap" style="display:none;margin:20px 0;">
					<div style="display:flex;justify-content:space-between;gap:12px;margin-bottom:8px;">
						<strong id="pv-cleanup-status"><?php esc_html_e( 'In attesa…', 'pornovirtuale' ); ?></strong>
						<strong id="pv-cleanup-percent">0%</strong>
					</div>
					<div role="progressbar" aria-label="<?php esc_attr_e( 'Avanzamento cancellazione video', 'pornovirtuale' ); ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" id="pv-cleanup-progress" style="height:22px;background:#dcdcde;border-radius:4px;overflow:hidden;">
						<div id="pv-cleanup-progress-fill" style="width:0%;height:100%;background:#b32d2e;transition:width .25s ease;"></div>
					</div>
					<p id="pv-cleanup-count" aria-live="polite">0 / <?php echo esc_html( (string) $total ); ?></p>
					<p id="pv-cleanup-message" aria-live="polite"></p>
				</div>

				<?php if ( $total > 0 ) : ?>
					<p><label for="pv-cleanup-confirm"><?php esc_html_e( 'Per confermare, scrivi esattamente ELIMINA VIDEO:', 'pornovirtuale' ); ?></label><br>
						<input type="text" id="pv-cleanup-confirm" class="regular-text" required autocomplete="off">
					</p>
					<p><button type="button" class="button button-primary" id="pv-cleanup-start"><?php esc_html_e( 'Elimina tutti i video definitivamente', 'pornovirtuale' ); ?></button></p>
					<p id="pv-cleanup-error" style="color:#b32d2e;" role="alert"></p>
				<?php else : ?>
					<p><strong><?php esc_html_e( 'Non ci sono video da eliminare.', 'pornovirtuale' ); ?></strong></p>
				<?php endif; ?>
			</div>
		</div>
		<script>
		(function () {
			const startButton = document.getElementById('pv-cleanup-start');
			if (!startButton) return;
			const confirmInput = document.getElementById('pv-cleanup-confirm');
			const wrap = document.getElementById('pv-cleanup-progress-wrap');
			const fill = document.getElementById('pv-cleanup-progress-fill');
			const bar = document.getElementById('pv-cleanup-progress');
			const percentText = document.getElementById('pv-cleanup-percent');
			const countText = document.getElementById('pv-cleanup-count');
			const statusText = document.getElementById('pv-cleanup-status');
			const messageText = document.getElementById('pv-cleanup-message');
			const errorText = document.getElementById('pv-cleanup-error');
			const totalInitial = <?php echo (int) $total; ?>;
			const ajaxUrl = <?php echo wp_json_encode( $ajax_url ); ?>;
			const nonce = <?php echo wp_json_encode( $nonce ); ?>;
			let running = false;

			async function request(action) {
				const body = new URLSearchParams({ action: action, nonce: nonce });
				const response = await fetch(ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
					body: body.toString()
				});
				const json = await response.json();
				if (!response.ok || !json.success) {
					throw new Error(json.data && json.data.message ? json.data.message : 'Errore durante la richiesta al server.');
				}
				return json.data;
			}

			function update(state) {
				const total = Number(state.total || totalInitial);
				const processed = Number(state.processed || 0);
				const deleted = Number(state.deleted || 0);
				const percent = total > 0 ? Math.min(100, Math.floor((processed / total) * 100)) : 100;
				fill.style.width = percent + '%';
				percentText.textContent = percent + '%';
				bar.setAttribute('aria-valuenow', String(percent));
				countText.textContent = deleted + ' eliminati · ' + processed + ' elaborati su ' + total;
				statusText.textContent = state.status === 'completed' ? 'Completato' : 'Cancellazione in corso';
				messageText.textContent = state.message || '';
			}

			async function runBatch() {
				try {
					const state = await request('pv_video_cleanup_batch');
					update(state);
					if (state.status === 'running') {
						setTimeout(runBatch, 150);
					} else {
						running = false;
						startButton.disabled = false;
						startButton.textContent = 'Procedura terminata';
						confirmInput.disabled = true;
					}
				} catch (error) {
					running = false;
					startButton.disabled = false;
					errorText.textContent = error.message;
					statusText.textContent = 'Operazione interrotta per errore';
				}
			}

			startButton.addEventListener('click', async function () {
				if (running) return;
				if (confirmInput.value.trim() !== 'ELIMINA VIDEO') {
					errorText.textContent = 'Scrivi esattamente ELIMINA VIDEO per confermare.';
					return;
				}
				if (!window.confirm('Confermi l’eliminazione DEFINITIVA di tutti i video, compresi quelli nel cestino? L’operazione non è reversibile.')) return;
				running = true;
				startButton.disabled = true;
				errorText.textContent = '';
				wrap.style.display = 'block';
				statusText.textContent = 'Avvio…';
				messageText.textContent = '';
				try {
					const state = await request('pv_video_cleanup_start');
					update(state);
					if (state.status === 'running') {
						runBatch();
					} else {
						running = false;
						startButton.textContent = 'Nessun video da eliminare';
					}
				} catch (error) {
					running = false;
					startButton.disabled = false;
					errorText.textContent = error.message;
				}
			});
		})();
		</script>
		<?php
	}
}

PV_Video_Cleanup::init();
