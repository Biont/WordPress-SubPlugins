<?php # -*- coding: utf-8 -*-

if ( ! function_exists( 'add_subplugin_support' ) ) {
	function add_subplugin_support( $plugin_folder, $prefix, $args = array() ) {

		foreach (
			array(
				'Biont_SubPlugins_PluginListTable',
				'Models/Biont_SubPlugins_PluginsModel',
				'Views/Biont_SubPlugins_PluginsView',
			) as $file
		) {
			require_once dirname( __FILE__ ) . '/' . $file . '.php';
		}

		$plugins = new Biont_SubPlugins_PluginsModel( $plugin_folder, $prefix, $args );
		$plugins->register();

	}
}

if ( ! function_exists( 'biont_get_subplugin_model' ) ) {

	/**
	 * @param $prefix
	 *
	 * @return Biont_SubPlugins_PluginsModel
	 */
	function biont_get_subplugin_model( $prefix ) {

		return Biont_SubPlugins_PluginsModel::get_instance( $prefix );

	}
}
