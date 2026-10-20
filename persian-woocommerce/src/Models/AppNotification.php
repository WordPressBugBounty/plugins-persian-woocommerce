<?php

namespace PersianWooCommerce\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class AppNotification
 *
 * @package PersianWooCommerce\Models
 *
 * @property int         $id
 * @property string      $event_id
 * @property string      $type
 * @property string      $title
 * @property string      $body
 * @property int         $order_id
 * @property string      $amount
 * @property array       $meta
 * @property Carbon|null $created_at
 *
 * @method static AppNotification create( array $attributes = [] )
 */
class AppNotification extends Model {

	protected $table = 'woocommerce_ir_app_notifications';

	protected $fillable = [
		'event_id',
		'type',
		'title',
		'body',
		'order_id',
		'amount',
		'meta',
		'created_at',
	];

	public $timestamps = false;

	protected $casts = [
		'order_id'   => 'integer',
		'meta'       => 'array',
		'created_at' => 'datetime',
	];
}