<?php
/**
 * Shortcode e blocchi dinamici.
 *
 * @package Pornovirtuale
 */

defined( 'ABSPATH' ) || exit;

class PV_Embed {

	/**
	 * Registra gli shortcode.
	 */
	public static function register() {
		add_shortcode( 'pv_stanza', array( __CLASS__, 'sc_stanza' ) );
		add_shortcode( 'pv_video', array( __CLASS__, 'sc_video' ) );
		add_shortcode( 'pv_rooms', array( __CLASS__, 'sc_rooms' ) );
		add_shortcode( 'pv_videos', array( __CLASS__, 'sc_videos' ) );
		add_shortcode( 'pv_models', array( __CLASS__, 'sc_models' ) );
		add_shortcode( 'pv_cam_live', array( __CLASS__, 'sc_cam_live' ) );
		add_shortcode( 'pv_articles', array( __CLASS__, 'sc_articles' ) );
	}

	/**
	 * Player di una stanza.
	 */
	public static function sc_stanza( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'     => 0,
				'width'  => '100%',
				'vr'     => null,
			),
			$atts,
			'pv_stanza'
		);

		$post_id = (int) $atts['id'];

		if ( ! $post_id ) {
			return '';
		}

		$poster = get_the_post_thumbnail_url( $post_id, 'pv-card' );
		$title  = get_the_title( $post_id );
		$code   = get_post_meta( $post_id, '_pv_embed_code', true );
		$vr     = null === $atts['vr'] ? (int) get_post_meta( $post_id, '_pv_vr_mode', true ) : (int) $atts['vr'];

		return PV_Player::render(
			array(
				'url'    => PV_Player::stanza_url( $post_id ),
				'code'   => $code,
				'poster' => $poster,
				'title'  => $title,
				'vr'     => $vr,
			)
		);
	}

	/**
	 * Player di un video.
	 */
	public static function sc_video( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'    => 0,
				'width' => '100%',
			),
			$atts,
			'pv_video'
		);

		$post_id = (int) $atts['id'];

		if ( ! $post_id ) {
			return '';
		}

		$poster = get_the_post_thumbnail_url( $post_id, 'pv-card' );
		$title  = get_the_title( $post_id );
		$code   = get_post_meta( $post_id, '_pv_embed_code', true );
		$url    = PV_Player::video_url( $post_id );
		$source = get_post_meta( $post_id, '_pv_source', true );
		$vr     = (int) get_post_meta( $post_id, '_pv_vr_mode', true );

		// Video locale: uso il tag <video> con poster.
		if ( 'locale' === $source && $url && ! $code ) {
			$html  = '<div class="pv-player pv-player--video">';
			$html .= '<video controls preload="none" poster="' . esc_url( $poster ) . '" playsinline';
			$html .= ' src="' . esc_url( $url ) . '"></video>';
			$html .= '</div>';

			return $html;
		}

		return PV_Player::render(
			array(
				'url'    => $url,
				'code'   => $code,
				'poster' => $poster,
				'title'  => $title,
				'vr'     => $vr,
			)
		);
	}

	/**
	 * Griglia di stanze live.
	 */
	public static function sc_rooms( $atts ) {
		$atts = shortcode_atts(
			array(
				'number'   => 12,
				'categoria' => '',
				'tipologia' => '',
				'layout'   => 'grid',
				'title'    => '',
			),
			$atts,
			'pv_rooms'
		);

		$args = array(
			'post_type'      => 'stanza',
			'post_status'    => 'publish',
			'posts_per_page' => (int) $atts['number'],
			'meta_key'       => '_pv_viewers',
			'orderby'        => 'meta_value_num',
			'order'          => 'DESC',
		);

		$tax_query = array();

		if ( $atts['categoria'] ) {
			$tax_query[] = array(
				'taxonomy' => 'pv_categoria',
				'field'    => 'slug',
				'terms'    => array_map( 'trim', explode( ',', $atts['categoria'] ) ),
			);
		}

		if ( $atts['tipologia'] ) {
			$tax_query[] = array(
				'taxonomy' => 'pv_tipologia',
				'field'    => 'slug',
				'terms'    => array_map( 'trim', explode( ',', $atts['tipologia'] ) ),
			);
		}

		if ( $tax_query ) {
			$args['tax_query'] = $tax_query;
		}

		$query = new WP_Query( $args );

		if ( ! $query->have_posts() ) {
			return '';
		}

		$html = '';

		if ( $atts['title'] ) {
			$html .= '<h2 class="pv-section-title">' . esc_html( $atts['title'] ) . '</h2>';
		}

		$html .= '<div class="pv-grid pv-grid--' . esc_attr( $atts['layout'] ) . '">';

		while ( $query->have_posts() ) {
			$query->the_post();
			$html .= self::room_card( get_the_ID() );
		}

		$html .= '</div>';

		wp_reset_postdata();

		return $html;
	}

	/**
	 * Card di una stanza.
	 */
	public static function room_card( $post_id ) {
		$title   = get_the_title( $post_id );
		$poster  = get_the_post_thumbnail_url( $post_id, 'pv-card' );
		$viewers = (int) get_post_meta( $post_id, '_pv_viewers', true );
		$model   = get_post_meta( $post_id, '_pv_model_name', true );
		$is_vip  = (int) get_post_meta( $post_id, '_pv_is_vip', true );
		$terms   = wp_get_post_terms( $post_id, 'pv_categoria', array( 'fields' => 'names' ) );

		$html  = '<article class="pv-card' . ( $is_vip ? ' pv-card--vip' : '' ) . '">';
		$html .= '<a class="pv-card__media" href="' . esc_url( get_permalink( $post_id ) ) . '" title="' . esc_attr( $title ) . '">';

		if ( $poster ) {
			$html .= '<img src="' . esc_url( $poster ) . '" alt="' . esc_attr( $title ) . '" loading="lazy" decoding="async">';
		} else {
			$html .= '<span class="pv-card__placeholder" aria-hidden="true"></span>';
		}

		$html .= '<span class="pv-card__badge">LIVE</span>';

		if ( $viewers ) {
			$html .= '<span class="pv-card__viewers">' . esc_html( number_format_i18n( $viewers ) ) . '</span>';
		}

		$html .= '</a>';
		$html .= '<div class="pv-card__body">';
		$html .= '<h3 class="pv-card__title"><a href="' . esc_url( get_permalink( $post_id ) ) . '">' . esc_html( $title ) . '</a></h3>';

		if ( $model ) {
			$html .= '<p class="pv-card__model">' . esc_html( $model ) . '</p>';
		}

		if ( $terms && ! is_wp_error( $terms ) ) {
			$html .= '<p class="pv-card__terms">' . esc_html( implode( ' · ', $terms ) ) . '</p>';
		}

		if ( $is_vip ) {
			$html .= '<p class="pv-card__vip">' . esc_html__( 'VIP', 'pornovirtuale' ) . '</p>';
		}

		$html .= '</div></article>';

		return $html;
	}

	/**
	 * Griglia di video.
	 */
	public static function sc_videos( $atts ) {
		$atts = shortcode_atts(
			array(
				'number'    => 12,
				'categoria' => '',
				'layout'    => 'grid',
				'title'     => '',
			),
			$atts,
			'pv_videos'
		);

		$args = array(
			'post_type'      => 'video',
			'post_status'    => 'publish',
			'posts_per_page' => (int) $atts['number'],
		);

		if ( $atts['categoria'] ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'pv_categoria',
					'field'    => 'slug',
					'terms'    => array_map( 'trim', explode( ',', $atts['categoria'] ) ),
				),
			);
		}

		$query = new WP_Query( $args );

		if ( ! $query->have_posts() ) {
			return '';
		}

		$html = '';

		if ( $atts['title'] ) {
			$html .= '<h2 class="pv-section-title">' . esc_html( $atts['title'] ) . '</h2>';
		}

		$html .= '<div class="pv-grid pv-grid--' . esc_attr( $atts['layout'] ) . '">';

		while ( $query->have_posts() ) {
			$query->the_post();
			$id      = get_the_ID();
			$poster  = get_the_post_thumbnail_url( $id, 'pv-card' );
			$title   = get_the_title( $id );
			$duration = get_post_meta( $id, '_pv_duration', true );
			$res      = get_post_meta( $id, '_pv_resolution', true );
			$vr       = (int) get_post_meta( $id, '_pv_vr_mode', true );

			$html .= '<article class="pv-card pv-card--video">';
			$html .= '<a class="pv-card__media" href="' . esc_url( get_permalink( $id ) ) . '" title="' . esc_attr( $title ) . '">';

			if ( $poster ) {
				$html .= '<img src="' . esc_url( $poster ) . '" alt="' . esc_attr( $title ) . '" loading="lazy" decoding="async">';
			} else {
				$html .= '<span class="pv-card__placeholder" aria-hidden="true"></span>';
			}

			$html .= '<span class="pv-card__badge">VR</span>';

			if ( $duration ) {
				$html .= '<span class="pv-card__duration">' . esc_html( $duration ) . '</span>';
			}

			$html .= '</a><div class="pv-card__body">';
			$html .= '<h3 class="pv-card__title"><a href="' . esc_url( get_permalink( $id ) ) . '">' . esc_html( $title ) . '</a></h3>';

			if ( $res ) {
				$html .= '<p class="pv-card__meta">' . esc_html( $res ) . ( $vr ? ' · ' . esc_html__( 'Visione VR', 'pornovirtuale' ) : '' ) . '</p>';
			}

			$html .= '</div></article>';
		}

		$html .= '</div>';

		wp_reset_postdata();

		return $html;
	}

	/**
	 * Griglia di modelli.
	 */
	public static function sc_models( $atts ) {
		$atts = shortcode_atts(
			array(
				'number' => 12,
				'title'  => '',
			),
			$atts,
			'pv_models'
		);

		$query = new WP_Query(
			array(
				'post_type'      => 'modello',
				'post_status'    => 'publish',
				'posts_per_page' => (int) $atts['number'],
			)
		);

		if ( ! $query->have_posts() ) {
			return '';
		}

		$html = '';

		if ( $atts['title'] ) {
			$html .= '<h2 class="pv-section-title">' . esc_html( $atts['title'] ) . '</h2>';
		}

		$html .= '<div class="pv-grid pv-grid--models">';

		while ( $query->have_posts() ) {
			$query->the_post();
			$id     = get_the_ID();
			$poster = get_the_post_thumbnail_url( $id, 'pv-card' );
			$url    = get_post_meta( $id, '_pv_affiliate_url', true );
			$title  = get_the_title( $id );

			$html .= '<article class="pv-card pv-card--model">';
			$html .= '<a class="pv-card__media" href="' . esc_url( $url ? $url : get_permalink( $id ) ) . '" title="' . esc_attr( $title ) . '"' . ( $url ? ' rel="nofollow sponsored noopener" target="_blank"' : '' ) . '>';

			if ( $poster ) {
				$html .= '<img src="' . esc_url( $poster ) . '" alt="' . esc_attr( $title ) . '" loading="lazy" decoding="async">';
			} else {
				$html .= '<span class="pv-card__placeholder" aria-hidden="true"></span>';
			}

			$html .= '</a><div class="pv-card__body">';
			$html .= '<h3 class="pv-card__title"><a href="' . esc_url( $url ? $url : get_permalink( $id ) ) . '">' . esc_html( $title ) . '</a></h3>';
			$html .= '</div></article>';
		}

		$html .= '</div>';

		wp_reset_postdata();

		return $html;
	}

	/**
	 * Widget cam affiliate (sorgente live esterna).
	 */
	public static function sc_cam_live( $atts ) {
		$atts = shortcode_atts(
			array(
				'height' => 480,
				'title'  => '',
			),
			$atts,
			'pv_cam_live'
		);

		$widget = PV_Settings::get( 'aff_cam_widget', '' );

		if ( ! $widget ) {
			$room = PV_Settings::get( 'aff_default_room', '' );

			if ( $room && false !== strpos( $room, ':' ) ) {
				list( $provider, $room_name ) = explode( ':', $room, 2 );
				$widget = PV_Player::build_url( $provider, $room_name );
			}
		}

		if ( ! $widget ) {
			return '<div class="pv-cam-placeholder"><p>' . esc_html__( 'Cam live non ancora configurata: inserisci la rete affiliate e la room predefinita nelle impostazioni.', 'pornovirtuale' ) . '</p></div>';
		}

		$html = '<div class="pv-cam-live">';

		if ( $atts['title'] ) {
			$html .= '<h2 class="pv-section-title">' . esc_html( $atts['title'] ) . '</h2>';
		}

		$html .= PV_Player::render(
			array(
				'url'    => $widget,
				'title'  => $atts['title'] ? $atts['title'] : __( 'Cam live', 'pornovirtuale' ),
				'aspect' => '16-9',
			)
		);

		$html .= '</div>';

		return $html;
	}

	/**
	 * Ultimi articoli.
	 */
	public static function sc_articles( $atts ) {
		$atts = shortcode_atts(
			array(
				'number' => 6,
				'title'  => '',
			),
			$atts,
			'pv_articles'
		);

		$query = new WP_Query(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => (int) $atts['number'],
			)
		);

		if ( ! $query->have_posts() ) {
			return '';
		}

		$html = '';

		if ( $atts['title'] ) {
			$html .= '<h2 class="pv-section-title">' . esc_html( $atts['title'] ) . '</h2>';
		}

		$html .= '<div class="pv-grid pv-grid--articles">';

		while ( $query->have_posts() ) {
			$query->the_post();
			$id    = get_the_ID();
			$title = get_the_title( $id );

			$html .= '<article class="pv-card pv-card--article">';
			$html .= '<div class="pv-card__body">';
			$html .= '<p class="pv-card__meta">' . esc_html( get_the_date( 'j F Y' ) ) . '</p>';
			$html .= '<h3 class="pv-card__title"><a href="' . esc_url( get_permalink( $id ) ) . '">' . esc_html( $title ) . '</a></h3>';
			$html .= '<p class="pv-card__excerpt">' . esc_html( wp_trim_words( get_the_excerpt(), 22, '...' ) ) . '</p>';
			$html .= '</div></article>';
		}

		$html .= '</div>';

		wp_reset_postdata();

		return $html;
	}
}

add_action( 'init', array( 'PV_Embed', 'register' ) );
