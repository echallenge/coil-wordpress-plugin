<?php
/**
 * Coil for WordPress - Meta Box.
 *
 * Adds a custom meta box for both the Classic Editor and Gutenberg.
 *
 * @author   Sébastien Dumont
 * @category Classes
 * @package  Coil/Classes/Meta Box
 * @license  GPL-2.0+
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Coil post meta data class.
 */
class Coil_Meta_Box {

	/**
	 * Constructor.
	 *
	 * @access public
	 */
	public function __construct() {
		// Ensure that we are only loading if we are in the admin.
		if ( is_admin() ) {
			add_action( 'load-post.php', array( $this, 'init_metabox' ) );
			add_action( 'load-post-new.php', array( $this, 'init_metabox' ) );
			add_action( 'save_post', array( $this, 'save_metabox' ) );
		}
	}

	/**
	 * Meta box initialization.
	 *
	 * @access public
	 */
	public function init_metabox() {
		add_action( 'add_meta_boxes', array( $this, 'add_metabox' ) );
	} // END init_metabox()

	/**
	 * Adds a metabox to the right side of the screen above the “Publish” box.
	 *
	 * @access public
	 */
	public function add_metabox() {
		add_meta_box(
			'coil', // Meta box ID (used in the 'id' attribute for the meta box).
			sprintf( __( 'Web Monetization - %s', 'coil-for-wp' ), 'Coil' ), // Meta Box Title
			array( $this, 'coil_metabox_callback' ), // Function that fills the box with the desired content.
			array( 'post', 'page' ), // The screen or screens on which to show the box (such as a post type)
			'side', // The context within the screen where the boxes should display. 
			'high' // The priority within the context where the boxes should show. Default: default
		);
	} // END add_metabox()

	/**
	 * Output the HTML for the metabox.
	 *
	 * @access public
	 * @global object $post
	 */
	public function coil_metabox_callback() {
		global $post;

		// Get the monetization status of the post.
		$monet_status = get_post_meta( $post->ID, '_coil_monetize_post_status', true );

		// Get post payout pointer ID if set.
		$post_payout_pointer_id = get_post_meta( $post->ID, '_coil_payout_pointer_id', true );

		// Output the fields.
		$monet_options = array(
			'no'                 => esc_html__( 'No (Default)', 'coil-for-wp' ),
			'no-gating'          => esc_html__( 'Monetize with No Gating', 'coil-for-wp' ),
			'gate-all'           => esc_html__( 'Monetize all Content', 'coil-for-wp' )
		);

		// If user loaded with the Gutenberg editor, add additional option.
		if ( function_exists( 'use_block_editor_for_post' ) && use_block_editor_for_post( $post ) ) {
			$monet_options['gate-tagged-blocks'] = esc_html__( 'Monetize Tagged Blocks', 'coil-for-wp' );
		}
		?>
		<fieldset>
			<legend><?php esc_html_e( 'Select the monetization status of this content.', 'coil-for-wp' ); ?></legend>
			<?php foreach( $monet_options as $option => $name ) { ?>
			<input type="radio" name="coil_monetize_post_status" id="<?php echo $option; ?>" value="<?php echo $option; ?>"<?php if( empty( $monet_status ) && $option == 'no' ) { echo 'checked="checked"'; } else { checked( $monet_status, $option ); } ?> /><label for="track"><?php echo $name; ?></label><br />
			<?php } ?>
		</fieldset>

		<?php
		// Only users with permission can override the global payout pointer.
		/*if ( current_user_can( 'edit_post', $post->ID ) ) {
		?>
		<div class="coil-payout-pointer-override" style="display:none;">
		<label for="coil_payout_pointer_id"><?php _e( 'Payout Pointer', 'coil-for-wp' ); ?></label>
		<input type="text" name="coil_payout_pointer_id" id="coil_payout_pointer_id" value="<?php echo esc_textarea( $post_payout_pointer_id ); ?>" class="widefat">
		<span class="description"><?php _e( 'Will override global payout pointer if set.', 'coil-for-wp' ); ?></span>
		</div>
		<script type="text/javascript">
		// Coil - Web Monetization Javascript
		$(document).ready(function() {
			var override   = $('.coil-payout-pointer-override'),
				pointer_id = $('coil_payout_pointer_id').val();

			// Show the payment pointer override field if not empty otherwise make sure it's hidden.
			if ( $(pointer_id) != '' ) {
				$(override).css('display', 'initial');
			} else {
				$(override).css('display', 'none');
			}

			// Displays the payment pointer override field if monetization is set.
			if ( $('input[type="radio"][name="coil_monetize_post_status"]:checked').val() != 'no' ) {
				$(override).css('display', 'initial');
			} else {
				$(override).css('display', 'none');
			}

			// Shows or Hides the payment pointer override field depending on the status set.
			$('input[type="radio"][name="coil_monetize_post_status"]').on('change', function() {
				if ( $(this).val() != 'no' ) {
					$(override).css('display', 'initial');
				} else {
					$(override).css('display', 'none');
				}
			});
		});
		</script>
		<?php
		}*/

		// Allow third party plugins hook here for additional support if needed.
		do_action( 'coil_metabox_action', $post );

		// Add nonce for security and authentication.
		wp_nonce_field( 'coil_metabox_nonce_action', 'coil_metabox_nonce' );
	} // END coil_metabox_callback()

	/**
	 * Save the metabox data.
	 *
	 * @access public
	 * @param  int $post_id - The ID of the post being saved.
	 * @return int $post_id
	 */
	public function save_metabox( $post_id ) {
		// Add nonce for security and authentication.
		$nonce_name   = isset( $_POST['coil_metabox_nonce'] ) ? $_POST['coil_metabox_nonce'] : '';
		$nonce_action = 'coil_metabox_nonce_action';

		// Check if nonce is valid.
		if ( ! wp_verify_nonce( $nonce_name, $nonce_action ) ) {
			return $post_id;
		}

		// Check if user has permissions to save data.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return $post_id;
		}

		// Checks if we are not autosaving or the post is not a revision.
		$do_autosave = defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE;
		$is_autosave = wp_is_post_autosave( $post_id );
		$is_revision = wp_is_post_revision( $post_id );

		if ( $do_autosave || $is_autosave || $is_revision ) {
			return $post_id;
		}

		// Check if there was a multisite switch before.
		if ( is_multisite() && ms_is_switched() ) {
			return $post_id;
		}

		// Now that we're authenticated, time to save the data.
		// This sanitizes the data from the field and saves it into an array `$coil_meta`.
		$coil_meta = array(
			'coil_monetize_post_status' => esc_textarea( $_POST['coil_monetize_post_status'] ),
			//'coil_payout_pointer_id' => esc_textarea( $_POST['coil_payout_pointer_id'] )
		);

		foreach( $coil_meta as $key => $value ) {
			if ( get_post_meta( $post_id, '_' . $key, false ) ) {
				// If the custom field already has a value, update it.
				update_post_meta( $post_id, '_' . $key, $value );
			} else {
				// If the custom field doesn't have a value, add it.
				add_post_meta( $post_id, '_' . $key, $value );
			}

			if ( ! $value ) {
				// Delete the meta key if there's no value
				delete_post_meta( $post_id, '_' . $key );
			}
		} // END foreach

		return $post_id;
	} // save_metabox()

} // END class.

return new Coil_Meta_Box();