<?php
/**
 * Encapsulate a book given properties from a CSV or JSON or alternatively
 * loaded from WordPRess via ISBN
 */

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
  public $tags = '';
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
        $rating = $book->tags;
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
  public function load_book($isbn) {

    require_once('utilities.php');

    $row = $result[0];
  	$args = array(
      'meta_key' 				=> 'isbn_prod',
  		'meta_value' 			=> $isbn,
      'post_type' 			=> 'product',
      'post_status' 		=> 'publish',
      'posts_per_page' 	=> 1,
  	);
  	$book_post = get_posts($args);
    if (!empty($book_post)) {

      $auth_id = get_toplevel_term('authors');
      $authors = get_post_terms($book_post->ID, $auth_id, array('fields' => 'name'));
      if (is_wp_error($authors)) {
        $authors = '';
      }

      $genres_id = get_toplevel_term('genres');
      if (is_wp_error($genres)) {
        $genres = '';
      }
      $genres = get_post_terms($book_post->ID, $genres_id, array('fields' => 'name'));

      $period_id = get_toplevel_term('periods');
      $periods = get_post_terms($book_post->ID, $period_id, array('fields' => 'name'));
      if (is_wp_error($periods)) {
        $periods = '';
      }

      $loc_id = get_toplevel_term('location');
      $locations = get_post_terms($book_post->ID, $loc_id, array('fields' => 'name'));
      if (is_wp_error($locations)) {
        $locations = '';
      }

      $tag_id = get_toplevel_term('product_tag');
      $tags = get_post_terms($book_post->ID, $tag_id, array('fields' => 'name'));
      if (!is_wp_error($tags)) {
        $tags = ', '.join($tags);
      }
      else {
        $tags = '';
      }

      $rating = get_post_terms($book_post->ID, '_wc_average_rating', array('fields' => 'name'));
      if (is_wp_error($rating)) {
        $rating = 0.0;
      }

      $image = '';
      $thumb_id = get_post_meta($book_post->ID, '_thumbnail_id', True);
  		$attachment = get_post($thumb_id);
  		if ($attachment) {
  			$image = $attachment->guid;
  		}

      return new Book($isbn, $book_post->title, $authors, $book_post->summary, $rating, $locations,
                      $genres, $periods, $tags, $image, $book_post->excerpt);
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
   * @param string $tags            List of comma seperated words
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
    $this->tags = $tags;
    $this->cover = $cover;
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
