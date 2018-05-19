<?php
include('class-wp-list-table.php');

/**
 * Class to generate the search results of book title or ISBN
 *
 * This should allow the user to select books to add in bulk or
 * on a row by row basis.
 */
class BookListTable extends WPListTable {

  /**
   * Constructor, we override the parent to pass our own arguments
   * We usually focus on three parameters: singular and plural labels,
   * as well as whether the class supports AJAX.
   */
  function __construct($data) {

    parent::__construct(array(
      'singular'=> 'book',
      'plural' => 'books',
      'ajax'   => false,
    ));

    $this->items = $data;
  }

  /**
   * Define the columns that are going to be used in the table
   *
   * @return array $columns, the array of columns to use with the table
   */
   function get_columns() {
    return $columns = array(
      'select'  => '<input type="checkbox">',
      'pub_date' => __('Publish'),
      'isbn' => __('ISBN'),
      'title' => __('Title'),
      'authors' => __('Authors'),
      'desc' => __('Summary'),
      'images' => __('Images'),
      'terms' => __('Tags'),
    );
  }

  /**
	 * Get an associative array ( option_name => option_title ) with the list
	 * of bulk actions available on this table.
	 *
	 * Optional. If you need to include bulk actions in your list table, this is
	 * the place to define them. Bulk actions are an associative array in the format
	 * 'slug'=>'Visible Title'
	 *
	 * If this method returns an empty value, no bulk action will be rendered. If
	 * you specify any bulk actions, the bulk actions box will be rendered with
	 * the table automatically on display().
	 *
	 * Also note that list tables are not automatically wrapped in <form> elements,
	 * so you will need to create those manually in order for bulk actions to function.
	 *
	 * @return array An associative array containing all the bulk actions.
	 */
	protected function get_bulk_actions() {
		$actions = array(
			'add' => _x('Add', 'List table bulk action'),
		);
		return $actions;
	}

  /**
   * Render a column when no column specific method exists.
   *
   * @param array $item
   * @param string $column_name
   *
   * @return mixed
   */
  public function column_default($item, $colname) {
    switch($colname) {
      default:
        return $item[$colname];
    }
  }

  /**
	 * Set checkbox column properties
	 *
	 * @param object $item A singular item (one full row's worth of data).
	 * @return string Text to be placed inside the column <td>.
	 */
	protected function column_select($item) {

		// Return the title contents.
		return sprintf('<input type="checkbox" name="action[]" value="%1$s" />',
			$item['isbn']
		);
	}

  /**
	 * Set publication date column properties
	 *
	 * @param object $item A singular item (one full row's worth of data).
	 * @return string Text to be placed inside the column <td>.
	 */
  protected function column_pub_date($item) {
    // Create the pub date field name as 2D form data array
    $fieldname = 'pub_date[' . $item['isbn'] . '][]';

    $temp = date_format(date_create($item['pub_date']), 'M j, Y');
    $elem = '<span class="">%1$s</span>';
    $elem .= '<input type="hidden" name="%3$s" value="%2$s" />';
		return sprintf($elem, $temp, $item['pub_date'], $fieldname);
	}

  /**
	 * Set ISBN column properties
	 *
	 * @param object $item A singular item (one full row's worth of data).
	 * @return string Text to be placed inside the column <td>.
	 */
  protected function column_isbn($item) {
		// Return the column contents.
    $fieldname = 'isbn[' . $item['isbn'] . '][]';

    $elem = '<span class="">%1$s</span>';
    $elem .= '<input type="hidden" name="%2$s" value="%1$s" />';
		return sprintf($elem, $item['isbn'], $fieldname);
	}

  /**
	 * Set book title column properties
	 *
	 * @param object $item A singular item (one full row's worth of data).
	 * @return string Text to be placed inside the column <td>.
	 */
  protected function column_title($item) {
    // Return the column contents.
    $fieldname = 'title[' . $item['isbn'] . '][]';

    $elem = '<span class="">%1$s</span>';
    $elem .= '<input type="hidden" name="%2$s" value="%1$s" />';
		return sprintf($elem, $item['title'], $fieldname);
	}

  /**
	 * Set authors date column properties
	 *
	 * @param object $item A singular item (one full row's worth of data).
	 * @return string Text to be placed inside the column <td>.
	 */
  protected function column_authors($item) {
		// Return the column contents.
    $fieldname = 'authors[' . $item['isbn'] . '][]';

    $elem = '<span class="">%1$s</span>';
    $elem .= '<input type="hidden" name="%2$s" value="%1$s" /> <br />';

    $authors = '';
    if (is_array($item['authors'])) {
      foreach($item['authors'] as $author) {
        $authors .= sprintf($elem, $author, $fieldname);
      }
    }
    else {
      $authors = sprintf($elem, $item['authors'], $fieldname);
    }

		return $authors;
	}

  /**
	 * Set book summary and excerpt column properties
	 *
	 * @param object $item A singular item (one full row's worth of data).
	 * @return string Text to be placed inside the column <td>.
	 */
  protected function column_desc($item) {
		// Return the column contents.
    $fieldname = 'desc[' . $item['isbn'] . '][]';
    $fieldname1 = 'excerpt[' . $item['isbn'] . '][]';

    $elem = '<span class="">%1$s</span>';
    $elem .= '<input type="hidden" name="%3$s" value="%1$s" />';
    $elem .= '<input type="hidden" name="%4$s" value="%2$s" />';
		return sprintf($elem, $item['desc'], $item['excerpt'], $fieldname, $fieldname1);
	}

  /**
	 * Set book cover image column properties
	 *
	 * @param object $item A singular item (one full row's worth of data).
	 * @return string Text to be placed inside the column <td>.
	 */
  protected function column_images($item) {
    // Return the column contents.
    $fieldname = 'imgfile[' . $item['isbn'] . '][]';
    $fieldname1 = 'imgwidth[' . $item['isbn'] . '][]';
    $fieldname2 = 'imgheight[' . $item['isbn'] . '][]';

    $elem = '<img src="%1$s" />';
    $elem .= '<input type="hidden" name="%5$s" value="%2$s" />';
    $elem .= '<input type="hidden" name="%6$s" value="%3$s" />';
    $elem .= '<input type="hidden" name="%7$s" value="%4$s" />';
		return sprintf($elem, $item['images']['medium']['file'],
                    $item['images']['large']['file'],
                    $item['images']['large']['width'],
                    $item['images']['large']['height'],
                     $fieldname, $fieldname1, $fieldname2);
	}

  protected function column_terms($item) {

    $col_html = '
      <div class="container-fluid">
        <div class="form-group">
          %1$s
          <input type="text" name="tags[%2$s][]" value="%3$s"
                 class="tags" placeholder="Tags"/>
        </div>
      </div>
    ';
    $term_list_html = '
        <select id="%1$s" name="%2$s" data-placeholder="%3$s"
                class="chosen-select form-control" multiple>
            %4$s
        </select>
    ';
    $subterm_list_html = '
        <option value="%1$s" %3$s>%2$s</option>
    ';

    $terms = $item['terms'];

    // If terms is empty then no currently selected top level terms
    // (ie from amazon search by title)
    if (empty($terms)) {
      $terms = array(
    		'genres'		=> array('term_id' => 0,
    												 'subterms' => array()),
    		'periods'		=> array('term_id' => 0,
    												 'subterms' => array()),
    		'location'	=> array('term_id' => 0,
    												 'subterms' => array()),
    	);
      foreach($terms as $termname => $termattrs) {
        $args_main = array(
      		'name'										 => $termname,
      		'type'                     => 'product',
      		'parent'                   => 0,
      		'orderby'                  => 'term_group',
      		'hide_empty'               => false,
      		'hierarchical'             => 1,
      		'taxonomy'                 => 'product_cat',
      		'pad_counts'               => false
      	);
      	$tlterms = get_terms($args_main);
      	if (!is_wp_error($tlterms) && !empty($tlterms)) {
          $terms[$termname]['term_id'] = $tlterms[0]->term_id;
          $terms[$termname]['subterms'] = array();
        }
      }
    }

    $terms_html = '';
    foreach($terms as $name => $attrs) {

      $args_main = array(
        'parent'          => $attrs['term_id'], // query for all subterms
    		'orderby'         => 'term_group',
    		'hide_empty'      => false,
    		'hierarchical'    => 1,
    		'taxonomy'        => 'product_cat',
    	);
      // Get all the subterms for current term (authors, location, genres, periods)
      $term_list = get_terms($args_main);
      if (!is_wp_error($term_list)) {
        if (($term_list !== null || $term_list !== 0) || !empty($term_list)) {

          $subterms_html = '';
          foreach($term_list as $term_obj) {

            $checked = '';
            if ($this->has_subterm($term_obj->name, $terms[$name]['subterms'])) {
              $checked = 'selected';
            }
            $subterms_html .= sprintf($subterm_list_html, $term_obj->term_id,
                                      $term_obj->name, $checked);
          }
        }

        $idname = $name . '_' . $item['isbn'];
        $fieldname = $name . '[' . $item['isbn'] . '][]';
        $terms_html .= sprintf($term_list_html, $idname, $fieldname, ucfirst($name),
                               $subterms_html);
      }
    }

    $final_html = sprintf($col_html, $terms_html, $item['isbn'], $item['tags']);

    return $final_html;
	}

  /**
  * Prepare the table with different parameters, pagination, columns and table elements
  */
  function prepare_items() {

    // Set pagnination parameters
    $per_page = 5;

    // Set table column properties
    $columns = $this->get_columns();
    $hidden = array();
    $sortable = array();

    $this->_column_headers = array($columns, $hidden, $sortable);

    $this->process_bulk_action();
  }

  /**
   * Checks if a data item has the specified term
   */
  private function has_subterm($term, $book_subterms) {
    $result = False;

    foreach($book_subterms as $attrs) {

      if (strcasecmp(htmlentities($attrs['term_name']), $term) === 0) {
        $result = True;
        break;
      }

      if ($result) { break; }
    }

    return $result;
  }
}

?>
