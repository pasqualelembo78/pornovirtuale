<?php
/**
 * Redirect 301 gestiti da WordPress.
 *
 * @package Pornovirtuale
 */

defined( 'ABSPATH' ) || exit;

class PV_Redirects {

	const OPTION = 'pv_redirects';

	/**
	 * Recupera la mappa dei redirect.
	 */
	public static function map() {
		$map = get_option( self::OPTION, array() );

		return is_array( $map ) ? $map : array();
	}

	/**
	 * Salva la mappa dei redirect.
	 */
	public static function set_map( $map ) {
		update_option( self::OPTION, (array) $map );
	}

	/**
	 * Esegue il redirect se il percorso e' mappato.
	 */
	public static function maybe_redirect() {
		if ( is_admin() || wp_doing_ajax() ) {
			return;
		}

		$path = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';

		if ( ! $path ) {
			return;
		}

		$path = strtok( $path, '?' );
		$path = trim( $path, '/' );

		if ( ! $path ) {
			return;
		}

		$map = self::map();

		if ( isset( $map[ $path ] ) ) {
			wp_safe_redirect( home_url( $map[ $path ] ), 301 );
			exit;
		}

		// Le vecchie URL di /video/ e /stanze/ che non esistono più non devono
		// finire in 404: le mandiamo all'archivio della sezione. I contenuti
		// reali non sono 404 e quindi non vengono toccati.
		if ( is_404() ) {
			$utm = array_intersect_key(
				wp_unslash( $_GET ),
				array_flip( array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term' ) )
			);

			if ( 0 === strpos( $path, 'video/' ) ) {
				$target = home_url( '/video-vr/' );
				if ( $utm ) {
					$target = add_query_arg( $utm, $target );
				}
				wp_safe_redirect( $target, 301 );
				exit;
			}
			if ( 0 === strpos( $path, 'stanze/' ) ) {
				$target = home_url( '/stanze/' );
				if ( $utm ) {
					$target = add_query_arg( $utm, $target );
				}
				wp_safe_redirect( $target, 301 );
				exit;
			}
		}
	}

	/**
	 * Aggiunge una pagina di redirect alla mappa.
	 */
	public static function add( $from, $to ) {
		$map = self::map();
		$map[ trim( $from, '/' ) ] = '/' . ltrim( $to, '/' );
		self::set_map( $map );
	}
}

add_action( 'template_redirect', array( 'PV_Redirects', 'maybe_redirect' ), 1 );
