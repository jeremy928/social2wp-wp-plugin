<?php
/**
 * Plugin Name: Social2WP
 * Plugin URI:  https://social2wp.com
 * Description: Companion plugin for Social2WP — automatically formats and publishes your synced Instagram posts your way.
 * Version:     1.1.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author:      Carroll Wood Integrated Services LLC
 * Author URI:  https://social2wp.com
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: social2wp
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'SOCIAL2WP_VERSION', '1.1.0' );
define( 'SOCIAL2WP_DIR', plugin_dir_path( __FILE__ ) );
define( 'SOCIAL2WP_URL', plugin_dir_url( __FILE__ ) );

require_once SOCIAL2WP_DIR . 'includes/class-settings.php';
require_once SOCIAL2WP_DIR . 'includes/class-formatter.php';
require_once SOCIAL2WP_DIR . 'includes/class-api.php';

register_activation_hook( __FILE__, function () {
    if ( ! get_option( 'social2wp_api_key' ) ) {
        update_option( 'social2wp_api_key', wp_generate_password( 32, false ) );
    }
} );

add_filter( 'plugin_action_links_social2wp/social2wp.php', function ( $links ) {
    array_unshift( $links, '<a href="' . admin_url( 'options-general.php?page=social2wp' ) . '">Settings</a>' );
    return $links;
} );

add_action( 'init', function () {
    new Social2WP_Settings();
    new Social2WP_API();
} );

add_action( 'admin_post_social2wp_connect', function () {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
    check_admin_referer( 'social2wp_connect' );

    $token = wp_generate_password( 32, false );
    update_option( 'social2wp_connect_token', $token );
    update_option( 'social2wp_connect_token_expiry', time() + 3600 );

    wp_redirect( add_query_arg( [
        'site'  => get_site_url(),
        'token' => $token,
    ], 'https://social2wp.com/plugin-connect' ) );
    exit;
} );
