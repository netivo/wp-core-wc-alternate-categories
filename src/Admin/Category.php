<?php
/**
 * Created by Netivo for wp-core-wc-alternate-categories
 * User: manveru
 * Date: 16.12.2025
 * Time: 16:47
 *
 */

namespace Netivo\Module\WooCommerce\AlternateCategories\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'HTTP/1.0 403 Forbidden' );
	exit;
}

/**
 * Handles the addition and editing of a custom attribute for product categories.
 *
 * This class introduces a custom checkbox for hiding categories within an
 * alternate category tree, providing a streamlined way to manage category visibility.
 */
class Category {

	public function __construct() {
		add_action( 'product_cat_add_form_fields', [ $this, 'alternative_hide' ], 24 );
		add_action( 'product_cat_edit_form_fields', [ $this, 'alternative_hide_edit' ], 24 );
		add_action( 'edited_product_cat', [ $this, 'alternative_hide_save' ], 24 );
		add_action( 'create_product_cat', [ $this, 'alternative_hide_save' ], 24 );
	}

	/**
	 * Displays a checkbox input field for the "alternative_hide" setting.
	 *
	 * @return void
	 */
	public function alternative_hide() {
		?>
        <div class="form-field term-display-type-wrap">
            <label for="alternative_hide">
                <input type="checkbox" name="alternative_hide" value="1" id="alternative_hide"/>
				<?php _e( 'Kategoria producenta', 'netivo' ); ?>
            </label>
        </div>

		<?php
	}

	/**
	 * Displays a checkbox input field for editing the "alternative_hide" setting within a taxonomy term.
	 *
	 * @param WP_Term $term The taxonomy term object being edited.
	 *
	 * @return void
	 */
	public function alternative_hide_edit( $term ) {

		$alternative_hide = get_term_meta( $term->term_id, '_alternative_hide', true );
		?>
        <tr class="form-field term-thumbnail-wrap">
            <td scope="row" valign="top" colspan="2">
                <label for="alternative_hide">
                    <input type="checkbox" name="alternative_hide" value="1"
                           id="alternative_hide" <?= ( ! empty( $alternative_hide ) ) ? 'checked' : ''; ?>/>
					<?php _e( 'Ukryć w alternatywnym drzewie', 'netivo' ); ?>
                </label>
            </td>
        </tr>

		<?php
	}

	/**
	 * Saves or deletes the "alternative_hide" metadata for a given term.
	 *
	 * @param int $term_id ID of the term for which the metadata is being updated.
	 *
	 * @return void
	 */
	public function alternative_hide_save( $term_id ) {
		if ( isset( $_POST['alternative_hide'] ) ) {
			update_term_meta( $term_id, '_alternative_hide', sanitize_text_field( $_POST['alternative_hide'] ) );
		} else {
			delete_term_meta( $term_id, '_alternative_hide' );
		}
	}
}