<?php

class Social2WP_API {

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes(): void {
        register_rest_route( 'social2wp/v1', '/status', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_status' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( 'social2wp/v1', '/sync', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'sync_post' ],
            'permission_callback' => [ $this, 'check_permission' ],
        ] );

        register_rest_route( 'social2wp/v1', '/exchange', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'exchange_token' ],
            'permission_callback' => '__return_true',
        ] );
    }

    public function exchange_token( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $token  = sanitize_text_field( $request->get_param( 'token' ) );
        if ( ! $token ) {
            return new WP_Error( 'missing_token', 'Token required', [ 'status' => 400 ] );
        }

        $stored = get_option( 'social2wp_connect_token' );
        $expiry = (int) get_option( 'social2wp_connect_token_expiry', 0 );

        if ( ! $stored || ! hash_equals( $stored, $token ) || time() > $expiry ) {
            return new WP_Error( 'invalid_token', 'Token invalid or expired', [ 'status' => 401 ] );
        }

        delete_option( 'social2wp_connect_token' );
        delete_option( 'social2wp_connect_token_expiry' );
        update_option( 'social2wp_connected_at', current_time( 'mysql' ) );

        return rest_ensure_response( [ 'api_key' => get_option( 'social2wp_api_key' ) ] );
    }

    public function check_permission( WP_REST_Request $request ): bool {
        $key         = $request->get_header( 'X-Social2WP-Key' );
        $stored_key  = get_option( 'social2wp_api_key' );

        if ( $key && $stored_key && hash_equals( $stored_key, $key ) ) {
            return true;
        }

        return false;
    }

    public function get_status( WP_REST_Request $request ): WP_REST_Response {
        $key        = $request->get_header( 'X-Social2WP-Key' );
        $stored_key = get_option( 'social2wp_api_key' );
        $key_valid  = $key && $stored_key && hash_equals( $stored_key, $key );

        return rest_ensure_response( [
            'active'    => true,
            'version'   => SOCIAL2WP_VERSION,
            'formats'   => $this->available_formats(),
            'key_valid' => $key_valid,
        ] );
    }

    private function available_formats(): array {
        $formats = [ 'native' ];
        if ( Social2WP_Formatter::masonry_available() ) {
            $formats[] = 'masonry';
        }
        return $formats;
    }

    public function sync_post( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $data = $request->get_json_params();

        if ( empty( $data['instagram_post_id'] ) ) {
            return new WP_Error( 'missing_field', 'instagram_post_id is required', [ 'status' => 400 ] );
        }

        $post_id_str = sanitize_text_field( $data['instagram_post_id'] );

        $existing = get_posts( [
            'post_type'      => 'post',
            'meta_key'       => '_social2wp_post_id',
            'meta_value'     => $post_id_str,
            'posts_per_page' => 1,
            'fields'         => 'ids',
        ] );

        if ( ! empty( $existing ) ) {
            return rest_ensure_response( [ 'post_id' => $existing[0], 'status' => 'exists' ] );
        }

        $formatter = new Social2WP_Formatter();
        $result    = $formatter->create_post( $data );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return rest_ensure_response( [ 'post_id' => $result, 'status' => 'created' ] );
    }
}
