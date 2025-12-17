<?php
/**
 * Created by Netivo for wp-core-wc-alternate-categories
 * Integrates with Yoast SEO to modify meta title, description, canonical and (if present) rel prev/next.
 */

namespace Netivo\Module\WooCommerce\AlternateCategories\Integration;

use Netivo\Module\WooCommerce\AlternateCategories\Product;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'HTTP/1.0 403 Forbidden' );
	exit;
}

/**
 * Modifies the Yoast SEO meta configurations (title, description, canonical URLs, and rel prev/next links).
 * Enhances the SEO integration by dynamically adjusting meta data using manufacturer and category information.
 */
class Yoast {

	public function __construct() {
		// Title & Description
		add_filter( 'wpseo_title', [ $this, 'filter_title' ] );
		add_filter( 'wpseo_metadesc', [ $this, 'filter_description' ] );

		// Canonical URL
		add_filter( 'wpseo_canonical', [ $this, 'filter_url' ] );

		// Adjacent rel links (Yoast may not output them anymore, but filters still exist in many installs)
		add_filter( 'wpseo_prev_rel_link', [ $this, 'filter_url' ] );
		add_filter( 'wpseo_next_rel_link', [ $this, 'filter_url' ] );
	}

	/**
	 * Filters the SEO title for a category based on the manufacturer in the category, if applicable.
	 *
	 * @param string $title The original title.
	 *
	 * @return string The modified title if a manufacturer is found, or the original title otherwise.
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
	 * Filters the meta description for SEO purposes based on the current category and manufacturer.
	 *
	 * @param string $description The original meta description.
	 *
	 * @return string The modified meta description, or the original if no changes are applied.
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
	 * Unified filter for canonical and adjacent URLs provided by Yoast.
	 *
	 * Hooks:
	 * - wpseo_canonical (string|null)
	 * - wpseo_prev_rel_link (string|false|null)
	 * - wpseo_next_rel_link (string|false|null)
	 *
	 * @param mixed $value
	 *
	 * @return mixed
	 */
	public function filter_url( $value ) {
		$manufacturer = Product::get_manufacturer_in_category();
		if ( $manufacturer === null ) {
			return $value;
		}

		if ( ! is_string( $value ) || $value === '' ) {
			return $value;
		}

		return Product::replace_home_with_manufacturer( $value, $manufacturer );
	}
}
