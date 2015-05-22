<?php # -*- coding: utf-8 -*-
/**
 * Created by PhpStorm.
 * User: Arbeit
 * Date: 15.08.2014
 * Time: 11:29
 */
class Biont_SubPlugins_PluginsView {

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
	private $prefix = '';

	/**
	 * Setup class variables
	 *
	 * @param $installed_plugins
	 * @param $active_plugins
	 * @param $prefix
	 */
	public function __construct( $installed_plugins, $active_plugins, $prefix ) {

		$this->installed_plugins = $installed_plugins;
		$this->active_plugins    = $active_plugins;
		$this->prefix            = $prefix;
	}

	/**
	 * Display the PluginListTable that manages the subplugins
	 */
	public function show() {

		?>

		<div class="wrap">

			<h2><?php echo esc_html( get_admin_page_title() ); ?></h2>
			<?php do_action( 'biont_before_subplugin_list' ) ?>
			<form id="sub_plugins" method="get">
				<input type="hidden" name="page" value="<?php echo $_REQUEST[ 'page' ] ?>" />
				<?php
				do_action( 'biont_subplugin_form_fields' );
				$table = new Biont_SubPlugins_PluginListTable( $this->installed_plugins, $this->prefix );
				$table->prepare_items();
				$table->display();
				?>
			</form>
			<?php do_action( 'biont_after_subplugin_list' ) ?>
		</div>
	<?php
	}
}
