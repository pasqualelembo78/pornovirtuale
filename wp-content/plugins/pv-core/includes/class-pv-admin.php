<?php
/**
 * Bacheca, colonne admin e hardening.
 *
 * @package Pornovirtuale
 */

defined( 'ABSPATH' ) || exit;

class PV_Admin {

	/**
	 * Widget nella bacheca.
	 */
	public static function dashboard_widget() {
		global $wpdb;

		$rooms = wp_count_posts( 'stanza' );
		$videos = wp_count_posts( 'video' );
		$models = wp_count_posts( 'modello' );

		$verified = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}pv_age_log WHERE result IN ('verified','accepted')" );
		$rejected = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}pv_age_log WHERE result IN ('rejected','invalid_state')" );
		$mode     = PV_Age::mode();
		?>
		<div class="pv-dash">
			<p>
				<strong><?php esc_html_e( 'Stanze live', 'pornovirtuale' ); ?>:</strong> <?php echo esc_html( number_format_i18n( $rooms->publish ) ); ?> &nbsp;|&nbsp;
				<strong><?php esc_html_e( 'Video VR', 'pornovirtuale' ); ?>:</strong> <?php echo esc_html( number_format_i18n( $videos->publish ) ); ?> &nbsp;|&nbsp;
				<strong><?php esc_html_e( 'Modelli', 'pornovirtuale' ); ?>:</strong> <?php echo esc_html( number_format_i18n( $models->publish ) ); ?>
			</p>
			<p>
				<strong><?php esc_html_e( 'Verifica eta', 'pornovirtuale' ); ?>:</strong> <?php echo esc_html( $mode ); ?> &nbsp;|&nbsp;
				<?php esc_html_e( 'Verifiche riuscite', 'pornovirtuale' ); ?>: <?php echo esc_html( number_format_i18n( $verified ) ); ?> &nbsp;|&nbsp;
				<?php esc_html_e( 'Respinte', 'pornovirtuale' ); ?>: <?php echo esc_html( number_format_i18n( $rejected ) ); ?>
			</p>
			<p>
				<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=stanza' ) ); ?>"><?php esc_html_e( 'Gestisci stanze', 'pornovirtuale' ); ?></a>
				<a class="button" href="<?php echo esc_url( admin_url( 'options-general.php?page=pv-settings' ) ); ?>"><?php esc_html_e( 'Impostazioni', 'pornovirtuale' ); ?></a>
			</p>
			<?php if ( 'declaration' === $mode ) : ?>
				<p style="color:#b32d2e"><strong><?php esc_html_e( 'Attenzione', 'pornovirtuale' ); ?>:</strong> <?php esc_html_e( 'la sola autodichiarazione non e\' conforme all\'art. 13-bis D.L. Caivano / Delibera AGCOM 96/25. Configura un provider certificato nella sezione Verifica eta.', 'pornovirtuale' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Registra il widget.
	 */
	public static function add_widget() {
		wp_add_dashboard_widget( 'pv_dashboard', __( 'Pornovirtuale - panoramica', 'pornovirtuale' ), array( __CLASS__, 'dashboard_widget' ) );
	}

	/**
	 * Colonne admin per le stanze.
	 */
	public static function stanza_columns( $columns ) {
		$columns['pv_provider'] = __( 'Provider', 'pornovirtuale' );
		$columns['pv_viewers']  = __( 'Spettatori', 'pornovirtuale' );
		$columns['pv_vip']      = __( 'VIP', 'pornovirtuale' );

		return $columns;
	}

	/**
	 * Contenuto colonne stanza.
	 */
	public static function stanza_column( $column, $post_id ) {
		if ( 'pv_provider' === $column ) {
			echo esc_html( get_post_meta( $post_id, '_pv_embed_provider', true ) );
		} elseif ( 'pv_viewers' === $column ) {
			echo esc_html( number_format_i18n( (int) get_post_meta( $post_id, '_pv_viewers', true ) ) );
		} elseif ( 'pv_vip' === $column ) {
			echo (int) get_post_meta( $post_id, '_pv_is_vip', true ) ? '&#10003;' : '&mdash;';
		}
	}

	/**
	 * Colonne admin per i video.
	 */
	public static function video_columns( $columns ) {
		$columns['pv_source']    = __( 'Fonte', 'pornovirtuale' );
		$columns['pv_duration'] = __( 'Durata', 'pornovirtuale' );
		$columns['pv_vr']       = __( 'VR', 'pornovirtuale' );

		return $columns;
	}

	/**
	 * Contenuto colonne video.
	 */
	public static function video_column( $column, $post_id ) {
		if ( 'pv_source' === $column ) {
			echo esc_html( get_post_meta( $post_id, '_pv_source', true ) );
		} elseif ( 'pv_duration' === $column ) {
			echo esc_html( get_post_meta( $post_id, '_pv_duration', true ) );
		} elseif ( 'pv_vr' === $column ) {
			$vr = (int) get_post_meta( $post_id, '_pv_vr_mode', true );
			echo $vr ? esc_html( $vr ) : '&mdash;';
		}
	}

	/**
	 * Disattiva XML-RPC se richiesto.
	 */
	public static function xmlrpc() {
		if ( PV_Settings::get( 'enable_xmlrpc', 0 ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Nasconde l'errore di login.
	 */
	public static function login_errors() {
		return __( 'Nome utente o password non corretti.', 'pornovirtuale' );
	}

	/**
	 * Previene l'enumerazione degli autori.
	 */
	public static function disable_author_enum( $redirect ) {
		if ( is_author() && ! is_user_logged_in() ) {
			wp_safe_redirect( home_url( '/' ), 301 );
			exit;
		}

		return $redirect;
	}

	/**
	 * Rimuove il link "?author=" dai redirect.
	 */
	public static function author_redirect( $redirect_url ) {
		if ( is_author() ) {
			return home_url( '/' );
		}

		return $redirect_url;
	}
}

add_action( 'wp_dashboard_setup', array( 'PV_Admin', 'add_widget' ) );
add_filter( 'manage_stanza_posts_columns', array( 'PV_Admin', 'stanza_columns' ) );
add_action( 'manage_stanza_posts_custom_column', array( 'PV_Admin', 'stanza_column' ), 10, 2 );
add_filter( 'manage_video_posts_columns', array( 'PV_Admin', 'video_columns' ) );
add_action( 'manage_video_posts_custom_column', array( 'PV_Admin', 'video_column' ), 10, 2 );
add_filter( 'xmlrpc_enabled', array( 'PV_Admin', 'xmlrpc' ) );
add_filter( 'login_errors', array( 'PV_Admin', 'login_errors' ) );
add_action( 'template_redirect', array( 'PV_Admin', 'disable_author_enum' ) );
add_filter( 'redirect_canonical', array( 'PV_Admin', 'author_redirect' ) );
