(function ($) {

$('document').ready(function() {

var tour;
var pluginUrl = search_insights_tour_ajax.pluginUrl;
console.log(pluginUrl);

tour = new Shepherd.Tour({
    defaults: {
        classes: 'shepherd-theme-arrows',
        // scrollTo: true
    }
});

tour.addStep('wpsi-step-1', {
    title: 'We are recording your searches!',
    // text: pluginUrl,
    text: '<span><img src="'+pluginUrl+'assets/images/logo.png"></span><b>Welcome to Search Insights</b>, get insigth in what your visitors are looking for!',
    attachTo: '.search-insights-most-popular bottom',
    classes: 'shepherd-theme-arrows',
    buttons: [
        {
            text: 'Press here to start!',
            action: tour.next
        }
    ]
});

tour.start();

});

})(jQuery);