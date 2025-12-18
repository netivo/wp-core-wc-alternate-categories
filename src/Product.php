<?php
/**
 * Created by Netivo for wp-core-wc-alternate-categories
 * User: manveru
 * Date: 17.12.2025
 * Time: 12:50
 *
 */

namespace Netivo\Module\WooCommerce\AlternateCategories;

use Netivo\Module\WooCommerce\AlternateCategories\Integration\RankMath;
use Netivo\Module\WooCommerce\AlternateCategories\Integration\Yoast;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'HTTP/1.0 403 Forbidden' );
	exit;
}

/**
 * Class Product
 *
 * This class modifies WooCommerce behavior for product categories. It includes methods to alter the page title,
 * display custom archive descriptions, and conditionally hide default descriptions based on query variables.
 */
class Product {


	public function __construct() {
		add_filter( 'woocommerce_page_title', array( $this, 'change_woocommerce_page_title' ) );
		add_action( 'woocommerce_archive_description', array( $this, 'display_custom_archive_description' ) );
		add_filter( 'woocommerce_taxonomy_archive_description_raw', [ $this, 'hide_description' ] );
		add_filter( 'woocommerce_get_breadcrumb', [ $this, 'modify_breadcrumbs' ], 20 );

		// Integrate with Rank Math SEO if available
		if ( defined( 'RANK_MATH_VERSION' ) ) {
			new RankMath();
		}

		// Integrate with Yoast SEO if available
		if ( defined( 'WPSEO_VERSION' ) ) {
			new Yoast();
		}
	}

	/**
	 * Modifies the WooCommerce page title for product category pages based on specific conditions.
	 *
	 * @param string $title The original page title.
	 *
	 * @return string The modified page title.
	 */
	public function change_woocommerce_page_title( string $title ): string {
		if ( is_product_category() ) {
			$cat_id       = get_queried_object_id();
			$cat          = get_term( $cat_id );
			$manufacturer = get_query_var( 'manufacturer' );

			if ( ! empty( $manufacturer ) ) {
				$term = get_term_by( 'slug', $manufacturer, 'product_brand' );
				if ( ! empty( $term ) ) {
					$opt = get_option( '_nt_man_' . $manufacturer . '_' . $cat_id, [] );
					if ( ! empty( $opt ) ) {
						$title = ( ! empty( $opt['title'] ) ) ? $opt['title'] : $cat->name . ' ' . $term->name;
					} else {
						$title = $cat->name . ' ' . $term->name;
					}
				}
			}
		}

		return $title;
	}

	/**
	 * Displays a custom archive description for product categories based on specific manufacturer and category data.
	 *
	 * This method checks if the current archive is a product category. If so, it retrieves the category ID
	 * and manufacturer query variable, then attempts to fetch a custom description from the options table.
	 * If a custom description is found and the query is on the first page, it outputs the description.
	 *
	 * @return void
	 */
	public function display_custom_archive_description(): void {
		if ( is_product_category() ) {
			$cat_id       = get_queried_object_id();
			$cat          = get_term( $cat_id );
			$manufacturer = get_query_var( 'manufacturer' );

			if ( ! empty( $manufacturer ) ) {
				$opt = get_option( '_nt_man_' . $manufacturer . '_' . $cat_id, [] );
				if ( ! empty( $opt ) && ! empty( $opt['description'] ) ) {
					if ( get_query_var( 'paged' ) == 0 ) {
						echo '<div class="term-description">' . wp_kses_post( apply_filters( 'the_content', $opt['description'] ) ) . '</div>';
					}
				}
			}
		}
	}

	/**
	 * Conditionally hides the description based on the presence of a query variable.
	 *
	 * @param string $description The original description to evaluate.
	 *
	 * @return string Returns an empty string if the 'manufacturer' query variable is set, otherwise returns the original description.
	 */
	public function hide_description( string $description ): string {
		$manufacturer = get_query_var( 'manufacturer' );
		if ( ! empty( $manufacturer ) ) {
			return '';
		}

		return $description;
	}

	/**
	 * Modifies WooCommerce breadcrumbs to add the brand name before categories on product category archives
	 * when a manufacturer is selected.
	 *
	 * @param array $breadcrumbs The original breadcrumbs array.
	 *
	 * @return array The modified breadcrumbs array.
	 */
	public function modify_breadcrumbs( array $breadcrumbs ): array {
		if ( is_product_category() ) {
			$manufacturer = get_query_var( 'manufacturer' );

			if ( ! empty( $manufacturer ) ) {
				$term = get_term_by( 'slug', $manufacturer, 'product_brand' );
				if ( ! empty( $term ) ) {
					$new_breadcrumb = [ $term->name, get_term_link( $manufacturer, 'product_brand' ) ];

					// Insert before the last item (which is the current category)
					if ( count( $breadcrumbs ) > 0 ) {
						$last          = array_pop( $breadcrumbs );
						$breadcrumbs[] = $new_breadcrumb;
						$breadcrumbs[] = $last;
					} else {
						$breadcrumbs[] = $new_breadcrumb;
					}
				}
			}
		}

		return $breadcrumbs;
	}

	/**
	 * Returns the manufacturer slug when in a product category archive context; otherwise null.
	 */
	public static function get_manufacturer_in_category(): ?string {
		if ( ! function_exists( 'is_product_category' ) || ! is_product_category() ) {
			return null;
		}

		$manufacturer = get_query_var( 'manufacturer' );
		if ( empty( $manufacturer ) ) {
			return null;
		}

		return (string) $manufacturer;
	}

	/**
	 * Naively replace the home_url() prefix with home_url()/{$manufacturer}/ in a given URL.
	 */
	public static function replace_home_with_manufacturer( string $url, string $manufacturer ): string {
		if ( $url === '' || $manufacturer === '' ) {
			return $url;
		}

		$base     = trailingslashit( home_url() );
		$with_man = trailingslashit( $base . trailingslashit( $manufacturer ) );

		// Ensure we don't end up with double slashes beyond the scheme
		$replaced = str_replace( $base, $with_man, $url );
		$replaced = preg_replace( '#(?<!:)//#', '/', $replaced );

		return $replaced;
	}

	/**
	 * Build SEO title for manufacturer-category archives.
	 * Preference order: option['seo_title'] → option['title'] → "{Category} {Brand}".
	 */
	public static function build_seo_title( int $cat_id, string $manufacturer, ?object $cat = null, ?object $brand = null ): string {
		if ( $cat === null ) {
			$cat = get_term( $cat_id );
		}

		if ( $brand === null ) {
			$brand = get_term_by( 'slug', $manufacturer, 'product_brand' );
		}

		$opt = get_option( '_nt_man_' . $manufacturer . '_' . $cat_id, [] );

		if ( ! empty( $opt['seo_title'] ) ) {
			return (string) $opt['seo_title'];
		}

		if ( ! empty( $opt['title'] ) ) {
			return (string) $opt['title'];
		}

		$cat_name   = is_object( $cat ) && isset( $cat->name ) ? (string) $cat->name : '';
		$brand_name = is_object( $brand ) && isset( $brand->name ) ? (string) $brand->name : '';

		return trim( sprintf( '%s %s', $cat_name, $brand_name ) );
	}

	/**
	 * Build SEO meta description for manufacturer-category archives.
	 * Returns null when no override is configured.
	 */
	public static function build_seo_description( int $cat_id, string $manufacturer ): ?string {
		$opt = get_option( '_nt_man_' . $manufacturer . '_' . $cat_id, [] );
		if ( ! empty( $opt['seo_description'] ) ) {
			return (string) $opt['seo_description'];
		}

		return null;
	}
}