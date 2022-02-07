<?php
defined( 'ABSPATH' ) or die( "you do not have access to this page!" );
?>
<div class="inside">
<div id="wpsi-dashboard-widget">
    <div>
        <div class="wpsi-widget-logo"><img width=35px" height="35px" src="<?php echo wpsi_url?>/assets/images/noname_logo.png" alt="review-logo">
            <span><?php printf("WP Search Insights %s", wpsi_version)?></span>
        </div>

        <h3><?php _e("Popular searches without results", "wp-search-insights")?></h3>
        <ul>
            {popular_searches}
        </ul>

        <h3><?php _e("Top searches", "wp-search-insights")?></h3>
        <ul>{top_searches}</ul>
    </div>
    <div id="wpsi-dashboard-widget-footer">
        <?php
            $admin_url = admin_url("tools.php?page=wpsi-settings-page");
            echo sprintf(__("%sDashboard%s ", "wp-search-insights"), "<a href='$admin_url'>", '<div class="dashicons dashicons-external"></div></a>'); ?> |
	    <?php
	    $help_url = "https://wpsi.io";
	    echo sprintf(__("%sHelp%s ", "wp-search-insights"), "<a target='_blank' href='$help_url'>", '<div class="dashicons dashicons-external"></div></a>'); ?>
    </div>
</div></div>