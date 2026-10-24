<?php
/**
 * Verifica dell'eta (art. 13-bis D.L. 123/2023 - D.L. Caivano; Delibera AGCOM 96/25/CONS).
 *
 * @package Pornovirtuale
 */

defined( 'ABSPATH' ) || exit;

class PV_Age {

	const COOKIE = 'pv_age_verified';

	/**
	 * Modalita' attuale.
	 */
	public static function mode() {
		return PV_Settings::get( 'age_mode', 'declaration' );
	}

	/**
	 * Firma del token.
	 */
	private static function sign( $payload ) {
		return hash_hmac( 'sha256', $payload, wp_salt( 'nonce' ) );
	}

	/**
	 * Genera un token firmato.
	 */
	public static function make_token( $days = null ) {
		$days = $days ? (int) $days : (int) PV_Settings::get( 'age_cookie_days', 30 );
		$exp  = time() + ( $days * DAY_IN_SECONDS );
		$payload = 'v1|' . $exp . '|' . wp_generate_password( 12, false );

		return $payload . '|' . self::sign( $payload );
	}

	/**
	 * Verifica un token.
	 */
	public static function validate_token( $token ) {
		$parts = explode( '|', (string) $token );

		if ( 4 !== count( $parts ) ) {
			return false;
		}

		list( $v, $exp, $rand, $sig ) = $parts;

		if ( 'v1' !== $v || ! ctype_digit( $exp ) ) {
			return false;
		}

		if ( (int) $exp < time() ) {
			return false;
		}

		$payload = $v . '|' . $exp . '|' . $rand;

		return hash_equals( self::sign( $payload ), $sig );
	}

	/**
	 * L'utente ha superato la verifica?
	 */
	public static function is_verified() {
		if ( 'off' === self::mode() ) {
			return true;
		}

		if ( is_user_logged_in() && current_user_can( 'edit_posts' ) ) {
			return true;
		}

		if ( empty( $_COOKIE[ self::COOKIE ] ) ) {
			return false;
		}

		return self::validate_token( sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) );
	}

	/**
	 * Imposta il cookie di verifica.
	 */
	public static function set_verified( $method = 'declaration' ) {
		$days  = (int) PV_Settings::get( 'age_cookie_days', 30 );
		$token = self::make_token( $days );

		if ( ! headers_sent() ) {
			setcookie(
				self::COOKIE,
				$token,
				array(
					'expires'  => time() + ( $days * DAY_IN_SECONDS ),
					'path'     => '/',
					'domain'   => '',
					'secure'   => is_ssl(),
					'httponly' => true,
					'samesite' => 'Lax',
				)
			);
		}

		$_COOKIE[ self::COOKIE ] = $token;

		self::log( $method, 'verified' );
	}

	/**
	 * Log delle verifiche.
	 */
	public static function log( $method, $result, $provider = '' ) {
		if ( ! PV_Settings::get( 'age_log', 1 ) ) {
			return;
		}

		global $wpdb;

		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 255 ) : '';

		$wpdb->insert(
			$wpdb->prefix . 'pv_age_log',
			array(
				'created_at' => current_time( 'mysql' ),
				'ip_hash'    => $ip ? hash( 'sha256', $ip . wp_salt( 'nonce' ) ) : '',
				'user_agent' => $ua,
				'method'     => substr( (string) $method, 0, 32 ),
				'result'     => substr( (string) $result, 0, 32 ),
				'provider'   => substr( (string) $provider, 0, 64 ),
			)
		);
	}

	/**
	 * Endpoint di richiesta verifica (AJAX).
	 */
	public static function ajax_verify() {
		check_ajax_referer( 'pv_age_verify', 'nonce' );

		$method = isset( $_POST['method'] ) ? sanitize_key( wp_unslash( $_POST['method'] ) ) : 'declaration';

		if ( 'provider' === $method ) {
			$url = PV_Settings::get( 'age_provider_url', '' );

			if ( ! $url ) {
				wp_send_json_error( array( 'message' => __( 'Provider non configurato', 'pornovirtuale' ) ) );
			}

			$state = wp_generate_password( 16, false );
			set_transient( 'pv_age_state_' . $state, 1, 10 * MINUTE_IN_SECONDS );

			$callback = add_query_arg(
				array(
					'pv_age_callback' => '1',
					'state'           => $state,
				),
				home_url( '/' )
			);

			wp_send_json_success(
				array(
					'provider_url' => add_query_arg( 'redirect_uri', rawurlencode( $callback ), $url ),
					'state'        => $state,
				)
			);
		}

		self::set_verified( 'declaration' );
		self::log( 'declaration', 'accepted' );

		wp_send_json_success( array( 'message' => __( 'Verifica completata', 'pornovirtuale' ) ) );
	}

	/**
	 * Callback dal provider di verifica.
	 */
	public static function handle_callback() {
		if ( empty( $_GET['pv_age_callback'] ) ) {
			return;
		}

		$token  = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
		$state  = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
		$key    = (string) PV_Settings::get( 'age_provider_key', '' );
		$back   = home_url( '/' );

		if ( $state && ! get_transient( 'pv_age_state_' . $state ) ) {
			self::log( 'provider', 'invalid_state' );
			wp_safe_redirect( add_query_arg( 'pv_age', 'error', $back ) );
			exit;
		}

		$valid = false;

		if ( $token && $key ) {
			$valid = hash_equals( $key, $token );
		}

		if ( $valid ) {
			self::set_verified( 'provider' );
			self::log( 'provider', 'accepted' );
			wp_safe_redirect( add_query_arg( 'pv_age', 'ok', $back ) );
		} else {
			self::log( 'provider', 'rejected' );
			wp_safe_redirect( add_query_arg( 'pv_age', 'error', $back ) );
		}

		exit;
	}

	/**
	 * Blocca i contenuti premium per chi non ha un account.
	 */
	public static function protect_vip( $content ) {
		if ( ! is_singular() ) {
			return $content;
		}

		$post_id = get_the_ID();

		if ( ! $post_id || ! (int) get_post_meta( $post_id, '_pv_is_vip', true ) ) {
			return $content;
		}

		if ( is_user_logged_in() ) {
			return $content;
		}

		$lock  = '<div class="pv-lock"><h3>' . esc_html__( 'Contenuto riservato agli iscritti VIP', 'pornovirtuale' ) . '</h3>';
		$lock .= '<p>' . esc_html__( 'Accedi o registrati per sbloccare questo contenuto esclusivo.', 'pornovirtuale' ) . '</p>';
		$lock .= '<p><a class="btn" href="' . esc_url( wp_login_url( get_permalink( $post_id ) ) ) . '">' . esc_html__( 'Accedi', 'pornovirtuale' ) . '</a> ';
		$lock .= '<a class="btn btn--ghost" href="' . esc_url( wp_registration_url() ) . '">' . esc_html__( 'Registrati', 'pornovirtuale' ) . '</a></p></div>';

		return $lock . '<div class="pv-locked-content" aria-hidden="true">' . $content . '</div>';
	}
}

add_action( 'wp_ajax_pv_age_verify', array( 'PV_Age', 'ajax_verify' ) );
add_action( 'wp_ajax_nopriv_pv_age_verify', array( 'PV_Age', 'ajax_verify' ) );
add_action( 'template_redirect', array( 'PV_Age', 'handle_callback' ) );
add_filter( 'the_content', array( 'PV_Age', 'protect_vip' ), 20 );
