<?php

class Social2WP_Settings {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_init', [ $this, 'handle_regenerate' ] );
    }

    public function add_menu(): void {
        add_options_page(
            'Social2WP',
            'Social2WP',
            'manage_options',
            'social2wp',
            [ $this, 'render_page' ]
        );
    }

    public function register_settings(): void {
        $options = [
            [ 'social2wp_gallery_format',  'string',  'native', 'sanitize_text_field' ],
            [ 'social2wp_publish_status',  'string',  'draft',  'sanitize_text_field' ],
            [ 'social2wp_default_category','integer', 0,        'absint' ],
            [ 'social2wp_post_author',     'integer', 1,        'absint' ],
        ];

        foreach ( $options as [ $name, $type, $default, $sanitize ] ) {
            register_setting( 'social2wp', $name, [
                'type'              => $type,
                'default'           => $default,
                'sanitize_callback' => $sanitize,
            ] );
        }
    }

    public function handle_regenerate(): void {
        if ( ! isset( $_POST['social2wp_regenerate'] ) ) return;
        if ( ! current_user_can( 'manage_options' ) ) return;
        check_admin_referer( 'social2wp_regenerate_key' );

        update_option( 'social2wp_api_key', wp_generate_password( 32, false ) );
        wp_redirect( admin_url( 'options-general.php?page=social2wp&regenerated=1' ) );
        exit;
    }

    public function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) return;

        $api_key        = get_option( 'social2wp_api_key', '' );
        $masonry_ok     = class_exists( 'PGC_Simply_Gallery_Block' ) || function_exists( 'pgc_sgb_init' );
        $format         = get_option( 'social2wp_gallery_format', 'native' );
        $status         = get_option( 'social2wp_publish_status', 'draft' );
        $category       = (int) get_option( 'social2wp_default_category', 0 );
        $author         = (int) get_option( 'social2wp_post_author', 1 );
        $categories     = get_categories( [ 'hide_empty' => false ] );
        $users          = get_users( [ 'capability' => 'edit_posts' ] );
        $site_url       = get_site_url();
        $dashboard_url  = 'https://social2wp.com/dashboard';
        $regenerated    = isset( $_GET['regenerated'] );
        $connected      = isset( $_GET['connected'] );
        ?>
        <div class="wrap">
            <h1>Social2WP</h1>

            <?php if ( $connected ) : ?>
            <div class="notice notice-success is-dismissible"><p>Connected! Your WordPress site is now linked to Social2WP.</p></div>
            <?php endif; ?>

            <?php if ( $regenerated ) : ?>
            <div class="notice notice-success is-dismissible"><p>API key regenerated. Update your Social2WP dashboard with the new key.</p></div>
            <?php endif; ?>

            <div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:1.5rem;margin-bottom:1rem;max-width:700px;">
                <h2 style="margin-top:0;font-size:1rem;">How to connect</h2>
                <ol style="color:#50575e;font-size:0.9rem;line-height:2;padding-left:1.25rem;margin:0;">
                    <li>Click <strong>Connect to Social2WP</strong> below</li>
                    <li>Create a Social2WP account (or sign in if you already have one)</li>
                    <li>Subscribe to a plan</li>
                    <li>Connect your Instagram account through Facebook</li>
                    <li>You're done — your WordPress site will be linked automatically</li>
                </ol>
                <p style="margin-top:0.75rem;margin-bottom:0;font-size:0.8125rem;">
                    <a href="https://social2wp.com/getting-started" target="_blank" rel="noopener">Full setup guide →</a>
                </p>
            </div>

            <div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:1.5rem;margin-bottom:1.5rem;max-width:700px;">
                <h2 style="margin-top:0;font-size:1rem;">Connection</h2>

                <div style="display:flex;gap:0.75rem;flex-wrap:wrap;margin-bottom:1.25rem;">
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <input type="hidden" name="action" value="social2wp_connect">
                        <?php wp_nonce_field( 'social2wp_connect' ); ?>
                        <button type="submit" class="button button-primary">Connect to Social2WP</button>
                    </form>
                    <a href="<?php echo esc_url( $dashboard_url ); ?>" class="button" target="_blank" rel="noopener">
                        Go to Social2WP Dashboard ↗
                    </a>
                </div>

                <details style="margin-bottom:1.25rem;">
                    <summary style="cursor:pointer;font-size:0.8125rem;color:#50575e;user-select:none;">Advanced: show API key</summary>
                    <div style="margin-top:0.75rem;">
                        <div style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;">
                            <code id="s2wp-api-key" style="background:#f6f7f7;border:1px solid #ddd;padding:0.35rem 0.75rem;border-radius:3px;font-size:13px;letter-spacing:0.04em;word-break:break-all;"><?php echo esc_html( $api_key ); ?></code>
                            <button type="button" class="button" onclick="
                                navigator.clipboard.writeText(document.getElementById('s2wp-api-key').innerText);
                                this.textContent='Copied!';
                                setTimeout(()=>this.textContent='Copy',2000);
                            ">Copy</button>
                        </div>
                        <p class="description" style="margin-top:0.5rem;">This key is managed automatically. You only need it if you're connecting manually.</p>

                        <form method="post" style="margin-top:0.75rem;">
                            <?php wp_nonce_field( 'social2wp_regenerate_key' ); ?>
                            <button type="submit" name="social2wp_regenerate" value="1" class="button"
                                style="color:#b32d2e;"
                                onclick="return confirm('Regenerating the key will disconnect Social2WP until you reconnect. Continue?')">
                                Regenerate API key
                            </button>
                        </form>
                    </div>
                </details>
            </div>

            <h2>Post settings</h2>
            <form method="post" action="options.php">
                <?php settings_fields( 'social2wp' ); ?>
                <table class="form-table" role="presentation">

                    <tr>
                        <th scope="row"><label for="social2wp_gallery_format">Gallery format</label></th>
                        <td>
                            <select name="social2wp_gallery_format" id="social2wp_gallery_format">
                                <option value="native" <?php selected( $format, 'native' ); ?>>Native WordPress Gallery</option>
                                <?php if ( $masonry_ok ) : ?>
                                    <option value="masonry" <?php selected( $format, 'masonry' ); ?>>Masonry (Simply Gallery Block)</option>
                                <?php else : ?>
                                    <option value="masonry" disabled>Masonry — requires Simply Gallery Block plugin</option>
                                <?php endif; ?>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="social2wp_publish_status">Post status</label></th>
                        <td>
                            <select name="social2wp_publish_status" id="social2wp_publish_status">
                                <option value="draft"   <?php selected( $status, 'draft' ); ?>>Draft</option>
                                <option value="publish" <?php selected( $status, 'publish' ); ?>>Publish immediately</option>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="social2wp_default_category">Default category</label></th>
                        <td>
                            <select name="social2wp_default_category" id="social2wp_default_category">
                                <option value="0" <?php selected( $category, 0 ); ?>>— Uncategorized —</option>
                                <?php foreach ( $categories as $cat ) : ?>
                                    <option value="<?php echo esc_attr( $cat->term_id ); ?>" <?php selected( $category, $cat->term_id ); ?>>
                                        <?php echo esc_html( $cat->name ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="social2wp_post_author">Post author</label></th>
                        <td>
                            <select name="social2wp_post_author" id="social2wp_post_author">
                                <?php foreach ( $users as $user ) : ?>
                                    <option value="<?php echo esc_attr( $user->ID ); ?>" <?php selected( $author, $user->ID ); ?>>
                                        <?php echo esc_html( $user->display_name ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>

                </table>
                <?php submit_button( 'Save settings' ); ?>
            </form>
        </div>
        <?php
    }
}
