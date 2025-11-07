<?php
/**
 * Trendyol ayarları yönetimi.
 *
 * @package Hediyesta_Trendyol_Sync
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'Hediyesta_Trendyol_Settings_Page' ) ) {

    class Hediyesta_Trendyol_Settings_Page {

        const MENU_SLUG = 'hediyesta-trendyol-sync';

        /**
         * Singleton örneği.
         *
         * @var Hediyesta_Trendyol_Settings_Page
         */
        protected static $instance = null;

        /**
         * Instance döndürür.
         *
         * @return Hediyesta_Trendyol_Settings_Page
         */
        public static function instance() {
            if ( null === self::$instance ) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        /**
         * Constructor.
         */
        private function __construct() {
            add_action( 'admin_menu', array( $this, 'register_menu' ) );
            add_action( 'admin_init', array( $this, 'register_settings' ) );
        }

        /**
         * Yönetim menüsünü kaydeder.
         */
        public function register_menu() {
            add_submenu_page(
                'woocommerce',
                __( 'Trendyol Senkronizasyonu', 'hediyesta-trendyol-sync' ),
                __( 'Trendyol Senkronizasyonu', 'hediyesta-trendyol-sync' ),
                'manage_woocommerce',
                self::MENU_SLUG,
                array( $this, 'render_page' )
            );
        }

        /**
         * Ayar alanlarını kaydeder.
         */
        public function register_settings() {
            register_setting( 'hediyesta_trendyol_settings', Hediyesta_Trendyol_Sync::OPTION, array( $this, 'sanitize_settings' ) );

            add_settings_section(
                'hediyesta_trendyol_credentials',
                __( 'API Bilgileri', 'hediyesta-trendyol-sync' ),
                '__return_false',
                self::MENU_SLUG
            );

            add_settings_field(
                'supplier_id',
                __( 'Tedarikçi (Supplier) ID', 'hediyesta-trendyol-sync' ),
                array( $this, 'render_text_field' ),
                self::MENU_SLUG,
                'hediyesta_trendyol_credentials',
                array(
                    'id'          => 'supplier_id',
                    'description'=> __( 'Trendyol panelinizde yer alan tedarikçi ID değeri.', 'hediyesta-trendyol-sync' ),
                )
            );

            add_settings_field(
                'username',
                __( 'Kullanıcı Adı', 'hediyesta-trendyol-sync' ),
                array( $this, 'render_text_field' ),
                self::MENU_SLUG,
                'hediyesta_trendyol_credentials',
                array(
                    'id' => 'username',
                )
            );

            add_settings_field(
                'password',
                __( 'Şifre', 'hediyesta-trendyol-sync' ),
                array( $this, 'render_password_field' ),
                self::MENU_SLUG,
                'hediyesta_trendyol_credentials',
                array(
                    'id' => 'password',
                )
            );

            add_settings_section(
                'hediyesta_trendyol_defaults',
                __( 'Varsayılan Değerler', 'hediyesta-trendyol-sync' ),
                '__return_false',
                self::MENU_SLUG
            );

            add_settings_field(
                'default_brand_id',
                __( 'Varsayılan Marka ID', 'hediyesta-trendyol-sync' ),
                array( $this, 'render_text_field' ),
                self::MENU_SLUG,
                'hediyesta_trendyol_defaults',
                array(
                    'id'          => 'default_brand_id',
                    'description'=> __( 'Ürün üzerinde değer bulunmadığında kullanılacak Trendyol marka ID değeri.', 'hediyesta-trendyol-sync' ),
                )
            );

            add_settings_field(
                'default_category_id',
                __( 'Varsayılan Kategori ID', 'hediyesta-trendyol-sync' ),
                array( $this, 'render_text_field' ),
                self::MENU_SLUG,
                'hediyesta_trendyol_defaults',
                array(
                    'id'          => 'default_category_id',
                    'description'=> __( 'Ürün üzerinde değer bulunmadığında kullanılacak Trendyol kategori ID değeri.', 'hediyesta-trendyol-sync' ),
                )
            );

            add_settings_field(
                'default_vat_rate',
                __( 'Varsayılan KDV Oranı', 'hediyesta-trendyol-sync' ),
                array( $this, 'render_number_field' ),
                self::MENU_SLUG,
                'hediyesta_trendyol_defaults',
                array(
                    'id'          => 'default_vat_rate',
                    'description'=> __( 'Ürün üzerinde KDV oranı yoksa kullanılacak değer.', 'hediyesta-trendyol-sync' ),
                    'step'        => '1',
                    'min'         => '0',
                )
            );

            add_settings_field(
                'default_dimensional_w',
                __( 'Varsayılan Desi (Dimensional Weight)', 'hediyesta-trendyol-sync' ),
                array( $this, 'render_number_field' ),
                self::MENU_SLUG,
                'hediyesta_trendyol_defaults',
                array(
                    'id'          => 'default_dimensional_w',
                    'description'=> __( 'Ürünlerde ölçü bilgisi yoksa kullanılacak desi değeri.', 'hediyesta-trendyol-sync' ),
                    'step'        => '0.1',
                    'min'         => '0',
                )
            );

            add_settings_field(
                'default_currency',
                __( 'Para Birimi', 'hediyesta-trendyol-sync' ),
                array( $this, 'render_select_field' ),
                self::MENU_SLUG,
                'hediyesta_trendyol_defaults',
                array(
                    'id'      => 'default_currency',
                    'options' => array(
                        'TRY' => 'TRY',
                        'USD' => 'USD',
                        'EUR' => 'EUR',
                    ),
                )
            );

            add_settings_field(
                'default_cargo_company',
                __( 'Varsayılan Kargo Firması ID', 'hediyesta-trendyol-sync' ),
                array( $this, 'render_text_field' ),
                self::MENU_SLUG,
                'hediyesta_trendyol_defaults',
                array(
                    'id'          => 'default_cargo_company',
                    'description'=> __( 'Trendyol API tarafından verilen kargo firması ID değeri.', 'hediyesta-trendyol-sync' ),
                )
            );

            add_settings_section(
                'hediyesta_trendyol_automation',
                __( 'Otomasyon', 'hediyesta-trendyol-sync' ),
                '__return_false',
                self::MENU_SLUG
            );

            add_settings_field(
                'enable_cron',
                __( 'Saatlik WP-Cron Senkronizasyonu', 'hediyesta-trendyol-sync' ),
                array( $this, 'render_checkbox_field' ),
                self::MENU_SLUG,
                'hediyesta_trendyol_automation',
                array(
                    'id'          => 'enable_cron',
                    'description'=> __( 'Aktif olduğunda ürünler saatlik olarak Trendyol ile eşitlenir.', 'hediyesta-trendyol-sync' ),
                )
            );
        }

        /**
         * Ayarları filtreler.
         *
         * @param array $input Ham input.
         *
         * @return array
         */
        public function sanitize_settings( $input ) {
            $output = array();

            $output['supplier_id']           = isset( $input['supplier_id'] ) ? sanitize_text_field( $input['supplier_id'] ) : '';
            $output['username']              = isset( $input['username'] ) ? sanitize_text_field( $input['username'] ) : '';
            $output['password']              = isset( $input['password'] ) ? sanitize_text_field( $input['password'] ) : '';
            $output['default_brand_id']      = isset( $input['default_brand_id'] ) ? absint( $input['default_brand_id'] ) : '';
            $output['default_category_id']   = isset( $input['default_category_id'] ) ? absint( $input['default_category_id'] ) : '';
            $output['default_vat_rate']      = isset( $input['default_vat_rate'] ) ? absint( $input['default_vat_rate'] ) : 18;
            $output['default_dimensional_w'] = isset( $input['default_dimensional_w'] ) ? floatval( $input['default_dimensional_w'] ) : 1;
            $output['default_currency']      = isset( $input['default_currency'] ) ? strtoupper( sanitize_text_field( $input['default_currency'] ) ) : 'TRY';
            $output['default_cargo_company'] = isset( $input['default_cargo_company'] ) ? absint( $input['default_cargo_company'] ) : '';
            $output['enable_cron']           = ( isset( $input['enable_cron'] ) && 'yes' === $input['enable_cron'] ) ? 'yes' : 'no';

            return $output;
        }

        /**
         * Ayar sayfasını render eder.
         */
        public function render_page() {
            if ( ! current_user_can( 'manage_woocommerce' ) ) {
                wp_die( esc_html__( 'Bu sayfaya erişim yetkiniz yok.', 'hediyesta-trendyol-sync' ) );
            }

            $settings   = get_option( Hediyesta_Trendyol_Sync::OPTION, array() );
            $last_sync  = get_option( 'hediyesta_trendyol_last_sync', false );
            $message    = isset( $_GET['hediyesta_message'] ) ? sanitize_text_field( wp_unslash( $_GET['hediyesta_message'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $status     = isset( $_GET['hediyesta_synced'] ) ? sanitize_text_field( wp_unslash( $_GET['hediyesta_synced'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

            ?>
            <div class="wrap">
                <h1><?php esc_html_e( 'Trendyol Senkronizasyonu', 'hediyesta-trendyol-sync' ); ?></h1>

                <?php if ( $message ) : ?>
                    <div class="notice notice-<?php echo ( '1' === $status ) ? 'success' : 'error'; ?> is-dismissible">
                        <p><?php echo esc_html( $message ); ?></p>
                    </div>
                <?php endif; ?>

                <form action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" method="post">
                    <?php
                    settings_fields( 'hediyesta_trendyol_settings' );
                    do_settings_sections( self::MENU_SLUG );
                    submit_button( __( 'Ayarları Kaydet', 'hediyesta-trendyol-sync' ) );
                    ?>
                </form>

                <hr />

                <h2><?php esc_html_e( 'Manuel Ürün Senkronizasyonu', 'hediyesta-trendyol-sync' ); ?></h2>
                <p><?php esc_html_e( 'Tüm yayınlanmış ürünlerinizi Trendyol hesabınız ile hemen eşitlemek için butona tıklayın.', 'hediyesta-trendyol-sync' ); ?></p>
                <form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
                    <?php wp_nonce_field( 'hediyesta_trendyol_manual_sync' ); ?>
                    <input type="hidden" name="action" value="hediyesta_trendyol_manual_sync" />
                    <?php submit_button( __( 'Ürünleri Şimdi Senkronize Et', 'hediyesta-trendyol-sync' ), 'secondary large', 'submit', false ); ?>
                </form>

                <?php if ( $last_sync ) : ?>
                    <p>
                        <?php
                        printf(
                            /* translators: %s: formatted date */
                            esc_html__( 'Son başarılı senkronizasyon: %s', 'hediyesta-trendyol-sync' ),
                            esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_sync ) )
                        );
                        ?>
                    </p>
                <?php endif; ?>
            </div>
            <?php
        }

        /**
         * Metin alanı render eder.
         *
         * @param array $args Alan argümanları.
         */
        public function render_text_field( $args ) {
            $options = get_option( Hediyesta_Trendyol_Sync::OPTION, array() );
            $value   = isset( $options[ $args['id'] ] ) ? $options[ $args['id'] ] : '';
            ?>
            <input type="text" id="<?php echo esc_attr( $args['id'] ); ?>" name="<?php echo esc_attr( Hediyesta_Trendyol_Sync::OPTION . '[' . $args['id'] . ']' ); ?>" value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
            <?php if ( ! empty( $args['description'] ) ) : ?>
                <p class="description"><?php echo esc_html( $args['description'] ); ?></p>
            <?php endif; ?>
            <?php
        }

        /**
         * Şifre alanı render eder.
         *
         * @param array $args Alan argümanları.
         */
        public function render_password_field( $args ) {
            $options = get_option( Hediyesta_Trendyol_Sync::OPTION, array() );
            $value   = isset( $options[ $args['id'] ] ) ? $options[ $args['id'] ] : '';
            ?>
            <input type="password" id="<?php echo esc_attr( $args['id'] ); ?>" name="<?php echo esc_attr( Hediyesta_Trendyol_Sync::OPTION . '[' . $args['id'] . ']' ); ?>" value="<?php echo esc_attr( $value ); ?>" class="regular-text" autocomplete="new-password" />
            <?php
        }

        /**
         * Sayısal alanı render eder.
         *
         * @param array $args Alan argümanları.
         */
        public function render_number_field( $args ) {
            $options = get_option( Hediyesta_Trendyol_Sync::OPTION, array() );
            $value   = isset( $options[ $args['id'] ] ) ? $options[ $args['id'] ] : '';
            ?>
            <input type="number" id="<?php echo esc_attr( $args['id'] ); ?>" name="<?php echo esc_attr( Hediyesta_Trendyol_Sync::OPTION . '[' . $args['id'] . ']' ); ?>" value="<?php echo esc_attr( $value ); ?>" class="small-text" step="<?php echo esc_attr( isset( $args['step'] ) ? $args['step'] : '1' ); ?>" min="<?php echo esc_attr( isset( $args['min'] ) ? $args['min'] : '0' ); ?>" />
            <?php if ( ! empty( $args['description'] ) ) : ?>
                <p class="description"><?php echo esc_html( $args['description'] ); ?></p>
            <?php endif; ?>
            <?php
        }

        /**
         * Seçim alanını render eder.
         *
         * @param array $args Alan argümanları.
         */
        public function render_select_field( $args ) {
            $options = get_option( Hediyesta_Trendyol_Sync::OPTION, array() );
            $value   = isset( $options[ $args['id'] ] ) ? $options[ $args['id'] ] : '';
            ?>
            <select id="<?php echo esc_attr( $args['id'] ); ?>" name="<?php echo esc_attr( Hediyesta_Trendyol_Sync::OPTION . '[' . $args['id'] . ']' ); ?>">
                <?php foreach ( $args['options'] as $key => $label ) : ?>
                    <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $value, $key ); ?>><?php echo esc_html( $label ); ?></option>
                <?php endforeach; ?>
            </select>
            <?php
        }

        /**
         * Checkbox alanı render eder.
         *
         * @param array $args Alan argümanları.
         */
        public function render_checkbox_field( $args ) {
            $options = get_option( Hediyesta_Trendyol_Sync::OPTION, array() );
            $value   = isset( $options[ $args['id'] ] ) ? $options[ $args['id'] ] : 'yes';
            ?>
            <label for="<?php echo esc_attr( $args['id'] ); ?>">
                <input type="checkbox" id="<?php echo esc_attr( $args['id'] ); ?>" name="<?php echo esc_attr( Hediyesta_Trendyol_Sync::OPTION . '[' . $args['id'] . ']' ); ?>" value="yes" <?php checked( $value, 'yes' ); ?> />
                <?php if ( ! empty( $args['description'] ) ) : ?>
                    <?php echo esc_html( $args['description'] ); ?>
                <?php endif; ?>
            </label>
            <?php
        }
    }
}
