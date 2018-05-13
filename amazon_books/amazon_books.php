<?php
/*
Plugin Name: Amazon Books
Plugin URI: http://www.prasarnet.com/
Description: This plugins Import Books From Amazon
Version: 0.0.1
Author: PNT
*/
global $wpdb;

function import_books() {
	// $url = plugins_url('amazon_books/import_books.php');
	// echo $url;
	include("import_books.php");
}

function export_books() {
	// $url = plugins_url('amazon_books/import_books.php');
	// echo $url;
	include("import_books.php");
	create_book_export_csv();
}

function import_amazon_book() {
	add_menu_page('Amazon Books', 'Import Books', 1, 'amazon_books', 'import_books', plugins_url('amazon_books/images/icon.png'), 7 );
}
add_action('admin_menu', 'import_amazon_book');

// Add an export action
add_action('admin_post_export_books', 'export_books');

// This will add the multiselect to all <select> with class chosen-select
function add_chosen_jq_multiselect() {
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
						var option = jQuery("<option>").val(this.value).text(this.value);
						chosen.prepend(option);
						chosen.find(option).prop("selected", true);

						// Trigger the update
						chosen.trigger("chosen:updated");
					}
				});
			});

			// Code for WordPress Table_List to allow bulk ops
			jQuery("th > input[type=\'checkbox\']").click(function() {
				var boxes = jQuery("td input[type=\'checkbox\']")
				var checked = false;
	  		if (jQuery(this).is(":checked")) {
					checked = true;
	  		}
				boxes.prop("checked", checked);
			});
		});
	</script>';
}
add_action('admin_footer', 'add_chosen_jq_multiselect');


function amazon_book_import_enqueue() {
    // JS
    wp_register_script('prefix_bootstrap',
			'//maxcdn.bootstrapcdn.com/bootstrap/3.3.6/js/bootstrap.min.js');
    wp_enqueue_script('prefix_bootstrap');

    // CSS
    wp_register_style('prefix_bootstrap',
			'//maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css');
    wp_enqueue_style('prefix_bootstrap');

		$url = plugins_url('amazon_books/javascripts');
		wp_register_script('amznbk_chosen_jq', $url . '/chosen.jquery.min.js');
		wp_enqueue_script('amznbk_chosen_jq');

		$url = plugins_url('amazon_books/css');
		wp_register_style('amznbk_chosen_jq', $url .'/chosen.min.css');
		wp_enqueue_style('amznbk_chosen_jq');
}
add_action('admin_enqueue_scripts', 'amazon_book_import_enqueue');
