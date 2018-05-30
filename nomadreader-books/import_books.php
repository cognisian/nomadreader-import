<?php
	// include("settings.php");
	require('book_list_table.php');
	require('lib/AmazonECS.class.php');

	$url = plugins_url(PLUGIN_NAME);
?>

<link rel="stylesheet" href="<?php echo $url; ?>/css/amazon_books.css">

<?php

function test_book_data() {

	$genres_id = get_toplevel_term('genres');
	$periods_id = get_toplevel_term('periods');
	$loc_id = get_toplevel_term('location');

	return array(
		array(
			'isbn' 				=> '0070915520',
			'title' 			=> 'Cabbagetown',
			'pub_date' 		=> '20020819',
			'desc' 				=> "Toronto's Cabbagetown in the Depression...North America's largest Anglo-Saxon slum. Ken Tilling leaves school to face the bleak prospects of the dirty thirties-where do you go, what do you do, how do you make a life for yourself when all the world offers in unemployment, poverty and uncertainty?\r\n\r\n\"As a social document, Cabbagetown is as important and revealing as either The Tin Flute or The Grapes of Wrath. Stern realism has also projected upon the pages of a whole gallery of types, lifelike and convincing. He is well fitted to hold the mirror up to human nature.\" Globe and Mail.\r\n\r\nCabbagetown was first published in an abbreviated paperback edition in 1950 and was published in its entirety in 1968.",
			'excerpt' 		=> "Toronto's Cabbagetown in the Depression",
			'authors' 		=> array('Hugh Garner'),
			'images' 			=> array(
				'large' => array(
					'width'  => 0,
					'height' => 0,
					'file'	 => 'something.jpeg'
				)
			),
			'terms' => array(
				'genres' 			=> array(
					'term_id'			=> $genres_id,
					'subterms'		=> get_product_terms(
														array('Fiction & Literature'), $genres_id)
				),
				'periods' 		=> array(
					'term_id'			=> $periods_id,
					'subterms'		=> get_product_terms(
														array('1960s'), $periods_id)
				),
				'location' 		=> array(
					'term_id'			=> $loc_id,
					'subterms'		=> get_product_terms(
														array('Toronto', 'Canada'), $loc_id)
				),
			),
			'tags' 				=> 'Awesome',
		)
	);
}
/**
 *
 */
function sort_pub_date($a, $b) {
	if ($a == $b) {
      return 0;
  }
	// We want descending dates
  return ($a < $b) ? 1 : -1;
}

/**
 * Setup and execute book title or ISBN search agaist Amazon API
 *
 * @param string $search The search term (title or ISBN)
 * @param bool $lookupFlag - Flag indicating if a lookup search (isbn/asin)
 * should be used, else use title text search
 * @return array THe list of books found or empty if none found
 */
function search_amazon($search, $lookupFlag = False) {

	$books = array();

	// Retrieve the AWS access opions
	$options = get_option(NR_OPT_AWS_TOKENS_CONFIG);
	$access_key = isset($options[NR_AWS_ACCESS_TOKEN]) ?
									$options[NR_AWS_ACCESS_TOKEN] : '';
	$secret_key = isset($options[NR_AWS_SECRET_TOKEN]) ?
								decrypt_stuff(base64_decode($options[NR_AWS_SECRET_TOKEN])) :
								'';
	$affilate_tag = isset($options[NR_AWS_AFFILIATE_TAG]) ?
									$options[NR_AWS_AFFILIATE_TAG] : '';

	// Set parameters for Amazon API
	$amzn = new AmazonECS($access_key, $secret_key, 'com', $affilate_tag);
	var_dump($access_key, $secret_key, $affilate_tag);
	try {
		// Select how we find books (search v lookup) based on whether it is a title or isbn
		if (!$lookupFlag) {
			$response = $amzn->responseGroup('Medium,Reviews')->
					category('Books')->search($search);
			$items = $response->Items->Item;
		}
		else {
			$isbn = $search['isbn'];
			$response = $amzn->responseGroup('Medium,Reviews')->
					category('Books')->lookup($isbn);
			$items = $response->Items;
		}

		// Amazon error
		if (isset($response->Items->Request->Errors)) {
			return new WP_Error('AmazonECS Error',
									'An error with AmazonECS ocurred');
		}

		// Lop through each returned item and build details
		$i = 0;
		foreach($items as $item_id) {
			// Skip this item if no ASIN attribute as it may be an
			// info block as part of response
			if (!isset($item_id->ASIN)) {
				continue;
			}

			// Extract all the image details
			$img_set_info = [];

			$large_img = $item_id->LargeImage;
			$img_lg_info = array('width'  => (int)$large_img->Width->_,
													 'height' => (int)$large_img->Height->_,
												 	 'file'   => $large_img->URL);

		 	$med_img = $item_id->MediumImage;
			$img_md_info = array('width'  => (int)$med_img->Width->_,
													 'height' => (int)$med_img->Height->_,
												 	 'file'   => $med_img->URL);

			$small_img = $item_id->SmallImage;
			$img_sm_info = array('width'  => (int)$small_img->Width->_,
													 'height' => (int)$small_img->Height->_,
												 	 'file'   => $small_img->URL);

			// save image details
			$img_set_info = array('large'  => $img_lg_info,
														'medium' => $img_md_info,
											 			'small'  => $img_sm_info);

			// Extract first sentence as excerpt
			$tmp_content = $item_id->EditorialReviews->EditorialReview->Content;
			$index = strpos($tmp_content, '<br');
			$excerpt = substr($tmp_content, 0, $index);
			if (!empty($excerpt) && strlen($excerpt) > 256) {
				$index = strpos($tmp_content, '.');
				$excerpt = substr($tmp_content, 0, $index);
			}
			$excerpt .= '<br/>';

			// Save book details
			$pdate = date_create($item_id->ItemAttributes->PublicationDate);
			$tmp_info = array(
				'pub_date' => date_format($pdate, 'Ymd'),
				'isbn'		 => $item_id->ItemAttributes->ISBN,
				'title' 	 => $item_id->ItemAttributes->Title,
				'authors'	 => $item_id->ItemAttributes->Author,
				'images'	 => $img_set_info,
				'excerpt'  => $excerpt,
				'desc'		 => $item_id->EditorialReviews->EditorialReview->Content,
			);

			// If no ISBN then not a book and ignore
			if (!empty($tmp_info['isbn'])) {
				$books[] = $tmp_info;
			}
		}
	}
	catch(Exception $e)	{
		// var_dump($e);
		// $traces = $e->getTrace();
		// foreach($traces as $trace) {
		// 	var_dump($trace['args']);
		// }
		echo $e->getMessage();
	}

	// Sort the books by dscending pub date
	usort($books, sort_pub_date);

	return $books;
}

/**
 * Generate the UI to allow user to search by book title or ISBN
 */
function ui_books_export_csv() {
	return '
		<h3>Export Books to CSV</h3>
		<div class="wrap amazon_books">
		<form action="'.admin_url('admin.php').'" method="post"
					name="export_books">
			<input type="hidden" name="action" value="export_books" />
			<input type="submit" name="submit_export" value="Export Books" />
		</form>
		</div>
		<br class="clear" />
	';
}

/**
 * Generate the UI to allow user to search by book title or ISBN
 */
function ui_books_update_ext_links() {
	return '
		<h3>Update the WooCommerce External URL and Buy Button text</h3>
		<p>Goto Setings -> NomadReader Amazon Tokens to set the value to use</p>
		<div class="wrap amazon_books">
		<form action="'.admin_url('admin.php').'" method="post"
					name="update_ext_links">
			<input type="hidden" name="action" value="update_ext_links" />
			<input type="submit" name="submit_upd_links" value="Update Links" />
		</form>
		</div>
		<br class="clear" />
	';
}

/**
 * Generate the UI to allow user to search by book title or ISBN
 */
function ui_book_title_isbn_search() {
	return '
		<div class="wrap amazon_books">
		<form action="" method="post" name="amazon_books" enctype="multipart/form-data">
			<h1>Search Books
			<input placeholder="Search Books In Amazon..." type="text"
				class="wp-filter-search" name="amazon_book_name">
			<input type="submit" name="submit_search" value="submit">
			</h1>
			<label for="use_test_data"> Use Test Data (no Amazon API access)</label>
			<input type="checkbox" name="use_amzn_test_data" />
			<br />
			<h1>Lookup ISBNs
			<input type="file" name="amazon_isbns">
			<input type="submit" name="submit_file" value="Lookup">
			</h1>
			<br />
			<label for="use_test_data"> Use Test Data (no Amazon API access)</label>
			<input type="checkbox" name="use_csv_test_data" />
		</form>
		</div>
	';
}

/**
 * Generate the UI to display the list of books found when searching
 * by book title.  It will generate a list of selectable books to add.
 *
 * @param array $books List of books retrieved from Amazon
 */
function ui_book_search_results($books) {

	$results_table = new BookListTable($books);
	$results_table->prepare_items();

	ob_start();

	echo '
	<div class="wrap">
		<h2>Search Results</h2>

		<div id="poststuff">
			<div id="post-body" class="metabox-holder columns-2">
				<div id="post-body-content">
					<div class="meta-box-sortables ui-sortable">
						<form method="post">
	';

	$results_table->display();

	echo '
						</form>
					</div>
				</div>
			</div>
			<br class="clear">
		</div>
	</div>
	';
	ob_flush();
}

/**
 * Check if the submit product button used
 *
 * When user has selected and updated a product fetched from
 * Amazon API
 */
function is_submit_book_search() {
	return isset($_POST['submit_search']) && !empty($_POST['submit_search']);
}

/**
 * Check if the submit product button used
 *
 * When user has selected and updated a product fetched from
 * Amazon API
 */
function is_submit_products() {
	// IF an action is present in POST could be from several different
	// sources, in this case action should contain an an arrat of ISBN
	// numbers which were selected by user in book list table
	// $is_isbn = preg_match('^[0-9]{10}[0-9]{0,2}[xX]?$', $_POST['action']);
	return isset($_POST['action']) && is_array($_POST['action']) &&
					!empty($_POST['action']);
}

/**
 * Check if the submit product button used
 *
 * When user has selected and updated a product fetched from
 * Amazon API
 */
function is_submit_product_list() {
	return isset($_POST['submit_file']) && !empty($_POST['submit_file']) &&
					isset($_FILES['amazon_isbns']) &&
					!empty($_FILES['amazon_isbns']['tmp_name']);
}

/**
 * Check if the export books button used
 *
 * When user wants to export th set of books into an importable CSV
 */
function is_submit_export() {
	return isset($_POST['submit_export']) && !empty($_POST['submit_export']);
}

/**
 * Check if the update external links button used
 *
 * When user wants to export th set of books into an importable CSV
 */
function is_submit_upd_links() {
	return isset($_POST['submit_upd_links']) && !empty($_POST['submit_upd_links']);
}

/**
 * Check if the use test data is checked
 *
 * When user has selected use test data to bypass Amazon API
 */
function is_use_amzn_test_data() {
	return isset($_POST['use_amzn_test_data']) &&
					!empty($_POST['use_amzn_test_data']);
}

/**
 * Check if the use test data is checked
 *
 * When user has selected use test data to bypass Amazon API
 */
function is_use_csv_test_data() {
	return isset($_POST['use_csv_test_data']) &&
					!empty($_POST['use_csv_test_data']);
}

/**
 * Gets the search terms posted by user
 */
function get_search_terms() {
		$search = '';
		if (isset($_POST['amazon_book_name']) &&
				!empty($_POST['amazon_book_name'])) {
			$search = $_POST['amazon_book_name'];
		}
		return $search;
}

/**
 * Given a HTML form submitted CSV file of books, extract list of books and add
 * any terms and tags
 *
 * @return array The list of books found or empty if none found
 */
function get_book_info_from_file() {

	$csvfile = $_FILES['amazon_isbns']['tmp_name'];
	$rows = file($csvfile);

	$book_lookups = array();
	foreach($rows as $row) {
			// Skip first line if it starts with column names
			if (substr(strtolower($row), 0 , 4) == 'isbn') {
				continue;
			}

			$book_lookup = [];

			// Parse out the CSV data
			$details = str_getcsv($row);

			// Build the book details from CSV data
			$book_lookup['isbn'] = $details[0];
			$book_lookup['tags'] = $details[5];

			$temp = array();
			$authors = explode(',', $details[1]);
			foreach($authors as $author) {
				$temp[] = trim($author);
			}
			$book_lookup['authors'] = $temp;

			$temp = array();
			$loc = explode(',', $details[2]);
			$temp['city'] = trim($loc[0]);
			if (count($loc) > 1) { $temp['country'] = trim($loc[1]); }
			$book_lookup['location'] = $temp;

			$temp = array();
			$genres = explode(',', $details[3]);
			foreach($genres as $genre) {
				$temp[] = trim($genre);
			}
			$book_lookup['genres'] = $temp;

			$temp = array();
			$periods = explode(',', $details[4]);
			foreach($periods as $period) {
				$temp[] = trim($period);
			}
			$book_lookup['periods'] = $temp;

			// Push the book
			$book_lookups[] = $book_lookup;
	}

	return $book_lookups;
}

/**
 * Given a HTML form submitted list of book details, extract list of books
 *
 * @return array The list of books found or empty if none found
 */
function get_book_info_from_form() {

	$books = [];

	// Given the weird POST data (it is sending all rows in table)
	// From the action var find the ISBNs being added and use that
	// to append to post field name to get remaining
	foreach($_POST['action'] as $isbn) {

		$pub_date_fldname = 'pub_date';
		$title_fldname = 'title';
		$authors_fldname = 'authors';
		$desc_fldname = 'desc';
		$excerpt_fldname = 'excerpt';
		$imgfile_fldname = 'imgfile';
		$imgheight_fldname = 'imgheight';
		$imgwidth_fldname = 'imgwidth';
		$location_fldname = 'location';
		$genres_fldname = 'genres';
		$periods_fldname = 'periods';
		$tags_fldname = 'tags';

		$img = array(
			'file'   => $_POST[$imgfile_fldname][$isbn][0],
			'height' => $_POST[$imgheight_fldname][$isbn][0],
			'width'  => $_POST[$imgwidth_fldname][$isbn][0],
		);

		$tlterm_id = get_toplevel_term('authors');
		$auths = get_product_terms($_POST[$authors_fldname][$isbn], $tlterm_id);

		$tlterm_id = get_toplevel_term('location');
		$location = get_product_terms($_POST[$location_fldname][$isbn], $tlterm_id);

		$tlterm_id = get_toplevel_term('genres');
		$genres = get_product_terms($_POST[$genres_fldname][$isbn], $tlterm_id);

		$tlterm_id = get_toplevel_term('periods');
		$periods = get_product_terms($_POST[$periods_fldname][$isbn], $tlterm_id);

		$tags = [];
		$temp = explode(',', $_POST[$tags_fldname][$isbn][0]);
		foreach($temp as $tag) {
			$tags[] = trim($tag);
		}

		$tmp_info = array(
			'desc'		  => addslashes($_POST[$desc_fldname][$isbn][0]),
			'pub_date'  => $_POST[$pub_date_fldname][$isbn][0],
			'isbn'		  => $isbn,
			'title' 	  => $_POST[$title_fldname][$isbn][0],
			'authors'	  => $auths,
			'images'	  => $img,
			'excerpt'   => addslashes($_POST[$excerpt_fldname][$isbn][0]),
			'location'  => $location,
			'genres'    => $genres,
			'periods'   => $periods,
			'tags'   		=> $tags,
		);

		$books[] = $tmp_info;
	}

	return $books;
}

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
		echo "WTF";
	}

	return $term_id;
}

/**
 * Given a list of subterm names and associated top level term,
 * check if it exists and return its id.  If the top level term
 * does not exist create it as a subter of the parent.
 *
 * @param array $terms The list of subterms to retrieve/create
 * @param int $parent_term_id The parent term of the list of $terms
 * @return array A list of struct with term id, term_taxonomy_id and term_name
 */
function get_product_terms($terms = array(), $parent_term_id) {

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
 * @param array $book An associative array of book attrs
 * @return int The inserted post ID
 */
function create_product_post($book = array()) {

	//Create product
	$post = array(
		'post_author' => 1,
		'post_content' => $book['desc'],
		'post_excerpt' => $book['excerpt'],
		'post_status' => "publish",
		'post_title' => $book['title'],
		'post_parent' => '',
		'post_type' => "product",
	);
	$post_id = wp_insert_post($post, $wp_error);

	// assigning the meta keys to the product
	add_post_meta($post_id, 'isbn_prod', $book['isbn'], true);
	// assigning the product type (ie affiliate link)
	wp_set_object_terms($post_id, 'external', 'product_type');

	// Assign the terms to the product
	$link_terms = [];
	$all_terms = array_merge($book['authors'], $book['genres'],
							             $book['periods'], $book['location']);
	foreach($all_terms as $term_props) {
		$link_terms[] = $term_props['term_id'];
	}
	wp_set_object_terms($post_id, $link_terms, 'product_cat', true);
	wp_update_term_count_now($all_terms, 'product_cat');

	// Update the product tags (ensure the woocommerce product_tag exists)
	if (!taxonomy_exists('product_tag')) {
		register_taxonomy('product_tag', 'product');
	}
	$res = wp_set_object_terms($post_id, $book['tags'], 'product_tag', true);

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
	update_post_meta( $post_id, '_product_url',
		"https://www.amazon.com/dp/" . $book['isbn'] . "/?tag=" . $value);

  $value = isset($options[NR_AMZN_BUY_BTN_TEXT]) ?
    				esc_attr($options[NR_AMZN_BUY_BTN_TEXT]) : '';
	update_post_meta( $post_id, '_button_text', $value);

	return $post_id;
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

	$imgurl = $img['file'];
	$attachment = array(
		// 'post_author' => 1,
		// 'post_content' => '',
		// 'post_excerpt' => '',
		'post_status' => "inherit",
		'post_title' => preg_replace('/\.[^.]+$/', '', basename($img['file'])),
		// 'post_parent' => $parent_post,
		// 'post_type' => "attachment",
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
 * Create the book export CSV file
 *
 * @return str The name of the CSV export file
 */
function create_book_export_csv() {

	$csv = [];
	$csv[] = 'ISBN, Authors, Location, Genres, Periods, Tags';


	// Get the list of published product posts
	$args = array(
		'orderby'          => 'date',
		'order'            => 'DESC',
		'meta_key'         => '',
		'meta_value'       => '',
		'post_type'        => 'product',
		'post_status'      => 'publish',
	);
	$books = get_posts($args);

	foreach($books as $book) {
		$tmp = [];
		// For each post get post meta data for ISBN
		$isbn = get_post_meta($book->ID, 'isbn_prod', true);
		$tmp['isbn'] = $isbn;

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
				  if (isset($tmp['authors'])) {
						$tmp['authors'] .= ', ' . $value->name;
					}
					else {
						$tmp['authors'] = $value->name;
					}
					break;

				case $loc_term_id->term_id:
					if (isset($tmp['location'])) {
						$tmp['location'] .= ', ' . $value->name;
					}
					else {
						$tmp['location'] = $value->name;
					}
					break;

				case $genres_term_id->term_id:
					if (isset($tmp['genres'])) {
						$tmp['genres'] .= ', ' . $value->name;
					}
					else {
						$tmp['genres'] = $value->name;
					}
					break;

				case $periods_term_id->term_id:
					if (isset($tmp['periods'])) {
						$tmp['periods'] .= ', ' . $value->name;
					}
					else {
						$tmp['periods'] = $value->name;
					}
					break;
			}
		}

		// For each post get all the associated tags product_tag
		$tags = wp_get_post_terms($book->ID, 'product_tag',
															array("fields" => "all"));
		foreach($tags as $tag) {
			if (isset($tmp['tags'])) {
				$tmp['tags'] .= ', ' . $tag->name;
			}
			else {
				$tmp['tags'] = $tag->name;
			}
		}

		// Build CSV record of ISBN, Authors, Location, Genres, Periods, Tags
		$csv_rec = '%1$s, "%2$s", "%3$s", "%4$s", "%5$s", "%6$s"';
		$csv[] = sprintf($csv_rec, $tmp['isbn'], $tmp['authors'],$tmp['location'],
		 								 html_entity_decode($tmp['genres']), $tmp['periods'],
										 $tmp['tags']);
	}

	// Build complete CSV string
	$result = '';
	foreach($csv as $row) {
		$result .= $row . "\n";
	}

	if (!empty($result)) {
		header('Content-Description: Download NomadReader books');
		header("Content-type: text/csv");
		header("Content-Disposition: attachment; filename=nomad_books.csv");
		header("Pragma: no-cache");
		header("Expires: 0");
		ob_end_clean();
		ob_start();
		echo $result;
		ob_end_flush();
	}
}

/**
 * Update the external links for Amazon Affiliate links using the
 * affiliate tag
 */
function update_external_links() {

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
		'meta_key'         => '',
		'meta_value'       => '',
		'post_type'        => 'product',
		'post_status'      => 'publish',
	);
	$books = get_posts($args);

	$count = 0;
	// For each post get post meta data for ISBN and update the
	// external URL for the book
	foreach($books as $book) {
		$isbn = get_post_meta($book->ID, 'isbn_prod', true);

		update_post_meta($book->ID, '_product_url',
			"https://www.amazon.com/dp/" . $isbn . "/?tag=" . $aff_value);
		update_post_meta($book->ID, '_button_text', $buy_value);

		$count += 1;
	}

	return $count;
}

/////////////////////////////////////////////////
// Render the appropriate UI and process SUBMITs
/////////////////////////////////////////////////
// var_dump($_POST);
// Process user ACTION (list from CSV, title search or add book)
if (is_submit_products()) {

	// Add all selected book
	$books = get_book_info_from_form();
	if (!empty($books)) {

		$msg = '';
		$temp = "<div class='success-msg'>Product <b>%1s</b> has been added successfully</div>";

		// Add each product avoiding duplicates (isbn)?
		foreach ($books as $book) {
			$post_id = create_product_post($book);
			$attach_id = create_attachment_post($book['images'], $post_id);
			$msg .= sprintf($temp, $book['title']);
		}

		// Print summary of what was added
		echo $msg;
		echo ui_book_title_isbn_search();
	}
	else {
		echo "<div class='err-msg'>Please select any of the product</div>";
	}
}
else if (is_submit_book_search()) {

	// Search by a book title
	if (!is_use_amzn_test_data()) {
		$search = get_search_terms();
		$books = search_amazon($search);
	}
	else {
		$books = test_book_data();
	}

	echo ui_book_search_results($books);
}
elseif (is_submit_product_list()) {

	// Search by book isbn from list
	$searches = get_book_info_from_file();

	// Given the fixed set of top level categorization terms get/create
	$terms = array(
		'genres'		=> array('term_id' => get_toplevel_term('genres'),
												 'subterms' => array()),
		'periods'		=> array('term_id' => get_toplevel_term('periods'),
												 'subterms' => array()),
		'location'	=> array('term_id' => get_toplevel_term('location'),
												 'subterms' => array()),
	);
	// authors is a separate field but internally to WP it is a term
	// So create it if not exists
	$author_tlterm_id = get_toplevel_term('authors');

	// Fnd the books
	$display_books = array();
	foreach ($searches as $search) {

		// as search returns a list of books and since we gave it an isbn
		// it should only return 1 book
		// For each top level term, created if term does not exist,
		// gather its child terms, adding the child terms if not exists
		if (!is_use_csv_test_data()) {
			$books = search_amazon($search, True);
		}
		else {
			$books = test_book_data();
		}
		// $books = test_book_data();
		$book = $books[0];

		// Add authors
		$book['authors'] = $search['authors'];
		$subterms = get_product_terms($search['authors'], $author_tlterm_id);

		// Add the tags
		$book['tags'] = $search['tags'];

		// Add book categorization
		$book_terms = $terms;
		foreach($book_terms as $term_name => $term_attr) {
			// Get the subterms based on the terms imported from book list
			$subterms = get_product_terms($search[$term_name], $term_attr['term_id']);
			$book_terms[$term_name]['subterms'] = $subterms;
			$book['terms'] = $book_terms;
		}

		$display_books[] = $book;
	}

	echo ui_book_search_results($display_books);
}

?>
