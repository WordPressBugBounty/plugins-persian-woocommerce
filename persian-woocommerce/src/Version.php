<?php

namespace PersianWooCommerce;


use Illuminate\Database\Schema\Blueprint;
use Nabik_Net_Database;

class Version extends \Nabik\Utils\V1\Version {

	protected string $current_version = PW_VERSION;

	public function install() {
		parent::install();

		$this->redirect_after_activation();
	}

	protected function redirect_after_activation(): void {
		wp_safe_redirect( admin_url( 'admin.php?page=persian-wc-about' ) );
		exit;
	}

	public function updated() {

		flush_rewrite_rules();

	}

	public function update_1007() {

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
}