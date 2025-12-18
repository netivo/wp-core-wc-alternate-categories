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

		Functions\when( 'home_url' )->justReturn( 'https://example.com' );
		Functions\when( 'trailingslashit' )->alias( function ( string $s ) {
			return rtrim( $s, '/' ) . '/';
		} );

		Functions\when( 'is_product_category' )->justReturn( true );
		Functions\when( 'get_query_var' )->alias( function ( string $name ) {
			return $name === 'manufacturer' ? 'nike' : null;
		} );
		Functions\expect( 'get_term_by' )->once()->with( 'slug', 'nike', 'product_brand' )->andReturn( (object) [ 'name' => 'Nike' ] );
		Functions\expect( 'get_term_link' )->once()->with( 'nike', 'product_brand' )->andReturn( 'https://example.com/nike/' );
		Functions\expect( 'wc_get_permalink_structure' )->once()->andReturn( [ 'category_rewrite_slug' => 'product-category' ] );

		$breadcrumbs = [
			[ 'Home', 'https://example.com/' ],
			[ 'Products', 'https://example.com/shop/' ],
			[ 'Shoes', 'https://example.com/product-category/shoes/' ],
		];

		$modified = $p->modify_breadcrumbs( $breadcrumbs );

		$expected = [
			[ 'Home', 'https://example.com/' ],
			[ 'Products', 'https://example.com/shop/' ],
			[ 'Nike', 'https://example.com/nike/' ],
			[ 'Shoes', 'https://example.com/nike/product-category/shoes/' ],
		];

		self::assertSame( $expected, $modified );
	}

	public function test_modify_breadcrumbs_adds_brand_before_first_category_in_complex_tree(): void {
		$p = new Product();

		Functions\when( 'home_url' )->justReturn( 'https://example.com' );
		Functions\when( 'trailingslashit' )->alias( function ( string $s ) {
			return rtrim( $s, '/' ) . '/';
		} );

		Functions\when( 'is_product_category' )->justReturn( true );
		Functions\when( 'get_query_var' )->alias( function ( string $name ) {
			return $name === 'manufacturer' ? 'testowa' : null;
		} );
		Functions\expect( 'get_term_by' )->once()->with( 'slug', 'testowa', 'product_brand' )->andReturn( (object) [ 'name' => 'Testowa' ] );
		Functions\expect( 'get_term_link' )->once()->with( 'testowa', 'product_brand' )->andReturn( 'https://example.com/testowa/' );
		Functions\expect( 'wc_get_permalink_structure' )->once()->andReturn( [ 'category_rewrite_slug' => 'kategoria' ] );

		// Strona główna / Clothing / Accessories
		// The user said: "Testowa is an Brand Clothing is parent category and Accessories is child category."
		// Expected: Strona główna / Testowa / Clothing / Accessories
		// If we use a different slug than the hardcoded '/product-category/', it should still work.
		$breadcrumbs = [
			[ 'Strona główna', 'https://example.com/' ],
			[ 'Clothing', 'https://example.com/kategoria/clothing/' ],
			[ 'Accessories', 'https://example.com/kategoria/clothing/accessories/' ],
		];

		$modified = $p->modify_breadcrumbs( $breadcrumbs );

		$expected = [
			[ 'Strona główna', 'https://example.com/' ],
			[ 'Testowa', 'https://example.com/testowa/' ],
			[ 'Clothing', 'https://example.com/testowa/kategoria/clothing/' ],
			[ 'Accessories', 'https://example.com/testowa/kategoria/clothing/accessories/' ],
		];

		self::assertSame( $expected, $modified );
	}

	public function test_modify_breadcrumbs_fallback_when_no_category_slug_matched(): void {
		$p = new Product();

		Functions\when( 'home_url' )->justReturn( 'https://example.com' );
		Functions\when( 'trailingslashit' )->alias( function ( string $s ) {
			return rtrim( $s, '/' ) . '/';
		} );

		Functions\when( 'is_product_category' )->justReturn( true );
		Functions\when( 'get_query_var' )->alias( function ( string $name ) {
			return $name === 'manufacturer' ? 'testowa' : null;
		} );
		Functions\expect( 'get_term_by' )->once()->with( 'slug', 'testowa', 'product_brand' )->andReturn( (object) [ 'name' => 'Testowa' ] );
		Functions\expect( 'get_term_link' )->once()->with( 'testowa', 'product_brand' )->andReturn( 'https://example.com/testowa/' );
		// Permalink structure returns something that won't match our links
		Functions\expect( 'wc_get_permalink_structure' )->once()->andReturn( [ 'category_rewrite_slug' => 'something-else' ] );

		$breadcrumbs = [
			[ 'Home', 'https://example.com/' ],
			[ 'Clothing', 'https://example.com/kategoria/clothing/' ],
			[ 'Accessories', 'https://example.com/kategoria/clothing/accessories/' ],
		];

		$modified = $p->modify_breadcrumbs( $breadcrumbs );

		// Should fallback to inserting at index 1
		// Since slug doesn't match ('something-else' vs 'kategoria'), links should remain original
		$expected = [
			[ 'Home', 'https://example.com/' ],
			[ 'Testowa', 'https://example.com/testowa/' ],
			[ 'Clothing', 'https://example.com/kategoria/clothing/' ],
			[ 'Accessories', 'https://example.com/kategoria/clothing/accessories/' ],
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
