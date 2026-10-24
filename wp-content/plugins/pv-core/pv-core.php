<?php
/**
 * Plugin Name:       PV Core
 * Plugin URI:        https://pornovirtuale.com
 * Description:       Nucleo funzionale di Pornovirtuale: tipi di contenuto (stanze, video, modelli), player con embed affiliate, verifica d'eta, GDPR, SEO e redirect.
 * Version:           2.0.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Pornovirtuale
 * Text Domain:       pornovirtuale
 * Domain Path:       /languages
 *
 * @package Pornovirtuale
 */

defined( 'ABSPATH' ) || exit;

define( 'PV_CORE_VERSION', '2.0.0' );
define( 'PV_CORE_FILE', __FILE__ );
define( 'PV_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'PV_CORE_URL', plugin_dir_url( __FILE__ ) );

require_once PV_CORE_DIR . 'includes/class-pv-settings.php';
require_once PV_CORE_DIR . 'includes/class-pv-cpt.php';
require_once PV_CORE_DIR . 'includes/class-pv-player.php';
require_once PV_CORE_DIR . 'includes/class-pv-age.php';
require_once PV_CORE_DIR . 'includes/class-pv-embed.php';
require_once PV_CORE_DIR . 'includes/class-pv-schema.php';
require_once PV_CORE_DIR . 'includes/class-pv-gdpr.php';
require_once PV_CORE_DIR . 'includes/class-pv-contact.php';
require_once PV_CORE_DIR . 'includes/class-pv-redirects.php';
require_once PV_CORE_DIR . 'includes/class-pv-admin.php';

register_activation_hook( __FILE__, array( 'PV_Settings', 'install' ) );

/**
 * Caricamento traduzione.
 */
function pv_core_load_textdomain() {
	load_plugin_textdomain( 'pornovirtuale', false, dirname( plugin_basename( PV_CORE_FILE ) ) . '/languages' );
}
add_action( 'init', 'pv_core_load_textdomain' );

/**
 * Indirizzo email di contatto del sito (usato dal form contatti).
 */
function pv_contact_email() {
	return apply_filters( 'pv_contact_email', get_option( 'pv_contact_email', 'webmaster@pornovirtuale.com' ) );
}
require_once PV_CORE_DIR . 'includes/class-pv-importer.php';
require_once PV_CORE_DIR . 'includes/class-pv-automation.php';
require_once PV_CORE_DIR . 'includes/class-pv-dashboard.php';
require_once PV_CORE_DIR . 'includes/class-pv-source-xvideos.php';
require_once PV_CORE_DIR . 'includes/class-pv-search-importer.php';
require_once PV_CORE_DIR . 'includes/class-pv-bulk-importer.php';
