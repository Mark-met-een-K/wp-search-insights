(function ($) {

    $('document').ready(function() {

        if (!window.Shepherd) return;

        var plugins_overview_tour = window.wpsi_plugins_overview_tour = new Shepherd.Tour();
        var main_tour = window.wpsi_main_tour = new Shepherd.Tour();

        // Localized variables
        var startTourtext = search_insights_tour_ajax.startTourtext;
        var nextBtnText = search_insights_tour_ajax.nextBtnText;
        var backBtnText = search_insights_tour_ajax.backBtnText;

        var plugins_overview_title = search_insights_tour_ajax.po_title;
        var plugins_overview_text = search_insights_tour_ajax.po_text;
        var dashboard_title = search_insights_tour_ajax.dashboard_title;
        var dashboard_text = search_insights_tour_ajax.dashboard_text;

        var recent_searches_title = search_insights_tour_ajax.recent_searches_title;
        var recent_searches_text = search_insights_tour_ajax.recent_searches_text;
        var settings_title = search_insights_tour_ajax.settings_title;
        var settings_text = search_insights_tour_ajax.settings_text;
        var finish_title = search_insights_tour_ajax.finish_title;
        var finish_text = search_insights_tour_ajax.finish_text;


        plugins_overview_tour.options.defaults = main_tour.options.defaults = {
            classes: 'shepherd-theme-arrows',
            showCancelLink: true,
            tetherOptions: {
                constraints: [
                    {
                        to: 'scrollParent',
                        attachment: 'together',
                        pin: false
                    }
                ]
            }
        };

        // Plugins overview tour

        plugins_overview_tour.addStep('intro', {
            // classes: 'wpsi-plugins-overview-tour-container shepherd-has-cancel-link',
            classes: 'shepherd-theme-arrows wpsi-plugins-overview-tour-container shepherd-has-cancel-link',
            attachTo: '.wpsi-settings-link right',
            title: plugins_overview_title,
            text: plugins_overview_text,
            buttons: [
                {
                    text: startTourtext,
                    action: function() {
                        window.location = search_insights_tour_ajax.linkTo;
                    }
                },

            ],
        });

        // Main tour

        main_tour.addStep('popular-searches', {
            classes: 'shepherd-theme-arrows shepherd-has-cancel-link',
            attachTo: '#search-insights-most-popular-table_info right',
            title: dashboard_title,
            text: dashboard_text,
            buttons: [
                {
                    text: nextBtnText,
                    action: main_tour.next
                },
            ],
        });

        main_tour.addStep('recent-searches', {
            classes: 'shepherd-theme-arrows shepherd-has-cancel-link',
            title: recent_searches_title,
            text: recent_searches_text,
            attachTo: '#search-insights-recent-table_info right',
            buttons: [
                {
                    text: search_insights_tour_ajax.backBtnText,
                    action: main_tour.back
                },
                {
                    text: nextBtnText,
                    action: function() {
                        $('.tab-settings').click();
                        main_tour.next();
                    },


                }
            ],
        });

        main_tour.addStep('settings', {
            classes: 'shepherd-theme-arrows shepherd-has-cancel-link',
            title: settings_title,
            text: settings_text,
            attachTo: '#wpsi-dashboard .tab-settings [bottom right]',
            buttons: [
                {
                    text: search_insights_tour_ajax.backBtnText,
                    action: function() {
                        $('.tab-dashboard').click();
                        main_tour.back();
                    },
                },
                {
                    text: search_insights_tour_ajax.nextBtnText,
                    action: main_tour.next
                }
                // {
                //     text: nextBtnText,
                //     action: main_tour.next
                // }
            ],
            tetherOptions: {
                constraints: [
                    {
                        to: 'scrollParent',
                        attachment: 'together',
                        pin: false
                    }
                ],
                offset: '20px 0'
            },
        });

        main_tour.addStep('settings', {
            classes: 'shepherd-theme-arrows shepherd-has-cancel-link',
            title: finish_title,
            text: finish_text,
            attachTo: '#wpsi-dashboard .tab-settings [bottom right]',
            buttons: [
                {
                    text: search_insights_tour_ajax.backBtnText,
                    action: main_tour.back
                },
                {
                    text: search_insights_tour_ajax.endTour,
                    action: main_tour.cancel
                }
                // {
                //     text: nextBtnText,
                //     action: main_tour.next
                // }
            ],
            tetherOptions: {
                constraints: [
                    {
                        to: 'scrollParent',
                        attachment: 'together',
                        pin: false
                    }
                ],
                offset: '20px 0'
            },
        });

        plugins_overview_tour.on('cancel', cancel_tour);
        main_tour.on('cancel', cancel_tour);

        if ($('#dashboard').length) {
            main_tour.start();
        }

        /**
         * Cancel tour
         */

        function cancel_tour() {
            // The tour is either finished or [x] was clicked
            plugins_overview_tour.canceled = true;
            main_tour.canceled = true;

            $.ajax({
                type: "POST",
                url: search_insights_tour_ajax.ajaxurl,
                dataType: 'json',
                data: ({
                    wpsi_cancel_tour: 'wpsi_cancel_tour',
                    token: search_insights_tour_ajax.token,
                })
            });
        };

        // start tour when the settings link appears after plugin activation
        if ($('.wpsi-settings-link').length) {
            plugins_overview_tour.start();
        }

    });

})(jQuery);