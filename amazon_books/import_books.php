<?php
	include("settings.php");
	require('book_list_table.php');
	require('lib/AmazonECS.class.php');

	$url = plugins_url('amazon_books');
?>

<link rel="stylesheet" href="<?php echo $url; ?>/css/amazon_books.css">

<?php
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

	// Set parameters for Amazon API
	$amzn = new AmazonECS(AWS_API_KEY, AWS_API_SECRET_KEY, 'com', AWS_ASSOCIATE_TAG);
	$amzn->associateTag(AWS_ASSOCIATE_TAG);

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
			return $books;
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
		echo $e->getMessage();
	}

	// Sort the books by dscending pub date
	usort($books, sort_pub_date);

	return $books;
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
			<input type="submit" name="submit" value="submit">
			</h1>
			<br />
			<h1>Lookup ISBNs
			<input type="file" name="amazon_isbns">
			<input type="submit" name="submit_file" value="Lookup">
			</h1>
		</form>
	</div>

	<br class="clear" />';
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
	return isset($_POST['submit']) && !empty($_POST['submit']);
}

/**
 * Check if the submit product button used
 *
 * When user has selected and updated a product fetched from
 * Amazon API
 */
function is_submit_products() {
	return ((isset($_POST['submit_products']) && !empty($_POST['submit_products'])) ||
				  (isset($_POST['action'][0]) && !empty($_POST['action'])));
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
 * Gets the search terms posted by user
 */
function get_search_terms() {
		$search = '';
		if (isset($_POST['amazon_book_name']) && !empty($_POST['amazon_book_name'])) {
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

	if (isset($_POST['action']) && !empty($_POST['action'])) {

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
			$auth_term = get_product_terms($_POST[$authors_fldname][$isbn],
																		 $tlterm_id);
			$auths = $auth_term;

			$tlterm_id = get_toplevel_term('location');
			$loc = explode(',', $_POST[$location_fldname][$isbn]);
			$temp[] = trim($loc[0]);
			if (count($loc) > 1) { $temp[] = trim($loc[1]); }
			$loc_id = get_product_terms($temp, $tlterm_id);
			$location = $loc_id;

			$tlterm_id = get_toplevel_term('genres');
			$genre_id = get_product_terms($_POST[$genres_fldname][$isbn], $tlterm_id);
			$genres = $genre_id;

			$periods = [];
			$tlterm_id = get_toplevel_term('periods');
			$period_id = get_product_terms($_POST[$periods_fldname][$isbn], $tlterm_id);
			$periods = $period_id;

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

	// Assign the terms to the product
	$link_terms = [];
	$all_terms = array_merge($book['authors'], $book['genres'],
							             $book['periods'], $book['location']);
	foreach($all_terms as $term_props) {
		$link_terms[] = $term_props['term_id'];
	}
	wp_set_object_terms($post_id, $link_terms, 'product_cat', true);
	wp_update_term_count_now($all_terms, 'product_cat');

	// TODO Update the product tags
	$tags = explode(',', $book['tags']);
	wp_set_post_tags($post_id, $tags, true);

	// assigning the product type (ie affiliate link)
	wp_set_object_terms($post_id, 'external', 'product_type');

	// assigning the meta keys to the product
	add_post_meta($post_id, 'isbn_prod', $isbn, true);

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
	update_post_meta( $post_id, '_product_url',
		"https://www.amazon.com/dp/" . $isbn . "/?tag=" . AWS_ASSOCIATE_TAG);
	update_post_meta( $post_id, '_button_text', AMAZON_BUY_BUTTON_TEXT);

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

/////////////////////////////////////////////////
// Render the appropriate UI and process SUBMITs
/////////////////////////////////////////////////

// Process user ACTION (list from CSV, title search or add book)
if (is_submit_products()) {
	// Add all selected book
	$books = get_book_info_from_form();

	if (!empty($books)) {

		$msg = '';
		$temp = "<div class='success-msg'>Product <b>%1s</b> has been added successfully</div>";

		// Add each product avoiding duplicates (isbn)
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
	$search = get_search_terms();
	$books = search_amazon($search);

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
		$books = search_amazon($search, True);
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
else {
	echo ui_book_title_isbn_search();
}

?>