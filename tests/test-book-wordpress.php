<?php
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
	protected $test_authors = array('Khaled Hosseini', 'Abe Author');
	protected $test_authors_upd = array('Mos Def', 'Douglas Adams');
	protected $test_summary = "Khaled Hosseini, The Nº1 New York Times-bestselling author of The Kite Runner and A Thousand Splendid Suns, has written a new novel about how we love, how we take care of one another, and how the choices we make resonate through generations.";
	protected $test_excerpt = "The Nº1 New York Times-bestselling author of The Kite Runner and A Thousand Splendid Suns, has written a new novel";
	protected $test_rating = 4.5;
	protected $test_locations = array('Kabul', 'Afghanistan', 'San Francisco', 'USA', 'Paris', 'France');
	protected $test_locations_upd = array('Berlin', 'Germany');
	protected $test_genres = array('Fiction &amp; Literature', 'Mystery');
	protected $test_genres_upd = array('Travel', 'Young Adult');
	protected $test_periods = array('1900s', '2000s');
	protected $test_periods_upd = array('1800s');
	protected $test_tags = array('Hugo', 'Pulitzer');
	protected $test_tags_upd = array('Booker');
	protected $test_cover = "https://images-na.ssl-images-amazon.com/images/I/TEST_1.jpg";
	protected $test_cover_upd = "https://images-na.ssl-images-amazon.com/images/I/TEST_2.jpg";


	/**
	 * Setup the Fixture
	 */
	function setUp() {
		parent::setUp();

		$this->factory->product = new WP_UnitTest_Factory_For_Product($this->factory);
		$this->factory->term = new WP_UnitTest_Factory_For_Term($this->factory, 'product_cat');
		$this->factory->cover = new WP_UnitTest_Factory_For_Product_Image($this->factory);

		// Create fixture
		$this->post_id = $this->factory->product->create(array(
			'isbn' => $this->test_isbn
		));
		$this->cover_id = $this->factory->cover->create(array(
			'post_parent' => $this->post_id,
			'guid' => $this->test_cover
		));

		// Create AUTHOR terms
		$auth_tax = $this->factory->term->create(array(
				'name' => 'Authors',
				'slug' => 'authors',
				'taxonomy' => 'product_cat'
		));
		$authors = array();
		foreach($this->test_authors as $auth) {
			$authors[] = $this->factory->term->create(array(
					'name' => $auth,
					'parent' => $auth_tax,
					'taxonomy' => 'product_cat'
			));
		}
		$this->factory->term->add_post_terms($this->post_id, $authors, 'product_cat',
			true);

		// Create GENRES terms
		$genres_tax = $this->factory->term->create(array(
				'name' => 'Genres',
				'slug' => 'genres',
				'taxonomy' => 'product_cat'
		));
		$genres = array();
		foreach($this->test_genres as $genre) {
			$genres[] = $this->factory->term->create(array(
					'name' => $genre,
					'parent' => $genres_tax,
					'taxonomy' => 'product_cat'
			));
		}
		$this->factory->term->add_post_terms($this->post_id, $genres, 'product_cat',
			true);

		// Create PERIODS terms
		$periods_tax = $this->factory->term->create(array(
				'name' => 'Periods',
				'slug' => 'periods',
				'taxonomy' => 'product_cat'
		));
		$periods = array();
		foreach($this->test_periods as $period) {
			$periods[] = $this->factory->term->create(array(
					'name' => $period,
					'parent' => $periods_tax,
					'taxonomy' => 'product_cat'
			));
		}
		$this->factory->term->add_post_terms($this->post_id, $periods, 'product_cat',
			true);

		// Create LOCATIONS terms
		$locs_tax = $this->factory->term->create(array(
				'name' => 'Locations',
				'slug' => 'locations',
				'taxonomy' => 'product_cat'
		));
		$locations = array();
		foreach($this->test_locations as $loc) {
			$locations[] = $this->factory->term->create(array(
					'name' => $loc,
					'parent' => $locs_tax,
					'taxonomy' => 'product_cat'
			));
		}
		$this->factory->term->add_post_terms($this->post_id, $locations, 'product_cat',
			true);

		// Create TAGS terms
		$this->factory->term->add_post_terms($this->post_id, $this->test_tags,
			'product_tag', true);
	}

	/**
	 * Test a Book is loaded from WordPress
	 */
	public function test_load_book_from_wordpress() {

		// Create test object
		$book = Book::load_book($this->test_isbn);

		// Assert that a Book object was returned
		$this->assertinstanceOf(Book::class, $book,
						'Book ' . $this->test_isbn . ' was not loaded from WordPress');

		$this->assertEquals($this->test_isbn, $book->isbn);
		$this->assertStringStartsWith('Book title', $book->title);
		$this->assertStringStartsWith('Book summary', $book->summary);
		$this->assertStringStartsWith('Book excerpt', $book->excerpt);
		// $this->assertEquals($this->rating, $book->rating);
		$this->assertEquals($this->test_tags, $book->tags);
		$this->assertEquals($this->test_cover, $book->cover);

		$this->assertCount(0, array_diff($this->test_authors, $book->authors),
			'Authors not loaded');
		$this->assertCount(0, array_diff($this->test_genres, $book->genres),
			'Genres not loaded');
		$this->assertCount(0, array_diff($this->test_periods, $book->periods),
			'Periods not loaded');
		$this->assertCount(0, array_diff($this->test_locations, $book->locations),
			'Locations not loaded');
	}

	/**
	 * Test a Book is inserted into WordPress
	 */
	public function test_insert_book_into_wordpress() {

		// Load the test Book created via WP Factories
		$book = Book::load_book($this->test_isbn);

		// Insert book which should return a non-zero value
		$post_id = $book->insert();

		$this->assertGreaterThan(0, $post_id);

		// Get the product posts, there should only be 2
		$args = array(
			'post_type'				=> 'product',
			'posts_per_page'	=> 2,
			'orderby'					=> 'ID',
			'order'						=> 'ASC'
		);
		$posts = get_posts($args);

		$this->assertEquals(2, count($posts));

		// posts[0] = orig, post[1] = inserted
		$this->assertEquals($post_id, $posts[1]->ID);
		$this->assertNotEquals($posts[0]->ID, $posts[1]->ID);
		$this->assertEquals($posts[0]->post_title, $posts[1]->post_title);
		$this->assertEquals($posts[0]->post_summary, $posts[1]->post_summary);

		// Get the attachment posts, there should only be 2
		$args = array(
			'post_type'				=> 'attachment',
			'posts_per_page'	=> 2,
			'orderby'					=> 'ID',
			'order'						=> 'ASC'
		);
		$posts = get_posts($args);

		$this->assertEquals(2, count($posts));

		// posts[0] = orig, post[1] = inserted
		$this->assertEquals($post_id, $posts[1]->post_parent);
		$this->assertNotEquals($posts[0]->ID, $posts[1]->ID);
		$this->assertEquals($posts[0]->guid, $posts[1]->guid);
	}

	/**
	 * Test a Book is inserted into WordPress
	 */
	public function test_update_book_in_wordpress() {

		// Load the book and update its values
		$upd_book = Book::load_book($this->test_isbn);
		$orig_book = clone $upd_book;

		$upd_book->title = $this->test_title;
		$upd_book->authors = $this->test_authors_upd;
		$upd_book->summary = $this->test_summary;
		$upd_book->summary = $this->test_summary;
		$upd_book->excerpt = $this->test_excerpt;
		$upd_book->rating = $this->test_rating;
		$upd_book->locations = $this->test_locations_upd;
		$upd_book->genres = $this->test_genres_upd;
		$upd_book->periods = $this->test_periods_upd;
		$upd_book->tags = $this->test_tags_upd;
		$upd_book->cover = $this->test_cover_upd;

		// Update book should return a non-zero value (post_id) of updated post
		$post_id = $upd_book->update();
		$this->assertGreaterThan(0, $post_id);

		// Create book with data to update.  We keep the same ISBN
		$book = new Book($this->test_isbn, $this->test_title, $this->test_authors_upd,
											$this->test_summary, $this->test_rating, $this->test_locations_upd,
											$this->test_genres_upd, $this->test_periods_upd, $this->test_tags_upd,
											$this->test_cover_upd, $this->test_excerpt);

		$this->assertThat((array)$orig_book, $this->logicalNot(
			$this->equalTo((array)$book)
		));
		$this->assertSame((array)$book, (array)$upd_book);
		
	}

}
