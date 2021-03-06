<?php
/**
 * Module Name: NomadReader Books
 * Plugin URI: https://www.nomadreader.com/
 * Description: Bok class to wrap saving/loading Book data to WordPress
 * Version: 1.0.0
 * Author: Sean Chalmers seandchalmers@yahoo.ca
 */

defined('WPINC') || die();

require_once('utilities.php');

// if ( 'no' === get_option( 'woocommerce_enable_review_rating' ) ) {
//   return;
// }

/**
 * Class to encapsulate the properties of book
 */
class Book {

  /* These class member names MUST match the lower case CSV column names */
  public $isbn = '';
  public $title = '';
  public $authors = array();
  public $summary = '';
  public $excerpt = '';
  public $rating = 0.0;
  public $locations = array();
  public $genres = array();
  public $periods = array();
  public $tags = array();
  public $cover = '';

  /**
   * Given CSV file of book(s) details create the instance(s) of the Book
   *
   * @param string $csv_file The path to the uploaded CSV file.
   * @return array           An array of Books from CSV list
   */
  static public function parse_csv($csv_file) {

    $books = array();

    // Open the CSV and get the file data as rows
    if (($handle = fopen($csv_file, "r")) !== FALSE) {
      while (($data = fgetcsv($handle, 4098, ",")) !== FALSE) {
        // Skip line if column headers
        if (substr(strtolower($data[0]), 0, 4) != 'isbn') {
          // Create and push book
          $books[] = new Book($data[0], $data[1], $data[2], $data[3], $data[4],
                              $data[5], $data[6], $data[7], $data[8], $data[9]);
  			}
      }
      fclose($handle);
    }

    return $books;
  }

  /**
   * Given JSON file of book(s) details create the instance(s) of the Book
   *
   * @param string $json_file The path to the uploaded CSV file.
   * @return array            An array of Books from CSV list
   */
  static public function parse_json($json_file) {

    $books = array();

    // Parse JSON data into indexed array
    $json = json_decode(file_get_contents($json_file));
    if (!is_array($json)) {
      $json = array($json);
    }
    foreach($json as $book) {

      // Convert the old style detailed JSON which contains separate keys
      // for city and country
      $locations = array();
      if (property_exists($book, 'locations')) {
        $locations = $book->locations;
      }
      elseif (property_exists($book, 'city') && property_exists($book, 'country')) {
        $locations[] = $book->city . ', ' . $book->country;
      }

      $rating = 0.0;
      if (property_exists($book, 'rating')) {
        $rating = $book->rating;
      }
      elseif (property_exists($book, 'ratings') &&
              property_exists($book->ratings, 'rating')) {
          $rating = $book->ratings->rating;
      }

      $tags = '';
      if (property_exists($book, 'tags')) {
        $tags = $book->tags;
      }

      $books[] = new Book($book->isbn, $book->title, $book->authors,
                          $book->summary, $rating, $locations,
                          $book->genres, $book->periods, $tags,$book->image);
    }

    return $books;
  }

  /**
   * Instantiate a Book given its ISBN from WprdPress datasource
   *
   * @param string $isbn  ISBN of book to retrieve from WordPress
   * @return Book|bool    An instantiated Book or False if error
   */
  static public function load_book($isbn) {

  	$args = array(
      'meta_key' 				=> META_KEY_ISBN,
  		'meta_value' 			=> $isbn,
      'post_type' 			=> 'product',
      'post_status' 		=> 'publish',
      'posts_per_page' 	=> 1,
  	);
  	$book_post = get_posts($args);
    if (!empty($book_post)) {

      $book = $book_post[0];
      // Load the product category terms and split into proper groups
      $authors = get_book_terms_names_by_category($book->ID,
                                                  get_toplevel_id(CATG_AUTHORS));
      $genres = get_book_terms_names_by_category($book->ID,
                                                  get_toplevel_id(CATG_GENRES));
      $periods = get_book_terms_names_by_category($book->ID,
                                                  get_toplevel_id(CATG_PERIODS));
      $locations = get_book_terms_names_by_category($book->ID,
                                                  get_toplevel_id(CATG_LOCATIONS));

      // Load the product tag terms
      $post_tags = wp_get_post_terms($book->ID, WC_TAGS_TAXN, array('fields' => 'names'));

      $rating = 0.0;
      $rating = (float)get_post_meta($book->ID, '_wc_average_rating', true);
      if (empty($rating)) {
        $rating = 0.0;
      }

      $image = '';
      $thumb_id = get_post_meta($book->ID, '_thumbnail_id', True);
  		$attachment = get_post($thumb_id);
  		if ($attachment) {
  			$image = $attachment->guid;
  		}

      return new Book($isbn, $book->post_title, $authors, $book->post_content,
                      $rating, $locations, $genres, $periods, $post_tags, $image,
                      $book->post_excerpt);
    }
    else {
      return False;
    }
  }

  /**
   * Setup and execute book title or ISBN search agaist Amazon API
   *
   * @param obj $provider     The search provider
   * @param string $search    The search term (title or ISBN/ASIN/etc)
   * @param bool $lookupFlag  Flag indicating if a lookup search (isbn/asin)
   * should be used, else use title text search. A lookup returns 0 or 1 item
   * @return array            The list of books found or empty if none found
   */
  static public function search($provider, $search, $lookupFlag = False) {

  }


  /**
   * Constructor
   *
   * @param string $isbn            ISBN-10/13/ASIN number
   * @param string $title           Book title
   * @param array|string $authors   List of authors as an array of strings or
   *                                a comma seperated string
   *                                ie 'Abe Writer, Bob Author'
   * @param string $summary         The long form summary
   * @param long $rating            Rating on scale of 5.0
   * @param array|string $locations List of comma seperated locations of locations
   *                                which are separated by semicolons
   *                                ie: 'Paris, France; London, England'
   * @param array|string $genres    List of genre names
   * @param array|string $periods   List of period names
   * @param array|string $tags      List of comma seperated words
   * @param string $cover           An URL for the image of the book cover
   * @param string $excerpt         Short desc of book.  Optional if empty then
   *                                take first sentence of summary. Optional
   */
  public function __construct($isbn, $title, $authors, $summary, $rating,
                              $locations, $genres, $periods, $tags, $cover,
                              $excerpt = '') {
    if (strlen($isbn) < 10) {
      $isbn = str_pad($isbn, 10, '0', STR_PAD_LEFT);
    }
    $this->isbn = $isbn;
    $this->title = $title;
    $this->authors = $this->extract_from_delim_string($authors);
    $this->summary = $summary;
    if (!empty($excerpt)) {
      $this->excerpt = $excerpt;
    }
    else {
      $this->excerpt = $this->extract_string($summary);
    }
    $this->rating = $rating;
    if (is_string($locations)) {
      $locations = str_replace(';', ',', $locations);
    }
    $this->locations = $this->extract_from_delim_string($locations);
    $this->genres = $this->extract_from_delim_string($genres);
    $this->periods = $this->extract_from_delim_string($periods);
    $this->tags = $this->extract_from_delim_string($tags);
    $this->cover = $cover;
  }

  /**
   * Inserts the Book details as a new post
   *
   * No checks will be done to determine if the book previously exists as a
   * product post.
   *
   * @return int   Return post ID of the inserted Book, otherwise 0
   */
  public function insert() {

    $post_id = $this->create_product_post();
    if ($post_id !== False) {
      $this->create_product_terms($post_id);
      $this->create_attachment_post($post_id);
      $this->create_rating_comment($post_id);
      // TODO WooCommerce check
      $this->create_product_metadata($post_id);
    }

    return $post_id;
  }

  /**
   * Updates an existing product post in WordPress with this Book's details.
   *
   * The properties which can be updated: title, summary, excerpt, authors,
   * gernres, periods, locations, tags, cover
   *
   * @return int   Return post ID of updated Book, otherwise 0
   */
  public function update() {

    $result = 0;

    // Find the post associated with ISBN number to update
    $args = array(
      'meta_key' 				=> META_KEY_ISBN,
  		'meta_value' 			=> $this->isbn,
      'post_type' 			=> 'product',
      'post_status' 		=> 'publish',
      'posts_per_page' 	=> -1,
  	);
  	$book_post = get_posts($args);
    if (count($book_post) == 1) {

      $book = $book_post[0];

      // Update the post details and link terms and tags, replacing
      // previous terms and tags
      $result = $this->update_product_post($book->ID);

      // Now update the terms, attachment and ratings
      if ($result > 0) {

        $this->add_terms_to_post($book->ID, False);

        $this->add_tags_to_post($book->ID, False);

        $args = array(
          'post_type' 			=> 'attachment',
          'post_status'     => "inherit",
          'post_parent'     => $book->ID,
          'posts_per_page' 	=> -1,
      	);
      	$attach_post = get_posts($args);
        if (count($attach_post) == 1) {
          $result = $this->update_attachment_post($attach_post[0]->ID, $book->ID);
        }

        if ($result > 0) {
          $result = $this->update_rating_comment($book->ID);
        }
      }
    }

    return $result;
  }

  /**
   * Determines if this book already exists in WordPress
   *
   * @return bool   Returns True iff 1 published product post with product meta
   * isbn_prod exists and equals this book's isbn
   */
  public function exists() {

    $result = False;

    // Find the post associated with ISBN number to update
    $args = array(
      'meta_key' 				=> META_KEY_ISBN,
  		'meta_value' 			=> $this->isbn,
      'post_type' 			=> 'product',
      'post_status' 		=> 'publish',
      'posts_per_page' 	=> -1,
  	);
  	$book_post = get_posts($args);
    if (count($book_post) == 1) {
      $result = True;
    }

    return $result;
  }

  /**
   * Representation of a Book object.
   *
   * Books are differentiated by ISBN and Title
   *
   * @return string   The string representation of a Book
   */
  public function __toString() {
    return $this->isbn . ' ' . $this->title;
  }

  /**
   * Create and insert the product post from the given info
   *
   * @return int   The inserted post ID, or 0 if error
   */
  private function create_product_post() {

  	// Create product
  	$post = array(
  		'post_author' => 1,
  		'post_content' => $this->summary,
  		'post_excerpt' => $this->excerpt,
  		'post_status' => "publish",
  		'post_title' => $this->title,
  		'post_type' => "product",
  	);
  	$post_id = wp_insert_post($post);
    if ($post_id != False) {
      // assigning the meta key ISBN to the product
    	add_post_meta($post_id, 'isbn_prod', $this->isbn, true);
    }

  	return $post_id;
  }

  /**
   * Update the Book product post
   *
   * @return int   The inserted post ID, or 0 if error
   */
  private function update_product_post($post_id) {

  	// Update product
  	$post = array(
      'ID'           => $post_id,
  		'post_content' => $this->summary,
  		'post_excerpt' => $this->excerpt,
  		'post_title'   => $this->title,
      'tags_input'   => $this->tags,
  	);
  	$upd_post_id = wp_update_post($post);

  	return $upd_post_id;
  }

  /**
   * Associate Terms and Tags with the post
   *
   * @param int $post_id  Associate the terms and tags to product post
   */
  private function create_product_terms($post_id) {

    // Update the product tags (ensure the woocommerce product_tag exists)
  	if (!taxonomy_exists(WC_TAGS_TAXN)) {
  		register_taxonomy(WC_TAGS_TAXN, 'product');
  	}

    // Assigning the product type (ie affiliate link)
    if (!taxonomy_exists('product_type')) {
  		register_taxonomy('product_type', 'product');
  	}
  	wp_set_object_terms($post_id, 'external', 'product_type');


    $this->add_terms_to_post($post_id, True);

    $this->add_tags_to_post($post_id, True);

  }

  /**
   * Set the product post terms, merge or replace with existing terms, for a
   * category (Authors, Genres etc)
   *
   * @param int $post_id          The post to merge/replace terms
   * @param bool $append          Flag indicating merge (True) or
   *                              replace (False, default)
   */
  private function add_terms_to_post($post_id, $append=False) {

    // Create the both the toplevel terms and their respective child
    // terms, if they do not exist
    $author_tl_term = create_terms(CATG_AUTHORS, WC_CATEGORY_TAXN);
    $author_terms = create_terms($this->authors, WC_CATEGORY_TAXN,
                                  CATG_AUTHORS);

    $genres_tl_term = create_terms(CATG_GENRES, WC_CATEGORY_TAXN);
    $genres_terms = create_terms($this->genres, WC_CATEGORY_TAXN,
                                  CATG_GENRES);

    $periods_tl_term = create_terms(CATG_PERIODS, WC_CATEGORY_TAXN);
    $periods_terms = create_terms($this->periods, WC_CATEGORY_TAXN,
                                  CATG_PERIODS);

    $locations_tl_term = create_terms(CATG_LOCATIONS, WC_CATEGORY_TAXN);
    $locations_terms = create_terms($this->locations, WC_CATEGORY_TAXN,
                                    CATG_LOCATIONS);

    $terms = array_merge($author_terms, $genres_terms, $periods_terms,
                          $locations_terms);
    if (!empty($terms)) {
      // Extract just the term_ids, regardless of parent
      $term_ids = filter_terms_by_category($terms, 0, 'term_id');
      // Do not set terms per toplevel, terms should added/merged all at once
      $res = wp_set_object_terms($post_id, $term_ids, WC_CATEGORY_TAXN, $append);
    }

  }

  /**
   * Set the product post tags, merge or replace with existing tags
   *
   * @param int $post_id          The post to merge/replace terms
   * @param bool $append          Flag indicating merge (True) or
   *                              replace (False, default)
   */
  private function add_tags_to_post($post_id, $append=False) {

    // Associate the terms to the post
    $new_tags = create_terms($this->tags, WC_TAGS_TAXN);
    if (!empty($new_tags)) {
      $tag_ids = filter_terms_by_category($new_tags, 0, 'term_id');
      $res = wp_set_object_terms($post_id, $tag_ids, WC_TAGS_TAXN, $append);
    }

  }

  /**
   * Update the Metadata for the product in wooCommerce specific data
   *
   * @param int $post_id Associate a book cover image with product
   * @param $isbn Assoicate the ISBN to the post
   * @param $rating Assoicate the book rating to the post
   * @param string $region Which Amazon region should URL point to
   */
  private function create_product_metadata($post_id, $region = 'com') {

  	// Update the WooCommerce fields (_product_url being the field controlling
  	// the URL assigned to the Buy button)
  	update_post_meta($post_id, '_visibility', 'visible');
  	update_post_meta($post_id, '_stock_status', 'instock');
  	update_post_meta($post_id, 'total_sales', '0');
  	update_post_meta($post_id, '_downloadable', 'no');
  	update_post_meta($post_id, '_virtual', 'no');
  	update_post_meta($post_id, '_regular_price', "10");
  	update_post_meta($post_id, '_sale_price', "");
  	update_post_meta($post_id, '_purchase_note', "");
  	update_post_meta($post_id, '_featured', "no");
  	update_post_meta($post_id, '_sku', "");
  	update_post_meta($post_id, '_product_attributes', array());
  	update_post_meta($post_id, '_sale_price_dates_from', "");
  	update_post_meta($post_id, '_sale_price_dates_to', "");
  	update_post_meta($post_id, '_price', "");
  	update_post_meta($post_id, '_sold_individually', "");
  	update_post_meta($post_id, '_manage_stock', "no");
  	update_post_meta($post_id, '_backorders', "no");
  	update_post_meta($post_id, '_stock', "");

  	// Load the values from wordpress options
  	$options = get_option(NR_OPT_AWS_TOKENS_CONFIG);

  	$value = isset($options[NR_AWS_AFFILIATE_TAG]) ?
  						esc_attr($options[NR_AWS_AFFILIATE_TAG]) : '';
  	update_post_meta($post_id, '_product_url',
  		"https://www.amazon." . $region . "/dp/" . $this->isbn . "/?tag=" . $value);

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
  private function create_attachment_post($parent_post = 0) {

  	$attachment_id = 0;

    $name = basename($this->cover);

  	$attachment = array(
  		'post_status' => "inherit",
  		'post_title' => preg_replace('/\.[^.]+$/', '', $name),
  		'post_parent' => $parent_post,
  		'post_mime_type' => 'image/jpeg',
  		'guid' => $this->cover,
  	);
  	$attachment_id = wp_insert_attachment($attachment);
    // TODO need to this with Thumbnails as well?

    $metadata = wp_generate_attachment_metadata($attachment_id, $this->cover);
  	$metadata['file'] = $name;
  	$metadata['sizes'] = array(
  		'full' => array(
  			'file'   => $name,
  			'height' => 0,
  			'width'  => 0
  		)
  	);
    wp_update_attachment_metadata($attachment_id, $metadata);

  	// Add the image as product image
  	add_post_meta($parent_post, '_thumbnail_id', $attachment_id, true);

  	return $attachment_id;
  }

  /**
   * Update the file attachment
   * This should also generate the meta data for the attachment
   *
   * @param int $attach_id    The attachment post ID to update
   * @param int $parent_post  Associate a book cover image with product post
   * @return int              Return 0 if update post failed or the post ID of
   * updated post
   */
  private function update_attachment_post($attach_id, $parent_post_id = 0) {

    // Delete the old attachment post and add new one
  	$result = wp_delete_attachment($attach_id, true);
    if ($result !== False) {
      $result = $this->create_attachment_post($parent_post_id);
    }

  	return $result;
  }

  /**
   * Create the comment and meta data associated with Rating
   *
   * @param int $parent_post  Associate a rating cooment with product
   */
  private function create_rating_comment($parent_post = 0) {

    $comment_id = 0;

    // Dont add new comment if one already exists
    $comment = get_comments(array('post_id' => $parent_post));
    if (empty($comment)) {
      $args = array(
      	'comment_post_ID'      => $parent_post,
      	'comment_author'       => 'nomadreader rating',
      	'comment_author_email' => '',
      	'comment_author_url'   => '',
      	'comment_content'      => 'Rating Comment',
      	'comment_type'         => 'comment',
      	'comment_parent'       => 0,
        'comment_approved'     => 1,
      	'user_id'              => 1,
      );
      $comment_id = wp_new_comment($args, true);
      if (!is_wp_error($comment_id) && $comment_id > 0) {
        add_comment_meta($comment_id, 'rating', $this->rating, true);
        update_post_meta($parent_post, '_wc_average_rating', $this->rating);
      }
    }

  	return $comment_id;
  }

  /**
   * Update the comment and meta data associated with Rating
   *
   * @param int $parent_post  Associate a rating cooment with product
   */
  private function update_rating_comment($parent_post = 0) {

    $result = False;

    // Find comment for post
    // Use the initial admin user to separate NomadReader rating from user rating
    $comment = get_comments(array(
      'post_id' => $parent_post,
      'user_id' => 1
    ));
    
    if (!empty($comment)) {
      update_comment_meta($comment[0]->comment_ID, 'rating', $this->rating, true);
      update_post_meta($parent_post, '_wc_average_rating', $this->rating);
      $result = True;
    }

    return $result;
  }

  /**
   * Extract the values if given a comma separated string
   *
   * @param string $values  The comma seperated list of values
   * @param string $delim   The delimiter which is seperating list of values
   * @return array          The list of values
   */
  private function extract_from_delim_string($values, $delim=',') {

    $result = array();

    if (is_string($values)) {
      $value_parts = explode($delim, $values);
      if (count($value_parts) > 0) {
        foreach($value_parts as $value) {
            $result[] = trim($value);
        }
      }
    }
    else {
      $result = $values;
    }

    return $result;
  }

  /**
   * Extract max number of characters from given string
   *
   * @param string $string The string to grab exerpt from
   * @return string        The extracted string
   */
  private function extract_string($string, $max_char = 120) {

    $result = '';

    // Locate an appropriate cut point
    $break_points = array("<br", "\n", '.');
    foreach ($break_points as $break) {
      $loc = stristr($string, $break, TRUE);
      if ($loc !== FALSE ) {
        $result = $loc;
        break;
      }
    }

    // If no cut point was found or the cut point is longer than 120 chars
    if (empty($result) || strlen($result) > $max_char) {
      $result = substr($string, 0, $max_char);
    }

    return $result;
  }

}

?>
