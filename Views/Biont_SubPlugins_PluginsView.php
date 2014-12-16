<?php # -*- coding: utf-8 -*-
/**
 * Created by PhpStorm.
 * User: Arbeit
 * Date: 15.08.2014
 * Time: 11:29
 */


class Biont_SubPlugins_PluginsView
{

    private $installed_plugins = array();

    private $active_plugins = array();

    public function __construct($installed_plugins, $active_plugins)
    {

        $this->installed_plugins = $installed_plugins;
        $this->active_plugins = $active_plugins;
    }

    public function show()
    {

        ?>

        <div class="wrap">

            <h2><?php echo esc_html(get_admin_page_title()); ?></h2>

            <form id="sub_plugins" method="get">
                <input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>"/>
                <?php
                $table = new Biont_SubPlugins_PluginListTable($this->installed_plugins, $this->active_plugins);
                $table->prepare_items();
                $table->display();
                ?>
            </form>
        </div>


    <?php
    }
}
