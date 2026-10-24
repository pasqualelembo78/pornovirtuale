<?php
/**
 * Form di contatto senza dipendenze esterne.
 *
 * @package Pornovirtuale
 */

defined( 'ABSPATH' ) || exit;

class PV_Contact {

	/**
	 * Shortcode del form.
	 */
	public static function shortcode() {
		ob_start();
		$sent = isset( $_GET['pv_sent'] ) ? sanitize_key( wp_unslash( $_GET['pv_sent'] ) ) : '';
		?>
		<div class="pv-form-wrap">
			<?php if ( 'ok' === $sent ) : ?>
				<p class="pv-notice pv-notice--ok"><?php esc_html_e( 'Messaggio inviato. Ti risponderemo al piu presto.', 'pornovirtuale' ); ?></p>
			<?php elseif ( 'err' === $sent ) : ?>
				<p class="pv-notice pv-notice--err"><?php esc_html_e( 'Invio non riuscito. Riprova piu tardi.', 'pornovirtuale' ); ?></p>
			<?php endif; ?>

			<form class="pv-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="pv_contact">
				<?php wp_nonce_field( 'pv_contact', 'pv_contact_nonce' ); ?>

				<p class="pv-hp" aria-hidden="true">
					<label><?php esc_html_e( 'Non compilare', 'pornovirtuale' ); ?><input type="text" name="pv_website" tabindex="-1" autocomplete="off"></label>
				</p>

				<label for="pv_name"><?php esc_html_e( 'Nome', 'pornovirtuale' ); ?></label>
				<input type="text" id="pv_name" name="pv_name" required>

				<label for="pv_email"><?php esc_html_e( 'Email', 'pornovirtuale' ); ?></label>
				<input type="email" id="pv_email" name="pv_email" required>

				<label for="pv_subject"><?php esc_html_e( 'Oggetto', 'pornovirtuale' ); ?></label>
				<input type="text" id="pv_subject" name="pv_subject">

				<label for="pv_message"><?php esc_html_e( 'Messaggio', 'pornovirtuale' ); ?></label>
				<textarea id="pv_message" name="pv_message" rows="6" required></textarea>

				<button type="submit" class="btn"><?php esc_html_e( 'Invia messaggio', 'pornovirtuale' ); ?></button>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Gestione invio.
	 */
	public static function handle() {
		$nonce = isset( $_POST['pv_contact_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['pv_contact_nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'pv_contact' ) ) {
			wp_safe_redirect( add_query_arg( 'pv_sent', 'err', wp_get_referer() ? wp_get_referer() : home_url( '/' ) ) );
			exit;
		}

		// Honeypot.
		if ( ! empty( $_POST['pv_website'] ) ) {
			wp_safe_redirect( add_query_arg( 'pv_sent', 'ok', wp_get_referer() ? wp_get_referer() : home_url( '/' ) ) );
			exit;
		}

		$name    = sanitize_text_field( wp_unslash( $_POST['pv_name'] ?? '' ) );
		$email   = sanitize_email( wp_unslash( $_POST['pv_email'] ?? '' ) );
		$subject = sanitize_text_field( wp_unslash( $_POST['pv_subject'] ?? '' ) );
		$message = sanitize_textarea_field( wp_unslash( $_POST['pv_message'] ?? '' ) );

		if ( ! $email || ! $message ) {
			wp_safe_redirect( add_query_arg( 'pv_sent', 'err', wp_get_referer() ? wp_get_referer() : home_url( '/' ) ) );
			exit;
		}

		$to      = pv_contact_email();
		$headers = array( 'Reply-To: ' . $name . ' <' . $email . '>' );

		$body = sprintf(
			"Nuovo messaggio dal modulo contatti di %s\n\nNome: %s\nEmail: %s\nOggetto: %s\n\n%s\n",
			get_bloginfo( 'name' ),
			$name,
			$email,
			$subject,
			$message
		);

		$sent = wp_mail( $to, $subject ? '[' . get_bloginfo( 'name' ) . '] ' . $subject : '[' . get_bloginfo( 'name' ) . '] Contatti', $body, $headers );

		wp_safe_redirect( add_query_arg( 'pv_sent', $sent ? 'ok' : 'err', wp_get_referer() ? wp_get_referer() : home_url( '/' ) ) );
		exit;
	}
}

add_shortcode( 'pv_contact_form', array( 'PV_Contact', 'shortcode' ) );
add_action( 'admin_post_nopriv_pv_contact', array( 'PV_Contact', 'handle' ) );
add_action( 'admin_post_pv_contact', array( 'PV_Contact', 'handle' ) );
