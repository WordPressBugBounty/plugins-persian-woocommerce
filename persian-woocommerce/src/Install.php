<?php

namespace PersianWooCommerce;

use Illuminate\Database\Schema\Blueprint;
use Nabik_Net_Database;

class Install extends \Nabik\Utils\V1\Install {

	public function tasks() {
		self::create_tables();
		self::remove_legacy_data();
		self::set_legacy_checkout_page();
	}

	public static function create_tables() {

		if ( ! Nabik_Net_Database::Schema()->hasTable( 'woocommerce_ir' ) ) {

			Nabik_Net_Database::Schema()->create( 'woocommerce_ir', function ( Blueprint $table ) {
				$table->increments( 'id' );
				$table->text( 'text1' );
				$table->text( 'text2' )->nullable();
			} );

		}

		if ( ! Nabik_Net_Database::Schema()->hasTable( 'woocommerce_ir_app_notifications' ) ) {

			Nabik_Net_Database::Schema()->create( 'woocommerce_ir_app_notifications', function ( Blueprint $table ) {
				$table->bigIncrements( 'id' );
				$table->string( 'event_id', 64 )->unique();
				$table->string( 'type', 64 );
				$table->string( 'title', 255 );
				$table->text( 'body' );
				$table->unsignedBigInteger( 'order_id' )->default( 0 );
				$table->string( 'amount', 64 )->default( '' );
				$table->longText( 'meta' )->nullable();
				$table->dateTime( 'created_at' )->index();
			} );

		}

	}

	public static function remove_legacy_data() {
		self::remove_legacy_tables();
		self::remove_legacy_options();
	}

	private static function remove_legacy_tables() {

		$deprecated_tables = [
			'woocommerce_ir_cities',
			'Woo_Iran_Cities_By_HANNANStd'
		];

		foreach ( $deprecated_tables as $table ) {

			if ( Nabik_Net_Database::Schema()->hasTable( $table ) ) {
				Nabik_Net_Database::Schema()->drop( $table );
			}

		}

	}

	private static function remove_legacy_options() {

		$deprecated_options = [
			'is_cities_installed',
			'pw_delete_city_table_2_5',
			'woocommerce_persian_feed',
			'redirect_to_woo_persian_about_page',
			'enable_woocommerce_notice_dismissed',
			'Persian_Woocommerce_rename_old_table',
		];

		foreach ( $deprecated_options as $option ) {
			delete_option( $option );
		}

		for ( $i = 0; $i < 10; $i ++ ) {
			delete_option( 'persian_woo_notice_number_' . $i );
		}

	}

	public static function set_legacy_checkout_page(): void {

		if ( ! function_exists( 'wc_get_page_id' ) ) {
			return;
		}

		$checkout_page_id = wc_get_page_id( 'checkout' );

		if ( - 1 == $checkout_page_id ) {
			return;
		}

		$current_content = get_post_field( 'post_content', $checkout_page_id );

		if ( '' === $current_content ) {
			return;
		}

		if ( str_contains( $current_content, '[woocommerce_checkout]' ) ) {
			return;
		}

		$updated_content = preg_replace( '/<!-- wp:woocommerce\/checkout -->(.*?)<!-- \/wp:woocommerce\/checkout -->/s', '[woocommerce_checkout]', $current_content );

		wp_update_post( [
			'ID'           => $checkout_page_id,
			'post_content' => $updated_content,
		] );

	}
}