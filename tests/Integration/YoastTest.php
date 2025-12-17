<?php

declare( strict_types=1 );

namespace Netivo\Module\WooCommerce\AlternateCategories\Tests\Integration;

use Brain\Monkey\Functions;
use Netivo\Module\WooCommerce\AlternateCategories\Integration\Yoast;
use Netivo\Module\WooCommerce\AlternateCategories\Tests\TestCase;

final class YoastTest extends TestCase {
	public function test_constructor_registers_filters(): void {
		Functions\expect( 'add_filter' )->once()->with( 'wpseo_title', \Mockery::type( 'array' ) )->andReturnNull();
		Functions\expect( 'add_filter' )->once()->with( 'wpseo_metadesc', \Mockery::type( 'array' ) )->andReturnNull();
		Functions\expect( 'add_filter' )->once()->with( 'wpseo_canonical', \Mockery::type( 'array' ) )->andReturnNull();
		Functions\expect( 'add_filter' )->once()->with( 'wpseo_prev_rel_link', \Mockery::type( 'array' ) )->andReturnNull();
		Functions\expect( 'add_filter' )->once()->with( 'wpseo_next_rel_link', \Mockery::type( 'array' ) )->andReturnNull();

		new Yoast();

		$this->addToAssertionCount( 1 );
	}

	public function test_filter_title_and_description_follow_helpers(): void {
		$y = new Yoast();

		// Manufacturer present
		Functions\when( 'is_product_category' )->justReturn( true );
		Functions\when( 'get_query_var' )->alias( fn( string $n ) => $n === 'manufacturer' ? 'mbrand' : null );

		// Title path
		Functions\expect( 'get_queried_object_id' )->once()->andReturn( 55 );
		Functions\expect( 'get_term' )->once()->with( 55 )->andReturn( (object) [ 'name' => 'Cat55' ] );
		Functions\expect( 'get_term_by' )->once()->with( 'slug', 'mbrand', 'product_brand' )->andReturn( (object) [ 'name' => 'Brand55' ] );
		// No seo_title / title in option -> fallback "{Cat} {Brand}"
		Functions\expect( 'get_option' )->once()->with( '_nt_man_mbrand_55', [] )->andReturn( [] );
		self::assertSame( 'Cat55 Brand55', $y->filter_title( 'Orig' ) );

		// Description path
		Functions\expect( 'get_queried_object_id' )->once()->andReturn( 56 );
		Functions\expect( 'get_option' )->once()->with( '_nt_man_mbrand_56', [] )->andReturn( [ 'seo_description' => 'D' ] );
		self::assertSame( 'D', $y->filter_description( 'OrigD' ) );
	}

	public function test_filter_url_rewrites_and_respects_non_strings(): void {
		$y = new Yoast();

		Functions\when( 'is_product_category' )->justReturn( true );
		Functions\when( 'get_query_var' )->alias( fn( string $n ) => $n === 'manufacturer' ? 'b' : null );
		Functions\when( 'home_url' )->justReturn( 'https://ex.com' );
		Functions\when( 'trailingslashit' )->alias( fn( string $s ) => rtrim( $s, '/' ) . '/' );

		self::assertSame( 'https://ex.com/b/path', $y->filter_url( 'https://ex.com/path' ) );
		self::assertNull( $y->filter_url( null ) );
		self::assertFalse( $y->filter_url( false ) );
		self::assertSame( '', $y->filter_url( '' ) );
	}
}
