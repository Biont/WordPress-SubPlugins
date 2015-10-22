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
	private $queues = array(
		'activate'   => array(),
		'deactivate' => array()
	);

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

		$defaults   = array(
			'menu_location'    => 'options-general.php',
			'page_title'       => __( 'Sub-Plugins' ),
			'menu_title'       => __( 'Plugins' ),
			'load_textdomains' => FALSE,
		);
		$this->args = wp_parse_args( $args, $defaults );

		$this->prefix         = $prefix;
		$this->plugin_folder  = $plugin_folder;
		$this->menu_location  = $this->args[ 'menu_location' ];
		$this->page_title     = $this->args[ 'page_title' ];
		$this->menu_title     = $this->args[ 'menu_title' ];
		$this->active_plugins = get_option( $this->prefix . '_active_plugins', array() );

		self::$instances[ $prefix ] = $this;

	}

	/**
	 * Add hooks for adding settings and menus and then load the active plugins
	 */
	public function register() {

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
				if ( $this->verify_nonce() ) {
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
				} else {
					die( 'Invalid request' );
				}
			}
		}
		$this->load_plugins();
		$this->handle_queues();

	}

	/**
	 * If plugins were activated, call their activation hooks
	 */
	private function handle_queues() {

		if ( ! empty( $this->queues ) ) {
			foreach ( $this->queues as $type => $queue ) {
				foreach ( $queue as $index => $plugin ) {
					unset( $this->queues[ $type ][ $index ] );
					$filename = $this->get_plugin_file_path( $plugin );
					if ( $this->plugin_exists( $filename ) ) {
						/**
						 * When this runs, the plugin was already removed from the active plugins. So manually
						 * load it one last time
						 */
						if ( $type === 'deactivate' ) {
							include_once( $filename );
						}
						$plugin_data = biont_get_plugin_data( $this->prefix, $filename );

						/**
						 * Build a hook name that is compatible with register_activation_hook()
						 * from within the main subplugin file :)
						 */
						$hookname = $type . '_' . plugin_basename( $filename );
						do_action( $hookname, $plugin_data );

						/**
						 * If the plugin is deactivated right from its activation hook, don't call the following action
						 */
						if ( $this->is_plugin_active( $plugin ) ) {
							/**
							 * Build a general hookname, for example
							 *
							 * 'xyz_activate_plugin'
							 */
							$hookname = $this->prefix . '_' . $type . '_plugin';
							do_action(
								$hookname,
								$plugin,
								$plugin_data,
								$filename,
								$this
							);
						}

					}
				}
			}

		}
	}

	public function get_plugin_file_path( $plugin ) {

		return $this->plugin_folder . '/' . basename( $plugin, '.php' ) . '/' . $plugin;

	}

	/**
	 * Validates a plugin file path
	 *
	 * @param $filename
	 *
	 * @return bool
	 */
	public function plugin_exists( $filename ) {

		return ( file_exists( $filename ) && ! is_dir( $filename ) );
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
	 * @return array
	 */
	public function get_installed_plugins() {

		if ( $this->installed_plugins == NULL ) {
			foreach ( glob( $this->plugin_folder . '/*', GLOB_ONLYDIR ) as $plugin_folder ) {
				$filename = $plugin_folder . '/' . basename( $plugin_folder ) . '.php';
				if ( $this->plugin_exists( $filename ) ) {

					$markup = apply_filters( $this->prefix . '_plugin_data_markup', TRUE );

					$data          = biont_get_plugin_data( $this->prefix, $filename, $markup );
					$plugin_handle = basename( $filename );
					if ( ! empty( $data[ 'Name' ] ) ) {
						$data[ 'File' ] = $filename;

						if ( $this->is_plugin_active( $plugin_handle ) ) {
							$data[ 'Active' ] = TRUE;
						} else {
							$data[ 'Active' ] = FALSE;
						}
						$this->installed_plugins[ $plugin_handle ] = $data;
					}

				}

			}
			/**
			 * This filter can currently be used for custom sorting using the Plugin Headers,
			 * or for hiding specific plugins on the UI
			 *
			 * *Adding* Plugins would not be functional as the library currently checks for the existence of the plugin file
			 *
			 * TODO: Think of ways we can extend this lib to support external plugin repositories as well
			 */
			$this->installed_plugins = apply_filters( $this->prefix . '_get_installed_plugins',
			                                          $this->installed_plugins );
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

		do_action( $this->prefix . '_pre_load_subplugins', $this );
		$invalid = array();
		foreach ( $this->active_plugins as $plugin => $data ) {

			if ( ! is_string( $plugin ) ) {
				$invalid[] = $plugin;
				continue;
			}

			$filename = $this->get_plugin_file_path( $plugin );

			if ( ! $this->plugin_exists( $filename ) ) {
				$invalid[] = $plugin;
				continue;
			}

			/**
			 * If the plugin file was changed, reactivate it automatically
			 */
			if ( isset( $data[ 'Timestamp' ] ) && $data[ 'Timestamp' ] !== filemtime( $filename ) ) {
				$this->queues[ 'activate' ][] = $plugin;
			}

			if ( $this->args[ 'load_textdomains' ] ) {
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
			}
			include_once( $filename );
		}
		/**
		 * Clean up in case there's been some invalid plugin data
		 */
		if ( ! empty( $invalid ) ) {
			foreach ( $invalid as $invalid_plugin ) {
				unset( $this->active_plugins[ $invalid_plugin ] );
			}
			update_option( $this->prefix . '_active_plugins', $this->active_plugins );
		}
	}

	/**
	 * Callback for register_setting
	 *
	 * @return array
	 */
	public function change_plugin_status() {

		if ( ! $this->verify_nonce() ) {
			die( 'Invalid Request' );
		}
		//Handle Plugin actions:
		if ( isset( $_GET[ 'action' ] ) ) {

			if ( $_GET[ 'action' ] == 'activate' ) {

				$this->activate_plugin( $_GET[ 'plugin' ] );

			}

			if ( $_GET[ 'action' ] == 'deactivate' ) {

				$this->deactivate_plugin( $_GET[ 'plugin' ] );

			}
		}
		$this->load_plugins();
		$this->redirect();
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
		$this->redirect();
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
		$this->redirect();
	}

	/**
	 * Adds a plugin to the active plugins array and to the activation queue
	 *
	 * @param $plugin
	 */
	public function activate_plugin( $plugin, $redirect = FALSE ) {

		if ( ! $this->is_plugin_active( $plugin ) ) {

			$filename = $this->get_plugin_file_path( $plugin );
			if ( ! $this->plugin_exists( $filename ) ) {
				return;
			}
			$data                            = biont_get_plugin_data( $this->prefix, $filename );
			$data[ 'Timestamp' ]             = filemtime( $filename );
			$this->active_plugins[ $plugin ] = $data;
			$this->queues[ 'activate' ][]    = $plugin;
			update_option( $this->prefix . '_active_plugins', $this->active_plugins );

		}

		if ( $redirect !== FALSE ) {
			$this->redirect( $redirect );
		}
	}

	/**
	 * Deactivate a single plugin
	 *
	 * @param $plugin
	 */
	public function deactivate_plugin( $plugin, $redirect = FALSE ) {

		if ( $this->is_plugin_active( $plugin ) ) {
			unset( $this->active_plugins[ $plugin ] );
			update_option( $this->prefix . '_active_plugins', $this->active_plugins );
			$this->queues[ 'deactivate' ][] = $plugin;
		}
		if ( $redirect !== FALSE ) {
			$this->redirect( $redirect );
		}
	}

	/**
	 * Redirect to the desired location
	 *
	 * @param  $location
	 */
	private function redirect( $location = FALSE ) {

		$this->handle_queues();
		if ( isset( $_GET[ 'redirect' ] ) ) {
			$target = $_GET[ 'redirect' ];
		} elseif ( is_string( $location ) ) {
			$target = $location;
		} else {
			$target = $this->get_menu_location();
		}

		wp_redirect( $target );

	}

	/**
	 * Delete all options used by this instance
	 */
	public function clear_data() {

		delete_option( $this->prefix . '_active_plugins' );
	}

	/**
	 * Is a specific plugin currently active?
	 *
	 * @param $plugin
	 *
	 * @return bool
	 */
	public function is_plugin_active( $plugin ) {

		if ( is_array( $plugin ) && isset( $plugin[ 'File' ] ) ) {
			$plugin = basename( $plugin[ 'File' ] );
		}

		return isset( $this->active_plugins[ $plugin ] );
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
		$view = new Biont_SubPlugins_PluginsView(
			$installed_plugins,
			$this->active_plugins,
			$this->prefix,
			$this->create_nonce()
		);
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

	private function verify_nonce() {

		return ( isset( $_GET[ 'nonce' ] ) && wp_verify_nonce( $_GET[ 'nonce' ], $this->get_nonce_action() ) );
	}

	private function get_nonce_action() {

		return $this->prefix . '_manage_plugins';
	}

	public function create_nonce() {

		return wp_create_nonce( $this->get_nonce_action() );
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
