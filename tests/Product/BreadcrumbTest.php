<?php

declare( strict_types=1 );

namespace Netivo\Module\WooCommerce\AlternateCategories\Tests\Product;

use Brain\Monkey\Functions;
use Netivo\Module\WooCommerce\AlternateCategories\Product;
use Netivo\Module\WooCommerce\AlternateCategories\Tests\TestCase;

final class BreadcrumbTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		// Silence constructor hook wiring from Product in these tests
		Functions\when( 'add_action' )->justReturn( null );
		Functions\when( 'add_filter' )->justReturn( null );
	}

	public function test_modify_breadcrumbs_adds_brand_before_category(): void {
		$p = new Product();

		Functions\when( 'is_product_category' )->justReturn( true );
		Functions\when( 'get_query_var' )->alias( function ( string $name ) {
			return $name === 'manufacturer' ? 'nike' : null;
		} );
		Functions\expect( 'get_term_by' )->once()->with( 'slug', 'nike', 'product_brand' )->andReturn( (object) [ 'name' => 'Nike' ] );
		Functions\expect( 'get_term_link' )->once()->with( 'nike', 'product_brand' )->andReturn( 'https://example.com/nike/' );

		$breadcrumbs = [
			[ 'Home', 'https://example.com/' ],
			[ 'Products', 'https://example.com/shop/' ],
			[ 'Shoes', 'https://example.com/shop/shoes/' ],
		];

		$modified = $p->modify_breadcrumbs( $breadcrumbs );

		$expected = [
			[ 'Home', 'https://example.com/' ],
			[ 'Products', 'https://example.com/shop/' ],
			[ 'Nike', 'https://example.com/nike/' ],
			[ 'Shoes', 'https://example.com/shop/shoes/' ],
		];

		self::assertSame( $expected, $modified );
	}

	public function test_modify_breadcrumbs_no_op_when_no_manufacturer(): void {
		$p = new Product();

		Functions\when( 'is_product_category' )->justReturn( true );
		Functions\when( 'get_query_var' )->alias( function ( string $name ) {
			return $name === 'manufacturer' ? '' : null;
		} );

		$breadcrumbs = [
			[ 'Home', 'https://example.com/' ],
			[ 'Products', 'https://example.com/shop/' ],
			[ 'Shoes', 'https://example.com/shop/shoes/' ],
		];

		$modified = $p->modify_breadcrumbs( $breadcrumbs );

		self::assertSame( $breadcrumbs, $modified );
	}
}
