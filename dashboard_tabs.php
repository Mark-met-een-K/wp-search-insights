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

	$element    = WPSI::$admin->get_template( 'grid-element.php',
		wpsi_path . '/grid' );
	$output     = '';


	foreach ( $grid_items as $index => $grid_item ) {
		$output .= str_replace( array(
			'{class}',
			'{content}',
			'{title}',
			'{index}',
			'{type}',
			'{controls}',
		), array(
			$grid_item['class'],
			$grid_item['content'],
			$grid_item['title'],
			$index,
			$grid_item['type'],
			$grid_item['controls'],
		), $element );
	}

	echo WPSI::$admin->get_template( 'grid-container.php',
		wpsi_path . '/grid', array(
		'grid_type'=> 'dashboard',
		'content' => $output
    ));


}

add_action( "wpsi_tab_content_dashboard", 'wpsi_tab_content_dashboard' );

/**
 * Settings tab on WPSI dashboard
 */

function wpsi_tab_content_settings(){
    if (!is_user_logged_in()) return;

	//get html of block
	$element = '';
	$blocks = array(
        array(
			'title' => __( "General settings", "wp-search-insights" ),
			'content' => wpsi_grid_content_settings(),
			'class' => '',
			'index' => 'settings',
			'type'=> 'settings',
			'controls' => '',
		),
		array(
			'title' => __( "Filters", "wp-search-insights" ),
			'content' => wpsi_grid_content_filter(),
			'class' => 'full-width',
			'index' => 'filter',
			'type'=> 'filter',
			'controls' => '',
		),
    );
	$blocks = apply_filters('wpsi_settings_blocks', $blocks);

	foreach($blocks as $args) {
		$element    .= WPSI::$admin->get_template( 'grid-element.php',
			wpsi_path . '/grid' , $args);
	}

	echo  WPSI::$admin->get_template( 'grid-container.php',wpsi_path . '/grid', array(
		    'grid_type'=> 'settings',
	        'content' => $element)
	);
}
add_action( "wpsi_tab_content_settings", 'wpsi_tab_content_settings');





function wpsi_grid_content_settings(){
	ob_start();
	do_settings_sections('wpsi-settings');
	settings_fields('wpsi-settings-tab');
	return ob_get_clean();
}


function wpsi_grid_content_filter(){
	ob_start();
	do_settings_sections('wpsi-filter');
	settings_fields('wpsi-filter-tab');
	return ob_get_clean();
}

/**
 * set of options in the tabs bar
 */

function wpsi_tab_options(){
    ?>
    <div class="documentation-pro">
        <div class="wpsi-date-container" style="background: #fff; cursor: pointer; padding: 5px 10px; border: 1px solid #ccc; width: 100%;margin-right:20px">
            <i class="dashicons dashicons-calendar-alt"></i>&nbsp;
            <span></span>
            <i class="dashicons dashicons-arrow-down-alt2"></i>
        </div>
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
