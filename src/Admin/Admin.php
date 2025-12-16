<?php
/**
 * Created by Netivo for wp-core-wc-alternate-categories
 * User: manveru
 * Date: 16.12.2025
 * Time: 16:53
 *
 */

namespace Netivo\Module\WooCommerce\AlternateCategories\Admin;

use Automattic\Jetpack\Status\Cache;
use SimplePie\Category;
use Netivo\Module\WooCommerce\AlternateCategories\Admin\Page\AlternateCategories;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'HTTP/1.0 403 Forbidden' );
	exit;
}

/**
 * Class Admin
 *
 * Represents an administrative user or entity that may handle or manage various operations.
 * The constructor initializes a new Category instance.
 */
class Admin {

	public function __construct() {
		new Category();
		new AlternateCategories( Module::get_module_path() . '/views/', [] );
	}
}