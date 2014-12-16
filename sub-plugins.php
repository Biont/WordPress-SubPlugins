<?php # -*- coding: utf-8 -*-

if ( ! function_exists( 'biont_add_subplugin_support' ) ) {
	function add_subplugin_support( $plugin_folder, $prefix, $args = array() ) {

		foreach (
			array(
				'Biont_SubPlugins_PluginListTable',
				'Models/Biont_SubPlugins_PluginsModel',
				'Views/Biont_SubPlugins_PluginsView',
			) as $file
		) {
			require dirname( __FILE__ ) . '/' . $file . '.php';
		}

		$plugins = new Biont_SubPlugins_PluginsModel( $plugin_folder, $prefix, $args );
		$plugins->register();

	}
}
