<?php
defined( 'ABSPATH' ) or die( "you do not have access to this page!" );
?>
<div class="inside">
    <div class="wpsi-date-btn wpsi-btn-no-results wpsi-header-right">
        <label class="wpsi-select-date-range-all-searches">
            <select name="wpsi_select_date_range_all_searches" class="wpsi_select_date_range">
                <option value="activate_plugins"><?php _e("Placeholder", "wp-search-insights") ?></option>
            </select>
        </label>
    </div>
    <div id="wpsi-dashboard-widget" class="wpsi-dashboard-widget-grid">
        <div>
            <h3><?php _e("Top searches", "wp-search-insights")?></h3>
            <ul>{top_searches}</ul>
        </div>
    </div>
</div>