<?php # -*- coding: utf-8 -*-
/**
 * Created by PhpStorm.
 * User: Arbeit
 * Date: 15.08.2014
 * Time: 13:05
 */
if ( ! function_exists( 'inpsyde_add_subplugin_support' ) ) {
	function add_subplugin_support( $plugin_folder, $prefix, $args = array() ) {



		foreach (
			array(
				'PluginListTable',
				'Models/PluginsModel',
				'Views/PluginsView',
			) as $file
		) {
			require dirname( __FILE__ ) . '/' . $file . '.php';
		}

		$plugins = new \Inpsyde\SubPlugins\Models\PluginsModel( $plugin_folder, $prefix, $args );
		$plugins->register();

	}
}
