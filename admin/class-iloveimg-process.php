<?php
use Iloveimg\WatermarkImageTask;

class iLoveIMG_Watermark_Process {

    public $proyect_public = '';
    public $secret_key     = '';

    public function watermark( $imagesID ) {
        global $_wp_additional_image_sizes, $wpdb;

        $images = array();
        try {

            if ( get_option( 'iloveimg_proyect' ) ) {
                $proyect              = explode( '#', get_option( 'iloveimg_proyect' ) );
                $this->proyect_public = $proyect[0];
                $this->secret_key     = $proyect[1];
            } elseif ( get_option( 'iloveimg_account' ) ) {
                $account              = json_decode( get_option( 'iloveimg_account' ), true );
                $this->proyect_public = $account['projects'][0]['public_key'];
                $this->secret_key     = $account['projects'][0]['secret_key'];
            }

            $filesProcessing = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->postmeta WHERE meta_key = 'iloveimg_status_watermark' AND meta_value = 1" );

            $imageCompressProcessing = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->postmeta WHERE meta_key = 'iloveimg_status_compress' AND meta_value = 1 AND post_id =  " . $imagesID );

            if ( $filesProcessing < ILOVE_IMG_WM_NUM_MAX_FILES and $imageCompressProcessing == 0 ) {
                update_post_meta( $imagesID, 'iloveimg_status_watermark', 1 ); // status compressing

                $_sizes = get_intermediate_image_sizes();

                array_unshift( $_sizes, 'full' );
                $_aOptions = unserialize( get_option( 'iloveimg_options_watermark' ) );

                if ( isset( $_aOptions['iloveimg_field_backup'] ) ) {
					if ( ! is_dir( ILOVE_IMG_WM_UPLOAD_FOLDER . '/iloveimg-backup' ) ) {
						mkdir( ILOVE_IMG_WM_UPLOAD_FOLDER . '/iloveimg-backup' );
					}
					$images_restore   = get_option( 'iloveimg_images_to_restore' ) ? unserialize( get_option( 'iloveimg_images_to_restore' ) ) : array();
					$images_restore[] = $imagesID;
					update_option( 'iloveimg_images_to_restore', serialize( $images_restore ) );
                }

                foreach ( $_sizes as $_size ) {
                    $image            = wp_get_attachment_image_src( $imagesID, $_size );
                    $pathFile         = $_SERVER['DOCUMENT_ROOT'] . str_replace( site_url(), '', $image[0] );
                    $images[ $_size ] = array( 'watermarked' => null );
                    if ( in_array( $_size, $_aOptions['iloveimg_field_sizes'] ) ) {
                        // if enable backup
                        if ( isset( $_aOptions['iloveimg_field_backup'] ) ) {

                            $new_path = ILOVE_IMG_WM_UPLOAD_FOLDER . '/iloveimg-backup' . str_replace( ILOVE_IMG_WM_UPLOAD_FOLDER, '', dirname( $pathFile ) );
                            if ( ! is_dir( $new_path ) ) {
								mkdir( $new_path, 0777, true );
                            }
                            copy( $pathFile, $new_path . '/' . basename( $pathFile ) );
                        }

                        $myTask = new WatermarkImageTask( $this->proyect_public, $this->secret_key );
                        $file   = $myTask->addFile( $pathFile );
                        if ( isset( $_aOptions['iloveimg_field_type'] ) ) {
                            $gravity = array( 'NorthWest', 'North', 'NorthEast', 'CenterWest', 'Center', 'CenterEast', 'SouthWest', 'South', 'SouthEast' );
                            if ( $_aOptions['iloveimg_field_type'] == 'text' ) {
                                $font_style = null;
                                if ( isset( $_aOptions['iloveimg_field_text_bold'] ) && isset( $_aOptions['iloveimg_field_text_italic'] ) ) {
									$font_style = 'Bold-Italic';
                                } elseif ( isset( $_aOptions['iloveimg_field_text_bold'] ) ) {
										$font_style = 'Bold';
								} elseif ( isset( $_aOptions['iloveimg_field_text_italic'] ) ) {
									$font_style = 'Italic';
                                }
                                $element = $myTask->addElement(
                                    array(
										'type'          => 'text',
										'text'          => isset( $_aOptions['iloveimg_field_text'] ) ? $_aOptions['iloveimg_field_text'] : 'Sample',
										'width_percent' => $_aOptions['iloveimg_field_scale'],
										'font_family'   => $_aOptions['iloveimg_field_text_family'],
										'font_style'    => $font_style,
										'font_weight'   => isset( $_aOptions['iloveimg_field_text_bold'] ) ? 'Bold' : null,
										'font_color'    => isset( $_aOptions['iloveimg_field_text_color'] ) ? $_aOptions['iloveimg_field_text_color'] : '#000',
										'transparency'  => $_aOptions['iloveimg_field_opacity'],
										'rotation'      => $_aOptions['iloveimg_field_rotation'],
										'gravity'       => isset( $_aOptions['iloveimg_field_position'] ) ? $gravity[ $_aOptions['iloveimg_field_position'] - 1 ] : 'Center',
										'mosaic'        => isset( $_aOptions['iloveimg_field_mosaic'] ) ? true : false,
										'vertical_adjustment_percent' => 2,
										'horizontal_adjustment_percent' => 2,
                                    )
                                );
                            } else {
                                $watermark = $myTask->addFileFromUrl( $_aOptions['iloveimg_field_image'] );
                                $element   = $myTask->addElement(
                                    array(
										'type'            => 'image',
										'text'            => isset( $_aOptions['iloveimg_field_text'] ) ? $_aOptions['iloveimg_field_text'] : 'Sample',
										'width_percent'   => $_aOptions['iloveimg_field_scale'],
										'server_filename' => $watermark->getServerFilename(),
										'transparency'    => $_aOptions['iloveimg_field_opacity'],
										'rotation'        => $_aOptions['iloveimg_field_rotation'],
										'gravity'         => isset( $_aOptions['iloveimg_field_position'] ) ? $gravity[ $_aOptions['iloveimg_field_position'] - 1 ] : 'Center',
										'mosaic'          => isset( $_aOptions['iloveimg_field_mosaic'] ) ? true : false,
										'vertical_adjustment_percent' => 2,
										'horizontal_adjustment_percent' => 2,
                                    )
                                );
                            }
                        }
                        $myTask->execute();
                        $myTask->download( dirname( $pathFile ) );
                        $images[ $_size ]['watermarked'] = 1;
                        do_action( 'iloveimg_watermarked_completed', $imagesID );

                    }
                }
                update_post_meta( $imagesID, 'iloveimg_watermark', $images );
                update_post_meta( $imagesID, 'iloveimg_status_watermark', 2 ); // status compressed
                return $images;

            } else {
                update_post_meta( $imagesID, 'iloveimg_status_watermark', 3 ); // status queue
                sleep( 2 );
                return $this->watermark( $imagesID );
            }

            // print_r($imagesID);
        } catch ( Exception $e ) {
            update_post_meta( $imagesID, 'iloveimg_status_watermark', 0 );
            return false;
        }
        return false;
    }
}
