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

    // Generate an RFC 4122 Version 4 compliant UUID
    function generateUUID() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            const r = Math.random() * 16 | 0;
            const v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }

    function afterFinishedTyping(e) {
        var term = activeSearchObject.val();

        if (term.length > 255) { // Match configured limit
            return;
        }

        if (!ajaxCallActive) {
            ajaxCallActive = true;

            // Generate search ID for AJAX searches
            let search_id = generateUUID();

            // Create search data object with current time and search ID
            let searchData = {
                term: term,
                timestamp: new Date().getTime(),
                results: [], // We don't know results yet for AJAX searches
                result_count: 0, // Will be updated after AJAX response
                search_id: search_id
            };

            // Set cookie for server-side access
            document.cookie = "wpsi_search_id=" + encodeURIComponent(search_id) +
                "; path=/; max-age=300; SameSite=Lax" +
                (window.location.protocol === 'https:' ? '; secure' : '');

            document.cookie = "wpsi_last_search=" + encodeURIComponent(JSON.stringify({
                    term: term,
                    timestamp: searchData.timestamp,
                    result_count: 0,
                    search_id: search_id
                })) + "; path=/; max-age=300; SameSite=Lax" +
                (window.location.protocol === 'https:' ? '; secure' : '');

            $.ajax({
                type: "POST",
                url: search_insights_ajax.ajaxurl,
                dataType: 'json',
                data: ({
                    action: 'wpsi_process_search',
                    searchterm: term,
                    search_id: search_id,
                    token: search_insights_ajax.token,
                }),
                success: function (response) {
                    ajaxCallActive = false;

                }
            });
        }
    }
});
