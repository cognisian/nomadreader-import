<?php

defined('WPINC') || die();

/**
 * Class BookWordPressTest
 *
 * @package Nomadreader_Import_Master
 */
require_once('factory-for-product.php');
require_once('factory-for-product-image.php');

require_once('Book.php');

/**
 * Test different instantiation of Book
 */
class BookWordPressTest extends WP_UnitTestCase {

	// Test data to ensure ISBN is padded correctly.
	protected $test_isbn = '1234632383';

	protected $test_title = 'And The Mountains Echoed';
	protected $test_title_upd = 'And The Mountains Echoed AGAIN';

	protected $test_authors = array('Khaled Hosseini', 'Abe Author');
	protected $test_authors_upd = array('Mos Def', 'Douglas Adams');

	protected $test_summary = "Khaled Hosseini, The Nº1 New York Times-bestselling author of The Kite Runner and A Thousand Splendid Suns, has written a new novel about how we love, how we take care of one another, and how the choices we make resonate through generations.";
	protected $test_excerpt = "The Nº1 New York Times-bestselling author of The Kite Runner and A Thousand Splendid Suns, has written a new novel";

	protected $test_rating = 4.5;
	protected $test_rating_upd = 3.5;

	protected $test_locations = array('Kabul', 'Afghanistan', 'San Francisco', 'USA', 'Paris', 'France');
	protected $test_locations_upd = array('Berlin', 'Germany');

	protected $test_genres = array('Fiction &amp; Literature', 'Mystery');
	protected $test_genres_upd = array('Travel', 'Young Adult');

	protected $test_periods = array('1900s', '2000s');
	protected $test_periods_upd = array('1800s');

	protected $test_tags = array('Hugo', 'Pulitzer');
	protected $test_tags_upd = array('Booker');

	protected $default_cover = "/test/image.jpg";
	protected $test_cover = "https://images-na.ssl-images-amazon.com/images/I/TEST_1.jpg";
	protected $test_cover_upd = "https://images-na.ssl-images-amazon.com/images/I/TEST_2.jpg";


	/**
	 * Setup the Fixture
	 */
	function setUp() {
		parent::setUp();

		$this->factory->post = new WP_UnitTest_Factory_For_Product($this->factory);
		$this->factory->term = new WP_UnitTest_Factory_For_Term($this->factory, 'product_cat');

		// Use custom Factory to hide some of the WP media details
		$this->factory->cover = new WP_UnitTest_Factory_For_Product_Image($this->factory);
	}

	/**
	 * Test a Book is loaded from WordPress with minimal data (ie no Authors, Tags etc)
	 */
	public function test_load_min_book_from_wordpress() {

		// FIXTURE test object
		$this->fixture_create_book($this->test_isbn);

		// TEST
		$book = Book::load_book($this->test_isbn);

		// VALIDATE that a Book object was returned
		$this->assertinstanceOf(Book::class, $book,
						'Book ' . $this->test_isbn . ' was not loaded from WordPress');

		// VALIDATE that a Book with dummy data is returned
		$this->assertEquals($this->test_isbn, $book->isbn);
		$this->assertStringStartsWith('Book title', $book->title);
		$this->assertStringStartsWith('Book summary', $book->summary);
		$this->assertStringStartsWith('Book excerpt', $book->excerpt);
		// $this->assertEquals($this->rating, $book->rating);
		$this->assertEquals(array(), $book->tags);
		$this->assertEquals($this->default_cover, $book->cover);

		$this->assertEquals(array(), $book->authors);
		$this->assertEquals(array(), $book->genres);
		$this->assertEquals(array(), $book->periods);
		$this->assertEquals(array(), $book->locations);
	}

	/**
	 * Test a Book is loaded from WordPress with full data (ie with Authors, Tags etc)
	 */
	public function test_load_all_book_from_wordpress() {

		// FIXTURE test object
		$this->fixture_create_book($this->test_isbn, $this->test_title,
							$this->test_authors, $this->test_summary,
							$this->test_rating, $this->test_locations,
							$this->test_genres, $this->test_periods, $this->test_tags,
							$this->test_cover, $this->test_excerpt);

		// TEST
		$book = Book::load_book($this->test_isbn);

		// VALIDATE that a Book object was returned
		$this->assertinstanceOf(Book::class, $book,
						'Book ' . $this->test_isbn . ' was not loaded from WordPress');

		// VALIDATE that a Book with dummy data is returned
		$this->assertEquals($this->test_isbn, $book->isbn);
		$this->assertEquals($this->test_title, $book->title);
		$this->assertEquals($this->test_summary, $book->summary);
		$this->assertEquals($this->test_excerpt, $book->excerpt);
		// $this->assertEquals($this->rating, $book->rating);
		$this->assertEquals($this->test_cover, $book->cover);

		// Assert against diff as the keys returned by Book are term_id, assertSame will fail
		$this->assertEquals(0, count(array_diff($this->test_authors, $book->authors)),
			'Unable to find Authors in post terms');
		$this->assertEquals(0, count(array_diff($this->test_genres, $book->genres)),
			'Unable to find Genres in post terms');
		$this->assertEquals(0, count(array_diff($this->test_locations, $book->locations)),
			'Unable to find Locations in post terms');
		$this->assertEquals(0, count(array_diff($this->test_periods, $book->periods)),
			'Unable to find Periods in post terms');

		$this->assertSame($this->test_tags, $book->tags);
	}

	/**
	 * Test a Book is inserted into WordPress
	 */
	public function test_insert_book_into_wordpress() {

		// FIXTURE Book object
		$book = new Book($this->test_isbn, $this->test_title, $this->test_authors,
											$this->test_summary, $this->test_rating, $this->test_locations,
											$this->test_genres, $this->test_periods, $this->test_tags,
											$this->test_cover, $this->test_excerpt);

		// TEST Insert book which should return a non-zero value
		$ins_post_id = $book->insert();

		// VALIDATE
		$this->assertGreaterThan(0, $ins_post_id);

		// VALIDATE that a Book with dummy data is returned
		$this->assertEquals($this->test_isbn, $book->isbn);
		$this->assertEquals($this->test_title, $book->title);
		$this->assertEquals($this->test_summary, $book->summary);
		$this->assertEquals($this->test_excerpt, $book->excerpt);
		// $this->assertEquals($this->rating, $book->rating);
		$this->assertEquals($this->test_cover, $book->cover);

		// Assert against diff as the keys returned by Book are term_id, assertSame will fail
		$this->assertEquals(0, count(array_diff($this->test_authors, $book->authors)),
			'Unable to find Authors in post terms');
		$this->assertEquals(0, count(array_diff($this->test_genres, $book->genres)),
			'Unable to find Genres in post terms');
		$this->assertEquals(0, count(array_diff($this->test_locations, $book->locations)),
			'Unable to find Locations in post terms');
		$this->assertEquals(0, count(array_diff($this->test_periods, $book->periods)),
			'Unable to find Periods in post terms');

		$this->assertSame($this->test_tags, $book->tags);
	}

	/**
	 * Test a Book is inserted into WordPress
	 * @group failing
	 */
	public function test_update_book_in_wordpress() {

		// Create a Book fixtue with real data to be updated
		$this->fixture_create_book($this->test_isbn, $this->test_title,
						$this->test_authors, $this->test_summary, $this->test_rating,
						$this->test_locations, $this->test_genres,
						$this->test_periods, $this->test_tags, $this->test_cover,
						$this->test_excerpt);

		// TEST Update the Book details with new values
		$orig_book = Book::load_book($this->test_isbn);
		$upd_book = clone $orig_book;
		$upd_book->title = $this->test_title_upd;
		$upd_book->authors = $this->test_authors_upd;
		$upd_book->summary = $this->test_summary;
		$upd_book->excerpt = $this->test_excerpt;
		$upd_book->rating = $this->test_rating_upd;
		$upd_book->locations = $this->test_locations_upd;
		$upd_book->genres = $this->test_genres_upd;
		$upd_book->periods = $this->test_periods_upd;
		$upd_book->tags = $this->test_tags_upd;
		$upd_book->cover = $this->test_cover_upd;

		$post_id = $upd_book->update();

		// Assert success
		$this->assertEquals($this->post_id, $post_id);

		// Reload Book from database to ensure updated
		$book = Book::load_book($this->test_isbn);

		// Assert original data was updated in WordPress
		$this->assertThat((array)$orig_book, $this->logicalNot(
			$this->equalTo((array)$book)
		));

		// VALIDATE that a Book with dummy data is returned
		$this->assertEquals($this->test_isbn, $book->isbn);
		$this->assertEquals($this->test_title_upd, $book->title);
		$this->assertEquals($this->test_summary, $book->summary);
		$this->assertEquals($this->test_excerpt, $book->excerpt);
		// $this->assertEquals($this->rating, $book->rating);
		$this->assertEquals($this->test_cover_upd, $book->cover);

		// Assert against diff as the keys returned by Book are term_id, assertSame will fail
		$this->assertEquals(0, count(array_diff($this->test_authors_upd, $book->authors)),
			'Unable to find Authors in post terms');
		$this->assertEquals(0, count(array_diff($this->test_genres_upd, $book->genres)),
			'Unable to find Genres in post terms');
		$this->assertEquals(0, count(array_diff($this->test_locations_upd, $book->locations)),
			'Unable to find Locations in post terms');
		$this->assertEquals(0, count(array_diff($this->test_periods_upd, $book->periods)),
			'Unable to find Periods in post terms');

		$this->assertSame($this->test_tags_upd, $book->tags);

	}

	/**
   * Test that importing books from file will create or update new product posts.
	 *
	 * This should run in a separate process as the import will call
	 * wp_redirect and die
	 *
	 * @runInSeparateProcess
   */
	public function test_import_books_insert_update() {

		// FIXTURE, create the initial products to export
		$books = Book::parse_csv(getcwd(). '/tests/data/test-books.csv');
		foreach($books as $book) {
			$book->insert();
		}

		// Setup the file to import:
		// 		2 books with same ISBN as existing books
		// 		1 new book with new ISBN
		$_POST['action'] = 'import_files';

		$_FILES['import_files']['tmp_name'] = array(
			getcwd(). '/tests/data/test-import-update.csv'
		);
		$_FILES['import_files']['name'] = array(
			'test-import-update.csv'
		);
		$_FILES['import_files']['type'] = array(
			'text/csv'
		);

		// TEST Import the updates to books
		import_files();

		// ASSERT 1 book added
		$book = Book::load_book('1111111111');
		$this->assertEquals('Title', $book->title);
		$this->assertEquals('Summary', $book->summary);
		$this->assertEquals(3.1, $book->rating);
		$this->assertSame(array('A Author'), $book->authors);
		$this->assertSame(array('Paris', 'France'), $book->locations);
		$this->assertSame(array('Romance'), $book->genres);
		$this->assertSame(array('1800s'), $book->periods);

		// ASSERT 2 books updated
		$book = Book::load_book('345530764');
		$this->assertStringStartsWith('UPDATED', $book->title);
		$this->assertStringStartsWith('UPDATED', $book->summary);
		$this->assertEquals(1.0, $book->rating);

		$this->assertSame(array('A Author'), $book->authors);
		$this->assertSame(array('Updated', 'France'), $book->locations);
		$this->assertSame(array('Fiction & Literature', 'Historical Fiction'),
											$book->genres);
		$this->assertSame(array('2000s'), $book->periods);

		$book = Book::load_book('553418793');
		$this->assertStringStartsWith('UPDATED', $book->title);
		$this->assertStringStartsWith('UPDATED', $book->summary);
		$this->assertEquals(2.0, $book->rating);

		$this->assertEquals(array('Nina George'), $book->authors);
		$this->assertSame(array('Another', 'France'), $book->locations);
		$this->assertSame(array('Mystery & Suspense', 'Romance'),
											$book->genres);
		$this->assertSame(array('Recent Releases'), $book->periods);
  }

	//
	// HELPERS
	//

	/**
	 * Create all the book details via factory create default values, or override
	 *
	 * @param string $isbn            ISBN-10/13/ASIN number
   * @param string $title           Book title
   * @param array $authors   				List of authors
   * @param string $summary         The long form summary
   * @param long $rating            Rating on scale of 5.0
   * @param array $locations 				List of locations
   * @param array $genres				    List of genre names
   * @param array $periods   				List of period names
   * @param string $tags            List of comma seperated words
   * @param string $cover           An URL for the image of the book cover
   * @param string $excerpt         Short desc of book.  Optional if empty then
   *                                take first sentence of summary. Optional
	 */
	private function fixture_create_book($isbn, $title='', $authors=array(), $summary='',
											$rating=0.0, $locations=array(), $genres=array(), $periods=array(),
											$tags=array(), $cover='/test/image.jpg', $excerpt='') {

		// Add details to fixture
		$post_args = array();
		if (!empty($title)) {
			$post_args['post_title'] = $title;
		}
		if (!empty($summary)) {
			$post_args['post_content'] = $summary;
		}
		if (!empty($excerpt)) {
			$post_args['post_excerpt'] = $excerpt;
		}

		// Create fixture - product post
		$this->post_id = $this->factory->post->create($post_args);
		add_post_meta($this->post_id, 'isbn_prod', $isbn, true);

		// Create fixture - product post categorization
		if (!empty($authors)) {
			$this->create_category($authors, 'Authors', 'authors');
			$this->factory->term->add_post_terms($this->post_id, $authors, 'product_cat',
				true);
		}

		if (!empty($genres)) {
			$this->create_category($genres, 'Genres', 'genres');
			$this->factory->term->add_post_terms($this->post_id, $genres, 'product_cat',
				true);
		}

		if (!empty($periods)) {
			$this->create_category($periods, 'Periods', 'periods');
			$this->factory->term->add_post_terms($this->post_id, $periods, 'product_cat',
				true);
		}

		if (!empty($locations)) {
			$this->create_category($locations, 'Locations', 'locations');
			$this->factory->term->add_post_terms($this->post_id, $locations, 'product_cat',
				true);
		}

		// Create fixture - product post categorization
		if (!empty($tags)) {
			$this->create_tags($tags);
			$this->factory->term->add_post_terms($this->post_id, $tags, 'product_tag',
				true);
		}

		// Create fixture - attachment post for media lib image
		$attach_args = array(
			'post_parent' => $this->post_id,
			'guid'				=> $cover
		);
		$this->factory->cover->create($attach_args);

		// Create fixture - comment items for Rating associated product post
		$this->comment_id = $this->factory->comment->create(array(
			'comment_post_ID' => $this->post_id,
			'user_id' => 1, // Admin user
		));
		add_comment_meta($this->comment_id, 'rating', $rating);
		add_post_meta($this->post_id, '_wc_average_rating', $rating);
	}

	/**
	 * Create the array for product post Term categorization under the WooCommerce
	 * taxonomy product_tag
	 */
	private function create_category($data, $name, $slug) {

		// Create the top level term category (Authors, Genres, Periods, Locations)
		$taxonomy = $this->factory->term->create(array(
				'name' => $name,
				'slug' => $slug,
				'taxonomy' => 'product_cat'
		));

		// For each term associate with
		$terms = array();
		foreach($data as $term) {
			$terms[] = $this->factory->term->create(array(
				'name'			=> $term,
				'parent' 		=> $taxonomy,
				'taxonomy'	=> 'product_cat'
			));
		}
	}

	/**
	 * Create the array for product post Term tags under the WooCommerce taxonomy
	 * product_tag
	 */
	private function create_tags($data) {

		// For each tag associate with WC product_tag
		$tags = array();
		foreach($data as $tag) {
			$tags[] = $this->factory->term->create(array(
				'name'			=> $tag,
				'taxonomy'	=> 'product_tag'
			));
		}
	}

}
