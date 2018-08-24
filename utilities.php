<?php
/**
 * Module Name: NomadReader Books
 * Plugin URI: https://www.nomadreader.com/
 * Description: Collection of NomadReader WordPress utility functions
 * Version: 1.0.0
 * Author: Sean Chalmers seandchalmers@yahoo.ca
 */

defined('WPINC') || die();

// Parent Term names (categories) to contain the NomadReader terms
define('CATG_AUTHORS', 'Authors');
define('CATG_GENRES', 'Genres');
define('CATG_PERIODS', 'Periods');
define('CATG_LOCATIONS', 'Locations');

// WooCommerce Taxonomies
define('WC_CATEGORY_TAXN', 'product_cat');
define('WC_TAGS_TAXN', 'product_tag');

// Post Meta Key for ISBN
define('META_KEY_ISBN', 'isbn_prod');


/**
 * Get all the ISBN numbers
 *
 * @return array  	The array of ISBN number (strings)
 */
 function get_all_isbns() {

  global $wpdb;

  $r = $wpdb->get_col("
    SELECT pm.meta_value
		FROM {$wpdb->postmeta} pm
    WHERE pm.meta_key = '" . META_KEY_ISBN ."'"
  );

  return $r;
 }

/**
 * Given the top level categorization (Author, Location, Period, Genre) name
 * return its ID
 *
 * @param string $category_name  The top level name
 * @return int                   Return the term_id of the category else 0
 */
function get_toplevel_id($category_name) {
  $term_id = 0;

  $args = array(
    'name'										 => $category_name,
    'taxonomy'                 => WC_CATEGORY_TAXN,
    'hide_empty'               => false,
    'hierarchical'             => 1,
    'fields'                   => 'ids'
  );
  $wp_term = get_terms($args);
  if (!is_wp_error($wp_term) && !empty($wp_term)) {
    $term_id = $wp_term[0];
  }

  return $term_id;
}

/**
 * Create the top level or child categories from a single string or an array of strings,
 * if term exists then return the array of WP_Terms details
 *
 * @param string|array $term_names  The string or array of string names to create
 * @param string $taxonomy          The raxonomy name to create terms under
 * @param string $category_name     The string name if any, of the parent category to
 *                                  add the $term_names to
 * @return array                    Return an array of WP_Term instances, else empty
 */
function create_terms($term_names, $taxonomy, $category_name='') {
  $product_terms = array();

  if (empty($term_names)) {
      return $product_terms;
  }

  if (!is_array($term_names)) {
    $term_names = array($term_names);
  }

  // Check if we are required to assign terms to a parent category
  $category_id = 0;
  if (!empty($category_name)) {
    $category_id = get_toplevel_id($category_name);
  }

  foreach($term_names as $term_name) {

    // If term exists then return it, else create it
    $temp = term_exists($term_name, $taxonomy, $category_id);
    if (($temp === null || $temp === 0) || empty($temp)) {
      $args = array();
      $args['parent'] = $category_id;
      $new_subterm = wp_insert_term($term_name, $taxonomy, $args);
      if (!is_wp_error($new_subterm)) {
        $product_terms[] = WP_Term::get_instance((int)$new_subterm['term_id'],
                                                  $taxonomy);
      }
    }
    else {
      $product_terms[] = WP_Term::get_instance((int)$temp['term_id'], $taxonomy);
    }
  }

  return $product_terms;
}

/**
 * Load all the child terms names from the parent category ID associated with
 * a product post
 *
 * @param int $post_id      The product post ID
 * @param int $category_id  The term id of the toplevel category to filter
 *                          the returned terms
 * @return array            An array of strings of author names, else empty
 */
function get_book_terms_names_by_category($post_id, $category_id) {
  $terms = [];

  $post_terms = wp_get_post_terms($post_id, WC_CATEGORY_TAXN);
  if (!is_wp_error($post_terms) && !empty($post_terms)) {
    $terms = filter_terms_by_category($post_terms, $category_id, 'name');
  }

  return $terms;
}

/**
 * Load all the child term IDs from the parent category name associated with
 * a product post
 *
 * @param int $post_id      The product post ID
 * @param int $category_id  The term id of the toplevel category to filter
 *                          the returned terms
 * @return array            An array of strings of author names, else empty
 */
function get_book_terms_ids_by_category($post_id, $category_id) {
  $terms = [];

  $post_terms = wp_get_post_terms($post_id, WC_CATEGORY_TAXN);
  if (!is_wp_error($post_terms) && !empty($post_terms)) {
    $terms = filter_term_by_category($post_terms, $category_id, 'id');
  }

  return $terms;
}

/**
 * Given ALL terms for a post, filtered to retrieve the list of child term
 * names whose category term name matches given
 *
 * @param array $post_terms         The set of WP_Term objects to c
 * @param string $category_id       The product category ID to check the parent property
 * @param string $term_field        The nameof the WP_Term attribute to return
 * @return array                    The list term names whose parent matches the
 * provided $category_id
 */
function filter_terms_by_category($post_terms, $category_id, $term_field) {

  $filtered = array();

  $terms = array();
  if ($category_id !== 0) {
    $terms = array_filter($post_terms, function($v) use ($category_id) {
      $res = False;
      if ($v->parent == $category_id) {
        $res = True;
      }
      return $res;
    });
  }
  else {
    $terms = $post_terms;
  }

  $filtered = array_map(function($v) use ($term_field) {
    return $v->$term_field;
  }, $terms);

  return $filtered;
}

///////////////////////////////////////////////////////////////////////////////
// UI Helpers
///////////////////////////////////////////////////////////////////////////////

/**
 * Add column headers to the WooCommerce Product admin table
 *
 * @param array   The array of column labels
 * @return array 	The new array of column names
 */
function add_book_columns($columns){
	return array(
		'cb' => '<input type="checkbox" />', // checkbox for bulk actions
		'thumb' => '<span class="wc-image tips" data-tip="Image">Image</span>',
		'isbn' => 'ISBN', // CUSTOM
		'name' => 'Name',
		'authors' => 'Authors',  // CUSTOM
		'location' => 'Locations',  // CUSTOM
		'genres' => 'Genres',  // CUSTOM
		'periods' => 'Periods',  // CUSTOM
		// 'sku' => 'SKU', // REMOVED
		// 'is_in_stock' => 'Stock',    // REMOVED
		// 'price' => 'Price',    // REMOVED
		// 'product_cat' => 'Categories',  // REMOVED
		'product_tag' => 'Tags',
		'rating' => 'Rating',  // CUSTOM
		'featured' => '<span class="wc-featured parent-tips" data-tip="Featured">Featured</span>',
		//'product_type' => '<span class="wc-type parent-tips" data-tip="Type">Type</span>' // REMOVED
	);
}

/**
 * Add the column headers which are to be sortable
 *
 * @param array   The array of column labels
 * @return array 	The new array of column names
 */
function add_book_sortable_columns($columns){
	return array(
		// 'isbn' => 'isbn_prod', // CUSTOM
		// 'name' => 'Name',
		// 'authors' => 'authors',  // CUSTOM
		// 'locations' => 'locations',  // CUSTOM
		// 'genres' => 'genres',  // CUSTOM
		// 'periods' => 'periods',
		'rating' => 'rating',  // CUSTOM
		'featured' => 'Featured',
	);
}

/**
 * Add the custom column data to the WooCommerce Product admin table
 *
 * @param string 	The current column name
 * @param string 	The column content
 */
function add_book_columns_content($column, $id){

	if (strtolower($column) == 'isbn') {
			$isbn = get_post_meta($id, META_KEY_ISBN, true);
			echo $isbn;
	}
	elseif (strtolower($column) == 'authors' || strtolower($column) == 'genres' ||
      strtolower($column) == 'periods') {

    $tl_term_id = get_toplevel_id(ucfirst($column));
		$names = get_book_terms_names_by_category($id, $tl_term_id);
		foreach($names as $name) {
			echo '<a href="' . esc_url(admin_url('edit.php?product_cat=' .
					esc_html(sanitize_title($name)) . '&post_type=product')) . ' ">' .
					esc_html($name) . '</a><br/>';
		}
	}
	elseif (strtolower($column) == 'location') {
		$names_link = array();

    $tl_term_id = get_toplevel_id(ucfirst($column));
		$names = array_chunk(get_book_terms_names_by_category($id, $tl_term_id), 2);
		foreach($names as $name) {
			$temp = '<a href="' . esc_url(admin_url('edit.php?product_cat=' .
					sanitize_title(strtolower($name[0])) . '&post_type=product')) . ' ">' .
					esc_html($name[0]) . '</a>';
			if (isset($name[1])) {
				$temp .= ', ' . '<a href="' . esc_url(admin_url('edit.php?product_cat=' .
						sanitize_title(strtolower($name[1])) . '&post_type=product')) . ' ">' .
						esc_html($name[1]) . '</a>';
			}
			$names_link[] = $temp;
		}
		$delim_names = implode(',<br/>', $names_link);
		echo $delim_names;
	}
	elseif (strtolower($column) == 'rating') {
	  $rating = get_post_meta($id, '_wc_average_rating', true);
    // I will do the echoing thank you very much
	  echo wp_star_rating(array('rating' => $rating, 'echo' => False));
	}
}

/**
 * How the columns should be sorted
 */
function book_orderby($query) {
	global $wpdb;

  $orderby = $query->get('orderby');

	if ('rating' === strtolower($orderby)) {
    $query->set('meta_key', '_wc_average_rating');
    $query->set('meta_type', 'numeric');
    $query->set('orderby', 'meta_value_num');
    $query->set('order', $_GET['order']);
  }

	return $clauses;
}

/**
 * How the columns should be sorted
 */
// function book_orderby($clauses, $query) {
// 	global $wpdb;
//
//   $orderby = $query->get('orderby');
//
// 	if ('authors' === strtolower($orderby) || 'periods' === strtolower($orderby) ||
//       'genres' === strtolower($orderby) || 'location' === strtolower($orderby)) {
//
// 		$id = get_toplevel_term($orderby);
//
// 		$clauses['join'] .= "
// 			LEFT OUTER JOIN {$wpdb->term_relationships} ON {$wpdb->posts}.ID={$wpdb->term_relationships}.object_id
// 			LEFT OUTER JOIN {$wpdb->term_taxonomy} USING (term_taxonomy_id)
// 			LEFT OUTER JOIN {$wpdb->terms} USING (term_id)";
// 		$clauses['where'] .= " AND (parent = {$id})";
// 		$clauses['groupby'] = "object_id";
// 		$clauses['orderby'] = "GROUP_CONCAT({$wpdb->terms}.name ORDER BY name ASC) ";
// 		$clauses['orderby'] .= ('ASC' == strtoupper($query->get('order'))) ? 'ASC' : 'DESC';
// 	}
//   elseif ('rating' === strtolower($orderby)) {
//     $query->set('orderby', 'meta_value');
//     $query->set('meta_key', '_wc_average_rating');
//     $query->set('meta_type', 'numeric');
//   }
//
// 	return $clauses;
// }

/**
 * Tweak the Product Admin table CSS layout
 */
function add_book_columns_style() {
	$css = ".widefat .column-isbn { width: 8%; }\n";
	$css = ".widefat .column-authors { width: 22%; }\n";
	$css = ".widefat .column-locations { width: 16%; }\n";
	$css = ".widefat .column-genres { width: 16%; }\n";
	$css = ".widefat .column-periods { width: 11%; }\n";
	wp_add_inline_style('woocommerce_admin_styles', $css);
}

///////////////////////////////////////////////////////////////////////////////
// ERROR Helpers
///////////////////////////////////////////////////////////////////////////////

/**
 * Add a informational message to the message stack
 *
 * @param array $msgs					The stack of previous messages to add notice
 * @param string $notice			The notice message in sprintf format
 * @param array $notice_parms	The array of parameters to insert into $notice
 */
function add_notice(&$msgs, $notice, $notice_parms=array()) {
	process_message($msgs, $notice, $notice_parms, 'inf');
}

/**
 * Add a informational message to the message stack
 *
 * @param array $msgs					The stack of previous messages to add notice
 * @param string $notice			The notice message in sprintf format
 * @param array $notice_parms	The array of parameters to insert into $notice
 */
function add_error(&$msgs, $notice, $notice_parms=array()) {
	process_message($msgs, $notice, $notice_parms, 'err');
}

/**
 * Add a informational message to the message stack
 *
 * @param array $msgs					The stack of previous messages to add notice
 * @param string $notice			The notice message in sprintf format
 * @param array $notice_parms	The array of parameters to insert into $notice
 * @param string $type				Type of message, either 'err' or 'inf'
 */
function process_message(&$msgs, $notice, $notice_parms, $type='err') {

	if (empty($msgs) || !array_key_exists($type, $msgs)) {
			$msgs[$type] = array();
	}

	if (count($msgs[$type]) < 10) {
		$msgs[$type][] = vsprintf($notice, $notice_parms);
	}
	else {
		// Replace last messaage with updated messages count
		$curr = count($msgs[$type]);

		$match = array();
		$res = preg_match('/^\d+/', $msgs[$type][$curr - 1], $match);
		if ($res !== FALSE && $res == 1) {
			$curr_count = (int)$match[0];
			$msgs[$type][$curr - 1] = sprintf("%d more %s messages",
																				$curr_count +  1, $type);
		}
		else {
			$msgs[$type][] = sprintf("%d more %s messages", 1, $type);
		}
	}
}

///////////////////////////////////////////////////////////////////////////////
// CONFIG Helpers
///////////////////////////////////////////////////////////////////////////////

/**
 * Encrypt a string using OpenSSL
 *
 * @param string $plaintext Strng to encrypt
 * @param int $key_size Number of bytes the key length should be
 * @return string|bool The encrypted string or False if failed
 */
function encrypt_stuff($plantext, $key_size = 16) {

	$key = substr(hash('sha256', ENC_K), 0, 16);
	$iv = substr(hash('sha256', EN_V), 0, 16);
	return openssl_encrypt($plantext, ENC_M, $key, OPENSSL_RAW_DATA, $iv);
}

/**
 * Decrypt a string using OpenSSL
 *
 * @param string $ciphertext Strng to decrypt
 * @param int $key_size Number of bytes the key length should be
 * @return string|bool The encrypted string or False if failed
 */
function decrypt_stuff($ciphertext, $key_size = 16) {
	$key = substr(hash('sha256', ENC_K), 0, 16);
	$iv = substr(hash('sha256', EN_V), 0, 16);
	return openssl_decrypt($ciphertext, ENC_M, $key, OPENSSL_RAW_DATA, $iv);
}

/**
 * disable the PHP max execution timer for long running process
 */
function disable_execution_timer() {
	if (ini_get('safe_mode')) {
		ini_set('max_execution_time', 0);
	}
	else {
		set_time_limit(0);
	}
}

?>
