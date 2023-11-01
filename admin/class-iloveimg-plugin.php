<?php

class Ilove_Img_Wm_Plugin {
    const VERSION = '1.0.3';
	const NAME    = 'ilove_img_watermark_plugin';

    public function __construct() {
        add_action( 'admin_init', array( $this, 'admin_init' ) );
    }

    public function admin_init() {
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_filter( 'manage_media_columns', array( $this, 'column_id' ) );
        add_filter( 'manage_media_custom_column', array( $this, 'column_id_row' ), 10, 2 );
        add_action( 'wp_ajax_ilove_img_wm_library', array( $this, 'ilove_img_wm_library' ) );
        add_action( 'wp_ajax_ilove_img_wm_restore', array( $this, 'ilove_img_wm_restore' ) );
        add_action( 'wp_ajax_ilove_img_wm_clear_backup', array( $this, 'ilove_img_wm_clear_backup' ) );
        add_action( 'wp_ajax_ilove_img_wm_library_is_watermarked', array( $this, 'ilove_img_wm_library_is_watermarked' ) );
        add_action( 'wp_ajax_ilove_img_wm_library_set_watermark_image', array( $this, 'ilove_img_wm_library_set_watermark_image' ) );
        add_filter( 'wp_generate_attachment_metadata', array( $this, 'process_attachment' ), 10, 2 );
        add_action( 'admin_action_iloveimg_bulk_action', array( $this, 'media_library_bulk_action' ) );
        add_action( 'attachment_submitbox_misc_actions', array( $this, 'show_media_info' ) );

        if ( ! class_exists( 'ILove_Img_Wm_Library_Init' ) ) {
            require_once 'class-iloveimg-library-init.php';
            new ILove_Img_Wm_Library_Init();
        }

        if ( ! is_plugin_active( 'iloveimg/iloveimgcompress.php' ) ) {
            add_action( 'admin_notices', array( $this, 'show_notices' ) );
        }
        add_thickbox();
    }

    public function enqueue_scripts() {
        wp_enqueue_script(
            self::NAME . '_spectrum_admin',
            plugins_url( '/assets/js/spectrum.js', __DIR__ ),
            array(),
            self::VERSION,
            true
        );
        wp_enqueue_script(
            self::NAME . '_admin',
            plugins_url( '/assets/js/main.js', __DIR__ ),
			array(),
            self::VERSION,
            true
        );
        wp_enqueue_style(
            self::NAME . '_admin',
            plugins_url( '/assets/css/app.css', __DIR__ ),
			array(),
            self::VERSION
		);
    }

    public function ilove_img_wm_library() {
        if ( isset( $_POST['id'] ) ) {
            $ilove         = new Ilove_Img_Wm_Process();
            $attachment_id = intval( $_POST['id'] );
            $images        = $ilove->watermark( $attachment_id );
            if ( $images !== false ) {
                Ilove_Img_Wm_Resources::render_watermark_details( $attachment_id );
            } else {
                ?>
                <p>You need more files</p>
                <?php
            }
        }
        wp_die();
    }

    public function ilove_img_wm_restore() {
        if ( is_dir( ILOVE_IMG_WM_UPLOAD_FOLDER . '/iloveimg-backup' ) ) {
            $folders = array_diff( scandir( ILOVE_IMG_WM_UPLOAD_FOLDER . '/iloveimg-backup' ), array( '..', '.' ) );
            foreach ( $folders as $key => $folder ) {
                Ilove_Img_Wm_Resources::rcopy( ILOVE_IMG_WM_UPLOAD_FOLDER . '/iloveimg-backup/' . $folder, ILOVE_IMG_WM_UPLOAD_FOLDER . '/' . $folder );
            }
            Ilove_Img_Wm_Resources::rrmdir( ILOVE_IMG_WM_UPLOAD_FOLDER . '/iloveimg-backup' );
            $images_restore = unserialize( get_option( 'iloveimg_images_to_restore' ) );
            foreach ( $images_restore as $key => $value ) {
                delete_post_meta( $value, 'iloveimg_status_watermark' );
                delete_post_meta( $value, 'iloveimg_watermark' );
                delete_post_meta( $value, 'iloveimg_status_compress' );
                delete_post_meta( $value, 'iloveimg_compress' );
                delete_option( 'iloveimg_images_to_restore' );
            }
        }

        wp_die();
    }

    public function ilove_img_wm_clear_backup() {
        if ( is_dir( ILOVE_IMG_WM_UPLOAD_FOLDER . '/iloveimg-backup' ) ) {
            Ilove_Img_Wm_Resources::rrmdir( ILOVE_IMG_WM_UPLOAD_FOLDER . '/iloveimg-backup' );
            delete_option( 'iloveimg_images_to_restore' );
        }
        wp_die();
    }

    public function ilove_img_wm_library_is_watermarked() {
        if ( isset( $_POST['id'] ) ) {
            $attachment_id    = intval( $_POST['id'] );
            $status_watermark = get_post_meta( $attachment_id, 'iloveimg_status_watermark', true );
            $images_compressed = Ilove_Img_Wm_Resources::get_sizes_watermarked( $attachment_id );
            if ( ( (int) $status_watermark === 1 || (int) $status_watermark === 3 ) ) {
                http_respone_code( 500 );
            } elseif ( (int) $status_watermark === 2 ) {
                Ilove_Img_Wm_Resources::render_watermark_details( $attachment_id );
            } elseif ( (int) $status_watermark === 0 && ! $status_watermark ) {
                echo 'Try again or buy more files';
            }
        }
        wp_die();
    }

    public function column_id( $columns ) {
        if ( (int) Ilove_Img_Wm_Resources::is_activated() === 0 ) {
            return $columns;
        }
        $columns['iloveimg_status_watermark'] = __( 'Status Watermark' );
        return $columns;
    }

    public function column_id_row( $column_name, $column_id ) {
        if ( $column_name == 'iloveimg_status_watermark' ) {
            Ilove_Img_Wm_Resources::get_status_of_column( $column_id );
        }
    }

    public function process_attachment( $metadata, $attachment_id ) {
        update_post_meta( $attachment_id, 'iloveimg_status_watermark', 0 ); // status no watermarked
        if ( (int) Ilove_Img_Wm_Resources::is_auto_watermark() === 1 && Ilove_Img_Wm_Resources::is_loggued() && (int) Ilove_Img_Wm_Resources::is_activated() === 1 ) {
            wp_update_attachment_metadata( $attachment_id, $metadata );
            $this->async_watermark( $attachment_id );
        } elseif ( ! (int) Ilove_Img_Wm_Resources::is_auto_watermark() && (int) Ilove_Img_Wm_Resources::is_watermark_image() == 1 ) {
                $_wm_options                                 = unserialize( get_option( 'iloveimg_options_watermark' ) );
                $_wm_options['iloveimg_field_autowatermark'] = 1;
                update_option( 'iloveimg_options_watermark', serialize( $_wm_options ) );
                delete_option( 'iloveimg_options_is_watermark_image' );
        }

        return $metadata;
    }

    public function ilove_img_wm_library_set_watermark_image() {
        $_wm_options = unserialize( get_option( 'iloveimg_options_watermark' ) );
        unset( $_wm_options['iloveimg_field_autowatermark'] );
        update_option( 'iloveimg_options_watermark', serialize( $_wm_options ) );
        update_option( 'iloveimg_options_is_watermark_image', 1 );
        wp_die();
    }

    public function async_watermark( $attachment_id ) {
        $args = array(
            'method'    => 'POST',
            'timeout'   => 0.01,
            'blocking'  => false,
            'body'      => array(
				'action' => 'ilove_img_wm_library',
				'id'     => $attachment_id,
			),
            'cookies'   => isset( $_COOKIE ) && is_array( $_COOKIE ) ? $_COOKIE : array(),
            'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
        );
        if ( getenv( 'WORDPRESS_HOST' ) !== false ) {
			wp_remote_post( getenv( 'WORDPRESS_HOST' ) . '/wp-admin/admin-ajax.php', $args );
		} else {
			wp_remote_post( admin_url( 'admin-ajax.php' ), $args );
		}
    }

    public function media_library_bulk_action() {
        die();
        foreach ( $_REQUEST['media'] as $attachment_id ) {
            $post = get_post( $attachment_id );
            if ( strpos( $post->post_mime_type, 'image/' ) !== false ) {
                $status_watermark = get_post_meta( $attachment_id, 'iloveimg_status_watermark', true );
                if ( (int) $status_watermark === 0 ) {
                    $this->async_watermark( $attachment_id );
                }
            }
        }
    }

    public function show_notices() {
        if ( ! Ilove_Img_Wm_Resources::is_loggued() ) {
			?>
            <div class="notice notice-warning is-dismissible">
                <p><strong>iLoveIMG</strong> - Please you need to be logged or registered. <a href="<?php echo admin_url( 'admin.php?page=iloveimg-watermark-admin-page' ); ?>">Go to settings</a></p>
            </div>
            <?php
        }

        if ( get_option( 'iloveimg_account_error' ) ) {
                $iloveimg_account_error = unserialize( get_option( 'iloveimg_account_error' ) );
            if ( $iloveimg_account_error['action'] == 'login' ) :
                ?>
                <div class="notice notice-error is-dismissible">
                    <p>Your email or password is wrong.</p>
                </div>
            <?php endif; ?>
            <?php if ( $iloveimg_account_error['action'] == 'register' ) : ?>
                <div class="notice notice-error is-dismissible">
                    <p>This email address has already been taken.</p>
                </div>
            <?php endif; ?>
            <?php if ( $iloveimg_account_error['action'] == 'register_limit' ) : ?>
                <div class="notice notice-error is-dismissible">
                    <p>You have reached limit of different users to use this WordPress plugin. Please relogin with one of your existing users.</p>
                </div>
            <?php endif; ?>
            <?php

        }
        // do query
        if ( get_option( 'iloveimg_account' ) ) {
            $account = json_decode( get_option( 'iloveimg_account' ), true );
            if ( ! array_key_exists( 'error', $account ) ) {
                $token    = $account['token'];
                $response = wp_remote_get(
                    ILOVE_IMG_WM_USER_URL . '/' . $account['id'],
                    array(
                        'headers' => array( 'Authorization' => 'Bearer ' . $token ),
                    )
                );

                if ( isset( $response['response']['code'] ) && $response['response']['code'] == 200 ) {
                    $account = json_decode( $response['body'], true );
                    if ( $account['files_used'] >= $account['free_files_limit'] and $account['package_files_used'] >= $account['package_files_limit'] and @$account['subscription_files_used'] >= $account['subscription_files_limit'] ) {
                        ?>
                        <div class="notice notice-warning is-dismissible">
                            <p><strong>iLoveIMG</strong> - Please you need more files. <a href="https://developer.iloveimg.com/pricing" target="_blank">Buy more files</a></p>
                        </div>
                        <?php
                    }
                }
            }
        }
    }

    public function show_media_info() {
        global $post;
        echo '<div class="misc-pub-section iloveimg-compress-images">';
        echo '<h4>';
        esc_html_e( 'iLoveIMG', 'iloveimg' );
        echo '</h4>';
        echo '<div class="iloveimg-container">';
        echo '<table><tr><td>';
        $status_watermark = get_post_meta( $post->ID, 'iloveimg_status_watermark', true );

        $images_compressed = Ilove_Img_Wm_Resources::get_sizes_watermarked( $post->ID );

        if ( (int) $status_watermark === 2 ) {
            Ilove_Img_Wm_Resources::render_watermark_details( $post->ID );
        } else {
            Ilove_Img_Wm_Resources::get_status_of_column( $post->ID );
        }
        echo '</td></tr></table>';
        echo '</div>';
        echo '</div>';
    }
}