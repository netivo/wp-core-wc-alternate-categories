<?php
/**
 * Created by PhpStorm.
 * User: Lukasz
 * Date: 28.11.2018
 * Time: 14:51
 */

namespace Netivo\Module\WooCommerce\AlternateCategories\Admin\Page;


use Netivo\Core\Admin\Page;
use ReflectionClass;

/**
 * Class Main
 */
#[\Netivo\Attributes\View( 'alternate-categories' )]
class AlternateCategories extends Page {
	/**
	 * Name of the page used as view name
	 *
	 * @var string
	 */
	protected string $_name = 'cat_man';

	/**
	 * Page type. One of: main, subpage, tab
	 * main - Main page will display in first level menu
	 * subpage - Sub page will display in second level menu, MUST have parent attribute
	 * tab - Tab for page, will not display in menu, MUST have parent attribute
	 *
	 * @var string
	 */
	protected string $_type = 'main';

	/**
	 * The text to be displayed in the title tags of the page when the menu is selected.
	 *
	 * @var string
	 */
	protected string $_page_title = 'Treści SEO';

	/**
	 * The text to be used for the menu.
	 *
	 * @var string
	 */
	protected string $_menu_text = 'Treści SEO';
	/**
	 * The capability required for this menu to be displayed to the user.
	 *
	 * @var string
	 */
	protected string $_capability = 'manage_options';
	/**
	 * The slug name to refer to this menu by. Should be unique for this menu page and only include lowercase alphanumeric, dashes, and underscores characters to be compatible with sanitize_key()
	 *
	 * @var string
	 */
	protected string $_menu_slug = 'nt_cat_man';
	/**
	 * The URL to the icon to be used for this menu.
	 * Pass a base64-encoded SVG using a data URI, which will be colored to match the color scheme. This should begin with 'data:image/svg+xml;base64,'.
	 * Pass the name of a Dashicons helper class to use a font icon, e.g. 'dashicons-chart-pie'.
	 * Pass 'none' to leave div.wp-menu-image empty so an icon can be added via CSS.
	 *
	 * Ignored when subpage or tab
	 *
	 * @var string
	 */
	protected string $_icon = 'dashicons-welcome-write-blog';
	/**
	 * The position in the menu order this one should appear.
	 *
	 * Ignored when subpage or tab
	 *
	 */
	protected ?int $_position = 54;

	/**
	 * Action done before displaying content
	 */
	public function do_action(): void {
		if ( empty( $_GET['action'] ) ) {
			$contents = get_option( '_nt_cat_man_contents', [] );
			$res      = [];
			if ( ! empty( $contents ) ) {
				foreach ( $contents as $content ) {
					$tmp = str_replace( '_nt_man_', '', $content );
					$tmp = explode( '_', $tmp );

					$man = get_term_by( 'slug', $tmp[0], 'product_brand' );
					$cat = get_term( $tmp[1] );

					$res[] = [
						'manufacturer' => $man->name,
						'category'     => $this->get_cat_name( $cat ),
						'name'         => $content
					];

				}
			}
			$this->view->contents = $res;
			$this->view->type     = 'list';
		} else {
			if ( $_GET['action'] == 'add' ) {
				$producents = get_terms( [ 'taxonomy' => 'product_brand', 'hide_empty' => false ] );
				$categories = get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => false ] );

				$this->view->manufacturers = $producents;
				$this->view->categories    = $categories;
				$this->view->get_cat_name  = function ( $category ) {
					return $this->get_cat_name( $category );
				};

				$this->view->type = 'add';
			}
			if ( $_GET['action'] == 'edit' ) {
				$data    = $_GET['element'];
				$content = get_option( $data, [] );

				$tmp = str_replace( '_nt_man_', '', $data );
				$tmp = explode( '_', $tmp );

				$man = get_term_by( 'slug', $tmp[0], 'product_brand' );
				$cat = get_term( $tmp[1] );

				$this->view->manufacturer = $man->name;
				$this->view->category     = $this->get_cat_name( $cat );

				$this->view->content = $content;

				$this->view->type = 'edit';
			}
			if ( $_GET['action'] == 'delete' ) {
				$data = $_GET['element'];
				delete_option( $data );

				$contents = get_option( '_nt_cat_man_contents', [] );
				$id       = array_search( $data, $contents );

				unset( $contents[ $id ] );

				update_option( '_nt_cat_man_contents', $contents );

				wp_redirect( admin_url( $this->_redirect_url . '&success' ) );

			}
		}
	}

	/**
	 * Save function, to be used in child class.
	 * Main data saving is done here.
	 */
	public function save(): void {
		if ( ! empty( $_GET['action'] ) && $_GET['action'] == 'add' ) {
			if ( ! empty( $_POST['manufacturer'] ) && ! empty( $_POST['category'] ) ) {
				$manufacturer = $_POST['manufacturer'];
			}
			$category = $_POST['category'];

			$title              = $_POST['main_title'];
			$description        = $_POST['description'];
			$bottom_title       = $_POST['bottom_title'];
			$bottom_description = $_POST['full_description'];
			$seo_title          = $_POST['seo_title'];
			$seo_description    = $_POST['seo_description'];

			$res = [
				'title'            => $title,
				'description'      => $description,
				'bottom_title'     => $bottom_title,
				'full_description' => $bottom_description,
				'seo_title'        => $seo_title,
				'seo_description'  => $seo_description
			];

			$name = '_nt_man_' . $manufacturer . '_' . $category;

			update_option( $name, $res );

			$contents   = get_option( '_nt_cat_man_contents', [] );
			$contents[] = $name;

			$now = new \DateTime();
			$now->setTimezone( new \DateTimeZone( 'Europe/Warsaw' ) );

			update_option( '_nt_cat_man_contents', $contents );
			update_option( '_nt_cat_man_modified', $now->format( 'c' ) );
		}
		if ( ! empty( $_GET['action'] ) && $_GET['action'] == 'edit' ) {

			$name = $_GET['element'];

			$title              = $_POST['main_title'];
			$description        = $_POST['description'];
			$bottom_title       = $_POST['bottom_title'];
			$bottom_description = $_POST['bottom_description'];
			$seo_title          = $_POST['seo_title'];
			$seo_description    = $_POST['seo_description'];

			$res = [
				'title'              => $title,
				'description'        => $description,
				'bottom_title'       => $bottom_title,
				'bottom_description' => $bottom_description,
				'seo_title'          => $seo_title,
				'seo_description'    => $seo_description
			];

			update_option( $name, $res );
			$now = new \DateTime();
			$now->setTimezone( new \DateTimeZone( 'Europe/Warsaw' ) );
			update_option( '_nt_cat_man_modified', $now->format( 'c' ) );
		}
	}

	/**
	 * @param $category \WP_Term|int
	 */
	public function get_cat_name( $category ) {
		if ( is_int( $category ) ) {
			$category = get_term( $category );
		}
		if ( $category->parent == 0 ) {
			return $category->name;
		}

		return $this->get_cat_name( $category->parent ) . ' > ' . $category->name;
	}

}