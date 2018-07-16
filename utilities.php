<?php
/**
 * Module Name: NomadReader Books
 * Plugin URI: https://www.nomadreader.com/
 * Description: Collection of utility functions to process book data into a
 * WooCommerce formated product with cover images as file attachements.
 * Some of the data suchas isbn, title, summary are inserted into WP
 * post of type product, a attachment and associated categorization into
 * WP terms and associated at top level to WooCommerce product_cat and
 * product_tag terms
 * Version: 1.0.0
 * Author: Sean Chalmers seandchalmers@yahoo.ca
 */

/**
 * Given a term name check if it exists as top level term (one of
 * author, location, genres, periods).  If the top level term does
 * not exist create it.
 *
 * @param string $termname The top level term name to retrieve, or
 * create if not exists
 * @return int The term ID
 */
function get_toplevel_term($termname = '') {

	$result = array();

	$args_main = array(
		'name'										 => $termname,
		'parent'                   => 0,
		'orderby'                  => 'term_group',
		'hide_empty'               => false,
		'hierarchical'             => 1,
		'taxonomy'                 => 'product_cat',
		'pad_counts'               => false
	);
	$term = get_terms($args_main);
	$term_id = 0;
	if (!is_wp_error($term)) {
		if (empty($term)) {
			$temp = wp_insert_term(ucfirst($termname), 'product_cat',
															array('slug' => $termname));
			if (!empty($temp)) {
				$term_id = $temp['term_id'];
			}
		}
		else {
			$term_id = $term[0]->term_id;
		}
	}
	else {
		// TODO ERROR processing
	}

	return $term_id;
}

/**
 * Given a list of subterm names and associated top level term,
 * check if it exists and return its id.  If the top level term
 * does not exist create it as a subterm of the parent.
 *
 * @param array $terms The list of subterms to retrieve/create
 * @param int $parent_term_id The parent term of the list of $terms
 * @return array A list of struct with term id, term_taxonomy_id and term_name
 */
function get_product_terms($terms, $parent_term_id) {

	$product_terms = array();

	// For each term check if it already exists if so return its id and name
	// if it doesnt exist then create it and return id and name
	foreach($terms as $term) {

    // Check if we are passing a name or a ID
		if (is_numeric($term)) {
			$term = (int)$term;
		}

		$temp = term_exists($term, 'product_cat', $parent_term_id);
		if (($temp === null || $temp === 0) || empty($temp)) {
			// Create new subterm
			$new_subterm = wp_insert_term($term, 'product_cat',
																		array('parent' => $parent_term_id));
			if (!is_wp_error($new_subterm)) {
				$product_terms[] = array(
					'term_id'						=> (int)$new_subterm['term_id'],
					'term_taxonomy_id'	=> (int)$new_subterm['term_taxonomy_id'],
					'term_name'					=> $term,
				);
			}
			else {
				// TODO ERROR processing
				$product_terms = array();
			}
		}
		else {
			// Else found an existing terms
			$product_terms[] = array(
				'term_id'						=> (int)$temp['term_id'],
				'term_taxonomy_id'	=> (int)$temp['term_taxonomy_id'],
				'term_name'					=> $term,
			);
		}
	}

	return $product_terms;
}

/**
 * Create and insert the product post from the given info
 *
 * @param obj $book 		A Book object to create the product post
 * @return int|WPError	The inserted post ID
 */
function create_product_post($book) {

	//Create product
	$post = array(
		'post_author' => 1,
		'post_content' => $book->summary,
		'post_excerpt' => $book->excerpt,
		'post_status' => "publish",
		'post_title' => $book->title,
		'post_parent' => '',
		'post_type' => "product",
	);
	$post_id = wp_insert_post($post, $wp_error);

	return $post_id;
}

/**
 * Associate Terms and Tags with the post
 *
 * @param int $post_id  Associate the terms and tags to product post
 * @param array $terms  The array of term IDs to associate
 * @param string $tags  A string of tags to associate
 */
function create_post_object_terms($post_id, $terms, $tags = '', $rating = 0.0) {

  // assigning the product type (ie affiliate link)
	wp_set_object_terms($post_id, 'external', 'product_type');

	// Assign the terms to the product
	$res = wp_set_object_terms($post_id, $terms, 'product_cat', true);
	wp_update_term_count_now($all_terms, 'product_cat');

	// Update the product tags (ensure the woocommerce product_tag exists)
	if (!taxonomy_exists('product_tag')) {
		register_taxonomy('product_tag', 'product');
	}
	wp_set_object_terms($post_id, $book['tags'], 'product_tag', true);
	
	// Set Rating
	$woo_rating = (int)$rating;
	$woo_stars = 'rated-'.$woo_rating;
	wp_set_object_terms($post_id, $woo_stars, 'product_visibility', true);

	return $res;
}

/**
 * Update the Metadata for the product in wooCommerce specific data
 *
 * @param array $img An assoc array of image properties (file, width, height)
 * @param int $parent_post Associate a book cover image with product
 * @param string $region Which Amazon region should URL point to
 */
function create_post_metadata($post_id, $isbn, $region = 'com') {

	// assigning the meta keys to the product
	add_post_meta($post_id, 'isbn_prod', $isbn, true);

	// Update the WooCommerce fields (_product_url being the field controlling
	// the URL assigned to the Buy button)
	update_post_meta( $post_id, '_visibility', 'visible' );
	update_post_meta( $post_id, '_stock_status', 'instock');
	update_post_meta( $post_id, 'total_sales', '0');
	update_post_meta( $post_id, '_downloadable', 'no');
	update_post_meta( $post_id, '_virtual', 'no');
	update_post_meta( $post_id, '_regular_price', "10" );
	update_post_meta( $post_id, '_sale_price', "" );
	update_post_meta( $post_id, '_purchase_note', "" );
	update_post_meta( $post_id, '_featured', "no" );
	update_post_meta( $post_id, '_sku', "");
	update_post_meta( $post_id, '_product_attributes', array());
	update_post_meta( $post_id, '_sale_price_dates_from', "" );
	update_post_meta( $post_id, '_sale_price_dates_to', "" );
	update_post_meta( $post_id, '_price', "" );
	update_post_meta( $post_id, '_sold_individually', "" );
	update_post_meta( $post_id, '_manage_stock', "no" );
	update_post_meta( $post_id, '_backorders', "no" );
	update_post_meta( $post_id, '_stock', "" );

	// Load the values from wordpress options
	$options = get_option(NR_OPT_AWS_TOKENS_CONFIG);

	$value = isset($options[NR_AWS_AFFILIATE_TAG]) ?
						esc_attr($options[NR_AWS_AFFILIATE_TAG]) : '';
	update_post_meta($post_id, '_product_url',
		"https://www.amazon." . $region . "/dp/" . $isbn . "/?tag=" . $value);

	$value = isset($options[NR_AMZN_BUY_BTN_TEXT]) ?
						esc_attr($options[NR_AMZN_BUY_BTN_TEXT]) : '';
	update_post_meta($post_id, '_button_text', $value);
}

/**
 * Create and insert the attachment
 * This should also generate the meta data for the attachment
 *
 * @param array $img An assoc array of image properties (file, width, height)
 * @param int $parent_post Associate a book cover image with product
 */
function create_attachment_post($img, $parent_post = 0) {

	$attachment_id = 0;

	$attachment = array(
		'post_status' => "inherit",
		'post_title' => preg_replace('/\.[^.]+$/', '', basename($img['file'])),
		'post_parent' => $parent_post,
		'post_mime_type' => 'image/jpeg',
		'guid' => $img['file'],
	);
	$attachment_id = wp_insert_attachment($attachment);

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

	// Add the image as product image
	add_post_meta($parent_post, '_thumbnail_id', $attachment_id, true);

	return $attachment_id;
}

/**
 * Convert array of term names into array of term_ids
 * This will create the terms under a existing or created parent_term
 */
function convert_term_names_to_term_ids($terms, $parent_term = '') {
	$parent_id = get_toplevel_term($parent_term);
	$temp = get_product_terms($terms, $parent_id);
	$result = array_reduce($temp, function($sum, $var) {
		$sum[] = $var['term_id'];
		return $sum;
	}, array());

	return $result;
}

/**
 * Get the subterm names for given parent term
 * 
 * @param int $post_id			  The post_id whose term names are to be retrieved
 * @param string $parent_term	The top level term to get its sub termnames
 * @param array	 							The subterm names for parent term
 */
function get_book_term_names($post_id, $parent_term = '') {
	
	$parent_id = get_toplevel_term($parent_term);
	$terms = get_the_terms($post_id, 'product_cat');
	// Remove any terms not related to parent_term
	$result = array_filter((array)$terms, function($val) use ($parent_id) {
		return ($val->parent == $parent_id);
	});
	// Remove all other term attributes
	$result = array_reduce($result, function($sum, $var) {
		$sum[] = $var->name;
		return $sum;
	}, array());
	
	return $result;
}

?>
