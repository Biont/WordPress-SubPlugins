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
		if ( did_action( 'plugins_loaded' ) ) {
			$plugins->register();
		} else {
			add_action( 'plugins_loaded', array( $plugins, 'register' ) );
		}

		return $plugins;
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

if ( ! function_exists( 'biont_get_plugin_data' ) ) {

	function biont_get_plugin_data( $prefix, $plugin_file, $markup = TRUE, $translate = TRUE ) {

		if ( ! function_exists( '_get_plugin_data_markup_translate' ) ) {
			include( ABSPATH . 'wp-admin/includes/plugin.php' );
		}
		$plugin_data = wp_cache_get( $prefix . $plugin_file, $prefix . '_subplugins' );
		if ( $plugin_data === FALSE ) {
			$default_headers = array(
				'Name'        => strtoupper( $prefix ) . '-Plugin Name',
				'PluginURI'   => 'Plugin URI',
				'Version'     => 'Version',
				'Description' => 'Description',
				'Author'      => 'Author',
				'AuthorURI'   => 'Author URI',
				'TextDomain'  => 'Text Domain',
				'DomainPath'  => 'Domain Path',
				'Network'     => 'Network',
				// Site Wide Only is deprecated in favor of Network.
				'_sitewide'   => 'Site Wide Only',
			);

			$default_headers = apply_filters( $prefix . '_plugin_data_headers', $default_headers );

			$plugin_data = get_file_data( $plugin_file, $default_headers, 'plugin' );

			// Site Wide Only is the old header for Network
			if ( ! $plugin_data[ 'Network' ] && $plugin_data[ '_sitewide' ] ) {
				_deprecated_argument( __FUNCTION__, '3.0',
				                      sprintf( __( 'The <code>%1$s</code> plugin header is deprecated. Use <code>%2$s</code> instead.' ),
				                               'Site Wide Only: true', 'Network: true' ) );
				$plugin_data[ 'Network' ] = $plugin_data[ '_sitewide' ];
			}
			$plugin_data[ 'Network' ] = ( 'true' == strtolower( $plugin_data[ 'Network' ] ) );
			unset( $plugin_data[ '_sitewide' ] );

			if ( $markup || $translate ) {
				$plugin_data = _get_plugin_data_markup_translate( $plugin_file, $plugin_data, $markup, $translate );
			} else {
				$plugin_data[ 'Title' ]      = $plugin_data[ 'Name' ];
				$plugin_data[ 'AuthorName' ] = $plugin_data[ 'Author' ];
			}

			wp_cache_set( $prefix . $plugin_file, $plugin_data, $prefix . '_subplugins' );
		}

		return $plugin_data;

	}
}
