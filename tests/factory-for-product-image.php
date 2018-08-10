<?php
/**
 * WordPress UNitTest class to create the necessary posts/meta data to create a
 * WooCommerce product post with thumbnail image and add the image to the
 * Media library
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

    $attachment_id = wp_insert_attachment($args);
    
    $metadata = wp_generate_attachment_metadata($attachment_id, $args['guid']);
  	$metadata['file'] = basename($args['guid']);
  	$metadata['sizes'] = array(
  		'full' => array(
  			'file'   => basename($args['guid']),
  			'height' => 0,
  			'width'  => 0
  		)
  	);
    wp_update_attachment_metadata($attachment_id, $metadata);

    // Add the image as product image
  	add_post_meta($args['post_parent'], '_thumbnail_id', $attachment_id, true);

    return $attachment_id;
  }

}

?>
