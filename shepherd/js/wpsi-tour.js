/* Tour for Search Insights */
(function ($) {
    'use strict';

    // Initialize Shepherd Tour
    function initTour() {
        if (typeof Shepherd !== 'function') {
            console.error('Shepherd.js not loaded');
            return;
        }

        // Initialize the tour
        var tour = new Shepherd.Tour({
            defaultStepOptions: {
                cancelIcon: {
                    enabled: true
                },
                classes: 'wpsi-tour-step',
                scrollTo: {behavior: 'smooth', block: 'center'}
            },
            useModalOverlay: true
        });

        // Add steps to the tour
        if (typeof wpsi_tour.steps !== 'undefined') {
            $.each(wpsi_tour.steps, function (index, step) {
                tour.addStep({
                    id: 'step-' + index,
                    title: step.title,
                    text: step.text,
                    attachTo: step.attachTo,
                    buttons: processButtons(step.buttons, tour)
                });
            });
        }

        // Handle tour completion
        tour.on('complete', function () {
            dismissTour();
        });

        // Handle tour cancellation
        tour.on('cancel', function () {
            dismissTour();
        });

        // Start the tour if needed
        if (wpsi_tour.start_tour) {
            // Wait a moment for the page to fully load
            setTimeout(function () {
                tour.start();
            }, 500);
        }
    }

    // Process button actions
    function processButtons(buttons, tour) {
        return buttons.map(function (button) {
            var action;

            // Set button action based on class
            if (button.classes.includes('wpsi-tour-next')) {
                action = tour.next;
            } else if (button.classes.includes('wpsi-tour-back')) {
                action = tour.back;
            } else if (button.classes.includes('wpsi-tour-close')) {
                action = function () {
                    tour.cancel();
                    dismissTour();
                };
            }

            return {
                text: button.text,
                classes: button.classes,
                action: action
            };
        });
    }

    // Dismiss tour via AJAX
    function dismissTour() {
        $.ajax({
            type: 'POST',
            url: wpsi_tour.ajaxurl,
            dataType: 'json',
            data: {
                action: 'wpsi_dismiss_tour',
                token: wpsi_tour.token
            }
        });
    }

    // Wait for DOM ready
    $(document).ready(function () {
        // Initialize the tour when document is ready
        initTour();
    });

})(jQuery);
