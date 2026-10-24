<?php
/**
 * Player: facade, embed affiliate e modalita VR.
 *
 * @package Pornovirtuale
 */

defined( 'ABSPATH' ) || exit;

class PV_Player {

	/**
	 * Template URL per provider.
	 */
	public static function templates() {
		$s = PV_Settings::all();

		return array(
			'chaturbate' => $s['tpl_chaturbate'] ?? 'https://chaturbate.com/{room}/embed/{aff}',
			'stripchat'  => $s['tpl_stripchat'] ?? 'https://stripchat.com/{room}/embed/{aff}',
			'xcams'      => $s['tpl_xcams'] ?? 'https://xcams.com/{room}/embed/{aff}',
		);
	}

	/**
	 * Costruisce l'URL del player da provider + room.
	 */
	public static function build_url( $provider, $room ) {
		$templates = self::templates();

		if ( ! isset( $templates[ $provider ] ) || empty( $room ) ) {
			return '';
		}

		$aff = self::affiliate_id( $provider );
		$url = str_replace( array( '{room}', '{aff}' ), array( rawurlencode( $room ), rawurlencode( $aff ) ), $templates[ $provider ] );

		if ( '' === $aff && false === strpos( $templates[ $provider ], '{aff}' ) ) {
			$url = rtrim( $url, '/' ) . '/';
		}

		return $url;
	}

	/**
	 * ID affiliate per provider.
	 */
	public static function affiliate_id( $provider ) {
		$s = PV_Settings::all();
		$map = array(
			'chaturbate' => $s['aff_chaturbate'],
			'stripchat'  => $s['aff_stripchat'],
			'xcams'      => $s['aff_xcams'],
		);

		return isset( $map[ $provider ] ) ? (string) $map[ $provider ] : '';
	}

	/**
	 * URL del player per una stanza.
	 */
	public static function stanza_url( $post_id ) {
		$code = get_post_meta( $post_id, '_pv_embed_code', true );

		if ( $code ) {
			return '';
		}

		$direct = get_post_meta( $post_id, '_pv_embed_url', true );

		if ( $direct ) {
			return $direct;
		}

		$provider = get_post_meta( $post_id, '_pv_embed_provider', true );
		$room     = get_post_meta( $post_id, '_pv_embed_room', true );

		return self::build_url( $provider, $room );
	}

	/**
	 * URL video per un contenuto video.
	 */
	public static function video_url( $post_id ) {
		$source = get_post_meta( $post_id, '_pv_source', true );
		$direct = get_post_meta( $post_id, '_pv_embed_url', true );

		if ( 'locale' === $source && $direct ) {
			return $direct;
		}

		return $direct;
	}

	/**
	 * Rendering del player.
	 *
	 * @param array $args url, code, poster, title, provider, room, vr, class, aspect, autoplay.
	 */
	public static function render( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'url'      => '',
				'code'     => '',
				'poster'   => '',
				'title'    => '',
				'provider' => '',
				'room'     => '',
				'vr'       => 0,
				'class'    => '',
				'aspect'   => '16-9',
				'autoplay' => null,
				'id'       => '',
			)
		);

		$facade = (bool) PV_Settings::get( 'player_facade', 1 );
		$fullscreen = (bool) PV_Settings::get( 'player_allow_fullscreen', 1 );
		$autoplay = null === $args['autoplay'] ? (bool) PV_Settings::get( 'player_autoplay', 0 ) : (bool) $args['autoplay'];

		$id = $args['id'] ? $args['id'] : 'pv-player-' . wp_unique_id();

		// Codice embed personalizzato: usato cosi' com'e'.
		if ( $args['code'] ) {
			$html  = '<div class="pv-player pv-player--custom ' . esc_attr( $args['class'] ) . '">';
			$html .= do_shortcode( $args['code'] );
			$html .= '</div>';

			return $html;
		}

		$url = $args['url'];

		if ( ! $url && $args['provider'] && $args['room'] ) {
			$url = self::build_url( $args['provider'], $args['room'] );
		}

		if ( ! $url ) {
			return '<div class="pv-player pv-player--empty ' . esc_attr( $args['class'] ) . '"><span>' . esc_html__( 'Player non configurato', 'pornovirtuale' ) . '</span></div>';
		}

		$vr = (int) $args['vr'];

		$html = '<div class="pv-player pv-player--facade is-vr-' . (int) $vr . ' ' . esc_attr( $args['class'] ) . '" data-pv-player="' . esc_attr( $id ) . '">';
		$html .= '<div class="pv-player__frame pv-aspect-' . esc_attr( $args['aspect'] ) . '">';

		// Poster / facciata.
		$html .= '<button type="button" class="pv-player__poster" data-pv-play aria-label="' . esc_attr( $args['title'] ? $args['title'] : __( 'Avvia il player', 'pornovirtuale' ) ) . '">';

		if ( $args['poster'] ) {
			$html .= '<img src="' . esc_url( $args['poster'] ) . '" alt="" loading="lazy" decoding="async">';
		}

		$html .= '<span class="pv-player__play" aria-hidden="true"><svg viewBox="0 0 24 24" width="26" height="26"><path fill="currentColor" d="M8 5v14l11-7z"/></svg></span>';
		$html .= '<span class="pv-player__badge">18+</span>';
		$html .= '</button>';

		// Iframe caricato al clic.
		$html .= '<iframe class="pv-player__iframe" data-pv-src="' . esc_url( $url ) . '" title="' . esc_attr( $args['title'] ) . '" allow="autoplay; fullscreen; encrypted-media; picture-in-picture" allowfullscreen="' . ( $fullscreen ? 'true' : 'false' ) . '" referrerpolicy="strict-origin-when-cross-origin" loading="lazy" frameborder="0"></iframe>';

		// Toggle vista stereo (solo per feed 180).
		if ( 1 === $vr ) {
			$html .= '<button type="button" class="pv-player__vr-toggle" data-pv-vr-toggle aria-pressed="false">' . esc_html__( 'Vista stereo', 'pornovirtuale' ) . '</button>';
		}

		$html .= '</div>';

		// Schermo intero VR (360).
		if ( 2 === $vr ) {
			$html .= '<button type="button" class="pv-player__vr-fs" data-pv-vr-fullscreen>' . esc_html__( 'Schermo intero 360', 'pornovirtuale' ) . '</button>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Registra gli script del player.
	 */
	public static function enqueue() {
		wp_enqueue_script( 'pv-player', PV_CORE_URL . 'assets/js/player.js', array(), PV_CORE_VERSION, true );
	}
}

add_action( 'wp_enqueue_scripts', array( 'PV_Player', 'enqueue' ) );
