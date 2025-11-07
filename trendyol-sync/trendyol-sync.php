<?php
/**
 * Plugin Name: Hediyesta Trendyol Sync
 * Plugin URI: https://www.hediyesta.com.tr
 * Description: WooCommerce ürünlerini Trendyol satıcı paneli ile güvenilir şekilde senkronize eder.
 * Version: 1.0.0
 * Author: Hediyesta
 * Author URI: https://www.hediyesta.com.tr
 * Text Domain: hediyesta-trendyol-sync
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

if ( ! class_exists( 'Hediyesta_Trendyol_Sync' ) ) {

    final class Hediyesta_Trendyol_Sync {

        const VERSION   = '1.0.0';
        const OPTION    = 'hediyesta_trendyol_settings';
        const CRON_HOOK = 'hediyesta_trendyol_sync_cron';

        /**
         * Holds the singleton instance.
         *
         * @var Hediyesta_Trendyol_Sync
         */
        private static $instance = null;

        /**
         * Returns the singleton instance.
         *
         * @return Hediyesta_Trendyol_Sync
         */
        public static function instance() {
            if ( null === self::$instance ) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        /**
         * Hediyesta_Trendyol_Sync constructor.
         */
        private function __construct() {
            $this->define_constants();
            $this->includes();
            $this->init_hooks();
        }

        /**
         * Define plugin constants.
         */
        private function define_constants() {
            if ( ! defined( 'HEDIYE_STA_TRENDYOL_FILE' ) ) {
                define( 'HEDIYE_STA_TRENDYOL_FILE', __FILE__ );
            }

            if ( ! defined( 'HEDIYE_STA_TRENDYOL_PATH' ) ) {
                define( 'HEDIYE_STA_TRENDYOL_PATH', plugin_dir_path( __FILE__ ) );
            }

            if ( ! defined( 'HEDIYE_STA_TRENDYOL_URL' ) ) {
                define( 'HEDIYE_STA_TRENDYOL_URL', plugin_dir_url( __FILE__ ) );
            }
        }

        /**
         * Include required files.
         */
        private function includes() {
            require_once HEDIYE_STA_TRENDYOL_PATH . 'includes/class-trendyol-api-client.php';
            require_once HEDIYE_STA_TRENDYOL_PATH . 'includes/class-trendyol-sync-service.php';
            require_once HEDIYE_STA_TRENDYOL_PATH . 'includes/admin/class-trendyol-settings-page.php';
        }

        /**
         * Initialize core hooks.
         */
        private function init_hooks() {
            add_action( 'plugins_loaded', array( $this, 'init_plugin' ) );
        }

        /**
         * Initialize plugin functionality.
         */
        public function init_plugin() {
            load_plugin_textdomain( 'hediyesta-trendyol-sync', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

            if ( ! class_exists( 'WooCommerce' ) ) {
                add_action( 'admin_notices', array( $this, 'missing_wc_notice' ) );
                return;
            }

            Hediyesta_Trendyol_Settings_Page::instance();
            Hediyesta_Trendyol_Sync_Service::instance();
        }

        /**
         * Admin notice when WooCommerce is missing.
         */
        public function missing_wc_notice() {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'Hediyesta Trendyol Sync eklentisi için WooCommerce kurulmalı ve aktif olmalıdır.', 'hediyesta-trendyol-sync' ) . '</p></div>';
        }

        /**
         * Run on plugin activation.
         */
        public static function activate() {
            if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
                wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', self::CRON_HOOK );
            }
        }

        /**
         * Run on plugin deactivation.
         */
        public static function deactivate() {
            $timestamp = wp_next_scheduled( self::CRON_HOOK );
            if ( $timestamp ) {
                wp_unschedule_event( $timestamp, self::CRON_HOOK );
            }
        }
    }
}

Hediyesta_Trendyol_Sync::instance();

register_activation_hook( __FILE__, array( 'Hediyesta_Trendyol_Sync', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Hediyesta_Trendyol_Sync', 'deactivate' ) );
