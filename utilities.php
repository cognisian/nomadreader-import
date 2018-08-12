<?php
/**
 * Module Name: NomadReader Books
 * Plugin URI: https://www.nomadreader.com/
 * Description: Collection of NomadReader WordPress utility functions
 * Version: 1.0.0
 * Author: Sean Chalmers seandchalmers@yahoo.ca
 */

defined('WPINC') || die();


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
    WHERE pm.meta_key = 'isbn_prod'
  ");

  return $r;
 }

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
		'authors' => 'authors',  // CUSTOM
		'location' => 'location',  // CUSTOM
		'genres' => 'genres',  // CUSTOM
		'periods' => 'periods',
		// 'rating' => 'rating',  // CUSTOM
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
			$isbn = get_post_meta($id, 'isbn_prod', true);
			echo $isbn;
	}
	elseif ($column == 'authors' || $column == 'genres' || $column == 'periods') {
		$names = get_book_term_names($id, $column);
		foreach($names as $name) {
			echo '<a href="' . esc_url(admin_url('edit.php?product_cat=' .
					esc_html(sanitize_title($name)) . '&post_type=product')) . ' ">' .
					esc_html($name) . '</a><br/>';
		}
	}
	elseif ($column == 'location') {
		$names_link = array();

		$names = array_chunk(get_book_term_names($id, $column), 2);
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
	// elseif ($column == 'rating') {
	//$woo_stars = wp_get_object_terms($id, 'product_visibility');
	// $rating_html = '<div class="star-rating" title="' . $woo_stars . ' out of 5' . '">';
	// TODO Repeat this rating times
	// $rating_html = '<div class="star star-full"></div>';
	// $rating_html .= '</div>';
	// echo $rating_html;
	// }
}

/**
 * How the columns should be sorted
 */
function book_orderby($clauses, $query) {
	global $wpdb;

  $orderby = $query->get('orderby');

	if ('authors' === $orderby || 'periods' === $orderby || 'genres' === $orderby ||
			'location' === $orderby) {

		$id = get_toplevel_term($orderby);

		$clauses['join'] .= "
			LEFT OUTER JOIN {$wpdb->term_relationships} ON {$wpdb->posts}.ID={$wpdb->term_relationships}.object_id
			LEFT OUTER JOIN {$wpdb->term_taxonomy} USING (term_taxonomy_id)
			LEFT OUTER JOIN {$wpdb->terms} USING (term_id)";
		$clauses['where'] .= " AND (parent = {$id})";
		$clauses['groupby'] = "object_id";
		$clauses['orderby'] = "GROUP_CONCAT({$wpdb->terms}.name ORDER BY name ASC) ";
		$clauses['orderby'] .= ('ASC' == strtoupper($query->get('order'))) ? 'ASC' : 'DESC';
	}

	return $clauses;
}

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
