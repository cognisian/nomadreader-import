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

defined('WPINC') || die();


require_once("utilities.php");
require_once("config.php");

require_once("Book.php");


define('PLUGIN_NAME', 'nomadreader-import');
define('PLUGIN_URL', plugin_dir_url(__FILE__));
define('PLUGIN_VER', '1.0.0');

define('NR_MENU_SLUG', 'nomadreader-import');

define('NR_AWS_TOKENS_MENU_ID', 'nr-aws-tokens');

// CONFIG SECTIONS defns
define('NR_OPT_AWS_TOKENS_GRP', 'nr-aws-tokens-group');
define('NR_OPT_AWS_TOKENS_CONFIG', 'nr-aws-tokens-config');

define('NR_OPT_AWS_TOKENS_SECT', 'nr-aws-tokens-section');
define('NR_OPT_AFFILIATE_SECT', 'nr-affiliate-section');

// CONFIG FIELDS defns
define('NR_AWS_ACCESS_TOKEN', 'aws-access-token');
define('NR_AWS_SECRET_TOKEN', 'aws-secret-token');
define('NR_AWS_AFFILIATE_TAG', 'aws-affiliate-tag');
define('NR_AMZN_BUY_BTN_TEXT', 'nr-buy-button-text');

// enc stuff
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
add_action('admin_post_remove_dups', 'remove_duplicate_books');

// WooCommerce Product Admin table UI hook
add_filter('manage_edit-product_columns', 'add_book_columns', 10, 1);
add_filter('manage_edit-product_sortable_columns', 'add_book_sortable_columns', 10, 1);
add_filter('manage_product_posts_custom_column', 'add_book_columns_content', 10, 3);
add_action('pre_get_posts', 'book_orderby', 10, 1);
// add_action('posts_clauses', 'book_orderby', 10, 2);
add_action('admin_print_styles', 'add_book_columns_style');

/**
 * Entry point to create the NomadReader Books plugin menus
 */
function nomadreader_books() {

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

	// Add menu to update external affiliate links
	add_submenu_page(NR_MENU_SLUG, 'NomadReader Duplicate Book Detection',
		'Remove Duplicate Books', 1, 'remove_duplicate_books',
		'nomadreader_remove_duplicate_books');
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

/**
 * Show the Remove Duplicate Books UI
 */
function nomadreader_remove_duplicate_books() {

	ob_start();
		include('templates/tpl-remove-dups.phtml');
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

	disable_execution_timer();

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

	// CSV record layout
	$csv_rec = '%1$s, "%2$s", "%3$s", "%4$s", "%5$.1f", "%6$s", "%7$s", "%8$s", "%9$s", %10$s';
	// CSV header and book data to export
	$csv = [];
	$csv[] = 'ISBN, Title, Authors, Summary, Rating, Location, Genres, Periods, Tags, Cover';

	// Get the list of published product posts
	$isbns = get_all_isbns();
	if (!empty($isbns)) {
		foreach($isbns as $isbn) {

			$tmp = [];

			$book = Book::load_book($isbn);
			if ($book !== False) {

				// Formaat Book details
				$tmp['isbn'] = $isbn;
				$tmp['title'] = $book->title;
				$tmp['summary'] = str_replace('"', '""', $book->summary);
				$tmp['rating'] = (float)$book->rating;

				// Add term names to proper CSV column (properly formatted)
				$tmp['authors'] = implode(', ', $book->authors);
				$tmp['genres'] = html_entity_decode(implode(', ', $book->genres));
				$tmp['periods'] = implode(', ', $book->periods);
				$tmp['location'] = implode(', ', $book->locations);
				$tmp['tags'] = implode(', ', $book->tags);

				$tmp['cover'] = $book->cover;

				// Build CSV record of:
			 	// ISBN, Title, Authors, Summary, Rating, Location, Genres, Periods, Tags, Cover
				$csv[] = sprintf($csv_rec, $tmp['isbn'], $tmp['title'], $tmp['authors'],
													$tmp['summary'], $tmp['rating'], $tmp['location'],
													$tmp['genres'], $tmp['periods'], $tmp['tags'], $tmp['cover']);
			}
			else {
					add_error($msgs, "Unable to locate Book for %s", array($isbn));
			}
		}

		// Build complete CSV string
		foreach($csv as $row) {
			$result .= $row . "\n";
		}

		// Download It
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
	else {
		add_error($msgs, "Unable to locate any ISBNs");
	}

	return $result;
}

/**
 * Callback to handler admin POST submission to import a file
 */
function import_files() {

	disable_execution_timer();

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

					// Update or Insert book details into WordPress
					if ($book->exists()) {
						$post_id = $book->update();
						if ($post_id === 0) {
							add_error($msgs, "Unable to update Book %s %s to WordPress",
												array($book->isbn, $book->title));
						}
					}
					else {
						$post_id = $book->insert();
						if ($post_id === 0) {
							add_error($msgs, "Unable to insert Book %s %s to WordPress",
												array($book->isbn, $book->title));
						}
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
		'numberposts'			 => -1
	);
	$books = get_posts($args);
	if (!is_wp_error($books)) {
		// For each post get post meta data for ISBN and update the
		// external URL for the book
		foreach($books as $book) {
			$isbn = get_post_meta($book->ID, META_KEY_ISBN, true);

			update_post_meta($book->ID, '_product_url',
				"https://www.amazon.com/dp/" . $isbn . "/?tag=" . $aff_value);
			update_post_meta($book->ID, '_button_text', $buy_value);

			add_notice($msgs, "Updated external link for %s %s", array($isbn, $books->title));
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

/**
 * Remove Duplicate Books
 */
function remove_duplicate_books() {
	global $wpdb;

	disable_execution_timer();

	$msgs = array();
	$books = array();

	if (isset($_POST['action']) && $_POST['action'] == 'remove_dups') {

		// Get the list of ISBNs
		$results = $wpdb->get_results("
			SELECT meta_value as isbn
			FROM {$wpdb->prefix}postmeta
			WHERE meta_key = " . META_KEY_ISBN . "
			GROUP BY meta_value
			ORDER BY meta_value
		");

		// For each ISBN get the associated posts
		foreach($results as $row) {
			// Use DESC as latest post will have higher ID, that way first post returned
			// will be the one we keep ie higher post ID = most recent post
			$args = array(
		    'meta_key' 				=> META_KEY_ISBN,
				'meta_value' 			=> $row->isbn,
		    'post_type' 			=> 'product',
		    'post_status' 		=> 'publish',
		    'posts_per_page' 	=> -1,
				'orderby'         => 'ID',
				'order'           => 'DESC'
			);
			$posts = get_posts($args);

			// If 2 or more posts returned then duplicates
			if (count($posts) >= 2) {
				$ref_post = array_shift($posts);
				foreach($posts as $post) {
					if ($ref_post->post_title == $post->post_title) {
						// Update the post into the trash
					  $res = wp_trash_post($post->ID);
						if ($res === FALSE) {
							add_error($msgs, 'Unable to move post %d to trash for ISBN %s',
												array($post->ID, $row->isbn));
						}
						else {
							add_notice($msgs, 'Removed duplicate post %d for ISBN %s',
												 array($post->ID, $row->isbn));
						}
					}
					else {
						add_notice($msgs, 'Duplicate ISBNs %s point to different books % and %',
												array($row->isbn, $ref_post->post_title, $post->post_title));
					}
				}
			}
		}
	}

	$url = add_query_arg('msgs', base64_encode(json_encode($msgs)),
					admin_url('admin.php?page=' . 'remove_duplicate_books'));
	wp_redirect($url);
	die();
}

///////////////////////////////////////////////////////////////////////////////
// SUPPORT Functions
///////////////////////////////////////////////////////////////////////////////

/*
 * Register the NomadReader Books plugin settings
 */
function register_nomadreader_config() {

	// Insert the empty NomadReader options, if not exist
	$opts = array(
		NR_AWS_ACCESS_TOKEN	=> '',
		NR_AWS_SECRET_TOKEN	=> '',
		NR_AWS_AFFILIATE_TAG	=> '',
		NR_AMZN_BUY_BTN_TEXT	=> '',
	);
	add_option(NR_OPT_AWS_TOKENS_CONFIG, $opts);

	// Register the different config properties
	$args = array(
		'type'							=> 'string',
		'sanitize_callback'	=> 'sanitize_text_array',
	  'default'						=> NULL
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
 * Add the necessary JavaScript/CSS for the admin pages
 */
function nomadreader_books_enqueue($hook) {

	if ($hook != 'toplevel_page_'.PLUGIN_NAME ||
			$hook != 'nomadreader_page_update_ext_links' ||
			$hook != 'nomadreader_page_remove_duplicate_books' ||
			$hook != 'nomadreader_page_nomadreader_export_books') {
  	return;
  }

  // Twitter Bootstrap JS
  wp_register_script('prefix_bootstrap',
		'//maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js');
  wp_enqueue_script('prefix_bootstrap');

  // Twitter Bootstrap CSS
  wp_register_style('prefix_bootstrap',
		'//maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css');
  wp_enqueue_style('prefix_bootstrap');
}

?>
