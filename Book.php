<?php
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
   * Setup and execute book title or ISBN search agaist Amazon API
   *
   * @param obj $provider     The search provider
   * @param string $search    The search term (title or ISBN/ASIN/etc)
   * @param bool $lookupFlag  Flag indicating if a lookup search (isbn/asin)
   * should be used, else use title text search. A lookup returns 0 or 1 item
   * @return array            The list of books found or empty if none found
   */
  static public function search($provider, $search, $lookupFlag = False) {
    //require('lib/AmazonECS.class.php');
    // /**
    //  *
    //  */
    // function sort_pub_date($a, $b) {
    // 	if ($a == $b) {
    //       return 0;
    //   }
    // 	// We want descending dates
    //   return ($a < $b) ? 1 : -1;
    // }
    //
    // $books = array();
    //
  	// // Retrieve the AWS access opions
  	// $options = get_option(NR_OPT_AWS_TOKENS_CONFIG);
  	// $access_key = isset($options[NR_AWS_ACCESS_TOKEN]) ?
  	// 								$options[NR_AWS_ACCESS_TOKEN] : '';
  	// $secret_key = isset($options[NR_AWS_SECRET_TOKEN]) ?
  	// 							decrypt_stuff(base64_decode($options[NR_AWS_SECRET_TOKEN])) :
  	// 							'';
  	// $affilate_tag = isset($options[NR_AWS_AFFILIATE_TAG]) ?
  	// 								$options[NR_AWS_AFFILIATE_TAG] : '';
    //
  	// // Set parameters for Amazon API
  	// $amzn = new AmazonECS($access_key, $secret_key, 'com', $affilate_tag);
  	// try {
  	// 	// Select how we find books (search v lookup) based on whether it is a title or isbn
  	// 	if (!$lookupFlag) {
  	// 		$response = $amzn->responseGroup('Medium,Reviews')->
  	// 				category('Books')->search($search);
  	// 		$items = $response->Items->Item;
  	// 	}
  	// 	else {
  	// 		$isbn = $search['isbn'];
  	// 		$response = $amzn->responseGroup('Medium,Reviews')->
  	// 				category('Books')->lookup($isbn);
  	// 		$items = $response->Items;
  	// 	}
    //
  	// 	// Amazon error
  	// 	if (isset($response->Items->Request->Errors)) {
  	// 		return new WP_Error('AmazonECS Error',
  	// 								'An error with AmazonECS ocurred');
  	// 	}
    //
  	// 	// Lop through each returned item and build details
  	// 	$i = 0;
  	// 	foreach($items as $item_id) {
  	// 		// Skip this item if no ASIN attribute as it may be an
  	// 		// info block as part of response
  	// 		if (!isset($item_id->ASIN)) {
  	// 			continue;
  	// 		}
    //
  	// 		// Extract all the image details
  	// 		$img_set_info = [];
    //
  	// 		$large_img = $item_id->LargeImage;
  	// 		$img_lg_info = array('width'  => (int)$large_img->Width->_,
  	// 												 'height' => (int)$large_img->Height->_,
  	// 											 	 'file'   => $large_img->URL);
    //
  	// 	 	$med_img = $item_id->MediumImage;
  	// 		$img_md_info = array('width'  => (int)$med_img->Width->_,
  	// 												 'height' => (int)$med_img->Height->_,
  	// 											 	 'file'   => $med_img->URL);
    //
  	// 		$small_img = $item_id->SmallImage;
  	// 		$img_sm_info = array('width'  => (int)$small_img->Width->_,
  	// 												 'height' => (int)$small_img->Height->_,
  	// 											 	 'file'   => $small_img->URL);
    //
  	// 		// save image details
  	// 		$img_set_info = array('large'  => $img_lg_info,
  	// 													'medium' => $img_md_info,
  	// 										 			'small'  => $img_sm_info);
    //
  	// 		// Extract first sentence as excerpt
  	// 		$tmp_content = $item_id->EditorialReviews->EditorialReview->Content;
  	// 		$index = strpos($tmp_content, '<br');
  	// 		$excerpt = substr($tmp_content, 0, $index);
  	// 		if (!empty($excerpt) && strlen($excerpt) > 256) {
  	// 			$index = strpos($tmp_content, '.');
  	// 			$excerpt = substr($tmp_content, 0, $index);
  	// 		}
  	// 		$excerpt .= '<br/>';
    //
  	// 		// Save book details
  	// 		$pdate = date_create($item_id->ItemAttributes->PublicationDate);
  	// 		$tmp_info = array(
  	// 			'pub_date' => date_format($pdate, 'Ymd'),
  	// 			'isbn'		 => $item_id->ItemAttributes->ISBN,
  	// 			'title' 	 => $item_id->ItemAttributes->Title,
  	// 			'authors'	 => $item_id->ItemAttributes->Author,
  	// 			'images'	 => $img_set_info,
  	// 			'excerpt'  => $excerpt,
  	// 			'desc'		 => $item_id->EditorialReviews->EditorialReview->Content,
  	// 		);
    //
  	// 		// If no ISBN then not a book and ignore
  	// 		if (!empty($tmp_info['isbn'])) {
  	// 			$books[] = $tmp_info;
  	// 		}
  	// 	}
  	// }
  	// catch(Exception $e)	{
  	// 	// var_dump($e);
  	// 	// $traces = $e->getTrace();
  	// 	// foreach($traces as $trace) {
  	// 	// 	var_dump($trace['args']);
  	// 	// }
  	// 	echo $e->getMessage();
  	// }
    //
  	// // Sort the books by dscending pub date
  	// usort($books, sort_pub_date);
    //
  	// return $books;
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
    $this->locations = $this->extract_from_delim_string($locations, ';');
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
