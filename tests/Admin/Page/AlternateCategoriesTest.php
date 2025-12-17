<?php

declare( strict_types=1 );

namespace Netivo\Module\WooCommerce\AlternateCategories\Tests;

use Brain\Monkey\Functions;
use Netivo\Module\WooCommerce\AlternateCategories\Admin\Page\AlternateCategories;
use ReflectionClass;

final class AlternateCategoriesTest extends TestCase {
	private function makePage(): AlternateCategories {
		// Avoid hooking into WP during constructor
		Functions\when( 'add_action' )->justReturn( null );

		return new AlternateCategories( __DIR__ . '/..', [] );
	}

	private function getView( object $page ) {
		$ref  = new ReflectionClass( $page );
		$prop = $ref->getParentClass()->getProperty( 'view' );
		$prop->setAccessible( true );

		return $prop->getValue( $page );
	}

	public function test_do_action_lists_contents_when_no_action(): void {
		$page = $this->makePage();

		// No action
		unset( $_GET['action'] );

		// Stored items
		Functions\expect( 'get_option' )
			->once()
			->with( '_nt_cat_man_contents', [] )
			->andReturn( [ '_nt_man_nike_5' ] );

		// Resolve manufacturer and category
		Functions\expect( 'get_term_by' )
			->once()
			->with( 'slug', 'nike', 'product_brand' )
			->andReturn( (object) [ 'name' => 'Nike' ] );

		// get_term for id 5; also used by get_cat_name recursion
		Functions\expect( 'get_term' )
			->once()
			->with( 5 )
			->andReturn( (object) [ 'term_id' => 5, 'name' => 'Shoes', 'parent' => 0 ] );

		$page->do_action();

		$view = $this->getView( $page );
		$this->assertSame( 'list', $view->type );
		$this->assertIsArray( $view->contents );
		$this->assertCount( 1, $view->contents );
		$this->assertSame( 'Nike', $view->contents[0]['manufacturer'] );
		$this->assertSame( 'Shoes', $view->contents[0]['category'] );
		$this->assertSame( '_nt_man_nike_5', $view->contents[0]['name'] );
	}

	public function test_do_action_add_sets_form_data(): void {
		$page           = $this->makePage();
		$_GET['action'] = 'add';

		Functions\expect( 'get_terms' )
			->once()
			->with( [ 'taxonomy' => 'product_brand', 'hide_empty' => false ] )
			->andReturn( [ 'b1', 'b2' ] );

		Functions\expect( 'get_terms' )
			->once()
			->with( [ 'taxonomy' => 'product_cat', 'hide_empty' => false ] )
			->andReturn( [ 'c1' ] );

		$page->do_action();
		$view = $this->getView( $page );
		$this->assertSame( 'add', $view->type );
		$this->assertSame( [ 'b1', 'b2' ], $view->manufacturers );
		$this->assertSame( [ 'c1' ], $view->categories );
		$this->assertIsCallable( $view->get_cat_name );

		// Clean
		unset( $_GET['action'] );
	}

	public function test_do_action_edit_sets_existing_content(): void {
		$page            = $this->makePage();
		$_GET['action']  = 'edit';
		$_GET['element'] = '_nt_man_adidas_9';

		Functions\expect( 'get_option' )
			->once()
			->with( '_nt_man_adidas_9', [] )
			->andReturn( [ 'title' => 'T' ] );

		Functions\expect( 'get_term_by' )
			->once()
			->with( 'slug', 'adidas', 'product_brand' )
			->andReturn( (object) [ 'name' => 'Adidas' ] );

		Functions\expect( 'get_term' )
			->once()
			->with( 9 )
			->andReturn( (object) [ 'term_id' => 9, 'name' => 'Clothing', 'parent' => 0 ] );

		$page->do_action();
		$view = $this->getView( $page );
		$this->assertSame( 'edit', $view->type );
		$this->assertSame( 'Adidas', $view->manufacturer );
		$this->assertSame( 'Clothing', $view->category );
		$this->assertSame( [ 'title' => 'T' ], $view->content );

		unset( $_GET['action'], $_GET['element'] );
	}

	public function test_do_action_delete_removes_element_and_redirects(): void {
		$page            = $this->makePage();
		$_GET['action']  = 'delete';
		$_GET['element'] = '_nt_man_nike_5';

		// delete option called
		Functions\expect( 'delete_option' )->once()->with( '_nt_man_nike_5' )->andReturn( true );

		// contents updated without the element
		Functions\expect( 'get_option' )
			->once()
			->with( '_nt_cat_man_contents', [] )
			->andReturn( [ '_nt_man_nike_5', '_nt_man_puma_2' ] );

		Functions\expect( 'update_option' )
			->once()
			->with( '_nt_cat_man_contents', \Mockery::on( function ( $arr ) {
				return is_array( $arr ) && count( $arr ) === 1 && in_array( '_nt_man_puma_2', $arr, true );
			} ) )
			->andReturn( true );

		// redirect
		Functions\expect( 'admin_url' )
			->once()
			->with( 'admin.php?page=nt_cat_man&success' )
			->andReturn( '/wp-admin/admin.php?page=nt_cat_man&success' );

		Functions\expect( 'wp_redirect' )
			->once()
			->with( '/wp-admin/admin.php?page=nt_cat_man&success' )
			->andReturnNull();

		$page->do_action();

		$this->addToAssertionCount( 1 );

		unset( $_GET['action'], $_GET['element'] );
	}

	public function test_save_add_creates_entry_and_updates_list_and_timestamp(): void {
		$page           = $this->makePage();
		$_GET['action'] = 'add';
		$_POST          = [
			'manufacturer'     => 'reebok',
			'category'         => '12',
			'main_title'       => 'Title',
			'description'      => 'Desc',
			'bottom_title'     => 'BT',
			'full_description' => 'FD',
			'seo_title'        => 'ST',
			'seo_description'  => 'SD',
		];

		// Expect saving the element
		Functions\expect( 'update_option' )
			->once()
			->with( '_nt_man_reebok_12', \Mockery::on( function ( $arr ) {
				return isset( $arr['title'], $arr['description'], $arr['bottom_title'], $arr['full_description'], $arr['seo_title'], $arr['seo_description'] );
			} ) )
			->andReturn( true );

		// Existing list empty -> returns [] initially
		Functions\expect( 'get_option' )
			->once()
			->with( '_nt_cat_man_contents', [] )
			->andReturn( [] );

		// Update list with the new name
		Functions\expect( 'update_option' )
			->once()
			->with( '_nt_cat_man_contents', [ '_nt_man_reebok_12' ] )
			->andReturn( true );

		// Update modified timestamp
		Functions\expect( 'update_option' )
			->once()
			->with( '_nt_cat_man_modified', \Mockery::type( 'string' ) )
			->andReturn( true );

		$page->save();

		// Clean
		unset( $_GET['action'] );
		$_POST = [];

		$this->addToAssertionCount( 1 );
	}

	public function test_save_edit_updates_entry_and_timestamp(): void {
		$page            = $this->makePage();
		$_GET['action']  = 'edit';
		$_GET['element'] = '_nt_man_brand_99';
		$_POST           = [
			'main_title'         => 'T',
			'description'        => 'D',
			'bottom_title'       => 'BT',
			'bottom_description' => 'BD',
			'seo_title'          => 'ST',
			'seo_description'    => 'SD',
		];

		Functions\expect( 'update_option' )
			->once()
			->with( '_nt_man_brand_99', \Mockery::on( function ( $arr ) {
				return isset( $arr['title'], $arr['description'], $arr['bottom_title'], $arr['bottom_description'], $arr['seo_title'], $arr['seo_description'] );
			} ) )
			->andReturn( true );

		Functions\expect( 'update_option' )
			->once()
			->with( '_nt_cat_man_modified', \Mockery::type( 'string' ) )
			->andReturn( true );

		$page->save();

		unset( $_GET['action'], $_GET['element'] );
		$_POST = [];
		$this->addToAssertionCount( 1 );
	}
}
