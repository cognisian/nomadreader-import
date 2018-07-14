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

// Register the form submission handlers to process:
// 		import_file (csv and json detection required)
add_action('admin_post_import_files', 'import_files');

/**
 * Entry point to create the NomadReader Books plugin menus
 */
function nomadreader_books() {
	include("config.php");

	// Main menu for NomadReader functions
	add_menu_page('NomadReader Books', 'NomadReader', 1, NR_MENU_SLUG,
		'nomadreader_search', PLUGIN_URL . 'images/book.png', 7);

	// Add the menu option to export and add the target to perform the export
	add_submenu_page(NR_MENU_SLUG, 'NomadReader Export To CSV',
		'Export to CSV', 1, 'export_books', 'nomadreader_export_books');
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
	include('tpl-search-import.phtml');
	ob_end_flush();
}

//////////////////////////////////////////////////////////////////////////////
// POST Target functions
//////////////////////////////////////////////////////////////////////////////

/**
 * Callback to handler admin POST submission to import a file
 */
function import_files() {

	require('Book.php');
	require('utilities.php');

	if ((isset($_POST['action']) && $_POST['action'] == 'import_files') &&
	 		(isset($_FILES['import_files']) && is_array($_FILES['import_files']) &&
			 !empty($_FILES['import_files']))) {

		$import_files = $_FILES['import_files']['tmp_name'];

		$books = array();
		foreach($import_files as $idx => $import_file) {

			// Extract the book details from the given file type
			$file_type = $_FILES['import_files']['type'][$idx];
			if ($file_type == 'text/csv') {
				$books = Book::parse_csv($import_file);
			}
			elseif ($file_type == 'application/json') {
				$books = Book::parse_json($import_file);
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
					create_attachment_post($img, $post_id);

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
					create_post_object_terms($post_id, $all_terms, $book->tags);
				}
				else {
					// TODO Error Processing
				}
			}
		}
	}

	wp_redirect(admin_url('admin.php?page=' . PLUGIN_NAME));
	die();
}

/**
 * Callback to handler admin POST submission to import CSV file
 */
function import_json() {
}

///////////////////////////////////////////////////////////////////////////////
// SUPPORT Functions
///////////////////////////////////////////////////////////////////////////////

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

// UI Stuff

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

		// jQuery multiselect option dropdown/search plugin
		$url = plugins_url(PLUGIN_NAME . '/javascripts');
		wp_register_script('amznbk_jq_chosen_js', $url . '/chosen.jquery.min.js');
		wp_enqueue_script('amznbk_jq_chosen_js');

		$url = plugins_url(PLUGIN_NAME . '/css');
		wp_register_style('amznbk_jq_chosen_css', $url .'/chosen.min.css');
		wp_enqueue_style('amznbk_jq_chosen_css');
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
