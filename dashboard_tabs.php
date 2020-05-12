<?php

defined( 'ABSPATH' ) or die( "you do not have access to this page!" );


/**
 * Content of dashboard block
 */

function wpsi_tab_content_dashboard(){
	if (!is_user_logged_in()) return;

	?>
	<button class="button" id="wpsi-delete-selected">
		<?php _e("Delete selected terms", "wp-search-insights") ?>
	</button>
	<?php
	//get html of block
	$grid_items = WPSI::$admin->grid_items;
	$container  = WPSI::$admin->get_template( 'grid-container.php',
		wpsi_path . '/grid' );
	$element    = WPSI::$admin->get_template( 'grid-element.php',
		wpsi_path . '/grid' );
	$output     = '';
	foreach ( $grid_items as $index => $grid_item ) {
		$output .= str_replace( array(
			'{class}',
			'{content}',
			'{title}',
			'{index}',
			'{type}'
		), array(
			$grid_item['class'],
			$grid_item['content'],
			$grid_item['title'],
			$index,
			$grid_item['type']
		), $element );
	}
	echo str_replace( '{content}', $output, $container );
}

add_action( "wpsi_tab_content_dashboard", 'wpsi_tab_content_dashboard' );

/**
 * Settings tab on WPSI dashboard
 */

function wpsi_tab_content_settings(){
    if (!is_user_logged_in()) return;

	?>
	<form action="options.php" method="post">
		<div class="settings-section">
			<span class="settings-title"><h3><?php _e("General settings" , "wp-search-insights");?></h3></span>
			<div>
				<?php
				settings_fields('wpsi-settings-tab');
				do_settings_sections('wpsi-settings');
				?> <div id="clear-searches-btn-border"></div> <?php
				WPSI::$admin->save_button();
				?>
			</div>
		</div>

		<div class="settings-section">
			<div id="filter-inner">
                <span class="settings-title filter-title"><h3> <?php _e("Search filter" , "wp-search-insights");?> </h3>
                    <?php
                    WPSI::$help->get_help_tip(__("Exclude words, sentences or URL's. Separate each search term with whitespace or a comma", "wp-search-insights"));
                    ?>
                 </span>
			</div>
			<div class="filter-save">
				<?php WPSI::$admin->save_button(); ?>
			</div>
		</div>

	</form>
<?php
}
add_action( "wpsi_tab_content_settings", 'wpsi_tab_content_settings');



function wpsi_tab_options(){
    ?>
    <div class="documentation-pro">
        <div class="documentation">
            <a href="https://wpsearchinsights.com/#faq"><?php _e("Documentation", "wp-search-insights");?></a>
        </div>
        <div id="wpsi-toggle-options">
            <div id="wpsi-toggle-link-wrap">
                <button type="button" id="wpsi-show-toggles" class="button button button-upsell"
                        aria-controls="screen-options-wrap"><?php _e("Display options", "wp-search-insights"); ?>
                    <span id="wpsi-toggle-arrows" class="dashicons dashicons-arrow-down-alt2"></span>
                </button>
            </div>
        </div>
        <div class="header-upsell">
            <a href="https://paypal.me/wpsearchinsights" target="_blank">
                <button class="button button-upsell donate"><?php _e("Donate", "wp-search-insights");?></button>
            </a>
        </div>
    </div>
    <?php
}
add_action('wpsi_tab_options', 'wpsi_tab_options');
