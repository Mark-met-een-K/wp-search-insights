<?php
defined( 'ABSPATH' ) or die( "you do not have access to this page!" );
?>
<div class="inside">
    <div class="wpsi-date-container wpsi-btn-no-results wpsi-header-right wpsi-top-searches-btn">
    </div>
    <div id="wpsi-dashboard-widget" class="wpsi-dashboard-widget-grid">
        <div>
            <h3><?php _e("Top searches", "wp-search-insights")?></h3>
            <ul>{top_searches}</ul>
        </div>
    </div>
</div>