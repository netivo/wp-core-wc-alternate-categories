<?php
/**
 * Created by Netivo for Netivo Modules
 * User: manveru
 * Date: 16.12.2025
 * Time: 12:40
 *
 */

namespace Netivo\Module\WooCommerce\AlternateCategories;

use WP_Query;
use Netivo\Module\WooCommerce\AlternateCategories\Admin\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'HTTP/1.0 403 Forbidden' );
	exit;
}

/**
 * Class Module
 *
 * This class is responsible for extending WooCommerce functionality by adding custom endpoints,
 * query variables, and modifying queries for enhanced shop filtering capabilities.
 */
class Module {

	public function __construct() {
		add_action( 'init', [ $this, 'register_shop_endpoints' ], 1 );
		add_filter( 'query_vars', [ $this, 'register_query_vars' ] );

		add_filter( 'pre_get_posts', [ $this, 'modify_shop_query' ], 100, 1 );


		if ( is_admin() ) {
			new Admin();
		}
	}

	/**
	 * Registers custom rewrite rules for shop endpoints to handle manufacturer-based filtering
	 * and product category pagination.
	 *
	 * Adds rewrite rules to interpret URLs with a manufacturer and product category structure,
	 * enabling custom URLs for displaying products by manufacturer within specific categories
	 * and supporting pagination for those filtered product listings.
	 *
	 * @return void The method does not return a value.
	 */
	public function register_shop_endpoints(): void {
		$permalinks = wc_get_permalink_structure();

		add_rewrite_rule( '(.+?)/' . $permalinks['category_rewrite_slug'] . '/(.+?)/page/([0-9]{1,})/?$', 'index.php?product_cat=$matches[2]&manufacturer=$matches[1]&paged=$matches[3]', 'top' );
		add_rewrite_rule( '(.+?)/' . $permalinks['category_rewrite_slug'] . '/(.+?)/?$', 'index.php?product_cat=$matches[2]&manufacturer=$matches[1]', 'top' );
	}

	/**
	 * Registers custom query variables to be used in WordPress queries.
	 *
	 * @param array $vars An array of existing query variables.
	 *
	 * @return array The modified array of query variables, including the newly added custom variables.
	 */
	public function register_query_vars( array $vars ): array {
		$vars[] = 'manufacturer';

		return $vars;
	}

	/**
	 * Modifies the shop query for the product category taxonomy (product_cat) to filter products
	 * based on the manufacturer parameter and related product brand taxonomy.
	 *
	 * @param WP_Query $query The WordPress query object for the current request context.
	 *
	 * @return WP_Query The modified query object, updated with additional tax_query parameters if applicable.
	 */
	public function modify_shop_query( WP_Query $query ): WP_Query {
		if ( $query->is_tax( 'product_cat' ) ) {
			$manufacturer = get_query_var( 'manufacturer' );
			$tax_query    = $query->get( 'tax_query' );
			$query_change = false;
			if ( ! empty( $manufacturer ) ) {
				$alternative_hide = get_term_meta( get_queried_object_id(), '_alternative_hide', true );
				if ( ! $alternative_hide ) {
					$term = get_term_by( 'slug', $manufacturer, 'product_brand' );
					if ( ! empty( $term ) ) {
						$tax_query[]  = [
							'taxonomy' => 'product_brand',
							'terms'    => $term->term_id,
							'field'    => 'term_id',
							'operator' => 'IN'
						];
						$query_change = true;
					}
				}
			}
			if ( $query_change ) {
				$query->set( 'tax_query', $tax_query );
				$query->parse_tax_query( $query->query_vars );
				$query->parse_query();
			}
		}

		return $query;
	}

	/**
	 * Retrieves the file system path of the module directory.
	 *
	 * @return false|string|null Returns the absolute path to the module directory if it exists,
	 *                           false if the path cannot be resolved, or null if the file does not exist.
	 */
	public static function get_module_path(): false|string|null {
		$file = realpath( __DIR__ . '/../' );
		if ( file_exists( $file ) ) {
			return $file;
		}

		return null;
	}
}