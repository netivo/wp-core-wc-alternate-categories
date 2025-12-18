<?php

declare( strict_types=1 );

namespace Netivo\Module\WooCommerce\AlternateCategories\Tests;

use Brain\Monkey\Functions;
use Netivo\Module\WooCommerce\AlternateCategories\Product;

final class ProductTest extends TestCase {

	public function test_constructor_registers_hooks(): void {
		// Stub WP hook functions to capture expectations
		Functions\expect( 'add_filter' )
			->once()
			->with( 'woocommerce_page_title', \Mockery::type( 'array' ) )
			->andReturnNull();

		Functions\expect( 'add_action' )
			->once()
			->with( 'woocommerce_archive_description', \Mockery::type( 'array' ) )
			->andReturnNull();

		Functions\expect( 'add_filter' )
			->once()
			->with( 'woocommerce_taxonomy_archive_description_raw', \Mockery::type( 'array' ) )
			->andReturnNull();

		Functions\expect( 'add_filter' )
			->once()
			->with( 'woocommerce_get_breadcrumb', \Mockery::type( 'array' ), 20 )
			->andReturnNull();

		new Product();

		$this->addToAssertionCount( 1 );
	}

	public function test_change_title_uses_option_title_when_available(): void {
		// Avoid constructor expectations
		Functions\when( 'add_filter' )->justReturn( null );
		Functions\when( 'add_action' )->justReturn( null );

		Functions\expect( 'is_product_category' )->once()->andReturn( true );
		Functions\expect( 'get_queried_object_id' )->once()->andReturn( 42 );
		Functions\expect( 'get_term' )->once()->with( 42 )->andReturn( (object) [ 'name' => 'Shoes' ] );
		Functions\expect( 'get_query_var' )->once()->with( 'manufacturer' )->andReturn( 'nike' );
		Functions\expect( 'get_term_by' )->once()->with( 'slug', 'nike', 'product_brand' )->andReturn( (object) [ 'name' => 'Nike' ] );
		Functions\expect( 'get_option' )->once()->with( '_nt_man_nike_42', [] )->andReturn( [ 'title' => 'Custom Title' ] );

		$p     = new Product();
		$title = $p->change_woocommerce_page_title( 'Original' );
		self::assertSame( 'Custom Title', $title );
	}

	public function test_change_title_falls_back_to_cat_and_brand_name_when_no_option_title(): void {
		Functions\when( 'add_filter' )->justReturn( null );
		Functions\when( 'add_action' )->justReturn( null );

		Functions\expect( 'is_product_category' )->once()->andReturn( true );
		Functions\expect( 'get_queried_object_id' )->once()->andReturn( 5 );
		Functions\expect( 'get_term' )->once()->with( 5 )->andReturn( (object) [ 'name' => 'Clothing' ] );
		Functions\expect( 'get_query_var' )->once()->with( 'manufacturer' )->andReturn( 'adidas' );
		Functions\expect( 'get_term_by' )->once()->with( 'slug', 'adidas', 'product_brand' )->andReturn( (object) [ 'name' => 'Adidas' ] );
		Functions\expect( 'get_option' )->once()->with( '_nt_man_adidas_5', [] )->andReturn( [] );

		$p     = new Product();
		$title = $p->change_woocommerce_page_title( 'Original' );
		self::assertSame( 'Clothing Adidas', $title );
	}

	public function test_change_title_unchanged_when_not_product_category_or_no_manufacturer(): void {
		// Case 1: Not a product category
		Functions\when( 'add_filter' )->justReturn( null );
		Functions\when( 'add_action' )->justReturn( null );

		Functions\expect( 'is_product_category' )->once()->andReturn( false );
		$p     = new Product();
		$title = $p->change_woocommerce_page_title( 'Original' );
		self::assertSame( 'Original', $title );

		// Case 2: Is category but no manufacturer
		Functions\expect( 'is_product_category' )->once()->andReturn( true );
		Functions\expect( 'get_queried_object_id' )->once()->andReturn( 7 );
		Functions\expect( 'get_term' )->once()->with( 7 )->andReturn( (object) [ 'name' => 'Electronics' ] );
		Functions\expect( 'get_query_var' )->once()->with( 'manufacturer' )->andReturn( '' );
		$title2 = $p->change_woocommerce_page_title( 'Orig2' );
		self::assertSame( 'Orig2', $title2 );
	}

	public function test_display_custom_archive_description_outputs_when_first_page_and_description_exists(): void {
		Functions\when( 'add_filter' )->justReturn( null );
		Functions\when( 'add_action' )->justReturn( null );

		Functions\expect( 'is_product_category' )->once()->andReturn( true );
		Functions\expect( 'get_queried_object_id' )->once()->andReturn( 9 );
		Functions\expect( 'get_term' )->once()->with( 9 )->andReturn( (object) [ 'name' => 'Accessories' ] );
		Functions\expect( 'get_query_var' )->once()->with( 'manufacturer' )->andReturn( 'puma' );
		Functions\expect( 'get_option' )->once()->with( '_nt_man_puma_9', [] )->andReturn( [ 'description' => 'Hello <b>world</b>' ] );
		Functions\expect( 'get_query_var' )->once()->with( 'paged' )->andReturn( 0 );

		// the_content filtering and escaping
		Functions\expect( 'apply_filters' )->once()->with( 'the_content', 'Hello <b>world</b>' )->andReturn( 'Filtered' );
		Functions\expect( 'wp_kses_post' )->once()->with( 'Filtered' )->andReturn( 'Filtered' );

		$p = new Product();
		ob_start();
		$p->display_custom_archive_description();
		$out = ob_get_clean();

		self::assertStringContainsString( '<div class="term-description">Filtered</div>', $out );
	}

	public function test_display_custom_archive_description_no_output_when_not_first_page_or_missing_data(): void {
		Functions\when( 'add_filter' )->justReturn( null );
		Functions\when( 'add_action' )->justReturn( null );

		// Scenario A: not product category
		Functions\expect( 'is_product_category' )->once()->andReturn( false );
		$p = new Product();
		ob_start();
		$p->display_custom_archive_description();
		$outA = ob_get_clean();
		self::assertSame( '', $outA );

		// Scenario B: product category but no manufacturer
		Functions\expect( 'is_product_category' )->once()->andReturn( true );
		Functions\expect( 'get_queried_object_id' )->once()->andReturn( 3 );
		Functions\expect( 'get_term' )->once()->with( 3 )->andReturn( (object) [ 'name' => 'Cat' ] );
		Functions\expect( 'get_query_var' )->once()->with( 'manufacturer' )->andReturn( '' );
		ob_start();
		$p->display_custom_archive_description();
		$outB = ob_get_clean();
		self::assertSame( '', $outB );

		// Scenario C: description present but paged != 0
		Functions\expect( 'is_product_category' )->once()->andReturn( true );
		Functions\expect( 'get_queried_object_id' )->once()->andReturn( 10 );
		Functions\expect( 'get_term' )->once()->with( 10 )->andReturn( (object) [ 'name' => 'Cat' ] );
		Functions\expect( 'get_query_var' )->once()->with( 'manufacturer' )->andReturn( 'reebok' );
		Functions\expect( 'get_option' )->once()->with( '_nt_man_reebok_10', [] )->andReturn( [ 'description' => 'X' ] );
		Functions\expect( 'get_query_var' )->once()->with( 'paged' )->andReturn( 2 );
		ob_start();
		$p->display_custom_archive_description();
		$outC = ob_get_clean();
		self::assertSame( '', $outC );
	}

	public function test_hide_description_returns_empty_when_manufacturer_set_else_original(): void {
		Functions\when( 'add_filter' )->justReturn( null );
		Functions\when( 'add_action' )->justReturn( null );

		// manufacturer present -> empty
		Functions\expect( 'get_query_var' )->once()->with( 'manufacturer' )->andReturn( 'brand' );
		$p    = new Product();
		$res1 = $p->hide_description( 'Original description' );
		self::assertSame( '', $res1 );

		// manufacturer absent -> unchanged
		Functions\expect( 'get_query_var' )->once()->with( 'manufacturer' )->andReturn( '' );
		$res2 = $p->hide_description( 'Original description' );
		self::assertSame( 'Original description', $res2 );
	}
}
