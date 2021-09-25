<?php
/**
 * Plugin Name:       WP Import content
 * Plugin URI:        https://github.com/VasylPetrashkevych/wp-import-content
 * Description:       Import content from *.exe files with mapping.
 * Version:           1.0.0
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Vasyl Petrashkevych
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI:        https://example.com/my-plugin/
 * Text Domain:       wp-import-content
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

require_once 'inc/Config.php';
require_once 'inc/Routes.php';

use WIC\Config;

if ( ! class_exists( 'WP_Import_Content' ) ) {
    if(!defined('WIC_PATH')) {
        define('WIC_PATH', plugin_dir_path( __FILE__ ));
    }
    if(!defined('WIC_URL')) {
        define('WIC_URL', plugin_dir_url( __FILE__ ));
    }

    final class WP_Import_Content {

        public function init() {
            add_action( 'admin_enqueue_scripts', function () {
                wp_register_script( Config::SLUG, WIC_URL . 'assets/js/main.js', [], '', true );
                wp_register_style( Config::SLUG, WIC_URL . 'assets/styles/main.css', [], '' );
            } );

            add_action( 'admin_menu', function () {
                add_menu_page(
                    'Import content',
                    'Import content',
                    'manage_options',
                    'wp-import-content',
                    [
                        $this,
                        'setting_page_html'
                    ],
                    'dashicons-database-import'
                );
            } );
        }

        public function setting_page_html() {
            echo '<div id="root"></div>';
            wp_enqueue_script(Config::SLUG);
            wp_enqueue_style(Config::SLUG);
            wp_enqueue_media();

        }
    }

    ( new WP_Import_Content() )->init();
}
