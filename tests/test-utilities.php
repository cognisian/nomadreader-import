<?php

defined('WPINC') || die();


/**
 * Class BookUtilitiesTest
 *
 * @package Nomadreader_Import_Master
 */
require_once('factory-for-product.php');
require_once('factory-for-product-image.php');

require_once('utilities.php');

/**
 * Test the Utility WordPress functions
 */
class BookUtilitiesTest extends WP_UnitTestCase {

  /**
	 * Setup the Fixture
	 */
	function setUp() {
		parent::setUp();

		$this->factory->post = new WP_UnitTest_Factory_For_Product($this->factory);
		$this->factory->term = new WP_UnitTest_Factory_For_Term($this->factory, 'product_cat');

		// Use custom Factory to hide some of the WP media details
		$this->factory->cover = new WP_UnitTest_Factory_For_Product_Image($this->factory);
	}

  /**
	 * Test that the given a top level category name (Authors) we get
	 * correct term ID
	 *
	 * @covers ::get_toplevel_id
	 */
	public function test_load_term_category_id() {

    // FIXTURE - setup the product categories under the WooCommerce product_cat
    //           parent category
    $test_toplevel_name = 'Authors';
    $test_toplevel_slug = 'authors';

    // FIXTURE - Create the product category
		$taxonomy = $this->factory->term->create(array(
				'name' => $test_toplevel_name,
				'slug' => $test_toplevel_slug,
				'taxonomy' => WC_CATEGORY_TAXN
		));

		// TEST - Get category's term ID from category name
    $category_id = get_toplevel_id($test_toplevel_name);

		// VALIDATE - that a Book with dummy data is returned
		$this->assertEquals($taxonomy, $category_id);
	}

	/*
	 * Given that a product post exists which has associated author names under the
	 * product category Authors for WooCommerce product_cat
	 *
	 * @covers ::get_book_terms_names_by_category
	 * @covers ::filter_terms_by_category
	 */
	public function test_get_book_term_names() {

    // FIXTURE - Setup the product post, post meta and categories under the
		// 					 WooCommerce product_cat parent category and author names associated
		//					 with the product post
		// Create fixture - product post
		$post_id = $this->factory->post->create();

    $test_toplevel_name = CATG_AUTHORS;
    $test_toplevel_slug = lcfirst(CATG_AUTHORS);

		$test_author_names = array('A Author', 'B Author');

    // FIXTURE - Create the product category
		$taxonomy = $this->factory->term->create(array(
				'name' => $test_toplevel_name,
				'slug' => $test_toplevel_slug,
				'taxonomy' => WC_CATEGORY_TAXN
		));

		// FIXTURE - Create author terms
		$test_authors = array();
		foreach($test_author_names as $name) {
			$test_authors[] = $this->factory->term->create(array(
				'name'			=> $name,
				'parent' 		=> $taxonomy,
				'taxonomy'	=> WC_CATEGORY_TAXN
			));
		}

		// FIXTURE - Associate authors to product post
		$terms = $this->factory->term->add_post_terms($post_id, $test_authors,
																				 WC_CATEGORY_TAXN, true);

		// TEST - Get the Author names for the product post
		$authors = get_book_terms_names_by_category($post_id, get_toplevel_id(CATG_AUTHORS));

		// VALIDATE - Same arrays
		$this->assertSame($authors, $test_author_names);
	}

	/*
	 * Retrieve all ISBNs (post_meta)
	 *
	 * @covers ::get_all_isbns
	 */
	public function test_get_all_isbns() {

		// FIXTURE - Create posts with ISBN post meta
		$test_isbns = array('1111111111', '9999999999');
		foreach($test_isbns as $isbn) {
			$post_id = $this->factory->post->create();
			add_post_meta($post_id, META_KEY_ISBN, $isbn, true);
		}

		// TEST - Get ISBNS
		$isbns = get_all_isbns();

		// VALIDATE
		$this->assertSame($isbns, $test_isbns);
	}

	/*
	 * Test to create the top level terms (Authors, Locations etc)
	 *
	 * @covers ::create_terms
	 */
	public function test_create_toplevel_term() {

		// FIXTURE - Create posts with ISBN post meta
		$test_term = 'Authors';

		// TEST - Get ISBNS
		$terms = create_terms($test_term, WC_CATEGORY_TAXN);

		// VALIDATE
		$this->assertCount(1, $terms);
		$this->assertEquals($terms[0]->name, $test_term);
	}

	/*
	 * Test to create the top level terms (Authors, Locations etc)
	 *
	 * @covers ::create_terms
	 */
	public function test_create_child_term() {

		// FIXTURE - Create posts with ISBN post meta
		$test_toplevel_term = CATG_AUTHORS;
		$test_author_names = array('A Author', 'B Author');

		// TEST
		$toplevel_term = create_terms($test_toplevel_term, WC_CATEGORY_TAXN);
		$child_terms = create_terms($test_author_names, WC_CATEGORY_TAXN,
																$test_toplevel_term);

		// VALIDATE
		$term_names = filter_terms_by_category($child_terms,
																					get_toplevel_id(CATG_AUTHORS),
																					'name');
		$this->assertSame($term_names, $test_author_names);
	}

	/*
	 * Test to add additional child terms and associate with a product post
	 */
	public function test_add_terms_to_post() {

		// FIXTURE - Setup the product post and categories under the
		// 					 WooCommerce product_cat parent category and author names associated
		//					 with the product post
		$append = True;

		$post_id = $this->factory->post->create();

    $test_toplevel_term = CATG_AUTHORS;
		$test_author_names = array('A Author', 'B Author');
		$test_append_author_name = 'C Author';

		$toplevel_term = create_terms($test_toplevel_term, WC_CATEGORY_TAXN);
		$child_terms = create_terms($test_author_names, WC_CATEGORY_TAXN,
																$test_toplevel_term);
		$term_ids = filter_terms_by_category($child_terms,
																				get_toplevel_id($test_toplevel_term),
																				'term_id');
		$res = wp_set_post_terms($post_id, $term_ids, WC_CATEGORY_TAXN);

		// TEST
		$append_term = create_terms($test_append_author_name, WC_CATEGORY_TAXN,
																$test_toplevel_term);
		$appended_term_id = filter_terms_by_category($append_term,
																								get_toplevel_id($test_toplevel_term),
																								'term_id');
		wp_set_post_terms($post_id, $appended_term_id, WC_CATEGORY_TAXN,
										 $append);

		// VALIDATE
		$terms = get_book_terms_names_by_category($post_id,
																							get_toplevel_id($test_toplevel_term));

		$this->assertCount(1, $appended_term_id);
		$this->assertCount(3, $terms);
		$this->assertContains($test_append_author_name, $terms);
	}

	/*
	 * Test to replace child terms with new terms and associate with a product post
	 */
	public function test_replace_terms_to_post() {

		// FIXTURE - Setup the product post and categories under the
		// 					 WooCommerce product_cat parent category and author names associated
		//					 with the product post
		$append = False;

		$post_id = $this->factory->post->create();

    $test_toplevel_term = CATG_AUTHORS;
		$test_author_names = array('A Author', 'B Author');
		$test_replace_author_names = array('C Author', 'D Author');

		$toplevel_term = create_terms($test_toplevel_term, WC_CATEGORY_TAXN);
		$child_terms = create_terms($test_author_names, WC_CATEGORY_TAXN,
																$test_toplevel_term);
		$term_ids = filter_terms_by_category($child_terms,
																				get_toplevel_id($test_toplevel_term),
																				'term_id');
		$res = wp_set_post_terms($post_id, $term_ids, WC_CATEGORY_TAXN);

		// TEST
		$new_terms = create_terms($test_replace_author_names, WC_CATEGORY_TAXN,
															$test_toplevel_term);
		$new_term_ids = filter_terms_by_category($new_terms,
																						get_toplevel_id($test_toplevel_term),
																						'term_id');
		wp_set_post_terms($post_id, $new_term_ids, WC_CATEGORY_TAXN,
										 $append);

		// VALIDATE
		$terms = get_book_terms_names_by_category($post_id,
																							get_toplevel_id($test_toplevel_term));

		$this->assertCount(2, $new_term_ids);
		$this->assertCount(2, $terms);
		$this->assertSame($test_replace_author_names, $terms);
	}

	/*
	 * Test to merge (keep 1 term and replace 1 term) child terms and associate
	 * with a product post
	 */
	public function test_merge_terms_to_post() {

		// FIXTURE - Setup the product post and categories under the
		// 					 WooCommerce product_cat parent category and author names associated
		//					 with the product post
		$append = False;

		$post_id = $this->factory->post->create();

    $test_toplevel_term = CATG_AUTHORS;
		$test_author_names = array('A Author', 'B Author');
		$test_merge_author_names = array('B Author', 'D Author');

		$toplevel_term = create_terms($test_toplevel_term, WC_CATEGORY_TAXN);
		$child_terms = create_terms($test_author_names, WC_CATEGORY_TAXN,
																$test_toplevel_term);
		$term_ids = filter_terms_by_category($child_terms,
																				get_toplevel_id($test_toplevel_term),
																				'term_id');
		$res = wp_set_post_terms($post_id, $term_ids, WC_CATEGORY_TAXN);

		// TEST
		$merge_terms = create_terms($test_merge_author_names, WC_CATEGORY_TAXN,
															$test_toplevel_term);
		$merge_term_ids = filter_terms_by_category($merge_terms,
																							get_toplevel_id($test_toplevel_term),
																							'term_id');
		wp_set_post_terms($post_id, $merge_term_ids, WC_CATEGORY_TAXN,
		 								 $append);

		// VALIDATE
		$terms = get_book_terms_names_by_category($post_id,
																							get_toplevel_id($test_toplevel_term));

		$this->assertCount(2, $merge_term_ids);
		$this->assertCount(2, $terms);
		$this->assertSame($test_merge_author_names, $terms);
	}

}

?>
