jQuery(document).ready(function ($) {
    "use strict";
    //Input type search is default WP
    var input_type_search = $('input[type="search"]');
    //Elementor and some themes use input name=s
    var input_name_search = $('input[name="s"]');
    var typingTimer;
    var doneTypingInterval = 800;
    var activeSearchObject;
    var ajaxCallActive = false;
    //on keyup, start the countdown
    $(input_type_search, input_name_search).on('keyup', function (e) {
        activeSearchObject = $(this);
        clearTimeout(typingTimer);
        typingTimer = setTimeout(afterFinishedTyping, doneTypingInterval);
    });

    //on keydown, clear the countdown
    $(input_type_search, input_name_search).on('keydown', function (e) {
        clearTimeout(typingTimer);
    });

    function afterFinishedTyping(e){
        var term = activeSearchObject.val();
        if (!ajaxCallActive) {
            ajaxCallActive = true;
            $.ajax({
                type: "POST",
                url: search_insights_ajax.ajaxurl,
                dataType: 'json',
                data: ({
                    action: 'wpsi_process_search',
                    searchterm: term,
                    token: search_insights_ajax.token,
                }),
                success: function (response) {
                    ajaxCallActive = false;
                }
            });
        }
    }
});