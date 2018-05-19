<?php
/*
Plugin Name: NomadReader Books
Plugin URI: https://www.nomadreader.com/
Description: A WordPress WooCommerce plugin to import from CSV or search and import book details from Amazon Affiliate API, manage the Amazon Affiliate API Access and affiliate tokens, and export existing books to CSV
Version: 0.9.0
Author: Sean Chalmers seandchalmers@yahoo.ca
*/
define('PLUGIN_NAME', 'nomadreader-books');

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

/**
 * Entry point to create the NomadReader Books plugin options
 */
function nomadreader_books() {
	include("config.php");

	// Main menu for NomadReader functions
	add_menu_page('NomadReader Books', 'NomadReader', 1, 'nomadreader_books',
		'nomadreader_search', plugins_url(PLUGIN_NAME . '/images/book.png'), 7);

	// Add the menu option to export and add the target to perform the export
	add_submenu_page('nomadreader_books', 'NomadReader Export To CSV',
		'Export to CSV', 1, 'export_books', 'nomadreader_export_books');
	add_action('admin_action_export_books', 'export_books');

	// Add menu to update external affiliate links
	add_submenu_page('nomadreader_books',
		'NomadReader Update External Affiliate Links',
		'Update Ext Affiliate Links', 1, 'update_ext_links', 'nomadreader_update_ext_links');
	add_action('admin_action_update_ext_links', 'update_ext_links');

	// Add menu to Settings to update the Amazon tokens
 	add_options_page('NomadReader Amazon Tokens', 'NomadReader Amazon Tokens',
		'manage_options', NR_AWS_TOKENS_MENU_ID, 'ui_nomadreader_amzn_tokens');

	// Add any JS/CSS
	add_action('admin_enqueue_scripts', 'nomadreader_books_enqueue');
}

///////////////////////////////////////////////////////////////////////////////
// SUPPORT Functions
///////////////////////////////////////////////////////////////////////////////

/**
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
 * Show the Search/Import UI
 */
function nomadreader_search() {
	include 'import_books.php';
	// Show the UI if not processing search results
	if (empty($_POST)) {
		echo ui_book_title_isbn_search();
	}
}

/**
 * User requested export to CSV, so show the UI
 */
function nomadreader_export_books() {
	include("import_books.php");
	// Show the UI
	echo ui_books_export_csv();
}

/**
 * Callback for when user submits a CSV export
 */
function export_books() {
	include("import_books.php");

	// Do the download
	create_book_export_csv();
}

/**
 * User requested update external links, so show the UI
 */
function nomadreader_update_ext_links() {
	include("import_books.php");
	// Show the UI
	echo ui_books_update_ext_links();
}

/**
 * Callback for when user submits to update external links
 */
function update_ext_links() {
	include("import_books.php");
	// Do the update
	$count = update_external_links();
	wp_redirect(admin_url('admin.php?page=update_ext_links'));
	exit;
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

		// Check if we are on a page which should load the remainder of
		// if ( 'edit.php' != $hook ) {
    //     return;
    // }
		//
		// jQuery multiselect option dropdown/search plugin
		$url = plugins_url(PLUGIN_NAME . '/javascripts');
		wp_register_script('amznbk_jq_chosen_js', $url . '/chosen.jquery.min.js');
		wp_enqueue_script('amznbk_jq_chosen_js');

		$url = plugins_url(PLUGIN_NAME . '/css');
		wp_register_style('amznbk_jq_chosen_css', $url .'/chosen.min.css');
		wp_enqueue_style('amznbk_jq_chosen_css');

		// Add any functional JS code
		add_action('admin_footer', 'add_jq_chosen_multiselect');
}

/**
 * Add the JavaScript to enable the jQuery Chosen (multiselect dropdown & search) plugin
 */
function add_jq_chosen_multiselect() {

	echo '<script type="text/javascript">
		jQuery(document).ready(function() {

			// Initialize the Chosen multiselect dropdown
			var select = jQuery(".chosen-select")
			select.each(function(i,e) {
				var elem_id  = "#" + jQuery(e).attr("id");
				var chosen = jQuery(elem_id).chosen(
					{ no_results_text: "<b>Press ENTER</b> to add new entry:" }
				);

				var search_field = chosen.data("chosen").search_field;
				jQuery(search_field).on("keyup", function(evt) {

					// Get the ID of Chosen elem (<Select>) and build an ID to
					// reference the container Chosen uses to replace <select>
					var parent_con = chosen.siblings("#" + chosen.attr("id") + "_chosen");

					// If user hits ENTER and No Results showing then insert new term
					if (evt.which === 13 && parent_con.find("li.no-results").length > 0) {

						// Insert the new option to the multiselect control
						var option = jQuery("<option>").val(this.value).text(this.value);
						chosen.prepend(option);
						chosen.find(option).prop("selected", true);

						// Trigger the update to refresh list of options
						chosen.trigger("chosen:updated");
					}
				});
			});

			// Code for WordPress Table_List to allow bulk ops
			jQuery("th > input[type=\'checkbox\']").click(function() {
				var boxes = jQuery("td input[type=\'checkbox\']");

				var checked = false;
	  		if (jQuery(this).is(":checked")) {
					checked = true;
	  		}
				boxes.prop("checked", checked);
			});
		});
	</script>';
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
