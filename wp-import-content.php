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
    final class WP_Import_Content {

        public function init() {
            self::define( 'WIC_PATH', plugin_dir_path( __FILE__ ) );
            self::define( 'WIC_URL', plugin_dir_url( __FILE__ ) );
            add_action( 'admin_enqueue_scripts', function () {
                wp_register_script( Config::SLUG, WIC_URL . 'assets/js/main.js', [], '', true );
                wp_register_style( Config::SLUG, WIC_URL . 'assets/css/main.css', [], '', 'all' );
            } );

            add_action( 'admin_menu', function () {
                add_management_page(
                    'Import content',
                    'Import content',
                    'manage_options',
                    'wp-import-content',
                    [
                        $this,
                        'setting_page_html'
                    ]
                );
            } );
        }

        public function setting_page_html() { ?>
            <div class="wrap">

                <h1>Import content</h1>
                <table class="form-table">
                    <tbody>
                    <tr>
                        <th scope="row">
                            <label for="load_file">Select file</label>
                        </th>
                        <td>
                            <div class="load_file">
                                <div class="load_file__container">
                                    <button data-action="load-file">Load</button>
                                    <div class="file-name" data-file=""></div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="select_post_type">Select post type</label>
                        </th>
                        <td>
                            <select name="select_post_type" id="select_post_type">
                                <option selected disabled>Select post type</option>
                                <?php foreach (
                                    get_post_types( [
                                        'public'  => true,
                                        'show_ui' => true
                                    ] ) as $post_type
                                ) {
                                    if($post_type !== 'attachment') {
                                    ?>
                                    <option value="<?= $post_type; ?>"><?= $post_type; ?></option>
                                <?php }
                                }?>
                            </select>
                        </td>
                    </tr>
                    <tr style="display: none;" id="select_post_tr">
                        <th scope="row">
                            <label for="select_post">Select post</label>
                        </th>
                        <td>
                            <select name="select_post_type" id="select_post">
                                <option selected disabled>Select post</option>
                            </select>
                        </td>
                    </tr>
                    <tr style="display: none;" id="select_post_acf_group_row">
                        <th scope="row">
                            <label for="select_post_acf_group">Select ACF group</label>
                        </th>
                        <td>
                            <select name="select_post_type" id="select_post_acf_group"></select>
                        </td>
                    </tr>
                    <tr style="display: none;" id="select_post_acf_group_value_row">
                        <th scope="row">
                            <label for="select_post_acf_group_value">Select field from ACF group</label>
                        </th>
                        <td>
                            <select name="select_post_type" id="select_post_acf_group_value"></select>
                        </td>
                    </tr>
                    <tr id="data_mapping">
                        <th scope="row"><label for="">Map data</label></th>
                        <td>
                            <div class="data-mapping">
                                <div class="col col-3" data-fields="custom">
                                    <h4>Custom fields</h4>
                                    <div class="list">

                                    </div>
                                </div>
                                <div class="col col-2" data-fields="csv">
                                    <h4>Fields from file</h4>
                                    <div class="list">

                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    </tbody>
                </table>
                <button class="button button-primary" data-button-action="start-import">Start import</button>
            </div>
            <div class="preloader hidden" id="loader"></div>
            <?php
            wp_enqueue_script( Config::SLUG );
            wp_enqueue_style( Config::SLUG );
            wp_enqueue_media();
        }

        public static function define( string $var, $val ) {
            if ( ! defined( $var ) ) {
                define( $var, $val );
            }
        }
    }

    ( new WP_Import_Content() )->init();
}
