<?php

namespace WIC;

use WP_REST_Request;
use WP_REST_Response;

class Routes {
    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_endpoints' ] );
    }

    public function register_endpoints() {

        register_rest_route(
            Config::SLUG . '/v1',
            'load-custom-fields',
            [
                'methods'  => 'GET',
                'callback' => [ $this, 'load_custom_fields' ],
            ]
        );

        register_rest_route(
            Config::SLUG . '/v1',
            'read-file',
            [
                'methods'  => 'GET',
                'callback' => [ $this, 'upload_file' ],
            ]
        );
        register_rest_route(
            Config::SLUG . '/v1',
            'load-group-fields',
            [
                'methods'  => 'GET',
                'callback' => [ $this, 'load_group_fields' ],
            ]
        );

        register_rest_route(
            Config::SLUG . '/v1',
            'start-import',
            [
                'methods'  => 'POST',
                'callback' => [ $this, 'start_import' ],
            ]
        );
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

    public function load_custom_fields( WP_REST_Request $request ): WP_REST_Response {
        $post        = $request->get_param( 'post_type' );
        $postID      = $request->get_param( 'post_id' );
        $fields      = [];
        $html        = '';
        $groups_html = ' <option selected disabled>Select ACF group</option>';
        if ( $postID !== null ) {
            $groups = acf_get_field_groups( [ 'post_type' => $post, 'post_id' => $postID ] );
        } else {
            $groups = acf_get_field_groups( [ 'post_type' => $post ] );
        }
        $posts = '';
        foreach ( $groups as $group ) {
            $group_data              = acf_get_fields( $group['key'] );
            $groups_html             .= ' <option value="' . $group['key'] . '">' . $group['title'] . '</option>';
            $fields[ $group['key'] ] = [
                'title'  => $group['title'],
                'fields' => $group_data,
            ];

            $html .= '<div class="field_group" data-group="' . $group['key'] . '"><h5>' . $group['title'] . '</h5><div class="table"><ul>';
            foreach ( acf_get_fields( $group['key'] ) as $field ) {
                $html .= '<li>' . $field['label'] . '</li>';
            }
            $html .= '</ul><ul class="associate-table target-data">';
            foreach ( acf_get_fields( $group['key'] ) as $field ) {
                $html .= '<li class="target-data-fields"><div class="target-data-field" data-field="' . $field['name'] . '" data-type="' . $field['type'] . '">Put her field</div></li>';
            }
            $html .= '</ul></div></div>';
        }
        if ( $postID === null ) {
            $posts = '<option selected disabled>Select post</option>';

            foreach (
                get_posts( [
                    'post_type'      => $post,
                    'posts_per_page' => - 1,
                    'post_status'    => 'any',
                    'orderby'        => 'title',
                    'order'          => "ASC"
                ] ) as $post
            ) {
                $posts .= sprintf( '<option value="%s">%s</option>', $post->ID, $post->post_title );
            }
        }

        return new WP_REST_Response( [
            'group_html' => $groups_html,
            'posts'      => $posts,
            'fields'     => $fields,
            'html'       => $html
        ], 200 );
    }

    public function upload_file( WP_REST_Request $request ): WP_REST_Response {
        $file_data = $this->csv_to_array( get_attached_file( $request->get_param( 'id' ) ) );
        $html      = '<ul class="file-data associate-table">';
        foreach ( $file_data['titles'] as $title ) {
            $html .= '<li data-field="' . $title . '" draggable="true">' . $title . '</li>';
        }
        $html .= '</ul>';

        return new WP_REST_Response( $html, '200' );
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

    public function start_import( WP_REST_Request $request ) {
        $data      = $request->get_params();
        $file_data = $this->csv_to_array( get_attached_file( $data['file_id'] ) )['data'];
        if ( $data['type'] === 'posts' ) {
            foreach ( $file_data as $f_data ) {
                $post_id = wp_insert_post( [
                    'post_title' => $f_data['name'],
                    'post_type'  => $data['post_type']
                ] );
                foreach ( $f_data as $key => $value ) {
                    $m_data = $data['mapped'][ $key ];
                    if ( $m_data['type'] === 'image' && $value !== '' ) {
                        $value = $this->image_upload_from_url( $value );
                        $value = $value['attachment_id'];
                    }
                    update_field( $m_data['target'], $value, $post_id );
                }
            }
        }
//        return new WP_REST_Response( $file_data, '200' );
    }

    public function load_group_fields( WP_REST_Request $request ): WP_REST_Response {
        $params = $request->get_params();
        $fields = acf_get_fields( $params['group_id'] );
        $html   = '<option selected disabled>Select field from ACF group</option>';
        foreach ( $fields as $field ) {
            $html .= '<option value="' . $field['name'] . '">' . $field['label'] . '</option>';
        }

        return new WP_REST_Response( [ 'fields' => $html ] );
    }
}

new Routes();