<?php

class Social2WP_Formatter {

    private string $format;
    private string $post_status;
    private string $separator_style;
    private string $separator_color;
    private bool   $set_featured_image;
    private string $font_size;

    public function __construct() {
        $this->format              = get_option( 'social2wp_gallery_format', 'native' );
        $this->post_status         = get_option( 'social2wp_publish_status', 'draft' );
        $this->separator_style     = get_option( 'social2wp_separator_style', 'default' );
        $this->separator_color     = get_option( 'social2wp_separator_color', '' );
        $this->set_featured_image  = get_option( 'social2wp_set_featured_image', 'no' ) === 'yes';
        $this->font_size           = get_option( 'social2wp_font_size', '' );

        // Fall back to native if masonry plugin is gone
        if ( $this->format === 'masonry' && ! self::masonry_available() ) {
            $this->format = 'native';
        }
    }

    public function create_post( array $data ): int|WP_Error {
        $image_ids = [];
        $video_ids = [];

        foreach ( array_slice( $data['images'] ?? [], 0, 20 ) as $url ) {
            $id = $this->sideload( $url );
            if ( ! is_wp_error( $id ) ) {
                $image_ids[] = $id;
            }
        }

        foreach ( array_slice( $data['videos'] ?? [], 0, 20 ) as $url ) {
            $id = $this->sideload( $url, 'video/mp4' );
            if ( ! is_wp_error( $id ) ) {
                $video_ids[] = $id;
            }
        }

        $content = $this->build_content( $image_ids, $video_ids, $data );

        $caption    = $data['caption'] ?? '';
        $first_line = trim( strtok( $caption, "\n" ) );
        $title      = $first_line
            ? mb_substr( $first_line, 0, 100 )
            : 'Instagram — ' . date( 'F j, Y', strtotime( $data['timestamp'] ?? 'now' ) );

        $ts       = strtotime( $data['timestamp'] ?? 'now' );
        $category = (int) get_option( 'social2wp_default_category', 0 );
        $author   = (int) get_option( 'social2wp_post_author', 1 );

        $post_arr = [
            'post_title'    => sanitize_text_field( $title ),
            'post_content'  => $content,
            'post_status'   => $this->post_status,
            'post_author'   => $author,
            'post_date'     => date( 'Y-m-d H:i:s', $ts ),
            'post_date_gmt' => gmdate( 'Y-m-d H:i:s', $ts ),
        ];

        if ( $category > 0 ) {
            $post_arr['post_category'] = [ $category ];
        }

        $post_id = wp_insert_post( $post_arr, true );
        if ( is_wp_error( $post_id ) ) return $post_id;

        update_post_meta( $post_id, '_social2wp_post_id', sanitize_text_field( $data['instagram_post_id'] ) );
        update_post_meta( $post_id, '_social2wp_permalink', esc_url_raw( $data['permalink'] ?? '' ) );

        if ( $this->set_featured_image && ! empty( $image_ids ) ) {
            set_post_thumbnail( $post_id, $image_ids[0] );
        }

        return $post_id;
    }

    // -------------------------------------------------------------------------
    // Block builders
    // -------------------------------------------------------------------------

    private function build_content( array $image_ids, array $video_ids, array $data ): string {
        $parts = [];

        if ( ! empty( $image_ids ) ) {
            $parts[] = match ( $this->format ) {
                'masonry' => $this->masonry_block( $image_ids ),
                default   => $this->native_gallery( $image_ids ),
            };
        }

        foreach ( $video_ids as $id ) {
            $parts[] = $this->video_block( $id );
        }

        $sep = $this->separator_block();
        if ( $sep ) $parts[] = $sep;

        if ( ! empty( $data['caption'] ) ) {
            $parts[] = $this->paragraph_blocks( $data['caption'] );
        }

        if ( ! empty( $data['permalink'] ) ) {
            $url     = esc_url( $data['permalink'] );
            $parts[] = "<!-- wp:paragraph -->\n<p><a href=\"{$url}\" target=\"_blank\" rel=\"noopener noreferrer\">View on Instagram</a></p>\n<!-- /wp:paragraph -->";
        }

        return implode( "\n\n", array_filter( $parts ) );
    }

    private function separator_block(): string {
        if ( $this->separator_style === 'none' ) return '';

        $is_default  = $this->separator_style === 'default';
        $style_slug  = $is_default ? '' : esc_attr( $this->separator_style );
        $color_slug  = $this->separator_color; // palette slug, e.g. "ast-global-color-0"
        $has_color   = $color_slug !== '';

        $attrs = [];
        if ( ! $is_default ) $attrs['className']       = "is-style-{$style_slug}";
        if ( $has_color )    $attrs['backgroundColor'] = $color_slug;
        $attrs_json = empty( $attrs ) ? '' : ' ' . wp_json_encode( $attrs );

        $classes = 'wp-block-separator has-alpha-channel-opacity';
        if ( $has_color ) {
            $slug_esc  = esc_attr( $color_slug );
            $classes  .= " has-text-color has-{$slug_esc}-color has-background has-{$slug_esc}-background-color";
        }
        if ( ! $is_default ) $classes .= " is-style-{$style_slug}";

        return "<!-- wp:separator{$attrs_json} -->\n<hr class=\"{$classes}\"/>\n<!-- /wp:separator -->";
    }

    private function native_gallery( array $ids ): string {
        $columns = min( 3, count( $ids ) );
        $items   = array_map( function ( $id ) {
            $url = wp_get_attachment_url( $id );
            return "<!-- wp:image {\"id\":{$id},\"sizeSlug\":\"large\"} -->\n<figure class=\"wp-block-image size-large\"><img src=\"{$url}\" class=\"wp-image-{$id}\"/></figure>\n<!-- /wp:image -->";
        }, $ids );

        $ids_json = implode( ',', $ids );
        $inner    = implode( "\n", $items );

        return "<!-- wp:gallery {\"columns\":{$columns},\"linkTo\":\"none\",\"ids\":[{$ids_json}]} -->\n<figure class=\"wp-block-gallery has-nested-images columns-{$columns} is-cropped\">\n{$inner}\n</figure>\n<!-- /wp:gallery -->";
    }

    private function masonry_block( array $ids ): string {
        $columns    = min( 4, count( $ids ) );
        $gallery_id = substr( md5( implode( ',', $ids ) ), 0, 8 );
        $images_arr = [];

        foreach ( $ids as $id ) {
            $url  = wp_get_attachment_url( $id );
            $meta = wp_get_attachment_metadata( $id );
            $images_arr[] = [
                'id'     => $id,
                'url'    => $url,
                'width'  => $meta['width'] ?? 0,
                'height' => $meta['height'] ?? 0,
            ];
        }

        // pgcsimplygalleryblock/masonry is a dynamic block (server-side render).
        // Dynamic blocks must use self-closing comment syntax — no inner HTML.
        // 'useClobalSettings' matches the typo in the Simply Gallery Block plugin.
        $attr = wp_json_encode( [
            'collectionColumns'              => $columns,
            'collectionThumbRecomendedWidth' => 150,
            'startPosIndex'                  => 0,
            'galleryType'                    => 'pgc_sgb_masonry',
            'galleryId'                      => $gallery_id,
            'images'                         => $images_arr,
            'useClobalSettings'              => true,
            'isIndexing'                     => false,
        ] );

        return "<!-- wp:pgcsimplygalleryblock/masonry {$attr} /-->";
    }

    private function video_block( int $id ): string {
        $url = wp_get_attachment_url( $id );
        return "<!-- wp:video {\"id\":{$id}} -->\n<figure class=\"wp-block-video\"><video autoplay loop muted playsinline src=\"{$url}\"></video></figure>\n<!-- /wp:video -->";
    }

    private function paragraph_blocks( string $caption ): string {
        $paragraphs = array_filter( array_map( 'trim', explode( "\n\n", $caption ) ) );
        $fs         = $this->font_size;
        $attr       = $fs ? " {\"fontSize\":\"{$fs}\"}" : '';
        $class      = $fs ? " class=\"has-{$fs}-font-size\"" : '';
        $blocks     = array_map( function ( $p ) use ( $attr, $class ) {
            $html = str_replace( '<br />', '<br>', nl2br( esc_html( $p ) ) );
            // Link hashtags and @mentions to Instagram.
            $html = preg_replace(
                '/(?<![&\w])#([a-zA-Z0-9_]+)/',
                '<a href="https://www.instagram.com/explore/tags/$1/" target="_blank" rel="noopener noreferrer">#$1</a>',
                $html
            );
            $html = preg_replace(
                '/(?<!\w)@([a-zA-Z0-9_.]+)/',
                '<a href="https://www.instagram.com/$1/" target="_blank" rel="noopener noreferrer">@$1</a>',
                $html
            );
            return "<!-- wp:paragraph{$attr} -->\n<p{$class}>{$html}</p>\n<!-- /wp:paragraph -->";
        }, $paragraphs );
        return implode( "\n\n", $blocks );
    }

    // -------------------------------------------------------------------------
    // Media upload
    // -------------------------------------------------------------------------

    private function sideload( string $url, string $mime = 'image/jpeg' ): int|WP_Error {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $tmp = download_url( $url );
        if ( is_wp_error( $tmp ) ) return $tmp;

        $path = parse_url( $url, PHP_URL_PATH );
        $name = basename( $path ) ?: 'instagram-media';

        // Ensure a sensible extension
        if ( ! pathinfo( $name, PATHINFO_EXTENSION ) ) {
            $name .= ( str_starts_with( $mime, 'video' ) ? '.mp4' : '.jpg' );
        }

        $file = [
            'name'     => $name,
            'type'     => $mime,
            'tmp_name' => $tmp,
            'error'    => 0,
            'size'     => filesize( $tmp ),
        ];

        $id = media_handle_sideload( $file, 0 );
        if ( ! @unlink( $tmp ) ) { error_log( 'Social2WP: failed to unlink temp file ' . $tmp ); }

        return $id;
    }

    public static function masonry_available(): bool {
        return class_exists( 'PGC_Simply_Gallery_Block' ) || function_exists( 'pgc_sgb_init' );
    }
}
