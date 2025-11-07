<?php
/**
 * Trendyol senkronizasyon servisi.
 *
 * @package Hediyesta_Trendyol_Sync
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'Hediyesta_Trendyol_Sync_Service' ) ) {

    class Hediyesta_Trendyol_Sync_Service {

        /**
         * Singleton örneği.
         *
         * @var Hediyesta_Trendyol_Sync_Service
         */
        protected static $instance = null;

        /**
         * Trendyol ayarları.
         *
         * @var array
         */
        protected $settings = array();

        /**
         * WooCommerce logger.
         *
         * @var \WC_Logger|null
         */
        protected $logger = null;

        /**
         * Instance döndürür.
         *
         * @return Hediyesta_Trendyol_Sync_Service
         */
        public static function instance() {
            if ( null === self::$instance ) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        /**
         * Hediyesta_Trendyol_Sync_Service constructor.
         */
        private function __construct() {
            $this->settings = $this->get_settings();
            $this->logger   = function_exists( 'wc_get_logger' ) ? wc_get_logger() : null;

            add_action( 'admin_post_hediyesta_trendyol_manual_sync', array( $this, 'handle_manual_sync' ) );
            add_action( Hediyesta_Trendyol_Sync::CRON_HOOK, array( $this, 'handle_cron_sync' ) );
            add_action( 'save_post_product', array( $this, 'handle_product_save' ), 20, 3 );
            add_action( 'woocommerce_update_product', array( $this, 'handle_direct_sync' ), 20, 1 );
            add_action( 'woocommerce_new_product', array( $this, 'handle_direct_sync' ), 20, 1 );
            add_action( 'woocommerce_save_product_variation', array( $this, 'handle_variation_save' ), 20, 2 );
        }

        /**
         * Ayarları döndürür.
         *
         * @return array
         */
        public function get_settings() {
            $defaults = array(
                'supplier_id'             => '',
                'username'                => '',
                'password'                => '',
                'default_brand_id'        => '',
                'default_category_id'     => '',
                'default_vat_rate'        => 18,
                'default_dimensional_w'   => 1.0,
                'default_currency'        => 'TRY',
                'default_cargo_company'   => '',
                'enable_cron'             => 'yes',
            );

            $options = get_option( Hediyesta_Trendyol_Sync::OPTION, array() );

            return wp_parse_args( is_array( $options ) ? $options : array(), $defaults );
        }

        /**
         * Manuel senkronizasyon isteğini işler.
         */
        public function handle_manual_sync() {
            if ( ! current_user_can( 'manage_woocommerce' ) ) {
                wp_die( esc_html__( 'Bu işlemi yapma yetkiniz yok.', 'hediyesta-trendyol-sync' ) );
            }

            check_admin_referer( 'hediyesta_trendyol_manual_sync' );

            $result = $this->sync_all_products();

            $redirect = add_query_arg(
                array(
                    'page'              => Hediyesta_Trendyol_Settings_Page::MENU_SLUG,
                    'hediyesta_synced'  => ( $result['success'] ? '1' : '0' ),
                    'hediyesta_message' => rawurlencode( $result['message'] ),
                ),
                admin_url( 'admin.php' )
            );

            wp_safe_redirect( $redirect );
            exit;
        }

        /**
         * WP-Cron senkronizasyonunu çalıştırır.
         */
        public function handle_cron_sync() {
            $settings = $this->get_settings();
            if ( isset( $settings['enable_cron'] ) && 'yes' !== $settings['enable_cron'] ) {
                return;
            }

            $this->log( 'info', __( 'WP-Cron ile Trendyol senkronizasyonu başlatıldı.', 'hediyesta-trendyol-sync' ) );
            $this->sync_all_products();
        }

        /**
         * Ürün kaydedildiğinde tetiklenir.
         *
         * @param int      $post_id   Post ID.
         * @param \WP_Post $post      Post nesnesi.
         * @param bool     $update    Güncelleme durumu.
         */
        public function handle_product_save( $post_id, $post, $update ) {
            if ( wp_is_post_revision( $post_id ) || 'product' !== $post->post_type ) {
                return;
            }

            if ( 'auto-draft' === $post->post_status ) {
                return;
            }

            $this->sync_product( $post_id );
        }

        /**
         * WooCommerce update/create hooklarından gelen senkronizasyon.
         *
         * @param int $product_id Product ID.
         */
        public function handle_direct_sync( $product_id ) {
            $this->sync_product( $product_id );
        }

        /**
         * Varyasyon kaydedildiğinde tetiklenir.
         *
         * @param int $variation_id Variation ID.
         * @param int $i            İndeks.
         */
        public function handle_variation_save( $variation_id, $i ) {
            $parent_id = wp_get_post_parent_id( $variation_id );
            if ( $parent_id ) {
                $this->sync_product( $parent_id );
            }
        }

        /**
         * Tüm ürünleri Trendyol ile senkronize eder.
         *
         * @return array Sonuç bilgisi.
         */
        public function sync_all_products() {
            $paged   = 1;
            $synced  = 0;
            $errors  = array();

            do {
                $products = wc_get_products(
                    array(
                        'status'  => array( 'publish' ),
                        'limit'   => 50,
                        'paginate'=> true,
                        'page'    => $paged,
                    )
                );

                if ( empty( $products->products ) ) {
                    break;
                }

                foreach ( $products->products as $product ) {
                    $result = $this->send_product_to_trendyol( $product );
                    if ( is_wp_error( $result ) ) {
                        $errors[] = $result->get_error_message();
                    } else {
                        $synced++;
                    }
                }

                $paged++;
            } while ( $paged <= $products->max_num_pages );

            $message = $synced > 0
                ? sprintf( __( '%d ürün Trendyol ile senkronize edildi.', 'hediyesta-trendyol-sync' ), $synced )
                : __( 'Senkronize edilecek ürün bulunamadı.', 'hediyesta-trendyol-sync' );

            if ( ! empty( $errors ) ) {
                $message .= ' ' . __( 'Bazı ürünler gönderilemedi:', 'hediyesta-trendyol-sync' ) . ' ' . implode( ' | ', array_unique( $errors ) );
            }

            if ( $synced > 0 ) {
                update_option( 'hediyesta_trendyol_last_sync', current_time( 'timestamp' ) );
            }

            return array(
                'success' => empty( $errors ),
                'message' => $message,
            );
        }

        /**
         * Tekil ürünü senkronize eder.
         *
         * @param int $product_id Product ID.
         */
        protected function sync_product( $product_id ) {
            $product = wc_get_product( $product_id );
            if ( ! $product ) {
                return;
            }

            $result = $this->send_product_to_trendyol( $product );

            if ( is_wp_error( $result ) ) {
                $this->log( 'error', sprintf( 'Ürün #%1$s Trendyol senkronizasyonu başarısız: %2$s', $product_id, $result->get_error_message() ) );
            } else {
                update_post_meta( $product_id, '_hediyesta_trendyol_last_synced', current_time( 'timestamp' ) );
                $this->log( 'info', sprintf( 'Ürün #%1$s Trendyol ile senkronize edildi.', $product_id ) );
            }
        }

        /**
         * Ürünü Trendyol API'sine gönderir.
         *
         * @param \WC_Product $product WooCommerce ürünü.
         *
         * @return array|\WP_Error
         */
        protected function send_product_to_trendyol( $product ) {
            $items = $this->prepare_items_for_product( $product );

            if ( empty( $items ) ) {
                return new \WP_Error( 'missing_items', __( 'Ürün Trendyol için gerekli bilgilere sahip değil.', 'hediyesta-trendyol-sync' ) );
            }

            $client  = new Hediyesta_Trendyol_API_Client( $this->settings );
            $results = array();

            foreach ( array_chunk( $items, 50 ) as $chunk ) {
                $response = $client->send_products( $chunk );

                if ( is_wp_error( $response ) ) {
                    return $response;
                }

                $results[] = $response;
            }

            return $results;
        }

        /**
         * WooCommerce ürününü Trendyol item dizisine dönüştürür.
         *
         * @param \WC_Product $product WooCommerce ürünü.
         *
         * @return array
         */
        protected function prepare_items_for_product( $product ) {
            $items = array();

            if ( $product->is_type( 'variable' ) ) {
                foreach ( $product->get_children() as $variation_id ) {
                    $variation = wc_get_product( $variation_id );
                    if ( $variation ) {
                        $item = $this->build_item_payload( $variation, $product );
                        if ( $item ) {
                            $items[] = $item;
                        }
                    }
                }
            } else {
                $item = $this->build_item_payload( $product );
                if ( $item ) {
                    $items[] = $item;
                }
            }

            return $items;
        }

        /**
         * Trendyol ürün item payload'u üretir.
         *
         * @param \WC_Product      $product  Ürün veya varyasyon.
         * @param \WC_Product|null $parent   Varyasyon ise üst ürün.
         *
         * @return array|null
         */
        protected function build_item_payload( $product, $parent = null ) {
            $sku = $product->get_sku();
            if ( empty( $sku ) ) {
                $sku = 'SKU-' . $product->get_id();
            }

            $barcode = $this->get_product_meta( $product, '_hediyesta_trendyol_barcode' );
            if ( empty( $barcode ) ) {
                $barcode = $sku;
            }

            $brand_id    = $this->get_product_meta( $product, '_hediyesta_trendyol_brand_id', $this->settings['default_brand_id'] );
            $category_id = $this->get_product_meta( $product, '_hediyesta_trendyol_category_id', $this->settings['default_category_id'] );

            if ( empty( $brand_id ) || empty( $category_id ) ) {
                $this->log( 'warning', sprintf( 'Ürün #%1$s için Trendyol marka veya kategori bilgisi eksik.', $product->get_id() ) );
                return null;
            }

            $quantity = $product->managing_stock() ? (int) $product->get_stock_quantity() : ( $product->is_in_stock() ? 1 : 0 );
            $quantity = max( 0, $quantity );

            $sale_price = wc_format_decimal( $product->get_price(), 2 );
            $list_price = wc_format_decimal( $product->get_regular_price() ? $product->get_regular_price() : $product->get_price(), 2 );

            if ( empty( $sale_price ) ) {
                $this->log( 'warning', sprintf( 'Ürün #%1$s için satış fiyatı bulunamadı.', $product->get_id() ) );
                return null;
            }

            $images = array();
            $image_ids = array();
            if ( $parent ) {
                $image_ids = $parent->get_gallery_image_ids();
                if ( $parent->get_image_id() ) {
                    array_unshift( $image_ids, $parent->get_image_id() );
                }
            } else {
                $image_ids = $product->get_gallery_image_ids();
                if ( $product->get_image_id() ) {
                    array_unshift( $image_ids, $product->get_image_id() );
                }
            }

            foreach ( array_unique( $image_ids ) as $image_id ) {
                $url = wp_get_attachment_url( $image_id );
                if ( $url ) {
                    $images[] = array( 'url' => esc_url_raw( $url ) );
                }
            }

            if ( empty( $images ) && $parent ) {
                $parent_image = wp_get_attachment_url( $parent->get_image_id() );
                if ( $parent_image ) {
                    $images[] = array( 'url' => esc_url_raw( $parent_image ) );
                }
            }

            if ( empty( $images ) ) {
                $this->log( 'warning', sprintf( 'Ürün #%1$s için görsel bulunamadı.', $product->get_id() ) );
            }

            $description = $parent ? $parent->get_description() : $product->get_description();
            if ( empty( $description ) ) {
                $description = $parent ? $parent->get_short_description() : $product->get_short_description();
            }

            $payload = array(
                'barcode'             => $barcode,
                'title'               => html_entity_decode( $product->get_name(), ENT_QUOTES, get_bloginfo( 'charset' ) ),
                'productMainId'       => $parent ? $parent->get_sku() ?: 'PRD-' . $parent->get_id() : $sku,
                'brandId'             => (int) $brand_id,
                'categoryId'          => (int) $category_id,
                'quantity'            => $quantity,
                'stockCode'           => $sku,
                'dimensionalWeight'   => (float) $this->settings['default_dimensional_w'],
                'description'         => wp_strip_all_tags( $description, true ),
                'vatRate'             => (int) $this->settings['default_vat_rate'],
                'salePrice'           => (float) $sale_price,
                'listPrice'           => (float) $list_price,
                'currencyType'        => strtoupper( $this->settings['default_currency'] ),
                'deliveryDuration'    => (int) $this->get_product_meta( $product, '_hediyesta_trendyol_delivery_duration', 1 ),
                'cargoCompanyId'      => $this->settings['default_cargo_company'] ? (int) $this->settings['default_cargo_company'] : null,
                'images'              => $images,
                'attributes'          => $this->prepare_attributes( $product, $parent ),
            );

            if ( empty( $payload['cargoCompanyId'] ) ) {
                unset( $payload['cargoCompanyId'] );
            }

            /**
             * Trendyol ürün payload'u filtrelemek için kullanılabilir.
             *
             * @param array           $payload Payload.
             * @param \WC_Product     $product Ürün veya varyasyon.
             * @param \WC_Product|null $parent Üst ürün.
             */
            $payload = apply_filters( 'hediyesta_trendyol_product_payload', $payload, $product, $parent );

            if ( empty( $payload ) || ! is_array( $payload ) ) {
                return null;
            }

            return $payload;
        }

        /**
         * Trendyol için nitelikleri hazırlar.
         *
         * @param \WC_Product      $product Ürün.
         * @param \WC_Product|null $parent  Üst ürün.
         *
         * @return array
         */
        protected function prepare_attributes( $product, $parent = null ) {
            $attributes = array();
            $source     = $parent ? $parent : $product;

            foreach ( $source->get_attributes() as $attribute ) {
                if ( $attribute->is_taxonomy() ) {
                    $terms = wc_get_product_terms( $source->get_id(), $attribute->get_name(), array( 'fields' => 'names' ) );
                    $value = implode( ', ', $terms );
                } else {
                    $value = implode( ', ', $attribute->get_options() );
                }

                if ( empty( $value ) ) {
                    continue;
                }

                $attributes[] = array(
                    'attributeId'   => (int) $this->get_attribute_meta( $attribute, 'hediyesta_trendyol_attribute_id' ),
                    'attributeName' => wc_attribute_label( $attribute->get_name() ),
                    'attributeValue'=> $value,
                );
            }

            $attributes = array_filter(
                $attributes,
                static function ( $attribute ) {
                    return ! empty( $attribute['attributeId'] );
                }
            );

            return array_values( $attributes );
        }

        /**
         * Ürün veya varyasyon meta bilgisini döndürür.
         *
         * @param \WC_Product $product      Ürün.
         * @param string       $meta_key     Meta anahtarı.
         * @param mixed        $default      Varsayılan.
         *
         * @return mixed
         */
        protected function get_product_meta( $product, $meta_key, $default = '' ) {
            $value = $product->get_meta( $meta_key, true );

            if ( '' === $value && $product->get_parent_id() ) {
                $parent = wc_get_product( $product->get_parent_id() );
                if ( $parent ) {
                    $value = $parent->get_meta( $meta_key, true );
                }
            }

            return '' !== $value ? $value : $default;
        }

        /**
         * Özellik meta bilgisini döndürür.
         *
         * @param \WC_Product_Attribute $attribute Attribute.
         * @param string                 $meta_key  Meta anahtarı.
         *
         * @return mixed
         */
        protected function get_attribute_meta( $attribute, $meta_key ) {
            $meta = get_term_meta( $attribute->get_id(), $meta_key, true );
            return $meta ? $meta : '';
        }

        /**
         * Log kaydı oluşturur.
         *
         * @param string $level   Seviye.
         * @param string $message Mesaj.
         */
        protected function log( $level, $message ) {
            if ( ! $this->logger ) {
                return;
            }

            $context = array( 'source' => 'hediyesta-trendyol-sync' );

            $this->logger->log( $level, $message, $context );
        }
    }
}
