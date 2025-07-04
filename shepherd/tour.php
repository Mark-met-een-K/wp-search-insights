<?php
defined('ABSPATH') or die("you do not have access to this page!");

class wpsi_tour
{
    public function __construct()
    {

        if (get_option('wpsi_tour_cancelled') || get_option('wpsi_tour_cancelled')) {
            // Tour has been previously dismissed
            return;
        }

        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('admin_footer', array($this, 'print_tour_js'), 20);
        add_action('wp_ajax_wpsi_cancel_tour', array($this, 'cancel_tour_callback'));
    }

    /**
     * Enqueue tour scripts and styles
     */
    public function enqueue_assets($hook)
    {

        global $search_insights_settings_page;

        // Only load on our settings page
        if ($hook !== $search_insights_settings_page) {
            return;
        }

        // Check if tour parameter is present
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Just checking URL parameter for tour display, not processing form data
        if (!isset($_GET['tour']) || absint(wp_unslash($_GET['tour'])) !== 1) {
            return;
        }

        // Enqueue Shepherd.js - no longer requires Tether
        wp_enqueue_script(
            'shepherd-js',
            trailingslashit(wpsi_url) . 'shepherd/js/shepherd.min.js',
            array('jquery'),
            wpsi_version,
            true
        );

        // Enqueue Shepherd CSS
        wp_enqueue_style(
            'shepherd-css',
            trailingslashit(wpsi_url) . 'shepherd/css/shepherd.css',
            array(),
            wpsi_version
        );

        // Enqueue WPSI tour styles
        wp_enqueue_style(
            'wpsi-tour-css',
            trailingslashit(wpsi_url) . 'shepherd/css/wpsi-tour.css',
            array('shepherd-css'),
            wpsi_version
        );
    }

    /**
     * Add tour JavaScript to footer
     */
    public function print_tour_js()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Just checking URL parameter for page context, not processing form data
        if (!isset($_GET['page']) || sanitize_text_field(wp_unslash($_GET['page'])) !== 'wpsi-settings-page') {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Just checking URL parameter for tour display, not processing form data
        if (!isset($_GET['tour']) || absint(wp_unslash($_GET['tour'])) !== 1) {
            return;
        }

        ?>
        <script>
            jQuery(document).ready(function ($) {
                // Wait for DOM to fully load including the Shepherd library
                setTimeout(function () {
                    // Check if Shepherd is loaded
                    if (typeof Shepherd === 'undefined') {
                        console.error('Shepherd.js not loaded');
                        return;
                    }

                    // Initialize tour with modern Shepherd API
                    const tour = new Shepherd.Tour({
                        defaultStepOptions: {
                            classes: 'shepherd-theme-arrows',
                            scrollTo: true,
                            cancelIcon: {
                                enabled: true
                            }
                        },
                        useModalOverlay: true
                    });

                    function generateButtons(type, customAction = null) {
                        // Start with the cancel button
                        const buttons = [
                            {
                                text: '<?php esc_html_e('Cancel Tour', 'wp-search-insights'); ?>',
                                action: function() {
                                    markTourAsSeen();
                                    return tour.cancel();
                                },
                                classes: 'shepherd-button-secondary wpsi-cancel-tour-link'
                            }
                        ];

                        // Add navigation buttons based on type
                        switch(type) {
                            case 'first': // First step - only Next
                                buttons.push({
                                    text: '<?php esc_html_e('Next', 'wp-search-insights'); ?>',
                                    classes: 'shepherd-button-primary',
                                    action: function() {
                                        return tour.next();
                                    }
                                });
                                break;

                            case 'middle': // Middle steps - Previous and Next
                                buttons.push({
                                    text: '<?php esc_html_e('Previous', 'wp-search-insights'); ?>',
                                    action: function() {
                                        return tour.back();
                                    }
                                });
                                buttons.push({
                                    text: '<?php esc_html_e('Next', 'wp-search-insights'); ?>',
                                    classes: 'shepherd-button-primary',
                                    action: function() {
                                        // If there's a custom action, execute it before moving to next step
                                        if (customAction) customAction();
                                        return tour.next();
                                    }
                                });
                                break;

                            case 'last': // Last step - only "Exit Tour"
                                buttons.push({
                                    text: '<?php esc_html_e('Exit Tour', 'wp-search-insights'); ?>',
                                    classes: 'shepherd-button-primary',
                                    action: function() {
                                        return tour.complete();
                                    }
                                });
                                break;

                            default:
                                // For any custom configurations
                                break;
                        }

                        return buttons;
                    }

                    // Define all the steps
                    const steps = [
                        // First step - Menu location (added as requested)
                        {
                            title: '<?php esc_html_e('Welcome to Search Insights!', 'wp-search-insights'); ?>',
                            text: '<?php esc_html_e('Search Insights is always accessible from this menu item. Click here anytime to view your search statistics and settings.', 'wp-search-insights'); ?>',
                            attachTo: {
                                element: '#toplevel_page_wpsi-settings-page',
                                on: 'right'
                            },
                            buttons: generateButtons('first')
                        },
                        // Welcome step
                        {
                            title: '<?php esc_html_e('The Search Insights Dashboard', 'wp-search-insights'); ?>',
                            text: '<?php esc_html_e('This dashboard gives you a complete overview of what your visitors are searching for. Let\'s walk through what you can see here.', 'wp-search-insights'); ?>',
                            attachTo: {
                                element: '#wpsi-dashboard',
                                on: 'top'
                            },
                            buttons: generateButtons('middle')
                        },
                        // Date Range Selection
                        {
                            title: '<?php esc_html_e('Date Range Selection', 'wp-search-insights'); ?>',
                            text: '<?php esc_html_e('Click here to choose which time period you want to analyze. You can select today, last week, last month, or set a custom date range.', 'wp-search-insights'); ?>',
                            attachTo: {
                                element: '.wpsi-date-container.wpsi-table-range',
                                on: 'bottom'
                            },
                            buttons: generateButtons('middle')
                        },
                        // All Searches
                        {
                            title: '<?php esc_html_e('All Searches', 'wp-search-insights'); ?>',
                            text: '<?php esc_html_e('This table shows every search performed on your site within the selected time period. You can see what was searched for, when, how many results were found, and which page the visitor was on when they searched.', 'wp-search-insights'); ?>',
                            attachTo: {
                                element: '.wpsi-grid [data-table_type="all"]',
                                on: 'bottom'
                            },
                            buttons: generateButtons('middle')
                        },
                        // Search Within Results
                        {
                            title: '<?php esc_html_e('Search Within Results', 'wp-search-insights'); ?>',
                            text: '<?php esc_html_e('Need to find something specific? Use this search box to filter through your search data. For example, type "product" to see only searches containing that word.', 'wp-search-insights'); ?>',
                            attachTo: {
                                element: '.wpsi-search-container',
                                on: 'bottom'
                            },
                            buttons: generateButtons('middle')
                        },
                        // Delete and Ignore
                        {
                            title: '<?php esc_html_e('Delete and Ignore', 'wp-search-insights'); ?>',
                            text: '<?php esc_html_e('Select any searches by clicking on them, then use these buttons to delete them or add them to your ignore list. Ignored terms won\'t be tracked in the future.', 'wp-search-insights'); ?>',
                            attachTo: {
                                element: '#wpsi-delete-selected',
                                on: 'right'
                            },
                            buttons: generateButtons('middle')
                        },
                        // Results Overview
                        {
                            title: '<?php esc_html_e('Results Overview', 'wp-search-insights'); ?>',
                            text: '<?php esc_html_e('This block shows you how effective your site search is. Green numbers show the percentage of searches that found results, while red shows searches that found nothing. Lower "no results" percentages are better!', 'wp-search-insights'); ?>',
                            attachTo: {
                                element: '.wpsi-grid [data-table_type="results"]',
                                on: 'top' // Change to 'top' instead of 'bottom'
                            },
                            buttons: generateButtons('middle')
                        },
                        // Popular Searches
                        {
                            title: '<?php esc_html_e('Popular Searches', 'wp-search-insights'); ?>',
                            text: '<?php esc_html_e('See what people search for most often on your site. You can filter this list using the dropdown above to show only searches with or without results. Green checkmarks (✓) show searches that found results, red X marks (✗) show searches that found nothing.', 'wp-search-insights'); ?>',
                            attachTo: {
                                element: '.wpsi-grid [data-table_type="popular"]',
                                on: 'bottom'
                            },
                            buttons: generateButtons('middle')
                        },
                        // Filter Popular Searches
                        {
                            title: '<?php esc_html_e('Filter Popular Searches', 'wp-search-insights'); ?>',
                            text: '<?php esc_html_e('Switch between viewing searches that found results and those that didn\'t. Focusing on "Without results" can help you identify gaps in your content.', 'wp-search-insights'); ?>',
                            attachTo: {
                                element: '#wpsi-popular-filter-select',
                                on: 'bottom'
                            },
                            buttons: generateButtons('middle')
                        },
                        // Tips & Tricks
                        {
                            title: '<?php esc_html_e('Tips & Tricks', 'wp-search-insights'); ?>',
                            text: '<?php esc_html_e('Looking to learn more? Here you\'ll find helpful articles that explain how to improve your site based on search data. Click any link to read the full article.', 'wp-search-insights'); ?>',
                            attachTo: {
                                element: '.wpsi-grid [data-table_type="tasks"]',
                                on: 'bottom'
                            },
                            buttons: generateButtons('middle')
                        },
                        // Display Options
                        {
                            title: '<?php esc_html_e('Display Options', 'wp-search-insights'); ?>',
                            text: '<?php esc_html_e('Click here to choose which blocks you want to show or hide on your dashboard. This lets you customize your view to focus on what matters most to you.', 'wp-search-insights'); ?>',
                            attachTo: {
                                element: '#wpsi-show-toggles',
                                on: 'bottom'
                            },
                            buttons: generateButtons('middle')
                        },
                        // Settings Tab
                        {
                            title: '<?php esc_html_e('Settings Tab', 'wp-search-insights'); ?>',
                            text: '<?php esc_html_e('Let\'s explore the Settings tab to configure how Search Insights works. Click on this tab to see all available options.', 'wp-search-insights'); ?>',
                            attachTo: {
                                element: '.tab-settings',
                                on: 'bottom'
                            },
                            buttons: generateButtons('middle', function() {
                                // Click the settings tab to navigate to it
                                $('.tab-settings')[0].click();
                                // Short delay to allow tab content to load
                                setTimeout(function() {
                                    // Force repositioning after tab switch
                                    tour.getCurrentStep().el.scrollIntoView();
                                }, 100);
                            })
                        },
                        {
                            title: '<?php esc_html_e('General Settings', 'wp-search-insights'); ?>',
                            text: '<?php esc_html_e('This section lets you configure the core behavior of Search Insights.', 'wp-search-insights'); ?>',
                            attachTo: {
                                element: '.wpsi-settings',
                                on: 'bottom'
                            },
                            buttons: generateButtons('middle')
                        },
                        // Exclude Admin Searches
                        {
                            title: '<?php esc_html_e('Exclude Admin Searches', 'wp-search-insights'); ?>',
                            text: '<?php esc_html_e('Enable this to stop tracking your own searches when logged in as admin. This keeps your statistics clean when you\'re testing search functionality.', 'wp-search-insights'); ?>',
                            attachTo: {
                                element: '#wpsi-exclude-admin-switch',
                                on: 'bottom'
                            },
                            buttons: generateButtons('middle')
                        },

                        // Min/Max Search Length
                        {
                            title: '<?php esc_html_e('Search Term Length Limits', 'wp-search-insights'); ?>',
                            text: '<?php esc_html_e('Set minimum and maximum character limits for tracked searches. This helps filter out very short terms (like "a") or extremely long entries that might be spam.', 'wp-search-insights'); ?>',
                            attachTo: {
                                element: '#wpsi_min_term_length',
                                on: 'bottom'
                            },
                            buttons: generateButtons('middle')
                        },

                        // Dashboard Capability
                        {
                            title: '<?php esc_html_e('Dashboard Access', 'wp-search-insights'); ?>',
                            text: '<?php esc_html_e('Control who can view search statistics. You can limit access to administrators only or allow all users with accounts to see the data.', 'wp-search-insights'); ?>',
                            attachTo: {
                                element: '#wpsi_select_dashboard_capability',
                                on: 'bottom'
                            },
                            buttons: generateButtons('middle')
                        },
                        // Ajax Search Tracking
                        {
                            title: '<?php esc_html_e('Ajax Search Tracking', 'wp-search-insights'); ?>',
                            text: '<?php esc_html_e('Enable this to track searches from plugins that use Ajax to show instant results without page reload. This ensures you capture all search activity.', 'wp-search-insights'); ?>',
                            attachTo: {
                                element: '#wpsi-track-ajax-searches-switch',
                                on: 'bottom'
                            },
                            buttons: generateButtons('middle')
                        },
                        // Auto Delete Terms
                        {
                            title: '<?php esc_html_e('Data Retention', 'wp-search-insights'); ?>',
                            text: '<?php esc_html_e('Choose how long to keep search data. You can keep everything forever or automatically delete older entries to keep your database size manageable.', 'wp-search-insights'); ?>',
                            attachTo: {
                                element: '#wpsi_select_term_deletion_period',
                                on: 'bottom'
                            },
                            buttons: generateButtons('middle')
                        },
                        // Custom Search Parameter
                        {
                            title: '<?php esc_html_e('Custom Search Parameter', 'wp-search-insights'); ?>',
                            text: '<?php esc_html_e('If your site uses a non-standard search URL parameter (other than "s"), enter it here. Most sites can leave this blank unless you use a custom search implementation.', 'wp-search-insights'); ?>',
                            attachTo: {
                                element: '#wpsi_custom_search_parameter',
                                on: 'bottom'
                            },
                            buttons: generateButtons('middle')
                        },
                        // Search Filters
                        {
                            title: '<?php esc_html_e('Search Filters', 'wp-search-insights'); ?>',
                            text: '<?php esc_html_e('Enter search terms you want to exclude from tracking, separated by commas. Use this for common terms like "login" or "admin" that might clutter your stats.', 'wp-search-insights'); ?>',
                            attachTo: {
                                element: '#wpsi_filter_textarea',
                                on: 'bottom'
                            },
                            buttons: generateButtons('middle')
                        },
                        // Data Settings
                        {
                            title: '<?php esc_html_e('Data Management', 'wp-search-insights'); ?>',
                            text: '<?php esc_html_e('These options control what happens to your search data when uninstalling the plugin or when you want to start fresh.', 'wp-search-insights'); ?>',
                            attachTo: {
                                element: '.wpsi-export-grid',
                                on: 'bottom'
                            },
                            buttons: generateButtons('middle')
                        },
                        {
                            title: '<?php esc_html_e('Export database', 'wp-search-insights'); ?>',
                            text: '<?php esc_html_e('Export your search data to CSV format for backup or external analysis. Select a date range to export only specific periods of search activity.', 'wp-search-insights'); ?>',
                            attachTo: {
                                element: '[data-target="wpsi_export_modal"]',
                                on: 'bottom'
                            },
                            buttons: generateButtons('middle')
                        },
                        {
                            title: '<?php esc_html_e('Clear data on uninstall', 'wp-search-insights'); ?>',
                            text: '<?php esc_html_e('When enabled, all search data will be permanently deleted if you uninstall the plugin. Leave this disabled if you plan to reinstall later and want to keep your valuable search analytics.', 'wp-search-insights'); ?>',
                            attachTo: {
                                element: '#wpsi_cleardatabase',
                                on: 'bottom'
                            },
                            buttons: generateButtons('middle')
                        },
                        {
                            title: '<?php esc_html_e('Clear database', 'wp-search-insights'); ?>',
                            text: '<?php esc_html_e('Permanently delete all search data from your database. This cannot be undone, so use with caution and consider exporting your data first. Useful for a fresh start or removing test data.', 'wp-search-insights'); ?>',
                            attachTo: {
                                element: '[data-target="wpsi_clear_searches_modal"]',
                                on: 'bottom'
                            },
                            buttons: generateButtons('middle')
                        },
                        // That's it!
                        {
                            title: '<?php esc_html_e('That\'s it!', 'wp-search-insights'); ?>',
                            text: '<?php esc_html_e('You\'re now ready to start using Search Insights. If you have any questions, click on the help icons next to each section title for more information.', 'wp-search-insights'); ?>',
                            buttons: generateButtons('last')
                        }
                    ];

                    // Add all steps to the tour
                    steps.forEach(step => {
                        tour.addStep(step);
                    });

                    // Start the tour
                    tour.start();

                    // When tour completes or is canceled
                    tour.on('complete', markTourAsSeen);
                    tour.on('cancel', markTourAsSeen);

                    // Mark tour as seen via AJAX
                    function markTourAsSeen() {
                        $.ajax({
                            type: "POST",
                            url: ajaxurl,
                            data: {
                                action: 'wpsi_cancel_tour',
                                security: '<?php echo esc_js( wp_create_nonce('wpsi_tour_nonce') ); ?>'
                            }
                        });
                    }
                }, 500); // Short delay to ensure everything is loaded
            });
        </script>
        <?php
    }

    /**
     * AJAX callback to mark the tour as seen/cancelled
     */
    public function cancel_tour_callback()
    {
        if (isset($_POST['security']) && wp_verify_nonce(sanitize_key($_POST['security']), 'wpsi_tour_nonce')) {
            update_option('wpsi_tour_cancelled', true);
        }
        wp_die();
    }
}
