<?php

namespace PersianWooCommerce\API;

use PersianWooCommerce\Services\AppNotificationService;
use PersianWooCommerce\Services\AppService;
use WC_REST_Authentication;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class AppAPI extends RestAPI {

	public AppService $service;

	public function __construct() {
		parent::__construct();
		// @todo: remove these codes and fix the android client
		add_filter( 'rest_endpoints', function ( $endpoints ) {

			$route = '/wc/v3/reports/sales';

			if ( empty( $endpoints[ $route ] ) ) {
				return $endpoints;
			}

			foreach ( $endpoints[ $route ] as $index => $endpoint ) {

				if ( empty( $endpoint['args']['period'] ) ) {
					continue;
				}

				if ( ! in_array( 'custom', $endpoint['args']['period']['enum'], true ) ) {
					$endpoints[ $route ][ $index ]['args']['period']['enum'][] = 'custom';
				}
			}

			return $endpoints;

		} );

		add_filter( 'rest_request_before_callbacks', function ( $response, $handler, $request ) {

			if ( '/wc/v3/reports/sales' !== $request->get_route() ) {
				return $response;
			}

			if ( 'custom' !== $request->get_param( 'period' ) ) {
				return $response;
			}

			$request->set_param( 'period', '' );

			return $response;

		}, 10, 3 );

		$this->service = new AppService();
	}

	public function register_routes() {

		register_rest_route( 'wc/persian-woocommerce/app/v1', '/init', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'init' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( 'wc/persian-woocommerce/app/v1', '/dashboard', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'dashboard' ],
			'permission_callback' => [ $this, 'permission_callback' ],
		] );

		register_rest_route( 'wc/persian-woocommerce/app/v1', '/sync', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'sync' ],
			'permission_callback' => [ $this, 'permission_callback' ],
			'args'                => [
				'since' => [
					'required' => true,
					'type'     => 'integer',
				],
			],
		] );

		register_rest_route( 'wc/persian-woocommerce/app/v1', '/notifications', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'notifications' ],
			'permission_callback' => [ $this, 'permission_callback' ],
			'args'                => [
				'limit' => [
					'required' => false,
					'type'     => 'integer',
					'default'  => 50,
				],
			],
		] );
	}

	public function init( WP_REST_Request $request ) {

		self::response( true, null,
			$this->service->get_init()
		);

	}

	public function dashboard( WP_REST_Request $request ) {

		self::response( true, null,
			$this->service->get_dashboard()
		);

	}

	public function sync( WP_REST_Request $request ) {

		$since = intval( $request->get_param( 'since' ) );

		self::response( true, null,
			$this->service->sync( $since )
		);

	}

	public function notifications( WP_REST_Request $request ) {

		$limit = intval( $request->get_param( 'limit' ) );

		self::response( true, null,
			$this->service->get_notifications( $limit )
		);

	}

	public function permission_callback( WP_REST_Request $request ): bool {

		$user_id = WC_REST_Authentication::instance()->authenticate( false );

		if ( ! $user_id ) {
			return false;
		}

		return user_can( $user_id, 'manage_woocommerce' );
	}
}