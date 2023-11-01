<?php
/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://iloveimg.com/
 * @since             1.0.1
 * @package           iloveimgwatermark
 *
 * @wordpress-plugin
 * Plugin Name:       Best Watermark - Protect images on your site with iLoveIMG
 * Plugin URI:        https://developer.iloveimg.com/
 * Description:       Protect your site from image theft with our reliable and easy-to-use watermark plugin. Effective protection for your images.
 * Version:           1.0.3
 * Author:            iLoveIMG
 * Author URI:        https://iloveimg.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       iloveimg-watermark
 * Domain Path:       /languages
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

require_once 'admin/class-iloveimg-plugin.php';
require_once 'admin/class-iloveimg-process.php';
require_once 'admin/class-resources.php';
require_once 'admin/class-serializer.php';
require_once 'admin/class-submenu-page.php';
require_once 'admin/class-submenu.php';
require_once 'admin/class-table-media-bulk-optimized.php';

function iLoveIMG_Watermark_custom_admin_settings() {

    $serializer = new iLoveIMG_Watermark_Serializer();
    $serializer->init();

    $plugin = new iLoveIMG_Watermark_Submenu( new iLoveIMG_Watermark_Submenu_Page() );
    $plugin->init();
}
add_action( 'plugins_loaded', 'iLoveIMG_Watermark_custom_admin_settings' );

function iLoveIMG_Watermark_add_plugin_page_settings_link( $links ) {
	$links[] = '<a href="' .
		admin_url( 'admin.php?page=iloveimg-watermark-admin-page' ) .
		'">' . __( 'Settings' ) . '</a>';
    $links[] = '<a href="' .
        admin_url( 'upload.php?page=iloveimg-media-page' ) .
        '">' . __( 'Bulk Optimization' ) . '</a>';
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'iLoveIMG_Watermark_add_plugin_page_settings_link' );

function iLoveIMG_Watermark_activate() {
    add_option( 'iLoveIMG_Watermark_db_version', ILOVE_IMG_WM_COMPRESS_DB_VERSION );

    if ( ! get_option( 'iloveimg_options_watermark' ) ) {
        $iloveimg_thumbnails = array( 'full', 'thumbnail', 'medium', 'medium_large', 'large' );
        if ( ! extension_loaded( 'gd' ) ) {
            $iloveimg_thumbnails = array( 'full' );
        }
        update_option(
            'iloveimg_options_watermark',
            serialize(
                array(
					// 'iloveimg_field_watermark_activated' => 0,
					// 'iloveimg_field_autowatermark' => 1,
					'iloveimg_field_type'     => 'text',
					'iloveimg_field_text'     => 'Sample',
					'iloveimg_field_scale'    => 33,
					'iloveimg_field_opacity'  => 1,
					'iloveimg_field_rotation' => 0,
					'iloveimg_field_position' => 1,
					'iloveimg_field_sizes'    => $iloveimg_thumbnails,

				)
            )
        );
    }
}
register_activation_hook( __FILE__, 'iLoveIMG_Watermark_activate' );

new iLoveIMG_Watermark_Plugin();

$upload_path = wp_upload_dir();

define( 'ILOVE_IMG_WM_REGISTER_URL', 'https://api.iloveimg.com/v1/user' );
define( 'ILOVE_IMG_WM_LOGIN_URL', 'https://api.iloveimg.com/v1/user/login' );
define( 'ILOVE_IMG_WM_USER_URL', 'https://api.iloveimg.com/v1/user' );
define( 'ILOVE_IMG_WM_NUM_MAX_FILES', 2 );
define( 'ILOVE_IMG_WM_COMPRESS_DB_VERSION', '1.0' );
define( 'ILOVE_IMG_WM_UPLOAD_FOLDER', $upload_path['basedir'] );
define( 'ILOVE_IMG_WM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

set_time_limit( 300 );
