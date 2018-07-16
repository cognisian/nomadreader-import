<?php
/**
 * Plugin Name: NomadReader Books
 * Plugin URI: https://www.nomadreader.com/
 * Description: A WordPress WooCommerce plugin to import from CSV or search and
 * import book details from Amazon Affiliate API, manage the Amazon Affiliate
 * API Access and affiliate tokens, and export existing books to CSV
 * Version: 1.0.0
 * Author: Sean Chalmers seandchalmers@yahoo.ca
 */

define('PLUGIN_NAME', 'nomadreader-import');
define('PLUGIN_URL', plugin_dir_url(__FILE__));
define('PLUGIN_VER', '1.0.0');

define('NR_MENU_SLUG', 'nomadreader-import');

define('NR_AWS_TOKENS_MENU_ID', 'nr-aws-tokens');

define('NR_OPT_AWS_TOKENS_GRP', 'nr-aws-tokens-group');
define('NR_OPT_AWS_TOKENS_CONFIG', 'nr-aws-tokens-config');

define('NR_OPT_AWS_TOKENS_SECT', 'nr-aws-tokens-section');
define('NR_OPT_AFFILIATE_SECT', 'nr-affiliate-section');

define('NR_AWS_ACCESS_TOKEN', 'aws-access-token');
define('NR_AWS_SECRET_TOKEN', 'aws-secret-token');
define('NR_AWS_AFFILIATE_TAG', 'aws-affiliate-tag');
define('NR_AMZN_BUY_BTN_TEXT', 'nr-buy-button-text');

define('ENC_K', '%reda-on_the-go@');
define('ENC_V', 'koob^7915~trval+');
define('ENC_M', 'AES-128-CBC');

///////////////////////////////////////////////////////////////////////////////
// ENTRY POINT
///////////////////////////////////////////////////////////////////////////////

add_action('admin_menu', 'nomadreader_books');
add_action('admin_init', 'register_nomadreader_config');

// Register the form submission handlers
add_action('admin_post_import_files', 'import_files');
add_action('admin_post_export_books', 'export_books');
add_action('admin_post_update_ext_links', 'update_ext_links');

// WooCommerce Product Admin table UI hooks
add_filter('manage_edit-product_columns', 'add_book_columns', 10, 1);
add_filter('manage_posts_custom_column', 'add_book_columns_content', 10, 3);
add_action('admin_print_styles', 'add_book_columns_style');

/**
 * Entry point to create the NomadReader Books plugin menus
 */
function nomadreader_books() {
	include("config.php");

	// Main menu for NomadReader functions
	add_menu_page('NomadReader Books', 'NomadReader', 1, NR_MENU_SLUG,
		'nomadreader_search', PLUGIN_URL . 'images/book.png', 7);

	// Add the menu option to export and add the target to perform the export
	add_submenu_page(NR_MENU_SLUG, 'NomadReader Export Books',
		'Export Books', 1, 'nomadreader_export_books', 'nomadreader_export_books');
	add_action('admin_action_export_books', 'export_books');

	// Add menu to update external affiliate links
	add_submenu_page(NR_MENU_SLUG, 'NomadReader Update External Affiliate Links',
		'Update Ext Affiliate Links', 1, 'update_ext_links',
		'nomadreader_update_ext_links');
	add_action('admin_action_update_ext_links', 'update_ext_links');

	// Add menu to Settings to update the Amazon tokens
 	add_options_page('NomadReader Amazon Tokens', 'NomadReader Amazon Tokens',
		'manage_options', NR_AWS_TOKENS_MENU_ID, 'ui_nomadreader_amzn_tokens');

	// Add any JS/CSS
	add_action('admin_enqueue_scripts', 'nomadreader_books_enqueue');
}

//////////////////////////////////////////////////////////////////////////////
// MENU Action functions
//////////////////////////////////////////////////////////////////////////////
/**
 * Show the Search/Import UI
 */
function nomadreader_search() {

	ob_start();
		$num_files_uploadable = ini_get('max_file_uploads');
		$size_files_uploadable = ini_get('post_max_size');
		include('templates/tpl-search-import.phtml');
		if (isset($_GET['msgs']) && !empty($_GET['msgs'])) {
			$msgs = json_decode(base64_decode($_GET['msgs']));
			if (property_exists($msgs, 'err')) {
				foreach($msgs->err as $err_msg) {
					include('templates/tpl-error-notice.phtml');
				}
			}
			if (property_exists($msgs, 'inf')) {
				foreach($msgs->inf as $inf_msg) {
					include('templates/tpl-info-notice.phtml');
				}
			}
		}
	ob_end_flush();
}

/**
 * Show the Export Books UI
 */
function nomadreader_export_books() {

	ob_start();
		include('templates/tpl-export-books.phtml');
		if (isset($_GET['msgs']) && !empty($_GET['msgs'])) {
			$msgs = json_decode(base64_decode($_GET['msgs']));
			if (property_exists($msgs, 'err')) {
				foreach($msgs->err as $err_msg) {
					include('templates/tpl-error-notice.phtml');
				}
			}
			if (property_exists($msgs, 'inf')) {
				foreach($msgs->inf as $inf_msg) {
					include('templates/tpl-info-notice.phtml');
				}
			}
		}
	ob_end_flush();
}

/**
 * Show the Export Books UI
 */
function nomadreader_update_ext_links() {

	ob_start();
		include('templates/tpl-update-ext-links.phtml');
		if (isset($_GET['msgs']) && !empty($_GET['msgs'])) {
			$msgs = json_decode(base64_decode($_GET['msgs']));
			if (property_exists($msgs, 'err')) {
				foreach($msgs->err as $err_msg) {
					include('templates/tpl-error-notice.phtml');
				}
			}
			if (property_exists($msgs, 'inf')) {
				foreach($msgs->inf as $inf_msg) {
					include('templates/tpl-info-notice.phtml');
				}
			}
		}
	ob_end_flush();
}

//////////////////////////////////////////////////////////////////////////////
// POST Target functions
//////////////////////////////////////////////////////////////////////////////

/**
 * Export all books to a specified format
 */
function export_books() {

	if (ini_get('safe_mode')) {
		ini_set('max_execution_time', 0);
	}
	else {
		set_time_limit(0);
	}

	$result = '';
	$msgs = array();

	$file_type = '';
	$file_mime = '';
	if (isset($_POST['export_books_csv_submit'])) {
		$file_type .= '.csv';
		$file_mime = 'text/csv';
	}
	elseif (isset($_POST['export_books_json_submit'])) {
		$file_type .= '.json';
		$file_mime = 'application/json';
	}
	else {
		add_error($msgs, 'Missing expected export type.');
	}
	$file_name = 'nomadreader-books' . $file_type;

	$csv = [];
	$csv[] = 'ISBN, Title, Authors, Summary, Rating, Location, Genres, Periods, Tags, Cover';
	$csv_rec = '%1$s, "%2$s", "%3$s", "%4$s", "%5$s", "%6$s", "%7$s", "%8$s", "%9$s", %10$s';


	// Get the list of published product posts
	$args = array(
		'orderby'          => 'ID',
		'order'            => 'DESC',
		'posts_per_page' 	 => -1,
		'post_type'        => 'product',
		'post_status'      => 'publish',
	);
	$books = get_posts($args);

	foreach($books as $book) {
		$tmp = [];
		// For each post get post meta data for ISBN
		$isbn = get_post_meta($book->ID, 'isbn_prod', true);
		if (!empty($isbn)) {
				$tmp['isbn'] = $isbn;
		}
		else {
			add_error($msgs, "Unable to locate ISBN for book %s", array($book->post_title));
		}

		$tmp['title'] = $book->post_title;
		$tmp['summary'] = str_replace('"', '""', $book->post_content);

		// For each top level term get ID
		$auth_term_id = get_term_by('name', 'Authors', 'product_cat');
		$loc_term_id = get_term_by('name', 'Location', 'product_cat');
		$genres_term_id = get_term_by('name', 'Genres', 'product_cat');
		$periods_term_id = get_term_by('name', 'Periods', 'product_cat');

		// For each post get all the associated terms product_cat
		$terms = wp_get_post_terms($book->ID, 'product_cat',
															 array("fields" => "all"));
		foreach ($terms as $value) {
			// Add term names to proper CSV column
			switch ($value->parent) {
				case $auth_term_id->term_id:
				  if (isset($tmp['authors'])) { $tmp['authors'] .= ', ' . $value->name; }
					else { $tmp['authors'] = $value->name; }
					break;

				case $loc_term_id->term_id:
					if (isset($tmp['location'])) { $tmp['location'] .= ', ' . $value->name; }
					else { $tmp['location'] = $value->name; }
					break;

				case $genres_term_id->term_id:
					if (isset($tmp['genres'])) { $tmp['genres'] .= ', ' . $value->name; }
					else { $tmp['genres'] = $value->name; }
					break;

				case $periods_term_id->term_id:
					if (isset($tmp['periods'])) { $tmp['periods'] .= ', ' . $value->name; }
					else { $tmp['periods'] = $value->name; }
					break;
			}
		}

		// For each post get all the associated tags product_tag
		$tags = wp_get_post_terms($book->ID, 'product_tag',
															array("fields" => "all"));
		$tmp['tags'] = '';
		foreach($tags as $tag) {
			if (isset($tmp['tags'])) { $tmp['tags'] .= ', ' . $tag->name; }
			else { $tmp['tags'] = $tag->name; }
		}

		$tmp['cover'] = '';
		$thumb_id = get_post_meta($book->ID, '_thumbnail_id', True);
		$attachment = get_post($thumb_id);
		if ($attachment) {
			$tmp['cover'] = $attachment->guid;
		}
		else {
			add_error($msgs, "Unable to image for book %s", array($book->post_title));
		}

		// Build CSV record of:
	 	// ISBN, Title, Authors, Summary, Rating, Location, Genres, Periods, Tags,	Cover
		$csv[] = sprintf($csv_rec, $tmp['isbn'], $tmp['title'], $tmp['authors'],
											$tmp['summary'], "0.0", $tmp['location'],
											html_entity_decode($tmp['genres']), $tmp['periods'],
											$tmp['tags'], $tmp['cover']);
	}

	// Build complete CSV string
	foreach($csv as $row) {
		$result .= $row . "\n";
	}

	// Download It
	if (!empty($result) && !empty($file_name)) {
		header('Content-Description: Download NomadReader books');
		header("Content-type: " . $file_mime);
		header("Content-Disposition: attachment; filename=" . $file_name);
		header("Pragma: no-cache");
		header("Expires: 0");
		ob_end_clean();
		ob_start();
			echo $result;
		ob_end_flush();
	}
}

/**
 * Callback to handler admin POST submission to import a file
 */
function import_files() {

	if (ini_get('safe_mode')) {
		ini_set('max_execution_time', 0);
	}
	else {
		set_time_limit(0);
	}

	require('Book.php');
	require('utilities.php');

	$msgs = array();
	$books = array();

	// Make sure this is the correct operation
	if (isset($_POST['action']) && $_POST['action'] == 'import_files' &&
	 		 isset($_FILES['import_files']['tmp_name'])) {

		$import_files = $_FILES['import_files']['tmp_name'];
		if (count(isset($import_files) > 0) && !empty($import_files[0])) {
			foreach($import_files as $idx => $import_file) {

				// Extract the book details from the given file type
				$file_type = $_FILES['import_files']['type'][$idx];
				if ($file_type == 'text/csv') {
					$books = Book::parse_csv($import_file);
				}
				elseif ($file_type == 'application/json') {
					$books = Book::parse_json($import_file);
				}
				else {
					add_error($msgs, "Unable to import %s of type %s",
										array($_FILES['import_files']['name'][$idx], $file_type));
					break;
				}

				// ADD to WordPress
				foreach($books as $book) {

					// Create the MAIN post
					$post_id = create_product_post($book);
					if (!is_wp_error($post_id)) {

						// Set the WooCommerce metadata
						create_post_metadata($post_id, $book->isbn);

						// Setup data structure to associate cover image with post
						$img = array(
							'file' 		=> $book->cover,
							'height'	=> 0,
							'width'		=> 0,
						);
						$attach_id = create_attachment_post($img, $post_id);
						if (is_wp_error($attach_id)) {
							add_error($msgs, "Could not insert attachment post for %s %s; %s",
												array($book->isbn, $book->title, $attach_id->get_error_message()));
						}

						// Create the set of term IDs, if not exist and associate
						$genres = convert_term_names_to_term_ids($book->genres, 'genres');
						$periods = convert_term_names_to_term_ids($book->periods, 'periods');
						$authors = convert_term_names_to_term_ids($book->authors, 'authors');
						// Split location on comma to get individual terms for city and country
						$location_parts = array();
						foreach($book->locations as $location) {
							$parts = explode(',', $location);
							foreach($parts as $temp) {
								$location_parts[] = trim($temp);
							}
						}
						$locations = convert_term_names_to_term_ids($location_parts, 'location');

						// Create the list of term IDs for all the differrent term types
						$all_terms = array_merge($authors, $genres, $periods, $locations);
						$result = create_post_object_terms($post_id, $all_terms, $book->tags,
																								$book->rating);
						if (is_wp_error($result)) {
							add_error($msgs, "Could not create/assoc terms for %s %s; %s",
												array($book->isbn, $book->title, $result->get_error_message()));
						}

						// TODO Add ratings
						// If no errors then add INFO book added message
						if (empty($msgs['err'])) {
							add_notice($msgs, "Added book %s %s", array($book->isbn, $book->title));
						}
					}
					else {
						// Failed to create WP post for book
						add_error($msgs, "Could not create Book product post for %s %s; %s",
											array($book->isbn, $book->title, $post_id->get_error_message()));
						break;
					}
				}
			}
		}
		else {
			add_error($msgs, "No Import File specified");
		}
	}

	$url = add_query_arg('msgs', base64_encode(json_encode($msgs)),
					admin_url('admin.php?page=' . PLUGIN_NAME));
	wp_redirect($url);
	die();
};

/**
 * Update the Affiliate external link URL to use on Buy button
 */
function update_ext_links() {

	$msgs = array();
	$count = 0;

	// Load the values from wordpress options
	$options = get_option(NR_OPT_AWS_TOKENS_CONFIG);

  $aff_value = isset($options[NR_AWS_AFFILIATE_TAG]) ?
    						esc_attr($options[NR_AWS_AFFILIATE_TAG]) : '';
  $buy_value = isset($options[NR_AMZN_BUY_BTN_TEXT]) ?
    						esc_attr($options[NR_AMZN_BUY_BTN_TEXT]) : '';

	// Get the list of published product posts
	$args = array(
		'orderby'          => 'date',
		'order'            => 'DESC',
		'post_type'        => 'product',
		'post_status'      => 'publish',
	);
	$books = get_posts($args);
	if (!is_wp_error($books)) {
		// For each post get post meta data for ISBN and update the
		// external URL for the book
		foreach($books as $book) {
			$isbn = get_post_meta($book->ID, 'isbn_prod', true);

			update_post_meta($book->ID, '_product_url',
				"https://www.amazon.com/dp/" . $isbn . "/?tag=" . $aff_value);
			update_post_meta($book->ID, '_button_text', $buy_value);

			$count += 1;
		}
	}
	else {
		add_error($msgs, "Unable to load posts: %s", array($books->get_error_message()));
	}

	$url = add_query_arg('msgs', base64_encode(json_encode($msgs)),
					admin_url('admin.php?page=update_ext_links'));
	wp_redirect($url);
	die();
}

///////////////////////////////////////////////////////////////////////////////
// SUPPORT Functions
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

/*
 * Register the NomadReader Books plugin settings
 */
function register_nomadreader_config() {

	// Insert the empty NomadReader options, if not exist
	$opts = array(
		NR_AWS_ACCESS_TOKEN		=> '',
		NR_AWS_SECRET_TOKEN		=> '',
		NR_AWS_AFFILIATE_TAG	=> '',
		NR_AMZN_BUY_BTN_TEXT	=> '',
	);
	add_option(NR_OPT_AWS_TOKENS_CONFIG, $opts);

	// Register the different config properties
	$args = array(
						'type' 							=> 'string',
          	'sanitize_callback' => 'sanitize_text_array',
          	'default' 					=> NULL
	);
	register_setting(NR_OPT_AWS_TOKENS_GRP, NR_OPT_AWS_TOKENS_CONFIG, $args);

	// Register the UI handler for config properties on the Settings page
	// to set the AWS Product API access tokens
	add_settings_section(NR_OPT_AWS_TOKENS_SECT, 'NomadReader Amazon Tokens',
		'ui_nr_aws_section_cb', NR_OPT_AWS_TOKENS_GRP);
	// Register the individual editable config properties
	add_settings_field(NR_AWS_ACCESS_TOKEN, 'AWS Access Key',
		'aws_access_token_cb', NR_OPT_AWS_TOKENS_GRP, NR_OPT_AWS_TOKENS_SECT);
	add_settings_field(NR_AWS_SECRET_TOKEN, 'AWS Secret Key',
		'aws_secret_token_cb', NR_OPT_AWS_TOKENS_GRP, NR_OPT_AWS_TOKENS_SECT);

	// Register the UI handler for config properties on the Settings page
	// to set the Amazon affiliate tag to use in URLs and the corr. button text
	add_settings_section(NR_OPT_AFFILIATE_SECT, 'NomadReader Affiliate Link',
		'ui_nr_aff_section_cb', NR_OPT_AWS_TOKENS_GRP);
	add_settings_field(NR_AWS_AFFILIATE_TAG, 'AWS Affiliate Tag',
		'aws_affiliate_tag_cb', NR_OPT_AWS_TOKENS_GRP, NR_OPT_AFFILIATE_SECT);
	add_settings_field(NR_AMZN_BUY_BTN_TEXT, 'Buy Button Text',
		'nr_buy_button_text_cb', NR_OPT_AWS_TOKENS_GRP, NR_OPT_AFFILIATE_SECT);
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


// UI Stuff

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
		'featured' => '<span class="wc-featured parent-tips" data-tip="Featured">Featured</span>',
		//'product_type' => '<span class="wc-type parent-tips" data-tip="Type">Type</span>' // REMOVED
	);
}

/**
 * Add the custom column data to the WooCommerce Product admin table
 *
 * @param string 	The current column name
 * @param string 	The column content
 */
function add_book_columns_content($column, $id){

	require_once('utilities.php');

	if (strtolower($column) == 'isbn') {
			$isbn = get_post_meta($id, 'isbn_prod', true);
			echo $isbn;
	}
	elseif ($column == 'authors' || $column == 'genres' || $column == 'periods') {
		// $names = get_book_term_names($id, $column);
		// $delim_names = implode(', ', $names);
		// $replaced = preg_replace('/(.*?),/', '$1<br/>', $delim_names);
		// echo $replaced;
		$names = get_book_term_names($id, $column);
		foreach($names as $name) {
			echo '<a href="' . esc_url(admin_url('edit.php?product_cat=' .
					esc_html(sanitize_title($name)) . '&post_type=product')) . ' ">' .
					esc_html($name) . '</a>, <br/>';
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
 * Add the necessary JavaScript/CSS for the admin pages
 */
function nomadreader_books_enqueue($hook) {

    // Twitter Bootstrap JS
    wp_register_script('prefix_bootstrap',
			'//maxcdn.bootstrapcdn.com/bootstrap/3.3.6/js/bootstrap.min.js');
    wp_enqueue_script('prefix_bootstrap');

    // Twitter Bootstrap CSS
    wp_register_style('prefix_bootstrap',
			'//maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css');
    wp_enqueue_style('prefix_bootstrap');
}

?>
