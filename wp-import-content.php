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
namespace WIC {
    require_once './inc/Config.php';

    if ( ! defined( 'ABSPATH' ) ) {
        exit; // Exit if accessed directly
    }

    if ( !class_exists( 'WP_Import_Content' ) ) {
        final class WP_Import_Content {

            public function init() {
                self::define( 'WIC_PATH', plugin_dir_path( __FILE__ ) );
                self::define( 'WIC_URL', plugin_dir_url( __FILE__ ) );
                add_management_page( 'Import content', 'Import content', 'manage_options', 'wp-import-content', [
                    $this,
                    'setting_page_html'
                ] );
            }

            private function setting_page_html() {

            }

            public static function define( string $var, $val ) {
                if ( !defined( $var ) ) {
                    define( $var, $val );
                }
            }
        }

        ( new WP_Import_Content() )->init();
    }
}