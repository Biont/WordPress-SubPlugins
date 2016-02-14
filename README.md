WordPress-SubPlugins
====================

A library that allows you to add arbitrary new plugin pages to WordPress.
You can use it to make your plugins more modular and allow users to turn features on and off as they wish

## Example implementation

This assumes the following folder structure:

       wp-content/plugins/my-plugin/my-plugin.php      - WordPress Plugin file
       wp-content/plugins/my-plugin/lib/sub-plugins    - SubPlugin library
       wp-content/plugins/my-plugin/plugins            - Subplugin folder

### wp-content/plugins/my-plugin/my-plugin.php

```php
    /**
     * Plugin Name:    My Plugin
     * Description:    A demo plugin that has a cool sub-plugin feature!
     * Version:        1.0
     * Author:         Biont
     * Licence:        GPLv3
     * Text Domain:    my-textdomain
     * Domain Path:    /languages
     */    

    include_once( plugin_dir_path( __FILE__ ) . 'lib/sub-plugins/sub-plugins.php' );
    
    $sub_plugin_folder = plugin_dir_path( __FILE__ ) . 'plugins';
    
    // This is used by all related options and for the sub-plugin files as well
    $prefix = 'biont';

    $plugin_args = array(
    	'menu_location' => 'index.php',                     // Where to show menu item 
	    'page_title'    => __( 'Modules of My Plugin', 'my-textdomain' ),
	    'menu_title'    => __( 'RBP-Plugins', 'my-textdomain' ),
    );
    
    // Will show up under "Dashboard". See https://codex.wordpress.org/Function_Reference/add_submenu_page 
    // for more information on where to put admin menu pages.
    add_subplugin_support( $sub_plugin_folder, $prefix, $plugin_args );
```

### wp-content/plugins/my-plugin/plugins/hello-world/hello-world.php

Sub-Plugins work just like regular WordPress plugins, with one exception:
I have made the deliberate change to include the (strtoupper'd) plugin prefix in the "Plugin-Name" 
attribute of the Plugin header.

This makes sub-plugins incompatible with WordPress (in case a user manually uploads it to the wrong folder)
and also makes individual instances of the sub-plugin feature incompatible with each other to avoid confusion


```php
    /**
     * BIONT-Plugin Name:    Hello World
     * Description:          A cool sub-plugin!
     * Version:              1.0
     * Author:               Biont
     * Licence:              GPLv3
     * Text Domain:          my-textdomain
     * Domain Path:          /languages
     */   
     
     add_action('init', function(){
         echo 'Hello World!';
     });
```
