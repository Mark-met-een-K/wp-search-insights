jQuery(document).ready(function ($) {
    "use strict";
    //Input type search is default WP
    var input_type_search = $('input[type="search"]');
    //Elementor and some themes use input name=s
    var input_name_search = $('input[name="s"]');
    var term = "";
    listen_for_search();

    function listen_for_search() {

        // Post the search term after x ms
        $(input_type_search, input_name_search).keyup(delay(function (e) {
            term = this.value;
            $.ajax({
                type: "POST",
                url: search_insights_ajax.ajaxurl,
                dataType: 'json',
                data: ({
                    action: 'wpsi_process_search',
                    searchterm: term,
                    token: search_insights_ajax.token,
                })
            });
        }, 500));
    }

    function delay(callback, ms) {
        var timer = 0;
        return function() {
            var context = this, args = arguments;
            clearTimeout(timer);
            timer = setTimeout(function () {
                callback.apply(context, args);
            }, ms || 0);
        };
    }
});