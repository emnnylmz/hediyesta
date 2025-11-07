<?php
/**
 * Trendyol API istemcisi.
 *
 * @package Hediyesta_Trendyol_Sync
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'Hediyesta_Trendyol_API_Client' ) ) {

    class Hediyesta_Trendyol_API_Client {

        /**
         * API ana URL'si.
         *
         * @var string
         */
        protected $base_url = 'https://api.trendyol.com/sapigw';

        /**
         * Ayarlar.
         *
         * @var array
         */
        protected $settings = array();

        /**
         * Hediyesta_Trendyol_API_Client constructor.
         *
         * @param array $settings Trendyol ayarları.
         */
        public function __construct( $settings ) {
            $this->settings = $settings;
        }

        /**
         * Ürünleri Trendyol API'ına gönderir.
         *
         * @param array $items Trendyol ürün item'ları.
         *
         * @return array|\WP_Error
         */
        public function send_products( array $items ) {
            $supplier_id = isset( $this->settings['supplier_id'] ) ? trim( $this->settings['supplier_id'] ) : '';

            if ( empty( $supplier_id ) ) {
                return new \WP_Error( 'missing_supplier_id', __( 'Trendyol tedarikçi (supplier) ID değeri eksik.', 'hediyesta-trendyol-sync' ) );
            }

            $endpoint = sprintf( '/suppliers/%s/v2/products', rawurlencode( $supplier_id ) );
            $payload  = array( 'items' => $items );

            return $this->request( 'POST', $endpoint, $payload );
        }

        /**
         * Trendyol API isteği yapar.
         *
         * @param string     $method  HTTP methodu.
         * @param string     $path    Endpoint path.
         * @param array|null $body    İstek gövdesi.
         * @param array      $headers Ek başlıklar.
         *
         * @return array|\WP_Error
         */
        public function request( $method, $path, $body = null, $headers = array() ) {
            $username = isset( $this->settings['username'] ) ? $this->settings['username'] : '';
            $password = isset( $this->settings['password'] ) ? $this->settings['password'] : '';

            if ( empty( $username ) || empty( $password ) ) {
                return new \WP_Error( 'missing_credentials', __( 'Trendyol kullanıcı adı veya şifre eksik.', 'hediyesta-trendyol-sync' ) );
            }

            $url = trailingslashit( $this->base_url ) . ltrim( $path, '/' );

            $args = array(
                'method'      => strtoupper( $method ),
                'headers'     => array_merge(
                    array(
                        'Authorization' => 'Basic ' . base64_encode( $username . ':' . $password ),
                        'Content-Type'  => 'application/json',
                        'Accept'        => 'application/json',
                    ),
                    $headers
                ),
                'timeout'     => 45,
                'redirection' => 3,
                'blocking'    => true,
                'body'        => null,
            );

            if ( null !== $body ) {
                $args['body'] = wp_json_encode( $body );
            }

            $response = wp_remote_request( $url, $args );

            if ( is_wp_error( $response ) ) {
                return $response;
            }

            $status_code = wp_remote_retrieve_response_code( $response );
            $data        = json_decode( wp_remote_retrieve_body( $response ), true );

            if ( $status_code >= 200 && $status_code < 300 ) {
                return is_array( $data ) ? $data : array();
            }

            $message = isset( $data['message'] ) ? $data['message'] : __( 'Trendyol API isteği başarısız oldu.', 'hediyesta-trendyol-sync' );

            return new \WP_Error(
                'trendyol_api_error',
                sprintf( __( 'Trendyol API hatası (%1$s): %2$s', 'hediyesta-trendyol-sync' ), $status_code, $message ),
                array(
                    'status_code' => $status_code,
                    'response'    => $data,
                )
            );
        }
    }
}
