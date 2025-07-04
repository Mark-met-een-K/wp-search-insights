<?php
defined('ABSPATH') or die("you do not have access to this page!");

/**
 * Modal utility class for Search Insights
 * Provides a unified way to create and manage modals throughout the plugin
 */
class WPSI_Modal
{

    /**
     * Static function to generate modal HTML
     *
     * @param array $args Modal configuration
     * @return string Modal HTML
     */
    public static function get_modal($args = array())
    {
        $defaults = array(
            'id' => 'wpsi_modal_' . uniqid('', true),
            'title' => __('Confirmation', 'wp-search-insights'),
            'content' => '',
            'footer_content' => '',
            'primary_button' => array(
                'text' => __('Confirm', 'wp-search-insights'),
                'class' => 'button button-primary wpsi-modal-action',
                'id' => '',
                'href' => '#',
                'data_attributes' => array(),
            ),
            'cancel_button' => array(
                'text' => __('Cancel', 'wp-search-insights'),
                'class' => 'button wpsi-modal-cancel',
            ),
            'size' => 'medium', // small, medium, large
            'custom_classes' => '',
        );

        $args = wp_parse_args($args, $defaults);

        // Start building HTML
        ob_start();

        // Modal container
        ?>
        <div id="<?php echo esc_attr($args['id']); ?>"
             class="wpsi-modal <?php echo esc_attr($args['custom_classes']); ?> wpsi-modal-<?php echo esc_attr($args['size']); ?>"
             style="display:none;">
            <div class="wpsi-modal-content">
                <!-- Header -->
                <div class="wpsi-modal-header">
                    <span class="wpsi-modal-close">&times;</span>
                    <h3><?php echo esc_html($args['title']); ?></h3>
                </div>

                <!-- Body -->
                <div class="wpsi-modal-body">
                    <?php echo wp_kses_post($args['content']); ?>
                </div>

                <!-- Footer -->
                <div class="wpsi-modal-footer">
                    <?php if (!empty($args['footer_content'])): ?>
                        <?php echo wp_kses_post($args['footer_content']); ?>
                    <?php else: ?>
                        <?php if (!empty($args['primary_button'])):
                            $button = $args['primary_button'];
                            $data_attrs = '';

                            if (!empty($button['data_attributes']) && is_array($button['data_attributes'])) {
                                foreach ($button['data_attributes'] as $key => $value) {
                                    $data_attrs .= ' data-' . esc_attr($key) . '="' . esc_attr($value) . '"';
                                }
                            }
                            ?>
                            <a
                                    href="<?php echo esc_url($button['href']); ?>"
                                    class="<?php echo esc_attr($button['class']); ?>"
                                    <?php if (!empty($button['id'])): ?>id="<?php echo esc_attr($button['id']); ?>"<?php endif; ?>
                                <?php
                                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the parts of $data_attrs have been individually escaped using esc_attr()
                                    echo $data_attrs;
                                ?>
                            >
                                <?php echo esc_html($button['text']); ?>
                            </a>
                        <?php endif; ?>

                        <button type="button" class="<?php echo esc_attr($args['cancel_button']['class']); ?>">
                            <?php echo esc_html($args['cancel_button']['text']); ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php

        return ob_get_clean();
    }

    /**
     * Create a modal for confirming a potentially dangerous action
     *
     * @param array $args Configuration for the confirmation modal
     * @return string Modal HTML
     */
    public static function confirmation_modal($args = array())
    {
        $defaults = array(
            'id' => 'wpsi_confirmation_modal',
            'title' => __('Are you sure?', 'wp-search-insights'),
            'content' => __('This action cannot be undone.', 'wp-search-insights'),
            'action_button' => array(
                'text' => __('Confirm', 'wp-search-insights'),
                'href' => '#',
                'class' => 'button button-primary wpsi-modal-action',
            ),
        );

        $args = wp_parse_args($args, $defaults);

        $modal_args = array(
            'id' => $args['id'],
            'title' => $args['title'],
            'content' => $args['content'],
            'primary_button' => array(
                'text' => $args['action_button']['text'],
                'href' => $args['action_button']['href'],
                'class' => $args['action_button']['class'],
            ),
        );

        return self::get_modal($modal_args);
    }

    /**
     * Create a modal for exporting data
     *
     * @return string Modal HTML
     */
    public static function export_modal()
    {
        $content = '
        <p>' . esc_html__("Select a date range for your export:", "wp-search-insights") . '</p>

        <div class="wpsi-date-container wpsi-export-modal-date">
            <i class="dashicons dashicons-calendar-alt"></i>&nbsp;
            <span></span>
            <i class="dashicons dashicons-arrow-down-alt2"></i>
        </div>

        <div class="wpsi-export-progress" style="display:none;">
            <div class="wpsi-export-progress-bar">
                <div class="wpsi-export-progress-bar-inner"></div>
            </div>
            <div class="wpsi-export-status">
                <span class="wpsi-export-percentage">0%</span>
            </div>
        </div>
    ';

        $footer_content = '
        <button type="button" class="button button-primary wpsi-export-start-button">
            ' . esc_html__("Start Export", "wp-search-insights") . '
        </button>
        <a class="button button-primary wpsi-export-download-button" href="#" style="display:none;">
            ' . esc_html__("Download", "wp-search-insights") . '
        </a>
        <button type="button" class="button wpsi-modal-cancel">
            ' . esc_html__("Cancel", "wp-search-insights") . '
        </button>
    ';

        $modal_args = array(
            'id' => 'wpsi_export_modal',
            'title' => __('Export Search Data', 'wp-search-insights'),
            'content' => $content,
            'footer_content' => $footer_content,
        );

        return self::get_modal($modal_args);
    }

    /**
     * Create a modal for clearing all searches
     *
     * @param array $args Arguments for the clear all searches modal
     * @return string Modal HTML
     */
    public static function clear_searches_modal($args = array())
    {
        $defaults = array(
            'action_url' => '#',
            'action_token' => '',
        );

        $args = wp_parse_args($args, $defaults);

        return self::confirmation_modal(array(
            'id' => 'wpsi_clear_searches_modal',
            'title' => __('Are you sure?', 'wp-search-insights'),
            'content' => __('Clearing the database deletes all recorded searches. You can create a backup by exporting the tables to either .csv or .xlsx format by pressing the \'Export\' button above.', 'wp-search-insights'),
            'action_button' => array(
                'text' => __('Clear all searches', 'wp-search-insights'),
                'href' => $args['action_url'],
                'class' => 'button button-primary wpsi-modal-action',
            ),
        ));
    }
}
