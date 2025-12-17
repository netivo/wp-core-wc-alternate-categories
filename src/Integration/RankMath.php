<?php
/**
 * Created by Netivo for wp-core-wc-alternate-categories
 * Integrates with Rank Math SEO to modify meta title, description, canonical and rel prev/next.
 */

namespace Netivo\Module\WooCommerce\AlternateCategories\Integration;

use Netivo\Module\WooCommerce\AlternateCategories\Product;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'HTTP/1.0 403 Forbidden' );
	exit;
}

/**
 * Class RankMath
 *
 * This class integrates with Rank Math SEO plugin to modify meta tags for product categories.
 * It adjusts the title, meta description, canonical URL, and rel prev/next links based on specific conditions.
 */
class RankMath {

	public function __construct() {
		add_filter( 'rank_math/frontend/title', [ $this, 'filter_title' ] );
		add_filter( 'rank_math/frontend/description', [ $this, 'filter_description' ] );

		// Use a single callback for canonical and pagination URLs
		add_filter( 'rank_math/frontend/canonical', [ $this, 'filter_url' ] );

		add_filter( 'rank_math/pagination/prev_url', [ $this, 'filter_url' ] );
		add_filter( 'rank_math/pagination/next_url', [ $this, 'filter_url' ] );
	}

	/**
	 * Filters the provided title based on the product category and manufacturer query variables.
	 *
	 * @param string $title The original title.
	 *
	 * @return string The filtered title based on manufacturer and category conditions, or the original title if no modifications are applied.
	 */
	public function filter_title( string $title ): string {
		$manufacturer = Product::get_manufacturer_in_category();
		if ( $manufacturer === null ) {
			return $title;
		}

		$cat_id = get_queried_object_id();
		$cat    = get_term( $cat_id );
		$term   = get_term_by( 'slug', $manufacturer, 'product_brand' );

		if ( empty( $term ) ) {
			return $title;
		}

		return Product::build_seo_title( $cat_id, $manufacturer, $cat, $term );
	}

	/**
	 * Filters the description for product category pages based on a specific manufacturer.
	 *
	 * @param string $description The original description to be filtered.
	 *
	 * @return string The filtered description if a custom meta description is set for the manufacturer-category combination; otherwise, returns the original description.
	 */
	public function filter_description( string $description ): string {
		$manufacturer = Product::get_manufacturer_in_category();
		if ( $manufacturer === null ) {
			return $description;
		}

		$cat_id = get_queried_object_id();
		$desc   = Product::build_seo_description( $cat_id, $manufacturer );
		if ( $desc !== null ) {
			return $desc;
		}

		return $description;
	}

	/**
	 * Unified filter for canonical and pagination URLs provided by Rank Math.
	 *
	 * Hooks:
	 * - rank_math/frontend/canonical (string)
	 * - rank_math/pagination/prev_url (string|false|null)
	 * - rank_math/pagination/next_url (string|false|null)
	 *
	 * @param mixed $value URL provided by Rank Math. May be string for canonical, or string|false|null for pagination.
	 *
	 * @return mixed Returns the transformed URL string when applicable; otherwise returns the original value (incl. false/null).
	 */
	public function filter_url( $value ) {
		$manufacturer = Product::get_manufacturer_in_category();
		if ( $manufacturer === null ) {
			return $value;
		}

		// Respect falsy values for prev/next (false/null/empty string) and non-string inputs.
		if ( ! is_string( $value ) || $value === '' ) {
			return $value;
		}

		return Product::replace_home_with_manufacturer( $value, $manufacturer );
	}

}
