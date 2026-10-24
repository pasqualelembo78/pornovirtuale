<?php
/**
 * Tipi di contenuto e tassonomie.
 *
 * @package Pornovirtuale
 */

defined( 'ABSPATH' ) || exit;

class PV_CPT {

	/**
	 * Registra tipi di contenuto e tassonomie.
	 */
	public static function register() {
		register_post_type(
			'stanza',
			array(
				'labels'       => array(
					'name'          => __( 'Stanze live', 'pornovirtuale' ),
					'singular_name' => __( 'Stanza', 'pornovirtuale' ),
					'add_new_item'  => __( 'Aggiungi stanza', 'pornovirtuale' ),
					'edit_item'     => __( 'Modifica stanza', 'pornovirtuale' ),
					'search_items'  => __( 'Cerca stanza', 'pornovirtuale' ),
					'not_found'     => __( 'Nessuna stanza', 'pornovirtuale' ),
					'menu_name'     => __( 'Stanze', 'pornovirtuale' ),
				),
				'public'       => true,
				'has_archive'  => 'stanze-live',
				'menu_icon'    => 'dashicons-video-alt3',
				'menu_position' => 21,
				'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'comments' ),
				'rewrite'      => array( 'slug' => 'stanza', 'with_front' => false ),
				'show_in_rest' => true,
			)
		);

		register_post_type(
			'video',
			array(
				'labels'       => array(
					'name'          => __( 'Video VR', 'pornovirtuale' ),
					'singular_name' => __( 'Video VR', 'pornovirtuale' ),
					'add_new_item'  => __( 'Aggiungi video', 'pornovirtuale' ),
					'edit_item'     => __( 'Modifica video', 'pornovirtuale' ),
					'not_found'     => __( 'Nessun video', 'pornovirtuale' ),
					'menu_name'     => __( 'Video VR', 'pornovirtuale' ),
				),
				'public'       => true,
				'has_archive'  => 'video-vr',
				'menu_icon'    => 'dashicons-format-video',
				'menu_position' => 22,
				'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'comments' ),
				'rewrite'      => array( 'slug' => 'video', 'with_front' => false ),
				'show_in_rest' => true,
			)
		);

		register_post_type(
			'modello',
			array(
				'labels'       => array(
					'name'          => __( 'Modelli', 'pornovirtuale' ),
					'singular_name' => __( 'Modello', 'pornovirtuale' ),
					'add_new_item'  => __( 'Aggiungi modello', 'pornovirtuale' ),
					'edit_item'     => __( 'Modifica modello', 'pornovirtuale' ),
					'not_found'     => __( 'Nessun modello', 'pornovirtuale' ),
					'menu_name'     => __( 'Modelli', 'pornovirtuale' ),
				),
				'public'       => true,
				'has_archive'  => 'modelli',
				'menu_icon'    => 'dashicons-groups',
				'menu_position' => 23,
				'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail' ),
				'rewrite'      => array( 'slug' => 'modello', 'with_front' => false ),
				'show_in_rest' => true,
			)
		);

		register_taxonomy(
			'pv_categoria',
			array( 'stanza', 'video', 'modello' ),
			array(
				'labels'            => array(
					'name'          => __( 'Categorie', 'pornovirtuale' ),
					'singular_name' => __( 'Categoria', 'pornovirtuale' ),
				),
				'hierarchical'      => true,
				'public'            => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'rewrite'           => array( 'slug' => 'categoria', 'with_front' => false ),
			)
		);

		register_taxonomy(
			'pv_tipologia',
			array( 'stanza' ),
			array(
				'labels'            => array(
					'name'          => __( 'Tipologie', 'pornovirtuale' ),
					'singular_name' => __( 'Tipologia', 'pornovirtuale' ),
				),
				'hierarchical'      => true,
				'public'            => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'rewrite'           => array( 'slug' => 'tipologia', 'with_front' => false ),
			)
		);

		register_taxonomy(
			'pv_fonte',
			array( 'stanza', 'video' ),
			array(
				'labels'            => array(
					'name'          => __( 'Fonti', 'pornovirtuale' ),
					'singular_name' => __( 'Fonte', 'pornovirtuale' ),
				),
				'hierarchical'      => false,
				'public'            => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'rewrite'           => array( 'slug' => 'fonte', 'with_front' => false ),
			)
		);
	}

	/**
	 * Metabox per i campi di stanza e video.
	 */
	public static function add_meta_boxes() {
		add_meta_box( 'pv_stanza', __( 'Player e affiliate', 'pornovirtuale' ), array( __CLASS__, 'render_stanza_box' ), 'stanza', 'normal', 'high' );
		add_meta_box( 'pv_video', __( 'Player e sorgente video', 'pornovirtuale' ), array( __CLASS__, 'render_video_box' ), 'video', 'normal', 'high' );
		add_meta_box( 'pv_modello', __( 'Profilo esterno', 'pornovirtuale' ), array( __CLASS__, 'render_modello_box' ), 'modello', 'side', 'default' );
	}

	/**
	 * Salvataggio dei campi.
	 */
	public static function save_meta( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! isset( $_POST['pv_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['pv_nonce'] ) ), 'pv_save_meta' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$fields = array(
			'pv_embed_provider' => 'sanitize_text_field',
			'pv_embed_room'     => 'sanitize_text_field',
			'pv_embed_url'      => 'esc_url_raw',
			'pv_embed_code'     => 'wp_kses_post',
			'pv_chat_url'       => 'esc_url_raw',
			'pv_model_name'     => 'sanitize_text_field',
			'pv_viewers'        => 'intval',
			'pv_duration'       => 'sanitize_text_field',
			'pv_resolution'     => 'sanitize_text_field',
			'pv_source'         => 'sanitize_text_field',
			'pv_vr_mode'        => 'intval',
			'pv_is_vip'         => 'intval',
			'pv_external_url'   => 'esc_url_raw',
			'pv_affiliate_url'  => 'esc_url_raw',
		);

		foreach ( $fields as $key => $sanitizer ) {
			if ( ! isset( $_POST[ $key ] ) ) {
				continue;
			}

			$value = wp_unslash( $_POST[ $key ] );
			$value = call_user_func( $sanitizer, $value );

			if ( '' === $value || 0 === $value ) {
				delete_post_meta( $post_id, '_' . $key );
			} else {
				update_post_meta( $post_id, '_' . $key, $value );
			}
		}
	}

	/**
	 * Metabox stanza.
	 */
	public static function render_stanza_box( $post ) {
		wp_nonce_field( 'pv_save_meta', 'pv_nonce' );
		$get = function ( $k ) use ( $post ) {
			return get_post_meta( $post->ID, '_' . $k, true );
		};
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Provider', 'pornovirtuale' ); ?></th>
				<td>
					<select name="pv_embed_provider" class="widefat">
						<?php foreach ( array( 'chaturbate', 'stripchat', 'xcams', 'custom' ) as $p ) : ?>
							<option value="<?php echo esc_attr( $p ); ?>" <?php selected( $get( 'embed_provider' ), $p ); ?>><?php echo esc_html( $p ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Room / model', 'pornovirtuale' ); ?></th>
				<td><input type="text" name="pv_embed_room" class="widefat" value="<?php echo esc_attr( $get( 'embed_room' ) ); ?>" placeholder="es. modellavr1"><p class="description"><?php esc_html_e( 'Usato per comporre l\'URL del player (es. chaturbate:modellavr1).', 'pornovirtuale' ); ?></p></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'URL embed diretto', 'pornovirtuale' ); ?></th>
				<td><input type="url" name="pv_embed_url" class="widefat" value="<?php echo esc_attr( $get( 'embed_url' ) ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Codice embed personalizzato', 'pornovirtuale' ); ?></th>
				<td><textarea name="pv_embed_code" rows="4" class="widefat code"><?php echo esc_textarea( $get( 'embed_code' ) ); ?></textarea></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Link chat / profilo', 'pornovirtuale' ); ?></th>
				<td><input type="url" name="pv_chat_url" class="widefat" value="<?php echo esc_attr( $get( 'chat_url' ) ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Nome modello', 'pornovirtuale' ); ?></th>
				<td><input type="text" name="pv_model_name" class="widefat" value="<?php echo esc_attr( $get( 'model_name' ) ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Spettatori', 'pornovirtuale' ); ?></th>
				<td><input type="number" name="pv_viewers" value="<?php echo esc_attr( $get( 'viewers' ) ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Contenuto VIP', 'pornovirtuale' ); ?></th>
				<td><label><input type="checkbox" name="pv_is_vip" value="1" <?php checked( (int) $get( 'is_vip' ), 1 ); ?>> <?php esc_html_e( 'Richiede abbonamento VIP', 'pornovirtuale' ); ?></label></td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Metabox video.
	 */
	public static function render_video_box( $post ) {
		wp_nonce_field( 'pv_save_meta', 'pv_nonce' );
		$get = function ( $k ) use ( $post ) {
			return get_post_meta( $post->ID, '_' . $k, true );
		};
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Sorgente', 'pornovirtuale' ); ?></th>
				<td>
					<select name="pv_source" class="widefat">
						<?php foreach ( array( 'embed', 'locale', 'hubtraffic', 'pornhub', 'youporn', 'redtube', 'xvideos', 'custom' ) as $p ) : ?>
							<option value="<?php echo esc_attr( $p ); ?>" <?php selected( $get( 'source' ), $p ); ?>><?php echo esc_html( $p ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'URL video / embed', 'pornovirtuale' ); ?></th>
				<td><input type="url" name="pv_embed_url" class="widefat" value="<?php echo esc_attr( $get( 'embed_url' ) ); ?>" placeholder="https://.../video.mp4 oppure URL pagina embed"></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Codice embed personalizzato', 'pornovirtuale' ); ?></th>
				<td><textarea name="pv_embed_code" rows="4" class="widefat code"><?php echo esc_textarea( $get( 'embed_code' ) ); ?></textarea></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Durata', 'pornovirtuale' ); ?></th>
				<td><input type="text" name="pv_duration" value="<?php echo esc_attr( $get( 'duration' ) ); ?>" placeholder="12:34"></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Risoluzione', 'pornovirtuale' ); ?></th>
				<td><input type="text" name="pv_resolution" value="<?php echo esc_attr( $get( 'resolution' ) ); ?>" placeholder="4K / 180 / 360"></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Vista VR', 'pornovirtuale' ); ?></th>
				<td>
					<select name="pv_vr_mode" class="widefat">
						<option value="0" <?php selected( (int) $get( 'vr_mode' ), 0 ); ?>><?php esc_html_e( 'Normale', 'pornovirtuale' ); ?></option>
						<option value="1" <?php selected( (int) $get( 'vr_mode' ), 1 ); ?>><?php esc_html_e( '180 stereo', 'pornovirtuale' ); ?></option>
						<option value="2" <?php selected( (int) $get( 'vr_mode' ), 2 ); ?>><?php esc_html_e( '360 monoscopio', 'pornovirtuale' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Contenuto VIP', 'pornovirtuale' ); ?></th>
				<td><label><input type="checkbox" name="pv_is_vip" value="1" <?php checked( (int) $get( 'is_vip' ), 1 ); ?>> <?php esc_html_e( 'Richiede abbonamento VIP', 'pornovirtuale' ); ?></label></td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Metabox modello.
	 */
	public static function render_modello_box( $post ) {
		wp_nonce_field( 'pv_save_meta', 'pv_nonce' );
		$get = function ( $k ) use ( $post ) {
			return get_post_meta( $post->ID, '_' . $k, true );
		};
		?>
		<table class="form-table" role="presentation">
			<tr><th scope="row"><?php esc_html_e( 'Profilo esterno', 'pornovirtuale' ); ?></th><td><input type="url" name="pv_external_url" class="widefat" value="<?php echo esc_attr( $get( 'external_url' ) ); ?>"></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Link affiliate', 'pornovirtuale' ); ?></th><td><input type="url" name="pv_affiliate_url" class="widefat" value="<?php echo esc_attr( $get( 'affiliate_url' ) ); ?>"></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Nome rete', 'pornovirtuale' ); ?></th><td><input type="text" name="pv_source" class="widefat" value="<?php echo esc_attr( $get( 'source' ) ); ?>"></td></tr>
		</table>
		<?php
	}
}

add_action( 'init', array( 'PV_CPT', 'register' ) );
add_action( 'add_meta_boxes', array( 'PV_CPT', 'add_meta_boxes' ) );
add_action( 'save_post', array( 'PV_CPT', 'save_meta' ) );
