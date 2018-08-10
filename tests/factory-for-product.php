<?php
/**
 *
 */

/**
 * WordPress UnitTest factory to create Products
 */
class WP_UnitTest_Factory_For_Product extends WP_UnitTest_Factory_For_Post {

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

}

?>
