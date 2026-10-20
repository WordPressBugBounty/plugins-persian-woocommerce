<?php

namespace PersianWooCommerce\Services;

use Automattic\WooCommerce\Admin\API\Reports\Orders\DataStore as OrdersDataStore;
use Automattic\WooCommerce\Internal\Admin\Settings;
use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use PersianWooCommerce\Models\AppNotification;
use WC_Customer;

class AppService {

	public string $valid_order_statuses;

	public string $query_date_column;

	public string $table_order_stats;

	public string $table_operational_data;

	public string $table_product_lookup;

	public function __construct() {
		global $wpdb;

		$this->table_order_stats      = $wpdb->prefix . 'wc_order_stats';
		$this->table_operational_data = $wpdb->prefix . 'wc_order_operational_data';
		$this->table_product_lookup   = $wpdb->prefix . 'wc_order_product_lookup';

		$this->valid_order_statuses = $this->get_valid_order_statuses();
		$this->query_date_column    = $this->get_query_date_column();

		new AppNotificationService();
	}

	public function get_init(): array {

		return [
			'site_name'                   => get_bloginfo( 'name' ),
			'site_description'            => get_bloginfo( 'description' ),
			'site_icon_url'               => get_site_icon_url(),
			'persian_woocommerce_version' => PW_VERSION,
			'hpos_enalbed'                => function_exists( 'wc_get_container' ) ? wc_get_container()->get( CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled() : false,
		];
	}

	public function get_dashboard(): array {
		global $wpdb;

		$timezone = wp_timezone();

		$today_start = new DateTimeImmutable( 'today', $timezone );
		$today_end   = $today_start->setTime( 23, 59, 59 );

		$week_start = $today_start->modify( '-6 days' );
		$week_end   = $today_end;

		$previous_week_start = ( clone $week_start )->modify( '-7 days' );
		$previous_week_end   = ( clone $week_end )->modify( '-7 days' );

		$today_start_gmt         = $today_start->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
		$today_end_gmt           = $today_end->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
		$week_start_gmt          = $week_start->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
		$week_end_gmt            = $week_end->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
		$previous_week_start_gmt = $previous_week_start->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
		$previous_week_end_gmt   = $previous_week_end->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );

		$timezone_name = $timezone->getName();

		$daily_rows = $wpdb->get_results(
			$wpdb->prepare(
				"
				WITH RECURSIVE date_range AS (
					SELECT DATE(%s) AS date
					UNION ALL
					SELECT DATE_ADD(date, INTERVAL 1 DAY)
					FROM date_range
					WHERE date < DATE(%s)
				),
				daily_stats AS (
					SELECT
						DATE(
							CONVERT_TZ(
								date_created_gmt,
								'+00:00',
								%s
							)
						) AS date,
						COALESCE(
							SUM(
								CASE
									WHEN status IN ({$this->valid_order_statuses})
									THEN total_sales
									ELSE 0
								END
							),
							0
						) AS revenue,
						COUNT(
							CASE
								WHEN status IN ({$this->valid_order_statuses})
								THEN order_id
							END
						) AS order_count,
						COALESCE(
							SUM(
								CASE
									WHEN status IN ({$this->valid_order_statuses})
									THEN 1
									ELSE 0
								END
							),
							0
						) AS completed_count,
						COALESCE(
							SUM(
								CASE
									WHEN status NOT IN ({$this->valid_order_statuses})
									THEN 1
									ELSE 0
								END
							),
							0
						) AS failed_count
					FROM {$this->table_order_stats}
					WHERE date_created_gmt >= %s
					  AND date_created_gmt <= %s
					GROUP BY DATE(
						CONVERT_TZ(
							date_created_gmt,
							'+00:00',
							%s
						)
					)
				)
				SELECT
					date_range.date,
					COALESCE(daily_stats.revenue, 0) AS revenue,
					COALESCE(daily_stats.order_count, 0) AS order_count,
					COALESCE(daily_stats.completed_count, 0) AS completed_count,
					COALESCE(daily_stats.failed_count, 0) AS failed_count
				FROM date_range
				LEFT JOIN daily_stats
					ON daily_stats.date = date_range.date
				ORDER BY date_range.date ASC
				",
				$week_start->format( 'Y-m-d' ),
				$today_start->format( 'Y-m-d' ),
				$timezone_name,
				$week_start_gmt,
				$week_end_gmt,
				$timezone_name
			),
			ARRAY_A
		);

		$summary = $wpdb->get_row(
			$wpdb->prepare(
				"
				WITH current_period AS (
					SELECT
						COALESCE(
							SUM(
								CASE
									WHEN status IN ({$this->valid_order_statuses})
									THEN total_sales
									ELSE 0
								END
							),
							0
						) AS revenue,
						COUNT(
							CASE
								WHEN status IN ({$this->valid_order_statuses})
								THEN order_id
							END
						) AS order_count,
						COALESCE(
							SUM(
								CASE
									WHEN status IN ({$this->valid_order_statuses})
									THEN 1
									ELSE 0
								END
							),
							0
						) AS completed_count,
						COALESCE(
							SUM(
								CASE
									WHEN status NOT IN ({$this->valid_order_statuses})
									THEN 1
									ELSE 0
								END
							),
							0
						) AS failed_count
					FROM {$this->table_order_stats}
					WHERE date_created_gmt >= %s
					  AND date_created_gmt <= %s
				),
				today_period AS (
					SELECT
						COALESCE(
							SUM(
								CASE
									WHEN status IN ({$this->valid_order_statuses})
									THEN total_sales
									ELSE 0
								END
							),
							0
						) AS revenue,
						COUNT(
							CASE
								WHEN status IN ({$this->valid_order_statuses})
								THEN order_id
							END
						) AS order_count
					FROM {$this->table_order_stats}
					WHERE date_created_gmt >= %s
					  AND date_created_gmt <= %s
				),
				previous_period AS (
					SELECT
						COALESCE(
							SUM(
								CASE
									WHEN status IN ({$this->valid_order_statuses})
									THEN total_sales
									ELSE 0
								END
							),
							0
						) AS revenue,
						COUNT(
							CASE
								WHEN status IN ({$this->valid_order_statuses})
								THEN order_id
							END
						) AS order_count
					FROM {$this->table_order_stats}
					WHERE date_created_gmt >= %s
					  AND date_created_gmt <= %s
				)
				SELECT
					today_period.revenue AS today_revenue,
					today_period.order_count AS today_order_count,
					current_period.revenue AS week_revenue,
					current_period.order_count AS week_order_count,
					previous_period.revenue AS previous_week_revenue,
					previous_period.order_count AS previous_week_order_count,
					current_period.completed_count,
					current_period.failed_count
				FROM current_period
				CROSS JOIN today_period
				CROSS JOIN previous_period
				",
				$week_start_gmt,
				$week_end_gmt,
				$today_start_gmt,
				$today_end_gmt,
				$previous_week_start_gmt,
				$previous_week_end_gmt
			)
		);

		$today_items = $wpdb->get_var(
			$wpdb->prepare(
				"
				WITH today_items AS (
					SELECT COALESCE(
						SUM(lookup.product_qty),
						0
					) AS total_items
					FROM {$this->table_product_lookup} AS lookup
					INNER JOIN {$this->table_order_stats} AS stats
						ON stats.order_id = lookup.order_id
					WHERE stats.date_created_gmt >= %s
					  AND stats.date_created_gmt <= %s
					AND stats.status IN ({$this->valid_order_statuses})
				)
				SELECT total_items FROM today_items;
				",
				$today_start_gmt,
				$today_end_gmt
			)
		);

		$week_chart   = [];
		$persian_days = [ 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه', 'شنبه' ];

		foreach ( $daily_rows as $row ) {

			$date = new DateTimeImmutable( $row['date'], $timezone );

			$week_chart[] = [
				'label'  => $persian_days[ intval( $date->format( 'w' ) ) ],
				'amount' => floatval( $row['revenue'] ),
				'date'   => $row['date'],
			];
			
		}

		$week_revenue     = floatval( ( $summary->week_revenue ?? 0 ) );
		$week_order_count = intval( ( $summary->week_order_count ?? 0 ) );
		$previous_revenue = floatval( ( $summary->previous_week_revenue ?? 0 ) );
		$previous_orders  = intval( ( $summary->previous_week_order_count ?? 0 ) );

		$revenue_change_percent     = $previous_revenue > 0 ? ( ( $week_revenue - $previous_revenue ) / $previous_revenue ) * 100 : ( $week_revenue > 0 ? 100 : 0 );
		$transaction_change_percent = $previous_orders > 0 ? ( ( $week_order_count - $previous_orders ) / $previous_orders ) * 100 : ( $week_order_count > 0 ? 100 : 0 );

		return [
			'today'                      => [
				'revenue'     => floatval( ( $summary->today_revenue ?? 0 ) ),
				'order_count' => intval( ( $summary->today_order_count ?? 0 ) ),
				'item_count'  => intval( $today_items ),
			],
			'week_chart'                 => $week_chart,
			'status_breakdown'           => [
				'completed' => intval( ( $summary->completed_count ?? 0 ) ),
				'pending'   => intval( ( $summary->failed_count ?? 0 ) ),
				'failed'    => 0,
			],
			'revenue'                    => $week_revenue,
			'transaction_count'          => $week_order_count,
			'revenue_change_percent'     => $revenue_change_percent >= 0 ? ceil( $revenue_change_percent ) : floor( $revenue_change_percent ),
			'transaction_change_percent' => $transaction_change_percent >= 0 ? ceil( $transaction_change_percent ) : floor( $transaction_change_percent ),
			'from_bridge'                => true,
		];
	}

	public function sync( int $since ): array {
		global $wpdb;

		if ( $since <= 0 ) {
			$since = time() - DAY_IN_SECONDS;
		}

		$since_gmt = gmdate( 'Y-m-d H:i:s', $since );

		$orders_table         = $wpdb->prefix . 'wc_orders';
		$addresses_table      = $wpdb->prefix . 'wc_order_addresses';
		$order_items_table    = $wpdb->prefix . 'woocommerce_order_items';
		$order_itemmeta_table = $wpdb->prefix . 'woocommerce_order_itemmeta';
		$order_meta_table     = $wpdb->prefix . 'wc_orders_meta';

		$posts_table    = $wpdb->posts;
		$postmeta_table = $wpdb->postmeta;

		$orders = $wpdb->get_results(
			$wpdb->prepare(
				"
				WITH order_numbers AS (
					SELECT
						order_id,
						MAX(
							NULLIF(meta_value, '')
						) AS order_number
					FROM {$order_meta_table}
					WHERE meta_key = '_order_number'
					GROUP BY order_id
				),
				billing AS (
					SELECT
						order_id,
						MAX(
							CASE
								WHEN address_type = 'billing'
								THEN first_name
							END
						) AS first_name,
						MAX(
							CASE
								WHEN address_type = 'billing'
								THEN last_name
							END
						) AS last_name,
						MAX(
							CASE
								WHEN address_type = 'billing'
								THEN phone
							END
						) AS phone,
						MAX(
							CASE
								WHEN address_type = 'billing'
								THEN email
							END
						) AS email,
						MAX(
							CASE
								WHEN address_type = 'billing'
								THEN address_1
							END
						) AS address_1,
						MAX(
							CASE
								WHEN address_type = 'billing'
								THEN city
							END
						) AS city,
						MAX(
							CASE
								WHEN address_type = 'billing'
								THEN state
							END
						) AS state
					FROM {$addresses_table}
					WHERE address_type = 'billing'
					GROUP BY order_id
				)
				SELECT
					o.id,
					o.status,
					o.currency,
					o.total_amount,
					o.customer_id,
					o.payment_method_title,
					o.customer_note,
					o.date_created_gmt,
					o.date_updated_gmt,
					COALESCE(
						order_numbers.order_number,
						o.id
					) AS order_number,
					billing.first_name,
					billing.last_name,
					billing.phone,
					billing.email,
					billing.address_1,
					billing.city,
					billing.state
				FROM {$orders_table} AS o
				LEFT JOIN order_numbers
					ON order_numbers.order_id = o.id
				LEFT JOIN billing
					ON billing.order_id = o.id
				WHERE o.type = 'shop_order'
				  AND o.date_updated_gmt >= %s
				ORDER BY o.date_updated_gmt DESC
				LIMIT 100
				",
				$since_gmt
			)
		);

		$order_ids = wp_list_pluck( $orders, 'id' );

		$order_items = [];

		if ( ! empty( $order_ids ) ) {

			$order_ids_sql = implode( ',', array_map( 'absint', $order_ids ) );

			$items = $wpdb->get_results(
				"
				WITH item_meta AS (
					SELECT
						order_item_id,
						MAX(
							CASE
								WHEN meta_key = '_qty'
								THEN meta_value
							END
						) AS quantity,
						MAX(
							CASE
								WHEN meta_key = '_line_subtotal'
								THEN meta_value
							END
						) AS subtotal,
						MAX(
							CASE
								WHEN meta_key = '_line_total'
								THEN meta_value
							END
						) AS total,
						MAX(
							CASE
								WHEN meta_key = '_product_id'
								THEN meta_value
							END
						) AS product_id,
						MAX(
							CASE
								WHEN meta_key = '_variation_id'
								THEN meta_value
							END
						) AS variation_id
					FROM {$order_itemmeta_table}
					WHERE order_item_id IN (
						SELECT order_item_id
						FROM {$order_items_table}
						WHERE order_id IN ({$order_ids_sql})
						  AND order_item_type = 'line_item'
					)
					GROUP BY order_item_id
				)
				SELECT
					oi.order_id,
					oi.order_item_id,
					oi.order_item_name,
					item_meta.quantity,
					item_meta.subtotal,
					item_meta.total,
					item_meta.product_id,
					item_meta.variation_id
				FROM {$order_items_table} AS oi
				LEFT JOIN item_meta
					ON item_meta.order_item_id = oi.order_item_id
				WHERE oi.order_id IN ({$order_ids_sql})
				  AND oi.order_item_type = 'line_item'
				ORDER BY oi.order_item_id ASC
				"
			);

			$product_ids = [];

			foreach ( $items as $item ) {

				if ( intval( $item->product_id ) > 0 ) {
					$product_ids[] = intval( $item->product_id );
				}

				if ( intval( $item->variation_id ) > 0 ) {
					$product_ids[] = intval( $item->variation_id );
				}

			}

			$product_ids  = array_values( array_unique( $product_ids ) );
			$product_data = [];

			if ( ! empty( $product_ids ) ) {

				$product_ids_sql = implode( ',', array_map( 'absint', $product_ids ) );

				$product_rows = $wpdb->get_results(
					"
					SELECT
						p.ID,
						MAX(
							CASE
								WHEN pm.meta_key = '_sku'
								THEN pm.meta_value
							END
						) AS sku,
						MAX(
							CASE
								WHEN pm.meta_key = '_thumbnail_id'
								THEN pm.meta_value
							END
						) AS image_id
					FROM {$posts_table} AS p
					LEFT JOIN {$postmeta_table} AS pm
						ON pm.post_id = p.ID
						AND pm.meta_key IN (
							'_sku',
							'_thumbnail_id'
						)
					WHERE p.ID IN ({$product_ids_sql})
					GROUP BY p.ID
					"
				);

				foreach ( $product_rows as $product ) {
					$product_data[ intval( $product->ID ) ] = [
						'sku'      => $product->sku ?: '',
						'image_id' => intval( $product->image_id ),
					];
				}

			}

			foreach ( $items as $item ) {

				$order_id     = intval( $item->order_id );
				$product_id   = intval( $item->product_id );
				$variation_id = intval( $item->variation_id );

				$product_lookup_id = $variation_id > 0 ? $variation_id : $product_id;
				$product           = $product_data[ $product_lookup_id ] ?? null;
				$image             = '';

				if ( $product && $product['image_id'] ) {
					$image = wp_get_attachment_image_url( $product['image_id'], 'thumbnail' ) ?: '';
				}

				$quantity = intval( $item->quantity );
				$total    = intval( $item->total );

				$order_items[ $order_id ][] = [
					'id'           => intval( $item->order_item_id ),
					'name'         => $item->order_item_name,
					'quantity'     => $quantity,
					'price'        => $quantity > 0 ? $total / max( 1, $quantity ) : 0,
					'subtotal'     => $item->subtotal ?? '0',
					'total'        => $item->total ?? '0',
					'product_id'   => $product_id,
					'variation_id' => $variation_id,
					'sku'          => $product['sku'] ?? '',
					'image'        => $image ? [ 'src' => $image ] : null,
				];
			}

		}

		$timezone = wp_timezone();

		$order_response = array_map(
			function ( $order ) use ( $order_items, $timezone ) {
				$order_id = intval( $order->id );

				return [
					'id'                   => $order_id,
					'number'               => strval( $order->order_number ),
					'status'               => str_replace( 'wc-', '', $order->status ),
					'total'                => strval( $order->total_amount ),
					'currency'             => $order->currency,
					'currency_symbol'      => get_woocommerce_currency_symbol( $order->currency ),
					'customer_id'          => intval( $order->customer_id ),
					'customer_note'        => $order->customer_note ?? '',
					'payment_method_title' => $order->payment_method_title ?? '',
					'billing'              => [
						'first_name' => $order->first_name ?? '',
						'last_name'  => $order->last_name ?? '',
						'phone'      => $order->phone ?? '',
						'email'      => $order->email ?? '',
						'address_1'  => $order->address_1 ?? '',
						'city'       => $order->city ?? '',
						'state'      => $order->state ?? '',
					],
					'line_items'           => $order_items[ $order_id ] ?? [],
					'date_created'         => $this->format_gmt_date( $order->date_created_gmt, $timezone ),
					'date_modified'        => $this->format_gmt_date( $order->date_updated_gmt, $timezone ),
				];
			},
			$orders
		);

		$products = $wpdb->get_results(
			$wpdb->prepare(
				"
				WITH product_meta AS (
					SELECT
						post_id,
						MAX(
							CASE
								WHEN meta_key = '_sku'
								THEN meta_value
							END
						) AS sku,
						MAX(
							CASE
								WHEN meta_key = '_regular_price'
								THEN meta_value
							END
						) AS regular_price,
						MAX(
							CASE
								WHEN meta_key = '_sale_price'
								THEN meta_value
							END
						) AS sale_price,
						MAX(
							CASE
								WHEN meta_key = '_price'
								THEN meta_value
							END
						) AS price,
						MAX(
							CASE
								WHEN meta_key = '_manage_stock'
								THEN meta_value
							END
						) AS manage_stock,
						MAX(
							CASE
								WHEN meta_key = '_stock'
								THEN meta_value
							END
						) AS stock_quantity,
						MAX(
							CASE
								WHEN meta_key = '_stock_status'
								THEN meta_value
							END
						) AS stock_status,
						MAX(
							CASE
								WHEN meta_key = '_thumbnail_id'
								THEN meta_value
							END
						) AS image_id
					FROM {$postmeta_table}
					WHERE meta_key IN (
						'_sku',
						'_regular_price',
						'_sale_price',
						'_price',
						'_manage_stock',
						'_stock',
						'_stock_status',
						'_thumbnail_id'
					)
					GROUP BY post_id
				),
				product_types AS (
					SELECT
						tr.object_id AS product_id,
						MAX(t.slug) AS product_type
					FROM {$wpdb->term_relationships} AS tr
					INNER JOIN {$wpdb->term_taxonomy} AS tt
						ON tt.term_taxonomy_id = tr.term_taxonomy_id
						AND tt.taxonomy = 'product_type'
					INNER JOIN {$wpdb->terms} AS t
						ON t.term_id = tt.term_id
					GROUP BY tr.object_id
				)
				SELECT
					p.ID,
					p.post_title,
					p.post_name,
					p.post_status,
					p.post_modified_gmt,
					product_meta.sku,
					product_meta.regular_price,
					product_meta.sale_price,
					product_meta.price,
					product_meta.manage_stock,
					product_meta.stock_quantity,
					product_meta.stock_status,
					product_meta.image_id,
					COALESCE(
						product_types.product_type,
						'simple'
					) AS product_type
				FROM {$posts_table} AS p
				LEFT JOIN product_meta
					ON product_meta.post_id = p.ID
				LEFT JOIN product_types
					ON product_types.product_id = p.ID
				WHERE p.post_type = 'product'
				  AND p.post_modified_gmt >= %s
				ORDER BY p.post_modified_gmt DESC
				LIMIT 100
				",
				$since_gmt
			)
		);

		$product_response = array_map(
			function ( $product ) use ( $timezone ) {
				$image_id = intval( $product->image_id );
				$image    = $image_id ? ( wp_get_attachment_image_url( $image_id, 'medium' ) ?: '' ) : '';

				return [
					'id'             => intval( $product->ID ),
					'name'           => $product->post_title,
					'slug'           => $product->post_name,
					'status'         => $product->post_status,
					'type'           => $product->product_type ?: 'simple',
					'sku'            => $product->sku ?: '',
					'regular_price'  => $product->regular_price ?: '',
					'sale_price'     => $product->sale_price ?: '',
					'price'          => $product->price ?: '',
					'manage_stock'   => $product->manage_stock === 'yes',
					'stock_quantity' => $product->stock_quantity !== null ? floatval( $product->stock_quantity ) : null,
					'stock_status'   => $product->stock_status ?: 'instock',
					'images'         => $image
						? [
							[
								'id'  => $image_id,
								'src' => $image,
							],
						]
						: [],
					'date_modified'  => $this->format_gmt_date( $product->post_modified_gmt, $timezone ),
				];
			},
			$products
		);

		return [
			'timestamp' => time(),
			'since'     => $since,
			'orders'    => $order_response,
			'products'  => $product_response,
			'customers' => $this->get_changed_customers( $since ),
		];
	}

	public function get_notifications( int $limit = 50 ): array {
		$limit = max( 1, min( 100, $limit ) );

		return AppNotification::query()
		                      ->orderByDesc( 'id' )
		                      ->limit( $limit )
		                      ->get()
		                      ->map(
			                      function ( $notification ) {
				                      $meta        = $notification->meta ?? [];
				                      $status_from = $meta['status_from'] ?? $meta['old_status'] ?? null;
				                      $status_to   = $meta['status_to'] ?? $meta['new_status'] ?? null;

				                      return [
					                      'id'           => $notification->event_id,
					                      'type'         => $notification->type,
					                      'title'        => $notification->title,
					                      'body'         => $notification->body,
					                      'message'      => $notification->body,
					                      'order_id'     => $notification->order_id,
					                      'order_number' => isset( $meta['order_number'] )
						                      ? strval( $meta['order_number'] )
						                      : '',
					                      'amount'       => $notification->amount,
					                      'status_from'  => $status_from,
					                      'status_to'    => $status_to,
					                      'created_at'   => $notification->created_at
						                      ? $notification->created_at
							                      ->setTimezone( wp_timezone() )
							                      ->toIso8601String()
						                      : current_time( 'c' ),
					                      'meta'         => $meta,
				                      ];
			                      }
		                      )
		                      ->values()
		                      ->all();
	}

	private function get_changed_customers( int $since ): array {
		$out  = [];
		$seen = [];

		$registered = get_users( [
			'role'       => 'customer',
			'number'     => 100,
			'orderby'    => 'registered',
			'order'      => 'DESC',
			'date_query' => [
				[
					'after'     => gmdate( 'Y-m-d H:i:s', $since ),
					'inclusive' => true,
				],
			],
		] );

		foreach ( $registered as $user ) {

			$id          = intval( $user->ID );
			$seen[ $id ] = true;
			$formatted   = $this->format_customer( $id );

			if ( $formatted ) {
				$out[] = $formatted;
			}

		}

		$recent = get_users( [
			'role'    => 'customer',
			'number'  => 80,
			'orderby' => 'ID',
			'order'   => 'DESC',
		] );

		foreach ( $recent as $user ) {

			$id = intval( $user->ID );

			if ( isset( $seen[ $id ] ) ) {
				continue;
			}

			try {

				$customer = new WC_Customer( $id );
				$modified = $customer->get_date_modified();

				if ( $modified && $modified->getTimestamp() >= $since ) {

					$formatted = $this->format_customer( $id, $customer );

					if ( $formatted ) {
						$out[] = $formatted;
					}

				}

			} catch ( Exception $e ) {

				continue;

			}

		}

		return $out;
	}

	private function format_customer( int $user_id, ?WC_Customer $customer = null ): ?array {

		try {

			if ( ! $customer ) {
				$customer = new WC_Customer( $user_id );
			}

			$created = $customer->get_date_created();

			return [
				'id'           => $customer->get_id(),
				'first_name'   => $customer->get_first_name(),
				'last_name'    => $customer->get_last_name(),
				'email'        => $customer->get_email(),
				'username'     => $customer->get_username(),
				'date_created' => $created ? $created->date( 'c' ) : null,
				'orders_count' => $customer->get_order_count(),
				'total_spent'  => $customer->get_total_spent(),
				'avatar_url'   => get_avatar_url( $customer->get_id() ),
				'billing'      => [
					'phone'     => $customer->get_billing_phone(),
					'city'      => $customer->get_billing_city(),
					'state'     => $customer->get_billing_state(),
					'address_1' => $customer->get_billing_address_1(),
					'postcode'  => $customer->get_billing_postcode(),
				],
			];

		} catch ( Exception $e ) {

			return null;
		}
	}

	private function format_gmt_date( ?string $date, DateTimeZone $timezone ): ?string {
		if ( empty( $date ) ) {
			return null;
		}

		return ( new DateTimeImmutable( $date, new DateTimeZone( 'UTC' ) ) )
			->setTimezone( $timezone )
			->format( 'c' );
	}

	private function get_valid_order_statuses(): string {
		global $wpdb;

		$registered   = wc_get_order_statuses();
		$unregistered = $this->get_unregistered_order_statuses();
		$all_statuses = array_merge( $registered, $unregistered );

		$formatted = Settings::get_order_statuses( $all_statuses );
		$all_slugs = array_keys( $formatted );

		$excluded_option = get_option( 'woocommerce_excluded_report_order_statuses', [] );
		$hard_exclusions = [ 'pending', 'failed', 'cancelled', 'auto-draft', 'trash', 'refunded' ];

		$excluded = array_unique( array_merge( $excluded_option, $hard_exclusions ) );
		$included = array_diff( $all_slugs, $excluded );

		$prefixed = array_map( function ( $status ) {
			return 'wc-' . $status;
		}, $included );

		if ( empty( $prefixed ) ) {
			return '';
		}

		$placeholders = implode( ',', array_fill( 0, count( $prefixed ), '%s' ) );

		return $wpdb->prepare( $placeholders, $prefixed );
	}

	private function get_unregistered_order_statuses(): array {
		$registered = wc_get_order_statuses();
		$all_synced = OrdersDataStore::get_all_statuses();

		$unregistered = array_diff( $all_synced, array_keys( $registered ) );

		if ( empty( $unregistered ) ) {
			return [];
		}

		$formatted = Settings::get_order_statuses(
			array_fill_keys( $unregistered, '' )
		);

		$keys = array_keys( $formatted );

		return array_combine( $keys, $keys );
	}

	private function get_query_date_column(): string {
		$column = get_option( 'woocommerce_date_type', 'date_paid' );

		return ! in_array( $column, [ 'date_created', 'date_paid', 'date_completed' ] ) ? 'date_created' : $column;
	}
}
