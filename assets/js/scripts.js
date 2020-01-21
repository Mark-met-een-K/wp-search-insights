jQuery(document).ready(function ($) {
    "use strict";

    // Initialize Datatables
    $('#wpsi-recent-table').DataTable( {
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


    $('#wpsi-popular-table').DataTable( {
        conditionalPaging: true,
        //https://datatables.net/reference/option/dom
        "dom": 'rt<"table-footer"iBp><"clear">',
        buttons: [
            'csv', 'excel'
        ],
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

    var href = $('.tab-settings').attr('href');
    if (href === hash) {
        $('.tab-settings')[0].click();
        window.location.href = href; //causes the browser to refresh and load the requested url
    }


    /**
     * select and delete functions
     */

    //set button to disabled
    $('#wpsi-delete-selected').attr('disabled', true);

    $('#wpsi-popular-table tbody').on( 'click', 'tr', function () {
        $('#wpsi-delete-selected').attr('disabled', true);
        if ( $(this).hasClass('wpsi-selected') ) {
            $(this).removeClass('wpsi-selected');
        } else {
            $(this).addClass('wpsi-selected');
        }

        //if at least one row is selected, enable the delete button
        var table = $(this).closest('.search-insights-table').find('.dataTable');
        table.find('.wpsi-selected').each(function(){
            $('#wpsi-delete-selected').attr('disabled', false);
        });

    } );

    $(document).on('click', '#wpsi-delete-selected', function(){
        var table = $(this).closest('.search-insights-table').find('.dataTable');
        //get all selected rows
        var termIDs=[];
        table.find('.wpsi-selected').each(function(){
            var row = $(this);
            termIDs.push($(this).find('.wpsi-term').data('term_id'));
            row.css('background-color', '#d7263d2e');
        });
        $.ajax({
            type: "POST",
            url: wpsi.ajaxurl,
            dataType: 'json',
            data: ({
                action: 'wpsi_delete_terms',
                term_ids: JSON.stringify(termIDs),
                token: wpsi.token
            }),
            success: function (response) {
                //get all occurrences on this page for this term id
                table.find('.wpsi-selected').each(function(){
                    var row = $(this);
                    row.remove();
                });
            }
        });


    });


});

