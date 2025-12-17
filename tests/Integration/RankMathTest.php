<?php

declare( strict_types=1 );

namespace Netivo\Module\WooCommerce\AlternateCategories\Tests\Integration;

use Brain\Monkey\Functions;
use Netivo\Module\WooCommerce\AlternateCategories\Integration\RankMath;
use Netivo\Module\WooCommerce\AlternateCategories\Product;
use Netivo\Module\WooCommerce\AlternateCategories\Tests\TestCase;

final class RankMathTest extends TestCase {
	public function test_constructor_registers_filters(): void {
		// Expect Rank Math hooks to be added
		Functions\expect( 'add_filter' )->once()->with( 'rank_math/frontend/title', \Mockery::type( 'array' ) )->andReturnNull();
		Functions\expect( 'add_filter' )->once()->with( 'rank_math/frontend/description', \Mockery::type( 'array' ) )->andReturnNull();
		Functions\expect( 'add_filter' )->once()->with( 'rank_math/frontend/canonical', \Mockery::type( 'array' ) )->andReturnNull();
		Functions\expect( 'add_filter' )->once()->with( 'rank_math/pagination/prev_url', \Mockery::type( 'array' ) )->andReturnNull();
		Functions\expect( 'add_filter' )->once()->with( 'rank_math/pagination/next_url', \Mockery::type( 'array' ) )->andReturnNull();

		new RankMath();

		// Not a risky test (Brain Monkey expectations assert behavior), add minimal assertion
		$this->addToAssertionCount( 1 );
	}

	public function test_filter_title_uses_product_helpers_when_in_manufacturer_category(): void {
		$rm = new RankMath();

		// Simulate context present
		Functions\expect( 'get_queried_object_id' )->once()->andReturn( 11 );
		Functions\expect( 'get_term' )->once()->with( 11 )->andReturn( (object) [ 'name' => 'Shoes' ] );
		Functions\expect( 'get_term_by' )->once()->with( 'slug', 'nike', 'product_brand' )->andReturn( (object) [ 'name' => 'Nike' ] );

		// Product helper path when manufacturer is present
		// We can't easily spy static call, but we can drive it by stubbing Product::get_manufacturer_in_category via a namespaced function.
		// Use a temporary shim by leveraging Brain Monkey to return manufacturer from get_query_var() & is_product_category().
		Functions\when( 'is_product_category' )->justReturn( true );
		Functions\when( 'get_query_var' )->alias( function ( string $name ) {
			return $name === 'manufacturer' ? 'nike' : null;
		} );

		// Option used by build_seo_title when seo_title empty and title empty -> falls back to "{Category} {Brand}"
		Functions\expect( 'get_option' )->once()->with( '_nt_man_nike_11', [] )->andReturn( [] );

		$title = $rm->filter_title( 'Original' );
		self::assertSame( 'Shoes Nike', $title );
	}

	public function test_filter_title_returns_original_when_no_manufacturer_or_term_missing(): void {
		$rm = new RankMath();

		// No manufacturer in category
		Functions\when( 'is_product_category' )->justReturn( false );
		$t1 = $rm->filter_title( 'Orig' );
		self::assertSame( 'Orig', $t1 );

		// Manufacturer present but brand term missing
		Functions\when( 'is_product_category' )->justReturn( true );
		Functions\when( 'get_query_var' )->alias( fn( string $n ) => $n === 'manufacturer' ? 'x' : null );
		Functions\expect( 'get_queried_object_id' )->once()->andReturn( 1 );
		Functions\expect( 'get_term' )->once()->with( 1 )->andReturn( (object) [ 'name' => 'Cat' ] );
		Functions\expect( 'get_term_by' )->once()->with( 'slug', 'x', 'product_brand' )->andReturn( null );
		$t2 = $rm->filter_title( 'Orig2' );
		self::assertSame( 'Orig2', $t2 );
	}

	public function test_filter_description_uses_meta_when_available(): void {
		$rm = new RankMath();

		Functions\when( 'is_product_category' )->justReturn( true );
		Functions\when( 'get_query_var' )->alias( fn( string $n ) => $n === 'manufacturer' ? 'adidas' : null );
		Functions\expect( 'get_queried_object_id' )->once()->andReturn( 22 );
		Functions\expect( 'get_option' )->once()->with( '_nt_man_adidas_22', [] )->andReturn( [ 'seo_description' => 'Meta Desc' ] );

		$desc = $rm->filter_description( 'OrigDesc' );
		self::assertSame( 'Meta Desc', $desc );
	}

	public function test_filter_description_returns_original_when_no_meta(): void {
		$rm = new RankMath();

		// No manufacturer
		Functions\when( 'is_product_category' )->justReturn( false );
		self::assertSame( 'D', $rm->filter_description( 'D' ) );

		// Manufacturer but no meta
		Functions\when( 'is_product_category' )->justReturn( true );
		Functions\when( 'get_query_var' )->alias( fn( string $n ) => $n === 'manufacturer' ? 'puma' : null );
		Functions\expect( 'get_queried_object_id' )->once()->andReturn( 33 );
		Functions\expect( 'get_option' )->once()->with( '_nt_man_puma_33', [] )->andReturn( [] );
		self::assertSame( 'D2', $rm->filter_description( 'D2' ) );
	}

	public function test_filter_url_rewrites_when_string_and_context_present_and_respects_falsy_values(): void {
		$rm = new RankMath();

		// manufacturer present
		Functions\when( 'is_product_category' )->justReturn( true );
		Functions\when( 'get_query_var' )->alias( fn( string $n ) => $n === 'manufacturer' ? 'brand' : null );

		// home_url/trailingslashit used by Product::replace_home_with_manufacturer
		Functions\when( 'home_url' )->justReturn( 'https://example.com' );
		Functions\when( 'trailingslashit' )->alias( function ( string $s ) {
			return rtrim( $s, '/' ) . '/';
		} );

		// String URL -> rewritten
		$url = $rm->filter_url( 'https://example.com/shop/cat/' );
		self::assertSame( 'https://example.com/brand/shop/cat/', $url );

		// Non-string and empty string values are returned as-is
		self::assertNull( $rm->filter_url( null ) );
		self::assertFalse( $rm->filter_url( false ) );
		self::assertSame( '', $rm->filter_url( '' ) );
	}
}
