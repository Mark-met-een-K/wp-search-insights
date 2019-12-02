<?php
defined( 'ABSPATH' ) or die( "you do not have access to this page!" );
?>
<div id="wpsi-dashboard-widget">
    <div>
        <div class="wpsi-widget-logo"><img width=50px" src="<?php echo wp_search_insights_url?>/assets/images/logo.png" alt="review-logo">
            <span><?php printf("WP Search Insights %s", wp_search_insights_version)?></span>
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
	    $help_url = "https://wpsearchinsights.com";
	    echo sprintf(__("%sHelp%s ", "wp-search-insights"), "<a target='_blank' href='$help_url'>", '<div class="dashicons dashicons-external"></div></a>'); ?>
    </div>
</div>