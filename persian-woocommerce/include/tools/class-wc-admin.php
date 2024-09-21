<?php

defined( 'ABSPATH' ) || exit;


use Carbon\Carbon;
use Morilog\Jalali\Jalalian;

if ( ! class_exists( 'PW_WC_Admin' ) ) {

	class PW_WC_Admin {

		public const PAGES = [
			'wc-admin',
		];

		public const PATHS = [
			'/analytics/overview',
			'/analytics/products',
			'/analytics/revenue',
			'/analytics/orders',
			'/analytics/variations',
			'/analytics/categories',
			'/analytics/coupons',
			'/analytics/taxes',
			'/analytics/downloads',
			'/analytics/stock',
		];

		public const REPORTS = [
			'revenue',
			'categories',
			'coupons',
			'customers',
			'downloads',
			'orders',
			'products',
			'taxes',
			'variations',
			'coupons_stats',
			'customers_stats',
			'downloads_stats',
			'orders_stats',
			'products_stats',
			'taxes_stats',
			'variations_stats',
		];

		public function __construct() {

			$is_jalali_enabled = PW()->get_options( 'enable_jalali_analytics', 'no' ) == 'yes';

			if ( ! $is_jalali_enabled ) {
				return;
			}

			if ( $this->is_target_page() ) {
				add_action( 'admin_enqueue_scripts', [ $this, 'jalali_frontend' ] );
				add_action( 'admin_enqueue_scripts', [ $this, 'admin_assets' ] );
			}

			foreach ( self::REPORTS as $report ) {
				add_filter( 'woocommerce_analytics_' . $report . '_query_args', [
					$this,
					'gregorian_query_dates',
				], 100 );

				add_filter( 'woocommerce_analytics_' . $report . '_select_query',
					[ $this, 'jalali_result_dates' ],
					100, 2
				);
			}

		}

		public function is_target_page(): bool {

			if ( ! isset( $_GET['path'] ) || ! isset( $_GET['page'] ) ) {
				return false;
			}

			$current_path = urldecode( $_GET['path'] );
			$current_page = $_GET['page'];

			$is_valid_page = in_array( $current_path, self::PATHS );
			$is_valid_path = in_array( $current_page, self::PAGES );

			if ( ! $is_valid_path || ! $is_valid_page ) {
				return false;
			}

			return true;
		}

		// Function to handle moment.js and reload jalali-moment in WordPresss
		public function jalali_frontend() {
			wp_dequeue_script( 'moment' );
			wp_deregister_script( 'moment' );

			wp_enqueue_script( 'moment',
				PW()->plugin_url( 'assets/js/jalali-moment/jalali-moment.browser.js' ),
				[],
				PW_VERSION
			);
			wp_add_inline_script( 'moment', 'moment.locale("fa");', 'after' );

		}

		// Function to load admin.js for admin-client area
		public function admin_assets() {
			wp_enqueue_style( 'pw-admin-client',
				PW()->plugin_url( 'assets/css/admin-client.css' ),
				[],
			);
			wp_enqueue_script( 'pw-admin-client',
				PW()->plugin_url( 'assets/js/admin-client.js' ),
				[ 'jquery', 'moment' ],
				PW_VERSION
			);
		}

		public function gregorian_query_dates( $query_vars ) {
			$keys = [ 'after', 'before', 'order_after', 'date_start', 'date_end' ];

			foreach ( $keys as $key ) {
				if ( isset( $query_vars[ $key ] ) ) {
					$query_vars[ $key ] = $this->convert_jalali_gregorian( $query_vars[ $key ] );
				}

			}

			return $query_vars;
		}

		public function jalali_result_dates( $result, $arg ) {
			$this->convert_gregorian_jalali_object( $result );

			return $result;
		}

		public function convert_gregorian_jalali_array( $array ) {

			foreach ( $array as $key => $value ) {

				if ( is_string( $value ) && strtotime( $value ) !== false ) {
					$array[ $key ] = $this->convert_gregorian_jalali( $value );
				} elseif ( is_array( $value ) ) {
					$array[ $key ] = $this->convert_gregorian_jalali_array( $value );
				} elseif ( is_object( $value ) ) {
					$array[ $key ] = $this->convert_gregorian_jalali_object( $value );
				}

			}

			return $array;
		}

		public function convert_gregorian_jalali_object( $object ) {

			foreach ( $object as $key => $value ) {

				if ( is_string( $value ) && strtotime( $value ) !== false ) {
					$object->$key = $this->convert_gregorian_jalali( $value );
				} elseif ( is_array( $value ) ) {
					$object->$key = $this->convert_gregorian_jalali_array( $value );
				} elseif ( is_object( $value ) ) {
					$object->$key = $this->convert_gregorian_jalali_object( $value );
				}
			}

			return $object;
		}

		public function convert_gregorian_jalali( $date ) {

			try {
				$jalali_date = Jalalian::fromCarbon( Carbon::parse( $date ) )->format( 'Y-m-d H:i:s' );

				return $jalali_date;
			} catch ( Exception $e ) {
				// If parsing fails, return the original date string
				return $date;
			}

		}

		public function convert_jalali_gregorian( $jalali_date ) {

			try {
				$jalalian = Jalalian::fromFormat( 'Y-m-d\TH:i:s', $jalali_date );
				$gregorian_date = $jalalian->toCarbon()->toString();

				return $gregorian_date;
			} catch ( Exception $e ) {
				// If parsing fails, return the original date string
				return $jalali_date;
			}

		}

	}

}

PW()->tools->wc_admin = new PW_WC_Admin();
