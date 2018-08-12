<?php

defined('WPINC') || die();


/**
 * Class BookTest
 *
 * @package Nomadreader_Import_Master
 */
require_once('Book.php');

/**
 * Test different instantiation of Book
 */
class BookTest extends WP_UnitTestCase {

	// Test data to ensure ISBN is padded correctly.
	protected $test_isbn = '1234632383';

	// This issue couldarise if CSV sees ISBN as number and not string
	protected $test_short_isbn = '4632383';
	protected $expect_short_isbn = '0004632383';

	protected $test_title = 'And The Mountains Echoed';

	// Authors fixture
	protected $test_authors = 'Khaled Hosseini, Abe Author';
	protected $test_authors_array = array('Khaled Hosseini', 'Abe Author');

	// Summary fixture
	protected $test_summary = "Khaled Hosseini, The Nº1 New York Times-bestselling author of The Kite Runner and A Thousand Splendid Suns, has written a new novel about how we love, how we take care of one another, and how the choices we make resonate through generations.";
	protected $test_summary_br = "Khaled Hosseini, The Nº1 New York Times-bestselling author of The Kite Runner and A Thousand Splendid Suns<br />, has written a new novel about how we love, how we take care of one another, and how the choices we make resonate through generations.";
	protected $test_summary_nl = "Khaled Hosseini, The Nº1 New York Times-bestselling author of The Kite Runner and A Thousand Splendid Suns\n, has written a new novel about how we love, how we take care of one another, and how the choices we make resonate through generations.";
	protected $test_summary_pd = "Khaled Hosseini, The Nº1 New York Times-bestselling author of The Kite Runner and A Thousand Splendid Suns.  Has written a new novel about how we love, how we take care of one another, and how the choices we make resonate through generations.";

	protected $test_excerpt = "The Nº1 New York Times-bestselling author of The Kite Runner and A Thousand Splendid Suns, has written a new novel";

	protected $expect_excerpt = "Khaled Hosseini, The Nº1 New York Times-bestselling author of The Kite Runner and A Thousand Splendid Suns";
	protected $expect_excerpt_max = "Khaled Hosseini, The Nº1 New York Times-bestselling author of The Kite Runner and A Thousand Splendid Suns, has written";

	// Rating fixture
	protected $test_rating = 4.5;

	// Location fixture
	protected $test_locations = "Kabul, Afghanistan, San Francisco, USA, Paris, France";
	protected $test_locations_array = array('Kabul', 'Afghanistan', 'San Francisco', 'USA',
																					'Paris', 'France');

	// Genres Fixture
	protected $test_genres = "Fiction & Literature, Mystery";
	protected $test_genres_array = array('Fiction & Literature', 'Mystery');

	// Periods fixture
	protected $test_periods = "1900s, 2000s";
	protected $test_periods_array = array('1900s', '2000s');

	// Tags fixture
	protected $test_tags = 'Booker, Hugo, Pulitzer';
	protected $test_tags_array = array('Booker', 'Hugo', 'Pulitzer');

	// Cover fixture
	protected $test_cover = "https://images-na.ssl-images-amazon.com/images/I/51LPx-tr1hL.jpg";


	/**
	 * Test a Book via its constructor using arrays for authors, locations,
	 * genres and periods
	 */
	public function test_create_book_from_arrays() {

		$book = new Book($this->test_isbn, $this->test_title, $this->test_authors_array,
											$this->test_summary, $this->test_rating,
											$this->test_locations_array, $this->test_genres_array,
											$this->test_periods_array, $this->test_tags_array,
											$this->test_cover, $this->test_excerpt);

		$this->assertEquals($this->test_isbn, $book->isbn);
		$this->assertEquals($this->test_title, $book->title);
		$this->assertSame($this->test_authors_array, $book->authors);
		$this->assertEquals($this->test_summary, $book->summary);
		$this->assertEquals($this->test_excerpt, $book->excerpt);
		$this->assertEquals($this->test_rating, $book->rating);
		$this->assertSame($this->test_locations_array, $book->locations);
		$this->assertSame($this->test_genres_array, $book->genres);
		$this->assertSame($this->test_periods_array, $book->periods);
		$this->assertSame($this->test_tags_array, $book->tags);
		$this->assertEquals($this->test_cover, $book->cover);
	}

	/**
	 * Test a Book via its constructor using string data for authors, locations,
	 * genres and periods
	 */
	public function test_create_book_from_strings() {

		$book = new Book($this->test_isbn, $this->test_title, $this->test_authors,
											$this->test_summary, $this->test_rating,
											$this->test_locations, $this->test_genres,
											$this->test_periods, $this->test_tags, $this->test_cover,
											$this->test_excerpt);

		$this->assertSame($this->test_authors_array, $book->authors);
		$this->assertSame($this->test_locations_array, $book->locations);
		$this->assertSame($this->test_genres_array, $book->genres);
		$this->assertSame($this->test_periods_array, $book->periods);
		$this->assertSame($this->test_tags_array, $book->tags);
	}

	/**
	 * Test to ensure that ISBN is padded properly
	 */
	public function test_create_book_with_short_isbn() {

		$book = new Book($this->test_short_isbn, $this->test_title, $this->test_authors_array,
											$this->test_summary, $this->test_rating,
											$this->test_locations_array, $this->test_genres_array,
											$this->test_periods_array, $this->test_tags, $this->test_cover,
											$this->test_excerpt);

		$this->assertEquals($this->expect_short_isbn, $book->isbn);
	}

	/**
	 * Test that if no excerpt, then the porion up to <br> and less than max char
	 * will be used as excerpt
	 */
	public function test_create_book_excerpt_from_summary_br() {

		$book = new Book($this->test_short_isbn, $this->test_title, $this->test_authors_array,
											$this->test_summary_br, $this->test_rating,
											$this->test_locations_array, $this->test_genres_array,
											$this->test_periods_array, $this->test_tags, $this->test_cover);

		$this->assertEquals($this->expect_excerpt, $book->excerpt);
	}

	/**
	 * Test that if no excerpt, then the porion up to \n and less than max char
	 * will be used as excerpt
	 */
	public function test_create_book_excerpt_from_summary_newline() {

		$book = new Book($this->test_short_isbn, $this->test_title, $this->test_authors_array,
											$this->test_summary_nl, $this->test_rating,
											$this->test_locations_array, $this->test_genres_array,
											$this->test_periods_array, $this->test_tags, $this->test_cover);

		$this->assertEquals($this->expect_excerpt, $book->excerpt);
	}

	/**
	 * Test that if no excerpt, then the porion up to period(.) and less than max char
	 * will be used as excerpt
	 */
	public function test_create_book_excerpt_from_summary_period() {

		$book = new Book($this->test_short_isbn, $this->test_title, $this->test_authors_array,
											$this->test_summary_pd, $this->test_rating,
											$this->test_locations_array, $this->test_genres_array,
											$this->test_periods_array, $this->test_tags, $this->test_cover);

		$this->assertEquals($this->expect_excerpt, $book->excerpt);
	}

	/**
	 * Test that if no excerpt, then the porion up to less than max char
	 * will be used as excerpt
	 */
	public function test_create_book_excerpt_from_summary_maxlen() {

		$book = new Book($this->test_short_isbn, $this->test_title, $this->test_authors_array,
											$this->test_summary, $this->test_rating,
											$this->test_locations_array, $this->test_genres_array,
											$this->test_periods_array, $this->test_tags, $this->test_cover);

		$this->assertEquals($this->expect_excerpt_max, $book->excerpt);
	}

	/**
	 * Test that if no excerpt, then the porion up to less than max char
	 * will be used as excerpt
	 */
	public function test_create_book_from_csv() {

		$books = Book::parse_csv(getcwd(). '/tests/data/test-books.csv');

		$this->assertEquals(5, count($books));

		$this->assertEquals('0345530764', $books[0]->isbn);
		$this->assertEquals('0553418793', $books[1]->isbn);
		$this->assertEquals('1402294158', $books[2]->isbn);
		$this->assertEquals('1250067774', $books[3]->isbn);
		$this->assertEquals('0393244466', $books[4]->isbn);

	}

	/**
	 * Test a JSON file downloaded from Chrome Extension will populate Book
	 */
	public function test_create_book_from_json_web_fmt() {

		$books = Book::parse_json(getcwd(). '/tests/data/test-book-web-fmt.json');

		$this->assertEquals(1, count($books));

		$this->assertEquals('0812985400', $books[0]->isbn);
		$this->assertEquals(array("Washington D.C., USA"), $books[0]->locations);

	}

	/**
	 * Test a JSON file created int the full detailed JSON format will populate Book
	 */
	public function test_create_book_from_json_city_cnty_fmt() {

		$books = Book::parse_json(getcwd(). '/tests/data/test-book-city-country-fmt.json');

		$this->assertEquals(1, count($books));

		$this->assertEquals('000721829X', $books[0]->isbn);
		$this->assertEquals(array("London, England"), $books[0]->locations);
		$this->assertEquals(3.7, $books[0]->rating, 0.1);

	}

	/**
	 * Test a Book is loaded from WordPress
	 */
	public function test_load_book_from_wordpress() {

		// $books = Book::parse_json(getcwd(). '/tests/data/test-book-city-country-fmt.json');
		//
		// $this->assertEquals(1, count($books));
		//
		// $this->assertEquals('000721829X', $books[0]->isbn);
		// $this->assertEquals(array("London, England"), $books[0]->locations);
		// $this->assertEquals(3.7, $books[0]->rating, 0.1);

	}

}
