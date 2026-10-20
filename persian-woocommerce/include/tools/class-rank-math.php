<?php

use Morilog\Jalali\Jalalian;

defined( 'ABSPATH' ) || exit;

class PW_Tools_Rank_Math {

	public function __construct() {
		add_filter( 'rank_math/replacements', [ $this, 'replacements' ], 10, 2 );
		add_filter( 'rank_math/snippet/rich_snippet_product_entity', [ $this, 'product_entity' ], 20 );
	}

	public function replacements( $replacements, $args ) {

		if ( isset( $replacements['%date(Y-m-d\TH:i:sP)%'] ) ) {
			$replacements['%date(Y-m-d\TH:i:sP)%'] = date( 'c', strtotime( $args->post_date ?? date( 'c' ) ) );
		}

		if ( isset( $replacements['%modified(Y-m-d\TH:i:sP)%'] ) ) {
			$replacements['%modified(Y-m-d\TH:i:sP)%'] = date( 'c', strtotime( $args->post_modified ?? date( 'c' ) ) );
		}

		return $replacements;
	}

	public function product_entity( $entity ) {

		if ( empty( $entity['hasVariant'] ) || ! is_array( $entity['hasVariant'] ) ) {
			return $entity;
		}

		foreach ( $entity['hasVariant'] as $key => $variant ) {

			if ( ( 'Product' === $variant['@type'] ?? '' ) && isset( $variant['offers']['priceValidUntil'] ) ) {
				try {
					$timestamp = Jalalian::fromFormat( 'Y-m-d', $variant['offers']['priceValidUntil'] )->toCarbon()->timestamp;

					$entity['hasVariant'][ $key ]['offers']['priceValidUntil'] = ( new DateTimeImmutable( '@' . $timestamp ) )
						->setTimezone( wp_timezone() )
						->format( 'Y-m-d' );
				} catch ( Exception $e ) {
					// Original value
				}
			}

		}

		return $entity;
	}
}

new PW_Tools_Rank_Math();