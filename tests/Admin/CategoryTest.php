<?php

declare( strict_types=1 );

namespace Netivo\Module\WooCommerce\AlternateCategories\Tests;

use Brain\Monkey\Functions;
use Netivo\Module\WooCommerce\AlternateCategories\Admin\Category as AdminCategory;

final class CategoryTest extends TestCase {
	public function test_constructor_registers_hooks(): void {
		// Expect hooks registration with proper callbacks and priority
		Functions\expect( 'add_action' )
			->once()
			->with( 'product_cat_add_form_fields', \Mockery::type( 'array' ), 24 )
			->andReturn( null );

		Functions\expect( 'add_action' )
			->once()
			->with( 'product_cat_edit_form_fields', \Mockery::type( 'array' ), 24 )
			->andReturn( null );

		Functions\expect( 'add_action' )
			->once()
			->with( 'edited_product_cat', \Mockery::type( 'array' ), 24 )
			->andReturn( null );

		Functions\expect( 'add_action' )
			->once()
			->with( 'create_product_cat', \Mockery::type( 'array' ), 24 )
			->andReturn( null );

		// Instantiate should trigger the four add_action calls
		new AdminCategory();

		// Count one assertion to avoid risky test when relying only on expectations
		$this->addToAssertionCount( 1 );
	}

	public function test_alternative_hide_outputs_checkbox_html(): void {
		// Do not care about constructor hooks for this test
		Functions\when( 'add_action' )->justReturn( null );

		// _e is called to output label text; we just allow it
		Functions\when( '_e' )->justReturn( null );

		$category = new AdminCategory();

		ob_start();
		$category->alternative_hide();
		$output = ob_get_clean();

		self::assertIsString( $output );
		self::assertStringContainsString( 'input type="checkbox"', $output );
		self::assertStringContainsString( 'name="alternative_hide"', $output );
		self::assertStringContainsString( 'id="alternative_hide"', $output );
	}

	public function test_alternative_hide_edit_outputs_unchecked_when_meta_empty(): void {
		Functions\when( 'add_action' )->justReturn( null );
		Functions\when( '_e' )->justReturn( null );

		// get_term_meta returns empty => no checked attribute
		Functions\expect( 'get_term_meta' )
			->once()
			->with( 10, '_alternative_hide', true )
			->andReturn( '' );

		$term     = (object) [ 'term_id' => 10 ];
		$category = new AdminCategory();
		ob_start();
		$category->alternative_hide_edit( $term );
		$output = ob_get_clean();

		self::assertStringContainsString( 'name="alternative_hide"', $output );
		self::assertStringNotContainsString( 'checked', $output );
	}

	public function test_alternative_hide_edit_outputs_checked_when_meta_set(): void {
		Functions\when( 'add_action' )->justReturn( null );
		Functions\when( '_e' )->justReturn( null );

		// get_term_meta returns non-empty => checked attribute present
		Functions\expect( 'get_term_meta' )
			->once()
			->with( 11, '_alternative_hide', true )
			->andReturn( '1' );

		$term     = (object) [ 'term_id' => 11 ];
		$category = new AdminCategory();
		ob_start();
		$category->alternative_hide_edit( $term );
		$output = ob_get_clean();

		self::assertStringContainsString( 'checked', $output );
	}

	public function test_alternative_hide_save_updates_meta_when_posted(): void {
		Functions\when( 'add_action' )->justReturn( null );

		// Simulate posted value
		$_POST['alternative_hide'] = '1';

		// sanitize_text_field should be called with '1' and return '1'
		Functions\expect( 'sanitize_text_field' )->once()->with( '1' )->andReturn( '1' );

		// Expect update of term meta
		Functions\expect( 'update_term_meta' )
			->once()
			->with( 22, '_alternative_hide', '1' )
			->andReturn( true );

		$category = new AdminCategory();
		$category->alternative_hide_save( 22 );

		// Cleanup
		unset( $_POST['alternative_hide'] );

		$this->addToAssertionCount( 1 );
	}

	public function test_alternative_hide_save_deletes_meta_when_not_posted(): void {
		Functions\when( 'add_action' )->justReturn( null );

		// Ensure no POST value
		unset( $_POST['alternative_hide'] );

		// Expect delete of term meta
		Functions\expect( 'delete_term_meta' )
			->once()
			->with( 23, '_alternative_hide' )
			->andReturn( true );

		$category = new AdminCategory();
		$category->alternative_hide_save( 23 );

		$this->addToAssertionCount( 1 );
	}
}
