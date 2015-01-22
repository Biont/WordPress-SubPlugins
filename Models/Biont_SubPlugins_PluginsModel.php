<?php # -*- coding: utf-8 -*-

/**
 * Class PluginsModel
 *
 */
class Biont_SubPlugins_PluginsModel {

	/**
	 * @var string
	 */
	private $plugin_folder = '';

	/**
	 * @var string
	 */
	private $prefix = '';

	/**
	 * @var array
	 */
	private $installed_plugins = array();

	/**
	 * @var array
	 */
	private $active_plugins = array();

	/**
	 * @var string
	 */
	private $menu_location;

	/**
	 * Store all instances of this Model so that it can be accessed later on
	 *
	 * @var array
	 */
	static $instances = array();

	/**
	 * Parse the arguments and set the class variables
	 *
	 * @param       $plugin_folder
	 * @param       $prefix
	 * @param array $args
	 */
	public function __construct( $plugin_folder, $prefix, $args = array() ) {

		$defaults = array(
			'menu_location' => 'options-general.php',
			'page_title'    => __( 'Sub-Plugins' ),
			'menu_title'    => __( 'Plugins' ),
		);
		$args     = wp_parse_args( $args, $defaults );

		$this->prefix        = $prefix;
		$this->plugin_folder = $plugin_folder;
		$this->menu_location = $args[ 'menu_location' ];
		$this->page_title    = $args[ 'page_title' ];
		$this->menu_title    = $args[ 'menu_title' ];

		self::$instances[ $prefix ] = $this;
	}

	/**
	 * Add hooks for adding settings and menus and then load the active plugins
	 */
	public function register() {

		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_menu', array( $this, 'register_menu_pages' ), 0 );

		$this->active_plugins = get_option( $this->prefix . '_active_plugins', array() );

		if ( isset( $_GET[ $this->prefix . '_plugins_changed' ] ) ) {
			$this->change_plugin_status();
		}
		$this->load_plugins();

	}

	/**
	 * Register a setting where our active plugins are stored
	 */
	public function register_settings() {

		register_setting( $this->prefix . '-plugins', $this->prefix . '_active_plugins' );
	}

	/**
	 * Add a submenu page at the selected location
	 */
	public function register_menu_pages() {

		add_submenu_page(
			$this->menu_location,
			$this->page_title,
			$this->menu_title,
			'manage_options',
			$this->prefix . '_plugins',
			array( $this, 'display_plugin_page' )
		);
		$this->menu_location = $this->menu_location.'?page='.$this->prefix . '_plugins';

	}

	/**
	 * Returns an array  of all plugins in the plugins folder
	 *
	 * @TODO: Check for existence of file_data?
	 * @return type
	 */
	public function get_installed_plugins() {

		if ( $this->installed_plugins == NULL ) {
			foreach ( glob( $this->plugin_folder . '/*', GLOB_ONLYDIR ) as $plugin_folder ) {
				if ( file_exists( $filename = $plugin_folder . '/' . basename( $plugin_folder ) . '.php' ) ) {
					$data = get_file_data(
						$filename, array(
							         'Name'        => strtoupper( $this->prefix ) . '-Plugin Name',
							         'PluginURI'   => 'Plugin URI',
							         'Description' => 'Description',
							         'Author'      => 'Author',
							         'AuthorURI'   => 'Author URI',
							         'Version'     => 'Version',
							         'Template'    => 'Template',
							         'Status'      => 'Status',
							         'Tags'        => 'Tags',
							         'TextDomain'  => 'Text Domain',
							         'DomainPath'  => 'Domain Path',
						         )
					);

					if ( ! empty( $data[ 'Name' ] ) ) {
						$data[ 'File' ] = basename( $filename );

						if ( in_array( $data[ 'File' ], $this->active_plugins ) ) {
							$data[ 'Active' ] = TRUE;
						} else {
							$data[ 'Active' ] = FALSE;
						}

						$this->installed_plugins[ ] = $data;
					}

				}

			}
		}

		return $this->installed_plugins;
	}

	/**
	 * Find all active plugins and load them
	 */
	public function load_plugins() {

		foreach ( $this->active_plugins as $plugin ) {
			$filename = $this->plugin_folder . '/' . basename(
					$plugin, '.php'
				) . '/' . $plugin;
			include_once( $filename );
		}
	}

	/**
	 * Callback for register_setting
	 *
	 * @return array
	 */
	public function change_plugin_status() {

		//Handle Plugin actions:
		if ( isset( $_GET[ 'action' ] ) ) {
			$filename = $this->plugin_folder . '/' . basename(
					$_GET[ 'plugin' ], '.php'
				) . '/' . $_GET[ 'plugin' ];

			if ( $_GET[ 'action' ] == 'activate' ) {

				if ( ! in_array( $_GET[ 'plugin' ], $this->active_plugins ) ) {
					$this->active_plugins[ ] = $_GET[ 'plugin' ];
					update_option( $this->prefix . '_active_plugins', $this->active_plugins );
					do_action( 'activate_' . plugin_basename( $filename ) );

					// Load the plugin manually.
					// It might be a better idea to force a refresh
					// with JS once the page has fully loaded,
					// so that the plugin can start as early as possible
					include_once( $filename );

				}

			}

			if ( $_GET[ 'action' ] == 'deactivate' ) {

				if ( FALSE !== $key = array_search( $_GET[ 'plugin' ], $this->active_plugins ) ) {
					unset( $this->active_plugins[ $key ] );
					update_option( $this->prefix . '_active_plugins', $this->active_plugins );
					do_action( 'deactivate_' . plugin_basename( $filename ) );
				}
			}
		}
	}

	/**
	 * Render the settings page for this plugin.
	 *
	 * First handle plugin de/activation and then spawn the view
	 *
	 * @since    1.0.0
	 */
	public function display_plugin_page() {

		$installed_plugins = $this->get_installed_plugins();

		do_action( $this->prefix . '_plugin_activation' );

		// Add the actual plugin page
		$view = new Biont_SubPlugins_PluginsView( $installed_plugins, $this->active_plugins, $this->prefix );
		$view->show();
	}

	public function get_menu_location() {

		return admin_url( $this->menu_location );
	}

	public static function get_instance( $prefix ) {

		if ( isset( self::$instances[ $prefix ] ) ) {
			return self::$instances[ $prefix ];
		}

		return NULL;
	}

}
