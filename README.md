WordPress-SubPlugins
====================

A library that allows you to add arbitrary new plugin pages to WordPress.
You can use it to make your plugins more modular and allow users to turn features on and off as they wish

## Example implementation

    //   wp-content/plugins/my-plugin/my-plugin.php      - WordPress Plugin file
    //   wp-content/plugins/my-plugin/lib/sub-plugins    - SubPlugin library
    //   wp-content/plugins/my-plugin/plugins            - Subplugin folder
    

    include_once( plugin_dir_path( __FILE__ ) . 'lib/sub-plugins/sub-plugins.php' );
    
    $sub_plugin_folder = plugin_dir_path( __FILE__ ) . 'plugins';
    
    $slug = 'my-plugins';

    $plugin_args = array(
    	'menu_location' => 'index.php',                     // Where to show menu item 
	    'page_title'    => __( 'Modules of My Plugin', 'my-textdomain' ),
	    'menu_title'    => __( 'RBP-Plugins', 'my-textdomain' ),
    );
    // Will show up under "Dashboard". See https://codex.wordpress.org/Function_Reference/add_submenu_page 
    // for more information on where to put admin menu pages.
    add_subplugin_support( $sub_plugin_folder, $slug, $plugin_args );

