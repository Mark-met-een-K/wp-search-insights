jQuery(document).ready(function ($) {
    "use strict";

    //Input type search is default WP
    var input_type_search = $('input[type="search"]');
    //Elementor and some themes use input name=s
    var input_name_search = $('input[name="s"]');

    var term = "";
    var sent = 0;

    listen_for_search();

    function listen_for_search() {
        $(input_type_search, input_name_search).keyup(function (e) {
            e.preventDefault();
            term = this.value;
            $('a').click(function (e) {
                if (sent == 0) {
                    setTimeout(function () {
                        // e.preventDefault();
                        $.ajax({
                            type: "POST",
                            url: search_insights_ajax.ajaxurl,
                            dataType: 'json',
                            data: ({
                                searchterm: term,
                                token: search_insights_ajax.token,
                            })
                        });
                    }, 200);
                }
                sent = 1;
            });
        });
    }
});