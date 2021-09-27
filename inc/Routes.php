<?php

namespace WIC;

use WP_REST_Request;
use WP_REST_Response;

class Routes {
    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_endpoints' ] );
    }
    private $group_key = null;

    public function register_endpoints() {
        register_rest_route(
            Config::SLUG . '/v1',
            'get_post_types',
            [
                'methods'  => 'GET',
                'callback' => [ $this, 'get_post_types' ],
            ]
        );

        register_rest_route(
            Config::SLUG . '/v1',
            'get_posts',
            [
                'methods'  => 'GET',
                'callback' => [ $this, 'get_posts' ],
            ]
        );

        register_rest_route(
            Config::SLUG . '/v1',
            'get_group_fields',
            [
                'methods'  => 'GET',
                'callback' => [ $this, 'get_group_fields' ],
            ]
        );

        register_rest_route(
            Config::SLUG . '/v1',
            'read_file',
            [
                'methods'  => 'GET',
                'callback' => [ $this, 'read_file' ],
            ]
        );

        register_rest_route(
            Config::SLUG . '/v1',
            'import',
            [
                'methods'  => 'POST',
                'callback' => [ $this, 'start_import' ],
            ]
        );

    }
    public function clear_data($group_key, $key, $post_id) {
        $field_slug = get_field_object($key)['name'];
        $page_fields = get_field($group_key, $post_id);
        $layout = null;
        $data = false;
        foreach ($page_fields as $field) {
            if(array_key_exists($field_slug, $field)) {
                $layout = $field['acf_fc_layout'];
            }
        }
        if ( have_rows( $group_key, $post_id ) ):
            while ( have_rows( $group_key, $post_id ) ) : the_row();
                if( get_row_layout() === $layout) {
                   $data  =  delete_sub_field($key);
                   break;
                }
            endwhile;
            endif;
        return $data;
    }
    public function get_post_types(): WP_REST_Response {
        $response = [];
        $excluded_post_types = [
            'attachment',
            'post'
        ];
        $posts    = get_post_types( [
            'public'  => true,
            'show_ui' => true
        ] );
        foreach ( $posts as $post ) {
            if ( !in_array($post, $excluded_post_types) ) {
                array_push( $response, $post );
            }
        }

        return new WP_REST_Response( [ 'postTypes' => $response, 'pluginUrl' => WIC_URL ], 201 );
    }

    public function get_posts( WP_REST_Request $request ): WP_REST_Response {
        $post_type   = $request->get_param( 'post_type' );
        $post_id     = $request->get_param( 'post' );
        $posts_lists = [];
        $fields      = [];

        if ( $post_id !== null ) {
            $groups = acf_get_field_groups( [ 'post_type' => $post_type, 'post_id' => $post_id ] );
        } else {
            $posts = get_posts( [ 'post_type' => $post_type, 'posts_per_page' => - 1 ] );
            foreach ( $posts as $post ) {
                array_push( $posts_lists, [
                    'postID' => $post->ID,
                    'title'  => $post->post_title,
                ] );
            }
            $groups = acf_get_field_groups( [ 'post_type' => $post_type ] );
        };

        foreach ( $groups as $group ) {
            $fields[] = [
                'value'    => $group['key'],
                'label'    => $group['title'],
                'type'     => $group['type'] ?? explode( '_', $group['key'] )[0],
                'children' => $this->render_fields_data( acf_get_fields( $group['key'] ), $group['key'] )
            ];
        }

        return new WP_REST_Response( [ 'posts' => $posts_lists, 'fields' => $fields], 201 );
    }

    private function render_fields_data( $fields, $parent_field = null, $group_key = null ): array {
        $result = [];
        foreach ( $fields as $field ) {
            if(array_key_exists('type', $field) && $field['type'] === 'flexible_content') {
                $this->group_key = $field['key'];
            }

            $result[] = [
                'value' => $field['key'],
                'label' => $field['label'],
                'type'  => $field['type'] ?? 'layout',
            ];
            $key      = array_search( $field['key'], array_column( $result, 'value' ) );
            if ( $parent_field !== null ) {
                $result[ $key ]['parent_field'] = $parent_field;
            }

            if ( $group_key !== null ) {
                $result[ $key ]['group_key'] = $group_key;
            }
            if ( array_key_exists( 'layouts', $field ) ) {
                $result[ $key ]['children'] = $this->render_fields_data( $field['layouts'], $field['key'], $this->group_key );
            } elseif ( array_key_exists( 'sub_fields', $field ) ) {
                $result[ $key ]['children'] = $this->render_fields_data( $field['sub_fields'], $field['key'], $this->group_key );
            }
        }

        return $result;
    }

    public function read_file( WP_REST_Request $request ): WP_REST_Response {
        return new WP_REST_Response( [ 'data' => $this->csv_to_array( get_attached_file( $request->get_param( 'id' ) ) )['titles'] ], 200 );
    }

    public function get_field_data( WP_REST_Request $request ): WP_REST_Response {
        $field_id = $request->get_param( 'field' );
        $field    = get_field_object( $field_id );

        return new WP_REST_Response( [ 'data' => $field ], 201 );
    }

    private function csv_to_array( $filename, string $delimiter = ',' ) {
        if ( ! file_exists( $filename ) || ! is_readable( $filename ) ) {
            return false;
        }

        $header = null;
        $data   = [];
        if ( ( $handle = fopen( $filename, 'r' ) ) !== false ) {
            while ( ( $row = fgetcsv( $handle, 2000, $delimiter ) ) !== false ) {
                if ( ! $header ) {
                    $header = $row;
                } else {
                    $data[] = array_combine( $header, $row );
                }
            }
            fclose( $handle );
        }

        return [
            'data'   => $data,
            'titles' => $header,
        ];
    }

    private function image_upload_from_url( $image_url, $attach_to_post = 0, $add_to_media = true ) {
        $remote_image = fopen( $image_url, 'r' );

        if ( ! $remote_image ) {
            return false;
        }

        $meta = stream_get_meta_data( $remote_image );

        $image_meta     = false;
        $image_filetype = false;

        if ( $meta && ! empty( $meta['wrapper_data'] ) ) {
            foreach ( $meta['wrapper_data'] as $v ) {
                if ( preg_match( '/Content\-Type: ?((image)\/?(jpe?g|png|gif|bmp))/i', $v, $matches ) ) {
                    $image_meta     = $matches[1];
                    $image_filetype = $matches[3];
                }
            }
        }

        // Resource did not provide an image.
        if ( ! $image_meta ) {
            return false;
        }

        $v = basename( $image_url );
        if ( $v && strlen( $v ) > 6 ) {
            // Create a filename from the URL's file, if it is long enough
            $path = $v;
        } else {
            // Short filenames should use the path from the URL (not domain)
            $url_parsed = parse_url( $image_url );
            $path       = isset( $url_parsed['path'] ) ? $url_parsed['path'] : $image_url;
        }

        $path            = preg_replace( '/(https?:|\/|www\.|\.[a-zA-Z]{2,4}$)/i', '', $path );
        $filename_no_ext = sanitize_title_with_dashes( $path, '', 'save' );

        $extension = $image_filetype;
        $filename  = $filename_no_ext . "." . $extension;

        // Simulate uploading a file through $_FILES. We need a temporary file for this.
        $stream_content = stream_get_contents( $remote_image );

        $tmp      = tmpfile();
        $tmp_path = stream_get_meta_data( $tmp )['uri'];
        fwrite( $tmp, $stream_content );
        fseek( $tmp, 0 ); // If we don't do this, WordPress thinks the file is empty

        $fake_FILE = [
            'name'     => $filename,
            'type'     => 'image/' . $extension,
            'tmp_name' => $tmp_path,
            'error'    => UPLOAD_ERR_OK,
            'size'     => strlen( $stream_content ),
        ];

        // Trick is_uploaded_file() by adding it to the superglobal
        $_FILES[ basename( $tmp_path ) ] = $fake_FILE;

        // For wp_handle_upload to work:
        include_once ABSPATH . 'wp-admin/includes/media.php';
        include_once ABSPATH . 'wp-admin/includes/file.php';
        include_once ABSPATH . 'wp-admin/includes/image.php';

        $result = wp_handle_upload( $fake_FILE, [
            'test_form' => false,
            'action'    => 'local',
        ] );

        fclose( $tmp ); // Close tmp file
        @unlink( $tmp_path ); // Delete the tmp file. Closing it should also delete it, so hide any warnings with @
        unset( $_FILES[ basename( $tmp_path ) ] ); // Clean up our $_FILES mess.

        fclose( $remote_image ); // Close the opened image resource

        $result['attachment_id'] = 0;

        if ( empty( $result['error'] ) && $add_to_media ) {
            $args = [
                'post_title'     => $filename_no_ext,
                'post_content'   => '',
                'post_status'    => 'publish',
                'post_mime_type' => $result['type'],
            ];

            $result['attachment_id'] = wp_insert_attachment( $args, $result['file'], $attach_to_post );

            $attach_data = wp_generate_attachment_metadata( $result['attachment_id'], $result['file'] );
            wp_update_attachment_metadata( $result['attachment_id'], $attach_data );

            if ( is_wp_error( $result['attachment_id'] ) ) {
                $result['attachment_id'] = 0;
            }
        }

        return $result;
    }

    private function update_data( $mapped_fields, $file_data, $postID, $row_id, $path ) {
        if(gettype($mapped_fields) === 'string') {
            $mapped_fields = json_decode($mapped_fields, true);
        }
        $this->clear_data($path[1], $path[count($path) -1 ], $postID);

        foreach ( $mapped_fields as $key => $field ) {
            if ( $key === 'no_parent' ) {
                foreach ( $file_data as $file_d ) {
                    foreach ( $field as $item ) {
                        if ( array_key_exists( $item['value'], $file_d ) ) {
                            update_field( $item['key'], $file_d[ $item['value'] ], $postID );
                        }
                    }
                }
            } else {

                foreach ( $file_data as $file_d ) {
                    $data = [];
                    $has_sub_fields = false;
                    $group_key = null;
                    foreach ( $field as $item ) {
                        if(array_key_exists('group_key', $item)) {
                            $group_key = $item['group_key'];
                            $has_sub_fields = acf_maybe_get_sub_field([$item['group_key'], $row_id, $key], acf_get_valid_post_id($postID), false);
                        }
                        if($item['type'] === 'link') {
                            if(array_key_exists( $item['value']['url'], $file_d ) && array_key_exists( $item['value']['title'], $file_d )) {
                                $value = [
                                    'url' => $file_d[ $item['value']['url']] === ''  ? '#' : $file_d[ $item['value']['url']],
                                    'title' => $file_d[ $item['value']['title'] ],
                                    'target'=> '',
                                ];
                                $data[ $item['key'] ] = $value;
                            }
                        } else if ( array_key_exists( $item['value'], $file_d ) ) {
                            $value = $file_d[ $item['value'] ];
                            if($item['type'] === 'image' && $file_d[ $item['value'] ] !== '') {
                                $value = $this->image_upload_from_url($file_d[ $item['value'] ])['attachment_id'];
                            }

                            $data[ $item['key'] ] = $value;
                        }
                    }
                    if ( $has_sub_fields ) {
                        add_sub_row( [$group_key, $row_id, $key], $data, $postID );
                    } else {
                        add_row( $key, $data, $postID );
                    }
                }
            }
        }
    }

    public function start_import( WP_REST_Request $request ): WP_REST_Response {
        $data      = $request->get_params();
        $file_data = $this->csv_to_array( get_attached_file( $data['fileID'] ) )['data'];
        if ( $data['postID'] !== null ) {
            $this->update_data( $data['data'], $file_data, $data['postID'], $data['rowID'], $data['path'] );
        }

        return new WP_REST_Response( [], '200' );
    }

}

new Routes();