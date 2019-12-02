(function ($) {

$('document').ready(function() {

var tour;

tour = new Shepherd.Tour({
    defaults: {
        classes: 'shepherd-theme-arrows',
        // scrollTo: true
    }
});

tour.addStep('wpsi-step-1', {
    title: 'We are recording your searches!',
    text: '<span></span><b>Welcome to Search Insights, get insigth in what your visitors are looking for!</b>',
    attachTo: '.search-insights-most-popular bottom',
    classes: 'shepherd-theme-arrows',
    buttons: [
        {
            text: 'Next',
            action: tour.next
        }
    ]
});

tour.start();

});

})(jQuery);