<?php # -*- coding: utf-8 -*-

/**
 * Class PluginsModel
 *
 */
class Biont_SubPlugins_PluginsModel {

	/**
	 * path to the plugins folder
	 *
	 * @var string
	 */
	private $plugin_folder = '';

	/**
	 * Prefix for this instance to use
	 *
	 * @var string
	 */
	private $prefix = '';

	/**
	 * All plugin files currently in the plugins folder
	 *
	 * @var array
	 */
	private $installed_plugins = array();

	/**
	 * All currently active plugins
	 *
	 * @var array
	 */
	private $active_plugins = array();

	/**
	 * URL segment of the plugin page menu
	 *
	 * @var string
	 */
	private $menu_location;

	/**
	 * Temporary storage to defer plugin activation
	 *
	 * @var array
	 */
	private $waiting_for_activation = array();

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

		$this->active_plugins = get_option( $this->prefix . '_active_plugins', array() );

		if ( is_admin() ) {

			add_action( 'admin_init', array( $this, 'register_settings' ) );
			add_action( 'admin_menu', array( $this, 'register_menu_pages' ), 0 );

			add_action( $this->prefix . '_bulk_activate', array( $this, 'bulk_activate' ) );
			add_action( $this->prefix . '_bulk_deactivate', array( $this, 'bulk_deactivate' ) );

			if ( isset( $_GET[ $this->prefix . '_plugins_changed' ] ) ) {
				$this->change_plugin_status();
			}

			/**
			 * Handle bulk actions.
			 * Since we need to do a redirect to get rid of request parameters,
			 * we cannot do that directly within the ListTable. Output has already started there
			 */
			if ( isset( $_GET[ 'page' ] )
			     && isset( $_GET[ 'plugin' ] )
			     && $_GET[ 'page' ] === $this->prefix . '_plugins'
			) {

				switch ( $this->current_action() ) {
					case'delete':
						break;
					case 'activate':
						$this->bulk_activate( $_GET[ 'plugin' ] );
						break;
					case 'deactivate':
						$this->bulk_deactivate( $_GET[ 'plugin' ] );
						break;
				}
			}
		}
		$this->load_plugins();
		$this->handle_activation_queue();

	}

	/**
	 * If plugins were activated, call their activation hooks
	 */
	private function handle_activation_queue() {

		if ( ! empty( $this->waiting_for_activation ) ) {
			foreach ( $this->waiting_for_activation as $plugin ) {
				$filename = $this->plugin_folder . '/' . basename(
						$plugin, '.php'
					) . '/' . $plugin;
				do_action( 'activate_' . plugin_basename( $filename ) );

			}

		}
	}

	/**
	 * Find out what plugin action we're currently doing
	 *
	 * @return bool
	 */
	private function current_action() {

		if ( isset( $_REQUEST[ 'filter_action' ] ) && ! empty( $_REQUEST[ 'filter_action' ] ) ) {
			return FALSE;
		}

		if ( isset( $_REQUEST[ 'action' ] ) && - 1 != $_REQUEST[ 'action' ] ) {
			return $_REQUEST[ 'action' ];
		}

		if ( isset( $_REQUEST[ 'action2' ] ) && - 1 != $_REQUEST[ 'action2' ] ) {
			return $_REQUEST[ 'action2' ];
		}

		return FALSE;
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
					$data = biont_get_plugin_data( $this->prefix, $filename );

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
	 * Returns all active plugins
	 *
	 * @return array
	 */
	public function get_active_plugins() {

		return $this->active_plugins;
	}

	/**
	 * Find all active plugins and load them
	 */
	public function load_plugins() {

		do_action( 'biont_pre_load_subplugins', $this );

		foreach ( $this->active_plugins as $plugin ) {
			$filename = $this->plugin_folder . '/' . basename(
					$plugin, '.php'
				) . '/' . $plugin;

			/**
			 * Try to load language files for this plugin
			 */
			$data = get_file_data( $filename, array(
				'TextDomain' => 'Text Domain',
				'DomainPath' => 'Domain Path',
			) );

			if ( isset( $data[ 'TextDomain' ], $data[ 'DomainPath' ] ) ) {
				$domain = $data[ 'TextDomain' ];
				$path   = dirname( $filename ) . $data[ 'DomainPath' ];
				$locale = get_locale();

				$mofile = $domain . '-' . $locale . '.mo';
				load_textdomain( $domain, $path . '/' . $mofile );
			}

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

			if ( $_GET[ 'action' ] == 'activate' ) {

				$this->activate_plugin( $_GET[ 'plugin' ] );

			}

			if ( $_GET[ 'action' ] == 'deactivate' ) {

				$this->deactivate_plugin( $_GET[ 'plugin' ] );

			}
		}
	}

	/**
	 * Adds a plugin to the active plugins array and to the activation queue
	 *
	 * @param $plugin
	 */
	public function activate_plugin( $plugin ) {

		if ( ! in_array( $plugin, $this->active_plugins ) ) {

			$this->active_plugins[ ]         = $plugin;
			$this->waiting_for_activation[ ] = $plugin;
			update_option( $this->prefix . '_active_plugins', $this->active_plugins );

		}
	}

	/**
	 * Activate a bunch of plugins in bulk
	 *
	 * @param $plugins
	 */
	public function bulk_activate( $plugins ) {

		if ( ! empty( $plugins ) ) {
			if ( is_string( $plugins ) ) {
				$plugins = array( $plugins );
			}
			foreach ( $plugins as $plugin ) {
				$this->activate_plugin( $plugin );
			}
		}
		wp_redirect( $this->get_menu_location() );
	}

	/**
	 * Deactivate a bunch of plugins in bulk
	 *
	 * @param $plugins
	 */
	public function bulk_deactivate( $plugins ) {

		if ( ! empty( $plugins ) ) {
			if ( is_string( $plugins ) ) {
				$plugins = array( $plugins );
			}
			foreach ( $plugins as $plugin ) {
				$this->deactivate_plugin( $plugin );
			}
		}
		wp_redirect( $this->get_menu_location() );
		exit;
	}

	/**
	 * Deactivate a single plugin
	 *
	 * @param $plugin
	 */
	public function deactivate_plugin( $plugin ) {

		if ( FALSE !== $key = array_search( $plugin, $this->active_plugins ) ) {

			$filename = $this->plugin_folder . '/' . basename(
					$plugin, '.php'
				) . '/' . $plugin;

			unset( $this->active_plugins[ $key ] );
			update_option( $this->prefix . '_active_plugins', $this->active_plugins );
			do_action( 'deactivate_' . plugin_basename( $filename ) );
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

	/**
	 * Returns the location of the menu entry
	 *
	 * @return string|void
	 */
	public function get_menu_location() {

		return admin_url( $this->menu_location . '?page=' . $this->prefix . '_plugins' );
	}

	/**
	 * Returns the SubPlugin instance with the given prefix
	 *
	 * @param $prefix
	 *
	 * @return null
	 */
	public static function get_instance( $prefix ) {

		if ( isset( self::$instances[ $prefix ] ) ) {
			return self::$instances[ $prefix ];
		}

		return NULL;
	}

}
