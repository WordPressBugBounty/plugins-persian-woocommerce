<?php

namespace PersianWooCommerce\Services;

use Exception;
use PersianWooCommerce\Models\AppNotification;
use WC_Order;

class AppNotificationService {

	public function __construct() {
		add_action( 'woocommerce_new_order', [ $this, 'on_new_order' ], 20, 2 );
		add_action( 'woocommerce_checkout_order_processed', [ $this, 'on_checkout_order_processed' ], 20, 3 );
		add_action( 'woocommerce_order_status_changed', [ $this, 'on_status_changed' ], 20, 4 );
	}

	public function on_new_order( int $order_id, WC_Order $order ): void {
		if ( $order_id <= 0 ) {
			return;
		}

		if ( get_transient( 'persian_woocommerce_app_notify_order_' . $order_id ) ) {
			return;
		}

		set_transient( 'persian_woocommerce_app_notify_order_' . $order_id, 1, 5 * MINUTE_IN_SECONDS );
	}

	public function on_checkout_order_processed( int $order_id, array $posted_data, WC_Order $order ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$total     = $order->get_total();
		$number    = $order->get_order_number();
		$formatted = html_entity_decode( wp_strip_all_tags( wc_price( $total ) ), ENT_QUOTES, 'UTF-8' );

		$this->add(
			'new_order',
			__( 'سفارش جدید', 'persian-woocommerce' ),
			sprintf(
				__( 'سفارش #%1$s به مبلغ %2$s ثبت شد', 'persian-woocommerce' ),
				$number,
				$formatted
			),
			$order_id,
			strval( $total ),
			[
				'order_number' => $number,
				'status'       => $order->get_status(),
			]
		);
	}

	public function on_status_changed( $order_id, $old_status, $new_status, $order ): void {

		if ( ! $order instanceof WC_Order ) {
			$order = wc_get_order( $order_id );
		}

		if ( ! $order ) {
			return;
		}

		if ( in_array( $old_status, [ 'new', 'auto-draft' ] ) ) {
			return;
		}

		$number   = $order->get_order_number();
		$old_name = wc_get_order_status_name( $old_status );
		$new_name = wc_get_order_status_name( $new_status );

		$type  = 'status_change';
		$title = __( 'وضعیت سفارش تغییر یافت', 'persian-woocommerce' );

		$body = sprintf(
		/* translators: 1: order number, 2: old status, 3: new status */
			__( 'سفارش #%1$s: %2$s ← %3$s', 'persian-woocommerce' ),
			$number,
			$old_name,
			$new_name
		);

		if ( 'cancelled' === $new_status ) {

			$type  = 'order_cancelled';
			$title = __( 'لغو سفارش', 'persian-woocommerce' );

		} elseif ( 'refunded' === $new_status ) {

			$type  = 'refund';
			$title = __( 'بازپرداخت سفارش', 'persian-woocommerce' );

		} elseif ( 'failed' === $new_status ) {

			$type  = 'payment_failed';
			$title = __( 'پرداخت ناموفق', 'persian-woocommerce' );

		} elseif (
			in_array( $new_status, [ 'processing', 'completed' ] )
			&& in_array( $old_status, [ 'pending', 'on-hold' ] )
		) {

			$type  = 'payment_success';
			$title = __( 'پرداخت موفق', 'persian-woocommerce' );

			$body = sprintf(
				__( 'پرداخت سفارش #%s با موفقیت انجام شد.', 'persian-woocommerce' ),
				$number
			);
		}

		$this->add( $type, $title, $body,
			intval( $order_id ),
			strval( $order->get_total() ),
			[
				'order_number' => $number,
				'status_from'  => $old_status,
				'status_to'    => $new_status,
			]
		);
	}

	public function add( string $type, string $title, string $body, int $order_id = 0, string $amount = '', array $meta = [] ): string {

		$event_id = md5( $type . '|' . $order_id . '|' . time() . '|' . wp_generate_password( 8, false ) );

		try {

			AppNotification::create( [
				'event_id'   => $event_id,
				'type'       => sanitize_key( $type ),
				'title'      => sanitize_text_field( $title ),
				'body'       => sanitize_textarea_field( $body ),
				'order_id'   => $order_id,
				'amount'     => sanitize_text_field( $amount ),
				'meta'       => $meta,
				'created_at' => current_time( 'mysql' ),
			] );

			$this->cleanup();

		} catch ( Exception $e ) {
			return '';
		}

		return $event_id;
	}

	public function list_recent( int $limit = 50 ): array {

		$limit = max( 1, min( 100, $limit ) );

		return AppNotification::query()
		                      ->orderByDesc( 'id' )
		                      ->limit( $limit )
		                      ->get()
		                      ->map(
			                      function ( AppNotification $notification ) {

				                      $meta = is_array( $notification->meta )
					                      ? $notification->meta
					                      : [];

				                      $order_number = isset( $meta['order_number'] ) ? (string) $meta['order_number'] : '';

				                      $status_from = $meta['status_from'] ?? $meta['old_status'] ?? null;

				                      $status_to = $meta['status_to'] ?? $meta['new_status'] ?? null;

				                      return [
					                      'id'           => $notification->event_id,
					                      'type'         => $notification->type,
					                      'title'        => $notification->title,
					                      'body'         => $notification->body,
					                      'message'      => $notification->body,
					                      'order_id'     => (int) $notification->order_id,
					                      'order_number' => $order_number,
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
		                      ->all();
	}

	private function cleanup(): void {

		$keep_ids = AppNotification::query()
		                           ->orderByDesc( 'id' )
		                           ->limit( 200 )
		                           ->pluck( 'id' );

		if ( $keep_ids->count() >= 200 ) {
			AppNotification::query()->whereNotIn( 'id', $keep_ids->all() )->delete();
		}
	}

}