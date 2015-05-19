<?php

/**
 * Check that 'class-wp-list-table.php' is available
 */
if ( ! class_exists( '\\WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class Biont_SubPlugins_PluginListTable extends WP_List_Table {

	protected $installed = array();

	function __construct( $installed, $prefix ) {

		$this->installed = $installed;

		$this->prefix    = $prefix;

		//Set parent defaults
		parent::__construct( array(
			                     'singular' => 'plugin',     //singular name of the listed records
			                     'plural'   => 'plugins',    //plural name of the listed records
			                     'ajax'     => FALSE        //does this table support ajax?
		                     ) );

	}

	/** ************************************************************************
	 * Recommended. This method is called when the parent class can't find a method
	 * specifically build for a given column. Generally, it's recommended to include
	 * one method for each column you want to render, keeping your package class
	 * neat and organized.
	 *
	 * @param array $item        A singular item (one full row's worth of data)
	 * @param array $column_name The name/slug of the column to be processed
	 *
	 * @return string Text or HTML to be placed inside the column <td>
	 **************************************************************************/
	function column_default( $item, $column_name ) {

		switch ( $column_name ) {
			case 'description':
				return $item[ ucfirst( $column_name ) ];
			default:
				return print_r( $item, TRUE ); //Show the whole array for troubleshooting purposes
		}
	}

	/** ************************************************************************
	 * Recommended. This is a custom column method and is responsible for what
	 * is rendered in any column with a name/slug of 'title'. Every time the class
	 * needs to render a column, it first looks for a method named
	 * column_{$column_title} - if it exists, that method is run. If it doesn't
	 * exist, column_default() is called instead.
	 *
	 * This example also illustrates how to implement rollover actions. Actions
	 * should be an associative array formatted as 'slug'=>'link html' - and you
	 * will need to generate the URLs yourself. You could even ensure the links
	 *
	 *
	 * @see WP_List_Table::::single_row_columns()
	 *
	 * @param array $item A singular item (one full row's worth of data)
	 *
	 * @return string Text to be placed inside the column <td> (movie title only)
	 **************************************************************************/
	function column_plugin( $item ) {

		$screen = get_current_screen();
		$screen = add_query_arg( array( 'page' => $_REQUEST[ 'page' ] ), $screen->parent_file );

		//Build row actions

		$actions = array();

		if ( $item[ 'Active' ] === TRUE ) {
			$actions[ 'deactivate' ] = sprintf( '<a href="%s&action=%s&%s=%s&%s_plugins_changed=1">%s</a>',
			                                    $screen,
			                                    'deactivate',
			                                    $this->_args[ 'singular' ],
			                                    esc_attr( $item[ 'File' ] ),
			                                    $this->prefix,
			                                    __( 'Deactivate' )
			);
		} else {
			$actions[ 'activate' ] = sprintf( '<a href="%s&action=%s&%s=%s&%s_plugins_changed=1">%s</a>',
			                                  $screen,
			                                  'activate',
			                                  $this->_args[ 'singular' ],
			                                  esc_attr( $item[ 'File' ] ),
			                                  $this->prefix,
			                                  __( 'Activate' )
			);
		}

		$actions[ 'delete' ] = sprintf( '<a href="%s&action=%s&%s=%s">%s</a>',
		                                $screen,
		                                'delete',
		                                $this->_args[ 'singular' ],
		                                esc_attr( $item[ 'File' ] ),
		                                __( 'Delete' )
		);

		$name = $item[ 'Name' ];

		if ( $item[ 'Active' ] ) {
			$name = '<strong>' . $name . '</strong>';
		}

		//Return the title contents
		return $name . ' ' . $this->row_actions( $actions, TRUE );

	}

	/**
	 * Generates content for a single row of the table
	 *
	 * @since  3.1.0
	 * @access public
	 *
	 * @param object $item The current item
	 */
	public function single_row( $item ) {

		$row_class = '';

		if ( $item[ 'Active' ] === TRUE ) {
			$row_class = 'active';
		} else {
			$row_class = 'inactive';

		}

		echo '<tr class="' . $row_class . '">';
		$this->single_row_columns( $item );
		echo '</tr>';
	}

	/** ************************************************************************
	 * REQUIRED if displaying checkboxes or using bulk actions! The 'cb' column
	 * is given special treatment when columns are processed. It ALWAYS needs to
	 * have it's own method.
	 *
	 * @see WP_List_Table::::single_row_columns()
	 *
	 * @param array $item A singular item (one full row's worth of data)
	 *
	 * @return string Text to be placed inside the column <td> (movie title only)
	 **************************************************************************/
	function column_cb( $item ) {

		return sprintf(
			'<input type="checkbox" name="%1$s[]" value="%2$s" />',
			/*$1%s*/
			$this->_args[ 'singular' ],  //Let's simply repurpose the table's singular label ("plugin")
			/*$2%s*/
			$item[ 'File' ]                //The value of the checkbox should be the record's id
		);
	}

	function column_description( $item ) {

		echo $item[ 'Description' ];

		$actions = array();

		$actions[ 'version' ] = $item[ 'Version' ];

		$actions[ 'author' ] = sprintf( '<a href="%s">%s</a>',
		                                $item[ 'AuthorURI' ],
		                                $item[ 'Author' ]
		);

		echo $this->row_actions( $actions, TRUE );

	}

	/** ************************************************************************
	 * REQUIRED! This method dictates the table's columns and titles. This should
	 * return an array where the key is the column slug (and class) and the value
	 * is the column's title text. If you need a checkbox for bulk actions, refer
	 * to the $columns array below.
	 *
	 * The 'cb' column is treated differently than the rest. If including a checkbox
	 * column in your table you must create a column_cb() method. If you don't need
	 * bulk actions or checkboxes, simply leave the 'cb' entry out of your array.
	 *
	 * @see WP_List_Table::::single_row_columns()
	 * @return array An associative array containing column information: 'slugs'=>'Visible Titles'
	 **************************************************************************/
	function get_columns() {

		$columns = array(
			'cb'          => '<input type="checkbox" />', //Render a checkbox instead of text
			'plugin'      => __( 'Plugin' ),
			'description' => __( 'Description' ),
		);

		return $columns;
	}

	/** ************************************************************************
	 * Optional. If you want one or more columns to be sortable (ASC/DESC toggle),
	 * you will need to register it here. This should return an array where the
	 * key is the column that needs to be sortable, and the value is db column to
	 * sort by. Often, the key and value will be the same, but this is not always
	 * the case (as the value is a column name from the database, not the list table).
	 *
	 * This method merely defines which columns should be sortable and makes them
	 * clickable - it does not handle the actual sorting. You still need to detect
	 * the ORDERBY and ORDER querystring variables within prepare_items() and sort
	 * your data accordingly (usually by modifying your query).
	 *
	 * @return array An associative array containing all the columns that should be sortable:
	 *               'slugs'=>array('data_values',bool)
	 **************************************************************************/
	function get_sortable_columns() {

		$sortable_columns = array(
			//            'plugin' => array('plugin', false),     //true means it's already sorted
			//            'description' => array('desription', false),
		);

		return $sortable_columns;
	}

	/** ************************************************************************
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
	 * @return array An associative array containing all the bulk actions: 'slugs'=>'Visible Titles'
	 **************************************************************************/
	function get_bulk_actions() {

		$actions = array(
			'activate'   => __( 'Activate' ),
			'deactivate' => __( 'Deactivate' ),
			'delete'     => __( 'Delete' )
		);

		return $actions;
	}

	/** ************************************************************************
	 * REQUIRED! This is where you prepare your data for display. This method will
	 * usually be used to query the database, sort and filter the data, and generally
	 * get it ready to be displayed. At a minimum, we should set $this->items and
	 * $this->set_pagination_args(), although the following properties and methods
	 * are frequently interacted with here...
	 *
	 * @global WPDB $wpdb
	 * @uses $this->_column_headers
	 * @uses $this->items
	 * @uses $this->get_columns()
	 * @uses $this->get_sortable_columns()
	 * @uses $this->get_pagenum()
	 * @uses $this->set_pagination_args()
	 **************************************************************************/
	function prepare_items() {

		/**
		 * First, lets decide how many records per page to show
		 */
		$per_page = 5;

		/**
		 * REQUIRED. Now we need to define our column headers. This includes a complete
		 * array of columns to be displayed (slugs & titles), a list of columns
		 * to keep hidden, and a list of columns that are sortable. Each of these
		 * can be defined in another method (as we've done here) before being
		 * used to build the value for our _column_headers property.
		 */
		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();

		/**
		 * REQUIRED. Finally, we build an array to be used by the class for column
		 * headers. The $this->_column_headers property takes an array which contains
		 * 3 other arrays. One for all columns, one for hidden columns, and one
		 * for sortable columns.
		 */
		$this->_column_headers = array( $columns, $hidden, $sortable );

		/**
		 * REQUIRED for pagination. Let's figure out what page the user is currently
		 * looking at. We'll need this later, so you should always include it in
		 * your own package classes.
		 */
		$current_page = $this->get_pagenum();
		$total_items  = count( $this->installed );
		$data         = array_slice( $this->installed, ( ( $current_page - 1 ) * $per_page ), $per_page );

		/**
		 * REQUIRED. Now we can add our *sorted* data to the items property, where
		 * it can be used by the rest of the class.
		 */
		$this->items = $data;

		/**
		 * REQUIRED. We also have to register our pagination options & calculations.
		 */
		$this->set_pagination_args( array(
			                            'total_items' => $total_items,
			                            //WE have to calculate the total number of items
			                            'per_page'    => $per_page,
			                            //WE have to determine how many items to show on a page
			                            'total_pages' => ceil( $total_items / $per_page )
			                            //WE have to calculate the total number of pages
		                            ) );
	}

}