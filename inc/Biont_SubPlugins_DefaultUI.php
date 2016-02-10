<?php

/**
 * Class Biont_SubPlugins_DefaultUI
 *
 * Handles the default, WP-Core-like UI
 */
class Biont_SubPlugins_DefaultUI {

	/**
	 * @var Biont_SubPlugins
	 */
	private $plugins;

	public function __construct( Biont_SubPlugins $plugins ) {

		$this->plugins = $plugins;
	}

	public function init() {

		add_action( 'admin_menu', array( $this, 'register_menu_pages' ), 0 );

		add_action( $this->plugins->get_prefix() . '_bulk_activate', array( $this, 'bulk_activate' ) );
		add_action( $this->plugins->get_prefix() . '_bulk_deactivate', array( $this, 'bulk_deactivate' ) );

		if ( isset( $_GET[ $this->plugins->get_prefix() . '_plugins_changed' ] ) ) {
			$this->change_plugin_status();
		}

		/**
		 * Handle bulk actions.
		 * Since we need to do a redirect to get rid of request parameters,
		 * we cannot do that directly within the ListTable. Output has already started there
		 */
		if ( isset( $_GET[ 'page' ] )
		     && isset( $_GET[ 'plugin' ] )
		     && $_GET[ 'page' ] === $this->plugins->get_prefix() . '_plugins'
		) {
			if ( $this->verify_nonce() ) {
				switch ( $this->current_action() ) {
					case'delete':
						break;
					case 'activate':
						$this->plugins->bulk_activate( $_GET[ 'plugin' ], $this->get_menu_location() );
						break;
					case 'deactivate':
						$this->plugins->bulk_deactivate( $_GET[ 'plugin' ], $this->get_menu_location() );
						break;
				}
			} else {
				die( 'Invalid request' );
			}
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

				$this->plugins->activate_plugin( $_GET[ 'plugin' ], FALSE );

			}

			if ( $_GET[ 'action' ] == 'deactivate' ) {

				$this->plugins->deactivate_plugin( $_GET[ 'plugin' ], FALSE );

			}
		}
		$this->plugins->load_plugins();
		$this->plugins->redirect( $this->get_menu_location() );
	}

	private function verify_nonce() {

		return ( isset( $_GET[ 'nonce' ] ) && wp_verify_nonce( $_GET[ 'nonce' ], $this->get_nonce_action() ) );
	}

	private function get_nonce_action() {

		return $this->plugins->get_prefix() . '_manage_plugins';
	}

	/**
	 * Returns the location of the menu entry
	 *
	 * @return string|void
	 */
	public function get_menu_location() {

		return admin_url( $this->plugins->get_arg( 'menu_location' ) . '?page=' . $this->plugins->get_prefix() . '_plugins' );
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
	 * Add a submenu page at the selected location
	 */
	public function register_menu_pages() {

		add_submenu_page(
			$this->plugins->get_arg( 'menu_location' ),
			$this->plugins->get_arg( 'page_title' ),
			$this->plugins->get_arg( 'menu_title' ),
			'manage_options',
			$this->plugins->get_prefix() . '_plugins',
			array( $this, 'display_plugin_page' )
		);
	}

	/**
	 * Render the settings page for this plugin.
	 *
	 * First handle plugin de/activation and then spawn the view
	 *
	 * @since    1.0.0
	 */
	public function display_plugin_page() {

		do_action( $this->plugins->get_prefix() . '_plugin_activation' );
		?>

		<div class="wrap">

			<h2><?php echo esc_html( get_admin_page_title() ); ?></h2>
			<?php do_action( 'biont_before_subplugin_list' ) ?>
			<form id="sub_plugins" method="get">
				<input type="hidden" name="page" value="<?php echo $_REQUEST[ 'page' ] ?>" />
				<?php
				do_action( 'biont_subplugin_form_fields' );
				$table = new Biont_SubPlugins_PluginListTable(
					$this->plugins->get_installed_plugins(),
					$this->plugins->get_prefix(),
					$this->create_nonce()
				);
				$table->prepare_items();
				$table->display();
				?>
			</form>
			<?php do_action( 'biont_after_subplugin_list' ) ?>
		</div>
		<?php
	}

	public function create_nonce() {

		return wp_create_nonce( $this->get_nonce_action() );
	}

}