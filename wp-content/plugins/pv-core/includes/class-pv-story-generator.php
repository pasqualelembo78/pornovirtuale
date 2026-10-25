<?php
/**
 * Generatore di storie narrative per video con Groq API.
 *
 * @package Pornovirtuale
 */

defined( 'ABSPATH' ) || exit;

class PV_Story_Generator {

	const OPTION_KEYS = 'pv_groq_keys';
	const OPTION_CFG  = 'pv_story_generator_cfg';
	const QUEUE_OPTION = 'pv_story_gen_queue';
	const LOG_OPTION   = 'pv_story_gen_log';
	const CRON_HOOK    = 'pv_story_gen_process';

	/**
	 * Impostazioni di default.
	 */
	public static function defaults() {
		return array(
			'enabled'        => 0,
			'batch_size'     => 10,
			'max_per_key'    => 50,
			'min_words'      => 500,
			'max_words'      => 700,
			'model'          => 'llama-3.1-70b-versatile',
			'temperature'    => 0.8,
			'retry_attempts' => 3,
			'timeout'        => 60,
			'skip_if_exists' => 1,
			'add_note'       => 1,
			'edit_note_url'  => 'https://pornovirtuale.com/nota-editoriale-video/',
			'unlimited'      => 1,
		);
	}

	/**
	 * Inizializzazione.
	 */
	public static function init() {
		add_action( self::CRON_HOOK, array( __CLASS__, 'process_queue' ) );
		add_action( 'init', array( __CLASS__, 'schedule' ) );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_actions' ) );
	}

	/**
	 * Schedulazione.
	 */
	public static function schedule() {
		$cfg = get_option( self::OPTION_CFG, self::defaults() );
		if ( ! is_array( $cfg ) ) {
			$cfg = self::defaults();
		}
		$next = wp_next_scheduled( self::CRON_HOOK );
		if ( ! empty( $cfg['enabled'] ) ) {
			if ( ! $next ) {
				wp_schedule_event( time() + 120, 'every_five_minutes', self::CRON_HOOK );
			}
		} else {
			if ( $next ) {
				wp_clear_scheduled_hook( self::CRON_HOOK );
			}
		}
	}

	/**
	 * Aggiunge intervallo cron.
	 */
	public static function cron_intervals( $schedules ) {
		$schedules['every_five_minutes'] = array(
			'interval' => 300,
			'display'  => __( 'Ogni 5 minuti', 'pornovirtuale' ),
		);
		return $schedules;
	}

	/**
	 * Menu admin.
	 */
	public static function admin_menu() {
		add_submenu_page(
			'pv-core-dashboard',
			__( 'Generatore Storie', 'pornovirtuale' ),
			__( 'Generatore Storie', 'pornovirtuale' ),
			'manage_options',
			'pv-story-generator',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Gestione azioni.
	 */
	public static function handle_actions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( isset( $_POST['pv_save_story_cfg'] ) ) {
			check_admin_referer( 'pv_save_story_cfg' );
			$cfg = array(
				'enabled'        => isset( $_POST['enabled'] ) ? 1 : 0,
				'batch_size'     => max( 1, min( 10, (int) ( $_POST['batch_size'] ?? 3 ) ) ),
				'max_per_key'    => max( 1, (int) ( $_POST['max_per_key'] ?? 20 ) ),
				'min_words'      => max( 200, (int) ( $_POST['min_words'] ?? 500 ) ),
				'max_words'      => max( 300, (int) ( $_POST['max_words'] ?? 700 ) ),
				'model'          => sanitize_text_field( wp_unslash( $_POST['model'] ?? 'llama-3.1-70b-versatile' ) ),
				'temperature'    => (float) ( $_POST['temperature'] ?? 0.8 ),
				'retry_attempts' => max( 1, min( 5, (int) ( $_POST['retry_attempts'] ?? 3 ) ) ),
				'timeout'        => max( 30, (int) ( $_POST['timeout'] ?? 60 ) ),
				'skip_if_exists' => isset( $_POST['skip_if_exists'] ) ? 1 : 0,
				'add_note'       => isset( $_POST['add_note'] ) ? 1 : 0,
				'edit_note_url'  => esc_url_raw( wp_unslash( $_POST['edit_note_url'] ?? 'https://pornovirtuale.com/nota-editoriale-video/' ) ),
			);
			update_option( self::OPTION_CFG, $cfg );
			self::schedule();
			add_settings_error( 'pv_story_gen', 'saved', __( 'Configurazione salvata.', 'pornovirtuale' ), 'updated' );
		}

		if ( isset( $_POST['pv_add_keys'] ) ) {
			check_admin_referer( 'pv_add_keys' );
			$raw = wp_unslash( $_POST['groq_keys'] ?? '' );
			if ( $raw ) {
				$keys = preg_split( '/[\r\n]+/', $raw );
				$stored = get_option( self::OPTION_KEYS, array() );
				if ( ! is_array( $stored ) ) {
					$stored = array();
				}
				foreach ( $keys as $k ) {
					$k = trim( $k );
					if ( $k && strpos( $k, 'gsk_' ) === 0 ) {
						$hash = hash( 'sha256', $k );
						// Store hashed + masked reference, never store plain in a way that's logged
						if ( ! isset( $stored[ $hash ] ) ) {
							$stored[ $hash ] = array(
								'mask'       => substr( $k, 0, 6 ) . '...' . substr( $k, -4 ),
								'used'       => 0,
								'errors'     => 0,
								'last_used'  => 0,
								'disabled'   => 0,
								'key'        => base64_encode( $k ), // obfuscated in DB only, not printed
							);
						}
					}
				}
				update_option( self::OPTION_KEYS, $stored );
				add_settings_error( 'pv_story_gen', 'keys', __( 'Chiavi aggiornate (non visualizzate).', 'pornovirtuale' ), 'updated' );
			}
			wp_safe_redirect( add_query_arg( array( 'page' => 'pv-story-generator' ), admin_url( 'admin.php' ) ) );
			exit;
		}

		if ( isset( $_POST['pv_clear_keys'] ) ) {
			check_admin_referer( 'pv_clear_keys' );
			delete_option( self::OPTION_KEYS );
			add_settings_error( 'pv_story_gen', 'keys', __( 'Chiavi rimosse.', 'pornovirtuale' ), 'updated' );
		}

		if ( isset( $_POST['pv_build_queue'] ) ) {
			check_admin_referer( 'pv_build_queue' );
			self::build_queue();
			add_settings_error( 'pv_story_gen', 'queue', __( 'Coda ricostruita.', 'pornovirtuale' ), 'updated' );
		}

		if ( isset( $_POST['pv_process_now'] ) ) {
			check_admin_referer( 'pv_process_now' );
			$res = self::process_queue( true );
			set_transient( 'pv_story_gen_result', $res, 300 );
			wp_safe_redirect( add_query_arg( array( 'page' => 'pv-story-generator' ), admin_url( 'admin.php' ) ) );
			exit;
		}

		if ( isset( $_POST['pv_build_and_process'] ) ) {
			check_admin_referer( 'pv_build_and_process' );
			self::build_queue();
			$res = self::process_queue( true );
			set_transient( 'pv_story_gen_result', $res, 300 );
			wp_safe_redirect( add_query_arg( array( 'page' => 'pv-story-generator' ), admin_url( 'admin.php' ) ) );
			exit;
		}

		if ( isset( $_POST['pv_pause'] ) ) {
			check_admin_referer( 'pv_pause' );
			$cfg = get_option( self::OPTION_CFG, self::defaults() );
			if ( ! is_array( $cfg ) ) {
				$cfg = self::defaults();
			}
			$cfg['enabled'] = 0;
			update_option( self::OPTION_CFG, $cfg );
			self::schedule();
			add_settings_error( 'pv_story_gen', 'paused', __( 'Processo messo in pausa.', 'pornovirtuale' ), 'updated' );
		}

		if ( isset( $_POST['pv_resume'] ) ) {
			check_admin_referer( 'pv_resume' );
			$cfg = get_option( self::OPTION_CFG, self::defaults() );
			if ( ! is_array( $cfg ) ) {
				$cfg = self::defaults();
			}
			$cfg['enabled'] = 1;
			update_option( self::OPTION_CFG, $cfg );
			self::schedule();
			add_settings_error( 'pv_story_gen', 'resumed', __( 'Processo ripreso.', 'pornovirtuale' ), 'updated' );
		}

		if ( isset( $_POST['pv_clear_queue'] ) ) {
			check_admin_referer( 'pv_clear_queue' );
			delete_option( self::QUEUE_OPTION );
			add_settings_error( 'pv_story_gen', 'queue', __( 'Coda svuotata.', 'pornovirtuale' ), 'updated' );
		}
	}

	/**
	 * Costruisce coda per video senza storia adeguata.
	 */
	public static function build_queue() {
		$cfg = get_option( self::OPTION_CFG, self::defaults() );
		if ( ! is_array( $cfg ) ) {
			$cfg = self::defaults();
		}
		$queue = array();
		$args  = array(
			'post_type'      => 'video',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'ASC',
			'fields'         => 'ids',
		);
		$ids = get_posts( $args );
		foreach ( $ids as $pid ) {
			$content = get_post_field( 'post_content', $pid );
			// Considera presente una "storia" adeguata se ha più di 400 caratteri e non solo shortcodes/player
			$stripped = strip_shortcodes( $content );
			$stripped = wp_strip_all_tags( $stripped );
			$stripped = trim( $stripped );
			if ( ! empty( $cfg['skip_if_exists'] ) && strlen( $stripped ) > 400 ) {
				continue;
			}
			$queue[] = (int) $pid;
		}
		// Rimuovi duplicati mantenendo ordine
		$queue = array_values( array_unique( $queue ) );
		update_option( self::QUEUE_OPTION, $queue );
		return count( $queue );
	}

	/**
	 * Ottieni prossima chiave disponibile.
	 */
	protected static function get_next_key() {
		$keys = get_option( self::OPTION_KEYS, array() );
		if ( ! is_array( $keys ) || empty( $keys ) ) {
			return false;
		}
		$cfg = get_option( self::OPTION_CFG, self::defaults() );
		if ( ! is_array( $cfg ) ) {
			$cfg = self::defaults();
		}
		$max_per = (int) $cfg['max_per_key'];
		foreach ( $keys as $hash => $data ) {
			if ( ! empty( $data['disabled'] ) ) {
				continue;
			}
			$used = isset( $data['used'] ) ? (int) $data['used'] : 0;
			if ( $used >= $max_per && $max_per > 0 ) {
				continue;
			}
			// decode
			if ( empty( $data['key'] ) ) {
				continue;
			}
			$key = base64_decode( $data['key'] );
			if ( ! $key || strpos( $key, 'gsk_' ) !== 0 ) {
				continue;
			}
			// aggiorna uso
			$data['used']      = $used + 1;
			$data['last_used'] = time();
			$keys[ $hash ]     = $data;
			update_option( self::OPTION_KEYS, $keys );
			return $key;
		}
		return false;
	}

	/**
	 * Registra chiave disabilitata in caso di rate limit persistente.
	 */
	protected static function mark_key_limited( $key ) {
		$keys = get_option( self::OPTION_KEYS, array() );
		if ( ! is_array( $keys ) ) {
			return;
		}
		$hash = hash( 'sha256', $key );
		if ( isset( $keys[ $hash ] ) ) {
			$keys[ $hash ]['errors'] = isset( $keys[ $hash ]['errors'] ) ? (int) $keys[ $hash ]['errors'] + 1 : 1;
			if ( $keys[ $hash ]['errors'] >= 5 ) {
				$keys[ $hash ]['disabled'] = 1;
			}
			update_option( self::OPTION_KEYS, $keys );
		}
	}

	/**
	 * Genera storia con Groq.
	 */
	protected static function generate_story( $post_id, $key ) {
		$cfg = get_option( self::OPTION_CFG, self::defaults() );
		if ( ! is_array( $cfg ) ) {
			$cfg = self::defaults();
		}

		$title   = get_the_title( $post_id );
		$cats    = wp_get_post_terms( $post_id, 'pv_categoria', array( 'fields' => 'names' ) );
		$duration = get_post_meta( $post_id, '_pv_duration', true );
		$res      = get_post_meta( $post_id, '_pv_resolution', true );
		$vr       = (int) get_post_meta( $post_id, '_pv_vr_mode', true );
		$source   = get_post_meta( $post_id, '_pv_source', true );

		$context = array();
		$context[] = 'Titolo: ' . $title;
		if ( ! is_wp_error( $cats ) && ! empty( $cats ) ) {
			$context[] = 'Categorie: ' . implode( ', ', $cats );
		}
		if ( $duration ) {
			$context[] = 'Durata: ' . $duration;
		}
		if ( $res ) {
			$context[] = 'Risoluzione: ' . $res;
		}
		if ( $vr ) {
			$context[] = 'Formato VR: sì';
		}
		if ( $source ) {
			$context[] = 'Sorgente: ' . $source;
		}

		$prompt  = "Scrivi una storia narrativa originale in italiano, adatta a un articolo su un sito di video VR per adulti consenzienti.\n";
		$prompt .= "Lunghezza: tra " . (int) $cfg['min_words'] . " e " . (int) $cfg['max_words'] . " parole.\n";
		$prompt .= "Usa un tono naturale, atmosferico, vario; evita introduzioni generiche tipo 'In questo video...', frasi fatte, ripetizioni e disclaimer lunghi.\n";
		$prompt .= "NON inventare nomi precisi, atti specifici dettagliati o descrivere esplicitamente scene frame per frame presentandoli come fatti certi. Usa il titolo e i metadati come ispirazione.\n";
		$prompt .= "Concentrati su atmosfera, suggestione, mood. Genera contenuto esclusivamente per adulti consenzienti.\n";
		$prompt .= "Non menzionare età in modo ambiguo; se hai dubbi su consenso/età evita di scrivere e rispondi solo con SKIP.\n";
		$prompt .= "Non includere link, hashtag, tag, elenco di parole chiave.\n";
		$prompt .= "Usa paragrafi ben strutturati.\n\n";
		$prompt .= "Informazioni ispiratrici:\n" . implode( "\n", $context ) . "\n\n";
		$prompt .= "Rispondi SOLO con il testo della storia in italiano, senza prefazioni, senza commenti.";

		$body = array(
			'model'       => $cfg['model'],
			'messages'    => array(
				array( 'role' => 'system', 'content' => 'Sei un narratore italiano esperto di atmosfere sensoriali per contenuti adulti consenzienti. Rispetta sempre le indicazioni.' ),
				array( 'role' => 'user', 'content' => $prompt ),
			),
			'temperature' => (float) $cfg['temperature'],
			'max_tokens'  => 2048,
		);

		$attempts = 0;
		$max_att  = max( 1, (int) $cfg['retry_attempts'] );

		while ( $attempts < $max_att ) {
			$attempts++;
			$response = wp_remote_post(
				'https://api.groq.com/openai/v1/chat/completions',
				array(
					'headers' => array(
						'Authorization' => 'Bearer ' . $key,
						'Content-Type'  => 'application/json',
					),
					'body'    => wp_json_encode( $body ),
					'timeout' => (int) $cfg['timeout'],
				)
			);

			if ( is_wp_error( $response ) ) {
				if ( $attempts < $max_att ) {
					sleep( 2 );
					continue;
				}
				return new WP_Error( 'request_error', $response->get_error_message() );
			}

			$code = wp_remote_retrieve_response_code( $response );
			$raw  = wp_remote_retrieve_body( $response );

			if ( 429 === $code || 503 === $code || 529 === $code ) {
				self::mark_key_limited( $key );
				if ( $attempts < $max_att ) {
					sleep( 3 * $attempts );
					continue;
				}
				return new WP_Error( 'rate_limited', 'rate/overload' );
			}

			if ( $code < 200 || $code >= 300 ) {
				if ( $attempts < $max_att ) {
					sleep( 2 );
					continue;
				}
				return new WP_Error( 'api_error', 'http_' . $code );
			}

			$data = json_decode( $raw, true );
			if ( ! is_array( $data ) ) {
				if ( $attempts < $max_att ) {
					sleep( 2 );
					continue;
				}
				return new WP_Error( 'bad_json', 'invalid_response' );
			}

			$text = $data['choices'][0]['message']['content'] ?? '';
			if ( ! is_string( $text ) ) {
				$text = '';
			}
			$text = trim( $text );
			if ( stripos( $text, 'SKIP' ) === 0 && strlen( $text ) < 20 ) {
				return new WP_Error( 'skipped', 'ambiguous_or_not_allowed' );
			}
			// Rimuovi eventuali backticks
			$text = preg_replace( '/^```[a-z]*\n?/im', '', $text );
			$text = preg_replace( '/\n?```$/im', '', $text );
			$text = trim( $text );
			if ( strlen( wp_strip_all_tags( $text ) ) < 200 ) {
				if ( $attempts < $max_att ) {
					sleep( 2 );
					continue;
				}
				return new WP_Error( 'too_short', 'generated_too_short' );
			}
			return $text;
		}

		return new WP_Error( 'failed', 'max_attempts' );
	}

	/**
	 * Verifica duplicati rispetto a contenuti esistenti (semplice euristica).
	 */
	protected static function is_too_similar( $text ) {
		$sample = wp_strip_all_tags( $text );
		$sample = preg_replace( '/\s+/', ' ', $sample );
		$sample = strtolower( $sample );
		$len    = strlen( $sample );
		if ( $len < 100 ) {
			return false;
		}
		// Prendi prime 80 e ultime 80 come impronta
		$head = substr( $sample, 0, 80 );
		$tail = substr( $sample, -80 );
		$args = array(
			'post_type'      => 'video',
			'post_status'    => 'publish',
			'posts_per_page' => 20,
			'orderby'        => 'rand',
			'fields'         => 'ids',
		);
		$ids = get_posts( $args );
		foreach ( $ids as $pid ) {
			$c = get_post_field( 'post_content', $pid );
			$c = wp_strip_all_tags( strip_shortcodes( $c ) );
			$c = preg_replace( '/\s+/', ' ', strtolower( $c ) );
			if ( strpos( $c, $head ) !== false && strpos( $c, $tail ) !== false ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Aggiorna post video con la storia.
	 */
	protected static function update_video_post( $post_id, $story ) {
		$post = get_post( $post_id );
		if ( ! $post || 'video' !== $post->post_type ) {
			return new WP_Error( 'invalid_post', 'not_a_video' );
		}
		if ( 'publish' !== $post->post_status ) {
			// Mantieni stato originale - non forzare pubblicazione se non già pubblicato? Richiesta dice "Mantieni gli articoli pubblicati pubblicati e rispetta lo stato originale degli articoli."
			// Ma dice anche "Aggiorna il contenuto degli articoli esistenti senza creare duplicati" - procediamo solo su pubblicati come da logica coda
		}

		$cfg   = get_option( self::OPTION_CFG, self::defaults() );
		if ( ! is_array( $cfg ) ) {
			$cfg = self::defaults();
		}

		$content = $post->post_content;
		// Conserva player/shortcode/embed - the_content contiene già tutto; aggiungiamo storia prima o dopo? "Inserisci la narrazione nella posizione corretta del contenuto, senza danneggiare il layout."
		// Mettiamo la storia in un blocco separato dopo eventuale contenuto tecnico, ma preservando tutto. Se c'è già molto testo (oltre 400 char stripped), saltiamo per policy.
		$stripped = strip_shortcodes( $content );
		$stripped = wp_strip_all_tags( $stripped );
		$stripped = trim( $stripped );
		if ( strlen( $stripped ) > 400 ) {
			return new WP_Error( 'already_has_story', 'skipped_existing' );
		}

		$story_html = wpautop( wp_kses_post( $story ) );
		$note       = '';
		if ( ! empty( $cfg['add_note'] ) && ! empty( $cfg['edit_note_url'] ) ) {
			$note = "\n\n<p><em><a href=\"" . esc_url( $cfg['edit_note_url'] ) . "\">" . esc_html__( 'Nota editoriale video', 'pornovirtuale' ) . "</a></em></p>\n";
		}

		// Aggiungi storia mantenendo contenuto esistente (player/shortcodes)
		$new_content = $content;
		// Sposta "Video importato da ..." in basso con link nofollow alla fonte
		$import_line = '';
		if ( preg_match( '/<p>\s*Video importato da[^<]+<\/p>/i', $new_content, $m ) ) {
			$import_line = $m[0];
			$new_content  = str_replace( $import_line, '', $new_content );
		} elseif ( preg_match( '/Video importato da[^\n]+/i', $new_content, $m ) ) {
			$import_line = '<p>' . esc_html( trim( $m[0] ) ) . '</p>';
			$new_content  = str_replace( $m[0], '', $new_content );
		}
		// Rimuovi newline residui
		$new_content = preg_replace( "/\R+/", "\n", $new_content );
		// Aggiungi link nofollow alla fonte se presente
		$src_url = get_post_meta( $post_id, '_pv_source_url', true );
		if ( $import_line && $src_url ) {
			$import_line = preg_replace( '/(Video importato da\s+[^<\s]+(?:\.[^\s<]+)?)/i', '<a href="' . esc_url( $src_url ) . '" rel="nofollow noopener noreferrer" target="_blank">$1</a>', $import_line );
		}

		if ( trim( $new_content ) === '' ) {
			$new_content = $story_html . $note . "\n\n" . $import_line;
		} else {
			// Inserisci storia dopo il contenuto tecnico esistente, nota editoriale e fonte in fondo
			$new_content = trim( $new_content ) . "\n\n" . $story_html . $note . "\n\n" . $import_line;
		}
		$new_content = preg_replace( "/\n{3,}/", "\n\n", $new_content );

		$result = wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => $new_content,
			),
			true
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( 0 === $result ) {
			return new WP_Error( 'update_failed', 'wordpress_update_failed' );
		}
		return true;
	}

	/**
	 * Processa coda.
	 */
	public static function process_queue( $manual = false ) {
		$cfg = get_option( self::OPTION_CFG, self::defaults() );
		if ( ! is_array( $cfg ) ) {
			$cfg = self::defaults();
		}
		if ( ! $manual && empty( $cfg['enabled'] ) ) {
			return array( 'status' => 'disabled' );
		}

		$queue = get_option( self::QUEUE_OPTION, array() );
		if ( ! is_array( $queue ) || empty( $queue ) ) {
			$built = self::build_queue();
			$queue = get_option( self::QUEUE_OPTION, array() );
			if ( ! is_array( $queue ) || empty( $queue ) ) {
				return array( 'status' => 'empty', 'built' => $built );
			}
		}

		$batch = ! empty( $cfg['unlimited'] ) ? 1000000 : (int) $cfg['batch_size'];
		if ( $batch < 1 ) {
			$batch = 1000000;
		}
		$processed = 0;
		$updated   = 0;
		$skipped   = 0;
		$failed    = 0;
		$log       = array();
		$total     = count( $queue );

		while ( ! empty( $queue ) && $processed < $batch ) {
			$post_id = array_shift( $queue );
			$post_id = (int) $post_id;
			update_option( self::QUEUE_OPTION, $queue );
			// Progress semplice
			if ( $manual || ( $processed % 5 === 0 && $total > 0 ) ) {
				// store progress
				update_option( self::QUEUE_OPTION . '_progress', array( 'done' => $processed, 'total' => $total, 'time' => time() ), false );
			}

			$post = get_post( $post_id );
			if ( ! $post || 'video' !== $post->post_type || 'publish' !== $post->post_status ) {
				$skipped++;
				$processed++;
				$log[] = array( 'id' => $post_id, 'status' => 'skipped_invalid' );
				continue;
			}

			$content = $post->post_content;
			$stripped = strip_shortcodes( $content );
			$stripped = wp_strip_all_tags( $stripped );
			$stripped = trim( $stripped );
			if ( strlen( $stripped ) > 400 ) {
				$skipped++;
				$processed++;
				$log[] = array( 'id' => $post_id, 'status' => 'skipped_has_content' );
				continue;
			}

			$key = self::get_next_key();
			if ( ! $key ) {
				// Rimetti in coda e riprova dopo
				array_unshift( $queue, $post_id );
				update_option( self::QUEUE_OPTION, $queue );
				$log[] = array( 'id' => $post_id, 'status' => 'queued_no_key' );
				break;
			}

			$story = self::generate_story( $post_id, $key );
			if ( is_wp_error( $story ) ) {
				$code = $story->get_error_code();
				if ( 'skipped' === $code ) {
					$skipped++;
					$processed++;
					$log[] = array( 'id' => $post_id, 'status' => 'skipped_ambiguous' );
					continue;
				}
				if ( 'rate_limited' === $code ) {
					// Rimetti in coda
					array_unshift( $queue, $post_id );
					update_option( self::QUEUE_OPTION, $queue );
					$failed++;
					$processed++;
					$log[] = array( 'id' => $post_id, 'status' => 'rate_limited_requeued' );
					break;
				}
				$failed++;
				$processed++;
				$log[] = array( 'id' => $post_id, 'status' => 'failed', 'err' => $code );
				continue;
			}

			if ( self::is_too_similar( $story ) ) {
				$skipped++;
				$processed++;
				$log[] = array( 'id' => $post_id, 'status' => 'skipped_similar' );
				continue;
			}

			$upd = self::update_video_post( $post_id, $story );
			if ( is_wp_error( $upd ) ) {
				$failed++;
				$processed++;
				$log[] = array( 'id' => $post_id, 'status' => 'failed_update', 'err' => $upd->get_error_code() );
				continue;
			}

			$updated++;
			$processed++;
			$log[] = array( 'id' => $post_id, 'status' => 'updated' );
		}

		// Aggiorna log
		$hist = get_option( self::LOG_OPTION, array() );
		if ( ! is_array( $hist ) ) {
			$hist = array();
		}
		array_unshift( $hist, array(
			'time'      => current_time( 'mysql' ),
			'manual'    => (bool) $manual,
			'processed' => $processed,
			'updated'   => $updated,
			'skipped'   => $skipped,
			'failed'    => $failed,
			'queue_remain' => count( $queue ),
			'log'       => $log,
			'progress'  => $total > 0 ? round( ( $total - count( $queue ) ) / $total * 100, 2 ) : 100,
		) );
		if ( count( $hist ) > 50 ) {
			$hist = array_slice( $hist, 0, 50 );
		}
		update_option( self::LOG_OPTION, $hist );
		update_option( self::QUEUE_OPTION . '_progress', array( 'done' => $total - count( $queue ), 'total' => $total, 'time' => time(), 'percent' => $total > 0 ? round( ( $total - count( $queue ) ) / $total * 100, 2 ) : 100 ), false );

		return array(
			'status'      => 'ok',
			'processed'   => $processed,
			'updated'     => $updated,
			'skipped'     => $skipped,
			'failed'      => $failed,
			'queue_remain'=> count( $queue ),
			'progress'    => $total > 0 ? round( ( $total - count( $queue ) ) / $total * 100, 2 ) : 100,
		);
	}

	/**
	 * Rendering pagina admin.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		settings_errors( 'pv_story_gen' );
		$cfg   = get_option( self::OPTION_CFG, self::defaults() );
		if ( ! is_array( $cfg ) ) {
			$cfg = self::defaults();
		}
		$keys  = get_option( self::OPTION_KEYS, array() );
		if ( ! is_array( $keys ) ) {
			$keys = array();
		}
		$queue = get_option( self::QUEUE_OPTION, array() );
		if ( ! is_array( $queue ) ) {
			$queue = array();
		}
		$log   = get_option( self::LOG_OPTION, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}
		$next  = wp_next_scheduled( self::CRON_HOOK );
		$res   = get_transient( 'pv_story_gen_result' );
		delete_transient( 'pv_story_gen_result' );

		$key_count = 0;
		$disabled  = 0;
		foreach ( $keys as $k ) {
			$key_count++;
			if ( ! empty( $k['disabled'] ) ) {
				$disabled++;
			}
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Generatore di Storie Narrative (Groq)', 'pornovirtuale' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Genera storie in italiano per i video esistenti, aggiorna gli articoli preservando player/embed/shortcode/metadati.', 'pornovirtuale' ); ?></p>

			<?php if ( $res ) : ?>
				<div class="notice notice-info"><p><strong><?php esc_html_e( 'Risultato esecuzione', 'pornovirtuale' ); ?></strong></p><pre><?php echo esc_html( print_r( $res, true ) ); ?></pre></div>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Stato configurazione Groq', 'pornovirtuale' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><?php esc_html_e( 'Chiavi configurate', 'pornovirtuale' ); ?></th><td><?php echo (int) $key_count; ?> (disabilitate/limitate: <?php echo (int) $disabled; ?>)</td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Stato processo', 'pornovirtuale' ); ?></th><td><?php echo ! empty( $cfg['enabled'] ) ? esc_html__( 'Attivo (schedulato)', 'pornovirtuale' ) : esc_html__( 'In pausa', 'pornovirtuale' ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Prossima esecuzione cron', 'pornovirtuale' ); ?></th><td><?php echo $next ? date_i18n( 'Y-m-d H:i:s', $next ) : esc_html__( 'Non pianificata', 'pornovirtuale' ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Elementi in coda', 'pornovirtuale' ); ?></th><td><?php echo count( $queue ); ?></td></tr>
			</table>
			<?php $prog = get_option( PV_Story_Generator::QUEUE_OPTION . '_progress', array() ); if(!empty($prog['total'])): $p = isset($prog['percent'])?$prog['percent']:round(($prog['done']/$prog['total'])*100,2); ?>
			<h3><?php esc_html_e( 'Avanzamento', 'pornovirtuale' ); ?></h3>
			<div style="background:#eee;width:100%;max-width:600px;height:24px;border-radius:4px;overflow:hidden;"><div style="background:#2271b1;height:24px;width:<?php echo esc_attr($p); ?>%;text-align:center;color:#fff;line-height:24px;font-size:12px;"><?php echo esc_html($p); ?>%</div></div>
			<p class="description"><?php echo esc_html($prog['done']); ?>/<?php echo esc_html($prog['total']); ?> elaborati</p>
			<?php endif; ?>

			<form method="post" action=""><?php wp_nonce_field( 'pv_add_keys' ); ?>
				<h2><?php esc_html_e( 'Aggiungi chiavi Groq', 'pornovirtuale' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Inserisci una o più chiavi (una per riga). Le chiavi non verranno mai visualizzate. Verranno memorizzate in modo offuscato.', 'pornovirtuale' ); ?></p>
				<table class="form-table"><tr><th scope="row"><?php esc_html_e( 'Chiavi', 'pornovirtuale' ); ?></th><td><textarea name="groq_keys" rows="4" class="large-text" placeholder="gsk_...&#10;gsk_..."></textarea></td></tr></table>
				<?php submit_button( __( 'Aggiungi chiavi', 'pornovirtuale' ), 'primary', 'pv_add_keys' ); ?>
			</form>
			<form method="post" action=""><?php wp_nonce_field( 'pv_clear_keys' ); ?><?php submit_button( __( 'Rimuovi tutte le chiavi', 'pornovirtuale' ), 'delete', 'pv_clear_keys' ); ?></form>

			<form method="post" action=""><?php wp_nonce_field( 'pv_save_story_cfg' ); ?>
				<h2><?php esc_html_e( 'Impostazioni generazione', 'pornovirtuale' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr><th scope="row"><?php esc_html_e( 'Abilita processo automatico', 'pornovirtuale' ); ?></th><td><label><input type="checkbox" name="enabled" value="1" <?php checked( ! empty( $cfg['enabled'] ), true ); ?>> <?php esc_html_e( 'Attiva schedulazione (ogni 5 min)', 'pornovirtuale' ); ?></label></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Elementi per batch', 'pornovirtuale' ); ?></th><td><input type="number" name="batch_size" value="<?php echo esc_attr( $cfg['batch_size'] ); ?>" min="1" max="10"></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Utilizzi max per chiave', 'pornovirtuale' ); ?></th><td><input type="number" name="max_per_key" value="<?php echo esc_attr( $cfg['max_per_key'] ); ?>" min="1"></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Lunghezza storia (parole)', 'pornovirtuale' ); ?></th><td><input type="number" name="min_words" value="<?php echo esc_attr( $cfg['min_words'] ); ?>" min="200" style="width:80px"> - <input type="number" name="max_words" value="<?php echo esc_attr( $cfg['max_words'] ); ?>" min="300" style="width:80px"></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Modello Groq', 'pornovirtuale' ); ?></th><td><input type="text" name="model" class="regular-text" value="<?php echo esc_attr( $cfg['model'] ); ?>"></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Temperatura', 'pornovirtuale' ); ?></th><td><input type="number" step="0.1" min="0" max="1" name="temperature" value="<?php echo esc_attr( $cfg['temperature'] ); ?>" style="width:80px"></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Tentativi max', 'pornovirtuale' ); ?></th><td><input type="number" name="retry_attempts" value="<?php echo esc_attr( $cfg['retry_attempts'] ); ?>" min="1" max="5"></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Timeout (secondi)', 'pornovirtuale' ); ?></th><td><input type="number" name="timeout" value="<?php echo esc_attr( $cfg['timeout'] ); ?>" min="30"></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Salta se già presente storia', 'pornovirtuale' ); ?></th><td><label><input type="checkbox" name="skip_if_exists" value="1" <?php checked( ! empty( $cfg['skip_if_exists'] ), true ); ?>> Sì</label></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Aggiungi nota editoriale', 'pornovirtuale' ); ?></th><td><label><input type="checkbox" name="add_note" value="1" <?php checked( ! empty( $cfg['add_note'] ), true ); ?>> Sì</label></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'URL nota editoriale', 'pornovirtuale' ); ?></th><td><input type="url" name="edit_note_url" class="regular-text" value="<?php echo esc_attr( $cfg['edit_note_url'] ); ?>"></td></tr>
				</table>
				<?php submit_button( __( 'Salva configurazione', 'pornovirtuale' ), 'primary', 'pv_save_story_cfg' ); ?>
			</form>

			<h2><?php esc_html_e( 'Gestione coda', 'pornovirtuale' ); ?></h2>
			<form method="post" action="" style="display:inline-block;"><?php wp_nonce_field( 'pv_build_and_process' ); ?><?php submit_button( __( 'Costruisci e Elabora', 'pornovirtuale' ), 'primary', 'pv_build_and_process', false ); ?></form>
			<form method="post" action="" style="display:inline-block;"><?php wp_nonce_field( 'pv_pause' ); ?><?php submit_button( __( 'Metti in pausa', 'pornovirtuale' ), 'secondary', 'pv_pause', false ); ?></form>
			<form method="post" action="" style="display:inline-block;"><?php wp_nonce_field( 'pv_resume' ); ?><?php submit_button( __( 'Riprendi', 'pornovirtuale' ), 'secondary', 'pv_resume', false ); ?></form>
			<form method="post" action="" style="display:inline-block;"><?php wp_nonce_field( 'pv_clear_queue' ); ?><?php submit_button( __( 'Svuota coda', 'pornovirtuale' ), 'delete', 'pv_clear_queue', false ); ?></form>

			<h2><?php esc_html_e( 'Log recente', 'pornovirtuale' ); ?></h2>
			<?php if ( empty( $log ) ) : ?><p><?php esc_html_e( 'Nessun log.', 'pornovirtuale' ); ?></p><?php else : ?><pre style="background:#fff;padding:10px;overflow:auto;max-height:400px;"><?php echo esc_html( print_r( array_slice( $log, 0, 10 ), true ) ); ?></pre><?php endif; ?>
		</div>
		<?php
	}
}

add_filter( 'cron_schedules', array( 'PV_Story_Generator', 'cron_intervals' ) );
PV_Story_Generator::init();

// Ensure defaults exist
add_action('init', function(){
	$cfg = get_option(PV_Story_Generator::OPTION_CFG, array());
	if(!is_array($cfg) || empty($cfg)){
		update_option(PV_Story_Generator::OPTION_CFG, PV_Story_Generator::defaults());
	}
}, 1);
