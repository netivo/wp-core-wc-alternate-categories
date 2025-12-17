<?php

declare( strict_types=1 );

namespace Netivo\Module\WooCommerce\AlternateCategories\Tests\Product;

use Brain\Monkey\Functions;
use Netivo\Module\WooCommerce\AlternateCategories\Product;
use Netivo\Module\WooCommerce\AlternateCategories\Tests\TestCase;

final class ProductSeoHelpersTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		// Silence constructor hook wiring from Product in these tests
		Functions\when( 'add_action' )->justReturn( null );
		Functions\when( 'add_filter' )->justReturn( null );
	}

	public function test_get_manufacturer_in_category(): void {
		// Not a product category -> null
		Functions\when( 'is_product_category' )->justReturn( false );
		self::assertNull( Product::get_manufacturer_in_category() );

		// Product category but no manufacturer -> null
		Functions\when( 'is_product_category' )->justReturn( true );
		Functions\when( 'get_query_var' )->alias( fn( string $n ) => $n === 'manufacturer' ? '' : null );
		self::assertNull( Product::get_manufacturer_in_category() );

		// Manufacturer present -> slug string
		Functions\when( 'get_query_var' )->alias( fn( string $n ) => $n === 'manufacturer' ? 'nike' : null );
		self::assertSame( 'nike', Product::get_manufacturer_in_category() );
	}

	public function test_replace_home_with_manufacturer(): void {
		// Empty inputs yield original
		self::assertSame( '', Product::replace_home_with_manufacturer( '', 'x' ) );
		self::assertSame( 'https://x/y', Product::replace_home_with_manufacturer( 'https://x/y', '' ) );

		Functions\when( 'home_url' )->justReturn( 'https://example.com' );
		Functions\when( 'trailingslashit' )->alias( fn( string $s ) => rtrim( $s, '/' ) . '/' );

		// Base replacement
		$res = Product::replace_home_with_manufacturer( 'https://example.com/shop/cat', 'brand' );
		self::assertSame( 'https://example.com/brand/shop/cat', $res );

		// Ensure no double slashes beyond scheme
		$res2 = Product::replace_home_with_manufacturer( 'https://example.com//shop//cat/', 'b' );
		self::assertSame( 'https://example.com/b/shop/cat/', $res2 );
	}

	public function test_build_seo_title_prefers_seo_title_then_title_then_fallback(): void {
		// Prefer seo_title
		Functions\expect( 'get_option' )->once()->with( '_nt_man_m_10', [] )->andReturn( [ 'seo_title' => 'SEO T' ] );
		self::assertSame( 'SEO T', Product::build_seo_title( 10, 'm', (object) [ 'name' => 'C' ], (object) [ 'name' => 'B' ] ) );

		// Then title if seo_title empty
		Functions\expect( 'get_option' )->once()->with( '_nt_man_m_11', [] )->andReturn( [ 'title' => 'Plain T' ] );
		self::assertSame( 'Plain T', Product::build_seo_title( 11, 'm', (object) [ 'name' => 'C' ], (object) [ 'name' => 'B' ] ) );

		// Fallback to "{Category} {Brand}" when both empty
		Functions\expect( 'get_option' )->once()->with( '_nt_man_m_12', [] )->andReturn( [] );
		self::assertSame( 'C B', Product::build_seo_title( 12, 'm', (object) [ 'name' => 'C' ], (object) [ 'name' => 'B' ] ) );
	}

	public function test_build_seo_description_returns_value_or_null(): void {
		Functions\expect( 'get_option' )->once()->with( '_nt_man_x_3', [] )->andReturn( [ 'seo_description' => 'DESC' ] );
		self::assertSame( 'DESC', Product::build_seo_description( 3, 'x' ) );

		Functions\expect( 'get_option' )->once()->with( '_nt_man_x_4', [] )->andReturn( [] );
		self::assertNull( Product::build_seo_description( 4, 'x' ) );
	}

	public function test_product_constructor_instantiates_integrations_when_constants_defined(): void {
		// Define plugin constants
		if ( ! defined( 'RANK_MATH_VERSION' ) ) {
			define( 'RANK_MATH_VERSION', '1.0.0' );
		}
		if ( ! defined( 'WPSEO_VERSION' ) ) {
			define( 'WPSEO_VERSION', '21.0' );
		}

		// Allow any add_filter/add_action, and spy on specific integration hooks
		$sawRank  = false;
		$sawYoast = false;
		Functions\when( 'add_action' )->justReturn( null );
		Functions\when( 'add_filter' )->alias( function ( ...$args ) use ( &$sawRank, &$sawYoast ) {
			$hook = $args[0] ?? '';
			if ( $hook === 'rank_math/frontend/title' ) {
				$sawRank = true;
			}
			if ( $hook === 'wpseo_title' ) {
				$sawYoast = true;
			}

			return null;
		} );

		new Product();

		self::assertTrue( $sawRank, 'Expected RankMath integration to register a title filter' );
		self::assertTrue( $sawYoast, 'Expected Yoast integration to register a title filter' );
	}
}
