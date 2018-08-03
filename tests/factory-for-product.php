<?php
/**
 *
 */

/**
 * WordPress UnitTest factory to create Products
 */
class WP_UnitTest_Factory_For_Product extends WP_UnitTest_Factory_For_Thing {

  /**
   * Construct the Post Factory to create a 'product' post
   */
  function __construct($factory = null) {

    parent::__construct($factory);

    // WooCommerce taxonomies
		register_post_type('product', array(
      'labels' => array(
        'name' => __('Products'),
        'singular_name' => __('Product')
      ),
      'public' => true,
      'has_archive' => true,
	  ));
		register_taxonomy('product_cat', 'product');
		register_taxonomy('product_tag', 'product');

    // Set default values
    $this->default_generation_definitions = array(
      'post_status' => 'publish',
      'post_title' => new WP_UnitTest_Generator_Sequence('Book title %s'),
      'post_content' => new WP_UnitTest_Generator_Sequence('Book summary %s'),
      'post_excerpt' => new WP_UnitTest_Generator_Sequence('Book excerpt %s'),
      'post_type' => 'product'
    );
  }

  /**
   * Creates a Product post with associated ISBN as meta data
   */
  function create_object($args) {

      $post_id = 0;

      $isbn = 'isbn';
      if (isset($args['isbn'])) {
        $isbn = $args['isbn'];
        unset($args['isbn']);
      }
      $post_id = wp_insert_post($args);
      if ($post_id > 0) {
        add_post_meta($post_id, 'isbn_prod', $isbn, true);
      }

      return $post_id;
  }

  /**
   * Update the Product post data
   */
  function update_object($post_id, $fields) {
      $fields['ID'] = $post_id;
      return wp_update_post($fields);
  }

  /**
   * Retrieve the Product post by post ID
   */
  function get_object_by_id($post_id) {
      return get_post($post_id);
  }

}

?>
