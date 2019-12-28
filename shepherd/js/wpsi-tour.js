jQuery(document).ready(function($) {
        if (!window.Shepherd) return;

        var plugins_overview_tour = new Shepherd.Tour();
        var widget_tour = new Shepherd.Tour();
        var main_tour = new Shepherd.Tour();

        // Localized variables
        var startTourtext = wpsi_tour.startTourtext;
        var nextBtnText = wpsi_tour.nextBtnText;
        var backBtnText = wpsi_tour.backBtnText;

        var plugins_overview_title = wpsi_tour.po_title;
        var plugins_overview_text = wpsi_tour.po_text;
        var widget_title = wpsi_tour.widget_title;
        var widget_text = wpsi_tour.widget_text;
        var dashboard_title = wpsi_tour.dashboard_title;
        var dashboard_text = wpsi_tour.dashboard_text;

        var recent_searches_title = wpsi_tour.recent_searches_title;
        var recent_searches_text = wpsi_tour.recent_searches_text;
        var settings_title = wpsi_tour.settings_title;
        var settings_text = wpsi_tour.settings_text;
        var finish_title = wpsi_tour.finish_title;
        var finish_text = wpsi_tour.finish_text;

        plugins_overview_tour.options.defaults = widget_tour.options.defaults = main_tour.options.defaults = {
            classes: 'shepherd-theme-arrows',
            scrollTo: true,
            scrollToHandler: function(e) {
                $('html, body').animate({
                    scrollTop: $(e).offset().top-200
                }, 1000);
            },
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
        var steps = wpsi_tour.steps;
    console.log(steps[1]['title']);

    plugins_overview_tour.addStep('intro', {
            classes: 'shepherd-theme-arrows wpsi-plugins-overview-tour-container shepherd-has-cancel-link',
            attachTo: '.wpsi-settings-link right',
            title: steps[1]['title'],
            text: wpsi_tour.html.replace('{content}', steps[1]['text']),    
            buttons: [
                {
                    text: wpsi_tour.startTour,
                    action: function() {
                        window.location = steps[1]['link'];
                    }
                },

            ],
        });
        widget_tour.addStep('widget', {
            classes: 'shepherd-theme-arrows shepherd-has-cancel-link',
            attachTo: '.wpsi-widget-logo right',
            title: steps[2]['title'],
            text: steps[2]['text'],
            buttons: [
                {
                    text: nextBtnText,
                    action: function() {
                        window.location = steps[2]['link'];
                    }
                },

            ],
        });
        main_tour.addStep('popular-searches', {
            classes: 'shepherd-theme-arrows shepherd-has-cancel-link',
            attachTo: '#search-insights-most-popular-table_info right',
            title: dashboard_title,
            text: dashboard_text,
            buttons: [
                {
                    text: backBtnText,
                    action: function() {
                        window.location = wpsi_tour.linkToDashboard;
                    }
                },
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
                    text: wpsi_tour.backBtnText,
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
                    text: wpsi_tour.backBtnText,
                    action: function() {
                        $('.tab-dashboard').click();
                        main_tour.back();
                    },
                },
                {
                    text: wpsi_tour.nextBtnText,
                    action: main_tour.next
                }
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
                    text: wpsi_tour.backBtnText,
                    action: main_tour.back
                },
                {
                    text: wpsi_tour.endTour,
                    action: main_tour.cancel
                }
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

        widget_tour.on('cancel', cancel_tour);
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
                url: wpsi_tour.ajaxurl,
                dataType: 'json',
                data: ({
                    wpsi_cancel_tour: 'wpsi_cancel_tour',
                    token: wpsi_tour.token,
                })
            });
        };

        // start tour when the settings link appears after plugin activation
        if ($('.wpsi-settings-link').length) {
            plugins_overview_tour.start();
        }

        if ($('.wpsi-widget-logo').length) {
            widget_tour.start();
        }


});