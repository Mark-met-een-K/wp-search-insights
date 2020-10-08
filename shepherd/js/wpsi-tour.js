jQuery(document).ready(function($) {
    if (!window.Shepherd) return;

    var plugins_overview_tour = new Shepherd.Tour();
    var widget_tour = new Shepherd.Tour();
    var main_tour = new Shepherd.Tour();

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

    plugins_overview_tour.addStep('wpsi-step-1', {
            classes: 'shepherd-theme-arrows wpsi-plugins-overview-tour-container shepherd-has-cancel-link',
            attachTo: '.wpsi-settings-link right',
            title: steps[1]['title'],
            text: wpsi_tour.html.replace('{content}', steps[1]['text']),
            buttons: [
                {
                    text: wpsi_tour.startTour,
                    classes: 'button button-primary',
                    action: function() {
                        window.location = steps[1]['link'];
                    }
                },

            ],
        });
        widget_tour.addStep('wpsi-step-2', {
            classes: 'shepherd-theme-arrows shepherd-has-cancel-link',
            attachTo: '#dashboard-widgets-wrap .wpsi-widget-logo right',
            title: steps[2]['title'],
            text: wpsi_tour.html.replace('{content}', steps[2]['text']),
            buttons: [
                {
                    text: wpsi_tour.nextBtnText,
                    classes: 'button button-primary',
                    action: function() {
                        window.location = steps[2]['link'];
                    }
                },

            ],
        });
        main_tour.addStep('wpsi-step-3', {
            classes: 'shepherd-theme-arrows shepherd-has-cancel-link',
            attachTo: '.table-overview right',
            title: steps[3]['title'],
            text: wpsi_tour.html.replace('{content}', steps[3]['text']),
            buttons: [
                {
                    text: wpsi_tour.backBtnText,
                    classes: 'button button-primary',
                    action: function() {
                        window.location = steps[1]['link'];
                    }
                },
                {
                    text: wpsi_tour.nextBtnText,
                    action: main_tour.next,
                    classes: 'button button-primary',
                },
            ],
        });

        main_tour.addStep('wpsi-step-4', {
            classes: 'shepherd-theme-arrows shepherd-has-cancel-link',
            title: steps[4]['title'],
            text: wpsi_tour.html.replace('{content}', steps[4]['text']),
            attachTo: '.wpsi-dashboard-widget-grid left',
            buttons: [
                {
                    text: wpsi_tour.backBtnText,
                    action: main_tour.back,
                    classes: 'button button-primary',
                },
                {
                    text: wpsi_tour.nextBtnText,
                    action: function() {
                        $('.tab-settings').click();
                        main_tour.next();
                    },
                    classes: 'button button-primary',
                }
            ],
        });

        main_tour.addStep('wpsi-step-5', {
            classes: 'shepherd-theme-arrows shepherd-has-cancel-link',
            title: steps[5]['title'],
            text: wpsi_tour.html.replace('{content}', steps[5]['text']),
            attachTo: '#wpsi-dashboard .tab-settings [bottom right]',
            buttons: [
                {
                    text: wpsi_tour.backBtnText,
                    action: function() {
                        $('.tab-dashboard').click();
                        main_tour.back();
                    },
                    classes: 'button button-primary',

                },
                {
                    text: wpsi_tour.nextBtnText,
                    action: main_tour.next,
                    classes: 'button button-primary',

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

        main_tour.addStep('wpsi-step-6', {
            classes: 'shepherd-theme-arrows shepherd-has-cancel-link',
            title: steps[6]['title'],
            text: wpsi_tour.html.replace('{content}', steps[6]['text']),
            attachTo: '#wpsi-dashboard .tab-settings [bottom right]',
            buttons: [
                {
                    text: wpsi_tour.backBtnText,
                    action: main_tour.back,
                    classes: 'button button-primary',

                },
                {
                    text: wpsi_tour.endTour,
                    action: main_tour.cancel,
                    classes: 'button button-primary',

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