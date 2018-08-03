<?php
/**
 *
 */

/**
 * WordPress UnitTest factory to create Attachment
 */
class WP_UnitTest_Factory_For_Product_Image extends WP_UnitTest_Factory_For_Attachment {

  /**
   * Construct the Post Factory to create a 'attachment' post
   */
  function __construct($factory = null) {

    parent::__construct($factory);
    $this->default_generation_definitions = array(
  		'post_title' => 'image.jpg',
  		'post_parent' => 1,
  		'post_mime_type' => 'image/jpeg',
  		'guid' => '/test/image.jpg',
      'post_status' => "inherit",
      'post_type' => 'attachment'
    );
  }

  /**
   * Create the book cover image as a Postand WooCommerce thumbnail
   *
   * @param $args   The set of properties to use to create attachment post. This
   *                will also include the keys 'file', 'width' and 'height'
   */
  function create_object($args) {

    if (isset($args['file']) && isset($args['height']) && isset($args['width'])) {

      $img['file'] = $args['file'];
      unset($args['file']);
      $img['height'] = $args['height'];
      unset($args['height']);
      $img['width'] = $args['width'];
      unset($args['width']);

      $attachment_id = wp_insert_attachment($args);

      $metadata = wp_generate_attachment_metadata($attachment_id, $img['file']);
    	$metadata['file'] = basename($img['file']);
    	$metadata['sizes'] = array(
    		'full' => array(
    			'file'   => basename($img['file']),
    			'height' => $img['height'],
    			'width'  => $img['width']
    		)
    	);
      wp_update_attachment_metadata($attachment_id, $metadata);
    }
    else {
      $attachment_id = wp_insert_attachment($args);
    }

  	// Add the image as product image
  	add_post_meta($args['post_parent'], '_thumbnail_id', $attachment_id, true);

    return $attachment_id;
  }

  function update_object($post_id, $fields) {
      $fields['ID'] = $post_id;
      return wp_update_post($fields);
  }

  function get_object_by_id($post_id) {
      return get_post($post_id);
  }

}

?>
