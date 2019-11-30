jQuery(document).ready(function ($) {
    "use strict";

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
});

