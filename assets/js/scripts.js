jQuery(document).ready(function ($) {
    "use strict";

    /**
     * Datatables
     */

    // Initialize Datatables
    $('#search-insights-recent-table').DataTable( {
        conditionalPaging: true,
        //https://datatables.net/reference/option/dom
        "dom": 'rt<"table-footer"iBp><"clear">',
        buttons: [
        'csv', 'excel'
        ],
        "language": {
            "paginate": {
                "previous": "<i class='icon-left-open'></i>",
                "next": "<i class='icon-right-open'></i>"
            },
            "emptyTable" : "No searches recorded yet!"
        },
        "order": [[ 1, "desc" ]]
    });

    $('#search-insights-most-popular-table').DataTable( {
        conditionalPaging: true,
        //https://datatables.net/reference/option/dom
        "dom": 'rt<"table-footer"iBp><"clear">',
        buttons: [
            'csv', 'excel'
        ],
        // "columns": [
        //     { "width": "20%" },
        //     { "width": "10%" },
        //     { "width": "10%" },
        // ],
        fixedHeader: {
            footer: true
        },
        "language": {
            "paginate": {
                "previous":"<i class='icon-left-open'></i>",
                "next": "<i class='icon-right-open'></i>"
            },
            "emptyTable" : "No searches recorded yet!"
        },
        "order": [[ 1, "desc" ]]
    });

    /**
     * Show/hide dashboard items
     */


    //Get the window hash for redirect to #settings after settings save
    var hash = "#" + window.location.hash.substr(1);

        $('ul.tabs li').click(function () {
        var tab_id = $(this).attr('data-tab');

        $('ul.tabs li').removeClass('current');
        $('.tab-content').removeClass('current');

        $(this).addClass('current');
        $("#" + tab_id).addClass('current');
    });

    // setTimeout(function() {
    var href = $('.tab-settings').attr('href');
    if (href === hash) {
        $('.tab-settings')[0].click();
        window.location.href = href; //causes the browser to refresh and load the requested url
    }
    // },15);

    /**
     *
     */

    show_hide_dashboard_items();

    $('.wpsi-toggle-items').click(function () {
        show_hide_dashboard_items();
    });

   // $('#toggle_data_id_1').change(function() {
        //     location.reload();
 //   });

    //
    // $('#toggle_data_id_2').change(function() {
    //     location.reload();
    // });
    //
    // $('#toggle_data_id_3').change(function() {
    //     location.reload();
    // });

    function show_hide_dashboard_items() {
        if ($('input#toggle_data_id_1').is(':checked')) {
            $('*[data-id="1"]').show();
        } else {
            $('*[data-id="1"]').hide();
        }

        if ($('input#toggle_data_id_2').is(':checked')) {
            $('*[data-id="2"]').show();
        } else {
            $('*[data-id="2"]').hide();
        }

        if ($('input#toggle_data_id_3').is(':checked')) {
            $('*[data-id="3"]').show();
        } else {
            $('*[data-id="3"]').hide();
        }
    }
});

