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
            [ 'social2wp_gallery_format',   'string',  'native',   'sanitize_text_field' ],
            [ 'social2wp_publish_status',   'string',  'draft',    'sanitize_text_field' ],
            [ 'social2wp_default_category', 'integer', 0,          'absint' ],
            [ 'social2wp_post_author',      'integer', 1,          'absint' ],
            [ 'social2wp_separator_style',       'string',  'default', 'sanitize_text_field' ],
            [ 'social2wp_separator_color',       'string',  '',        'sanitize_text_field' ],
            [ 'social2wp_set_featured_image',    'string',  'no',      'sanitize_text_field' ],
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

    private function get_account_status(): string {
        $api_key = get_option( 'social2wp_api_key', '' );
        if ( ! $api_key ) return 'unknown';

        $cached = get_transient( 'social2wp_account_status' );
        if ( $cached !== false ) return $cached;

        $response = wp_remote_get( 'https://social2wp.com/api/account-status', [
            'headers' => [ 'X-API-Key' => $api_key ],
            'timeout' => 5,
        ] );

        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return 'unknown';
        }

        $body   = json_decode( wp_remote_retrieve_body( $response ), true );
        $status = $body['status'] ?? 'unknown';
        set_transient( 'social2wp_account_status', $status, HOUR_IN_SECONDS );
        return $status;
    }

    public function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) return;

        $account_status  = $this->get_account_status();
        $api_key         = get_option( 'social2wp_api_key', '' );
        $masonry_ok      = Social2WP_Formatter::masonry_available();
        $format          = get_option( 'social2wp_gallery_format', 'native' );
        $status          = get_option( 'social2wp_publish_status', 'draft' );
        $category        = (int) get_option( 'social2wp_default_category', 0 );
        $author          = (int) get_option( 'social2wp_post_author', 1 );
        $separator_style = get_option( 'social2wp_separator_style', 'default' );
        $separator_color = get_option( 'social2wp_separator_color', '' );
        if ( str_starts_with( $separator_color, '#' ) ) $separator_color = '';
        $connected_at    = get_option( 'social2wp_connected_at', '' );
        $categories      = get_categories( [ 'hide_empty' => false ] );
        $users           = get_users( [ 'capability' => 'edit_posts' ] );
        $site_url        = get_site_url();
        $dashboard_url   = 'https://social2wp.com/dashboard';
        $regenerated     = isset( $_GET['regenerated'] );
        $connected       = isset( $_GET['connected'] );

        // Collect registered separator block styles (theme/plugin additions)
        $sep_styles = [];
        if ( class_exists( 'WP_Block_Styles_Registry' ) ) {
            $registered = WP_Block_Styles_Registry::get_instance()->get_registered_styles_for_block( 'core/separator' );
            foreach ( $registered as $style ) {
                if ( $style['name'] !== 'default' ) {
                    $sep_styles[] = $style;
                }
            }
        }

        // Collect theme color palette (slugs + hex values for swatch display)
        $palette = [];
        $global  = function_exists( 'wp_get_global_settings' ) ? wp_get_global_settings( [ 'color' ] ) : [];
        if ( ! empty( $global['palette']['theme'] ) ) {
            $palette = $global['palette']['theme'];
        } else {
            $support = get_theme_support( 'editor-color-palette' );
            if ( ! empty( $support[0] ) ) {
                foreach ( $support[0] as $c ) {
                    $palette[] = [ 'slug' => $c['slug'], 'name' => $c['name'], 'color' => $c['color'] ];
                }
            }
        }

        // If colors are CSS variables (e.g. Astra), resolve them to hex for admin display
        $css_var_map = [];
        $astra_opt = get_option( 'astra-settings', [] );
        if ( ! empty( $astra_opt['global-color-palette']['palette'] ) ) {
            foreach ( $astra_opt['global-color-palette']['palette'] as $i => $hex ) {
                if ( ! empty( $hex ) ) {
                    $css_var_map[ '--ast-global-color-' . $i ] = $hex;
                }
            }
        }
        foreach ( $palette as &$swatch ) {
            if ( str_starts_with( $swatch['color'], 'var(' ) ) {
                preg_match( '/var\(\s*([^)]+)\s*\)/', $swatch['color'], $m );
                $var = trim( $m[1] ?? '' );
                if ( isset( $css_var_map[ $var ] ) ) {
                    $swatch['color'] = $css_var_map[ $var ];
                }
            }
        }
        unset( $swatch );

        ?>
        <div class="wrap">
            <h1 style="display:flex;align-items:center;gap:0.6rem;">
                <img src="<?php echo esc_url( plugins_url( 'assets/social2wp-icon.png', dirname( __FILE__ ) ) ); ?>"
                     alt="" width="36" height="36" style="border-radius:8px;">
                Social2WP
            </h1>

            <?php if ( $account_status === 'past_due' ) : ?>
            <div class="notice notice-error" style="display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:0.75rem 1rem;">
                <p style="margin:0;">⚠️ <strong>Payment failed.</strong> Update your billing info to keep syncing.</p>
                <a href="https://social2wp.com/billing" target="_blank" rel="noopener" class="button button-primary" style="white-space:nowrap;">Update billing →</a>
            </div>
            <?php elseif ( $account_status === 'deactivated' ) : ?>
            <div class="notice notice-error" style="display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:0.75rem 1rem;">
                <p style="margin:0;">⚠️ <strong>Account deactivated.</strong> Resubscribe to resume syncing.</p>
                <a href="https://social2wp.com/billing" target="_blank" rel="noopener" class="button button-primary" style="white-space:nowrap;">Resubscribe →</a>
            </div>
            <?php endif; ?>

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
                    <li>Connect your Instagram account through Facebook</li>
                    <li>Start your 30-day free trial</li>
                    <li>You're done — your WordPress site will be linked automatically</li>
                </ol>
                <p style="margin-top:0.75rem;margin-bottom:0;font-size:0.8125rem;">
                    <a href="https://social2wp.com/getting-started" target="_blank" rel="noopener">Full setup guide →</a>
                </p>
            </div>

            <div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:1.5rem;margin-bottom:1.5rem;max-width:700px;">
                <h2 style="margin-top:0;font-size:1rem;">Connection</h2>

                <?php if ( $connected_at ) : ?>
                <p style="margin-bottom:1rem;">
                    <span style="display:inline-flex;align-items:center;gap:0.375rem;background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;font-size:0.8125rem;padding:0.3em 0.75em;border-radius:99px;">
                        <span style="width:7px;height:7px;border-radius:50%;background:#16a34a;display:inline-block;"></span>
                        Connected since <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $connected_at ) ) ); ?>
                    </span>
                </p>
                <?php else : ?>
                <p style="margin-bottom:1rem;">
                    <span style="display:inline-flex;align-items:center;gap:0.375rem;background:#fef9c3;border:1px solid #fde047;color:#854d0e;font-size:0.8125rem;padding:0.3em 0.75em;border-radius:99px;">
                        <span style="width:7px;height:7px;border-radius:50%;background:#ca8a04;display:inline-block;"></span>
                        Not connected
                    </span>
                </p>
                <?php endif; ?>

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
                            <p class="description">Social2WP currently supports Native WordPress Gallery and Simply Gallery Block. If you use a different gallery plugin, choose <strong>Native WordPress Gallery</strong> — many gallery plugins enhance the native block and will still apply their formatting automatically.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="social2wp_separator_style">Divider style</label></th>
                        <td>
                            <select name="social2wp_separator_style" id="social2wp_separator_style">
                                <option value="none"    <?php selected( $separator_style, 'none' ); ?>>None</option>
                                <option value="default" <?php selected( $separator_style, 'default' ); ?>>Default</option>
                                <?php foreach ( $sep_styles as $style ) : ?>
                                    <option value="<?php echo esc_attr( $style['name'] ); ?>" <?php selected( $separator_style, $style['name'] ); ?>>
                                        <?php echo esc_html( $style['label'] ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Divider inserted between the image and the caption in each synced post.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="social2wp_separator_color">Divider color</label></th>
                        <td>
                            <?php if ( ! empty( $palette ) ) : ?>
                            <div id="s2wp-swatches" style="display:flex;flex-wrap:wrap;gap:0.4rem;align-items:center;margin-bottom:0.5rem;">
                                <span class="s2wp-swatch" data-slug="" role="button" tabindex="0"
                                    style="display:inline-flex;align-items:center;padding:0 0.5rem;height:26px;font-size:0.8125rem;border-radius:3px;border:2px solid #ccc;background:#f6f7f7;cursor:pointer;">
                                    None
                                </span>
                                <?php foreach ( $palette as $swatch ) :
                                    $slug  = esc_attr( $swatch['slug'] );
                                    $hex   = esc_attr( $swatch['color'] );
                                    $label = esc_html( $swatch['name'] );
                                ?>
                                <span class="s2wp-swatch" data-slug="<?php echo $slug; ?>"
                                    role="button" tabindex="0"
                                    title="<?php echo $label; ?> (<?php echo $slug; ?>)"
                                    style="display:inline-block;width:26px;height:26px;border-radius:4px;border:2px solid #ccc;background:<?php echo $hex; ?>;cursor:pointer;"></span>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                            <div style="display:flex;align-items:center;gap:0.5rem;">
                                <input type="text" name="social2wp_separator_color" id="social2wp_separator_color"
                                    value="<?php echo esc_attr( $separator_color ); ?>"
                                    placeholder="e.g. ast-global-color-0"
                                    style="font-family:monospace;width:220px;" class="regular-text">
                                <button type="button" id="s2wp-clear-color" class="button">Clear</button>
                            </div>
                            <p class="description" style="margin-top:0.375rem;">
                                <?php if ( ! empty( $palette ) ) : ?>
                                Click a swatch to select a theme color, or type the color slug directly. Leave blank for the theme default.
                                <?php else : ?>
                                Type a theme color slug (e.g. <code>ast-global-color-0</code>), or leave blank for the theme default. The slug must match a color registered by your theme.
                                <?php endif; ?>
                            </p>
                            <details style="margin-top:0.5rem;">
                                <summary style="cursor:pointer;font-size:0.8125rem;color:#2271b1;text-decoration:underline;text-underline-offset:2px;list-style:none;">Colors not showing correctly?</summary>
                                <div style="margin-top:0.625rem;padding:0.75rem;background:#f6f7f7;border:1px solid #ddd;border-radius:3px;font-size:0.8125rem;line-height:1.6;max-width:480px;">
                                    <p style="margin:0 0 0.5rem;"><strong>How to find your color slug:</strong></p>
                                    <ol style="margin:0 0 0.75rem;padding-left:1.25rem;">
                                        <li>Open any post in the block editor</li>
                                        <li>Add a <strong>Separator</strong> block and choose the color you want from its sidebar panel</li>
                                        <li>Open the block editor's <strong>Options menu</strong> (⋮ top-right) and choose <strong>Code editor</strong></li>
                                        <li>Find the separator comment — the slug appears as <code>backgroundColor:"your-slug-here"</code></li>
                                    </ol>
                                    <p style="margin:0;">Type that slug into the field above. If swatches aren't loading for your theme, <a href="mailto:support@social2wp.com?subject=Color+swatches+not+loading&body=Theme+in+use:+%0AWordPress+version:+%0A" style="color:#2271b1;">let us know</a> and we'll add support for it.</p>
                                </div>
                            </details>
                            <script>
                            (function() {
                                var input   = document.getElementById('social2wp_separator_color');
                                var swatches = document.querySelectorAll('.s2wp-swatch');
                                function highlight(slug) {
                                    swatches.forEach(function(b) {
                                        b.style.borderColor = b.dataset.slug === slug ? '#0073aa' : '#ccc';
                                        b.style.outline = b.dataset.slug === slug ? '1px solid #0073aa' : 'none';
                                    });
                                }
                                highlight(input.value);
                                swatches.forEach(function(btn) {
                                    btn.addEventListener('click', function() {
                                        input.value = this.dataset.slug;
                                        highlight(this.dataset.slug);
                                    });
                                });
                                document.getElementById('s2wp-clear-color').addEventListener('click', function() {
                                    input.value = '';
                                    highlight('');
                                });
                                input.addEventListener('input', function() { highlight(this.value); });
                            })();
                            </script>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Featured image</th>
                        <td>
                            <label>
                                <input type="checkbox" name="social2wp_set_featured_image" value="yes"
                                    <?php checked( get_option( 'social2wp_set_featured_image', 'no' ), 'yes' ); ?>>
                                Set the first image as the featured image on each synced post
                            </label>
                            <p class="description">When unchecked, posts are created without a featured image.</p>
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
