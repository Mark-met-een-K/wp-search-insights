jQuery(document).ready(function ($) {
    "use strict";

    /**
     * Datatables
     */

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

    var href = $('.tab-settings').attr('href');
    if (href === hash) {
        $('.tab-settings')[0].click();
        window.location.href = href; //causes the browser to refresh and load the requested url
    }
    // },15);

    /**
     * Checkboxes
     */

    // Get grid toggle checkbox values
    var formValues = JSON.parse(localStorage.getItem('formValues')) || {};

    var $checkboxes = $("#wpsi-toggle-dashboard :checkbox");

    // Enable all checkboxes by default to show all grid items. Set localstorage val when set so it only runs once.
    if (localStorage.getItem("wpsiDashboardDefaultsSet") === null) {
            console.log("localstorage default not set, enable all");
            $checkboxes.each(function () {
                formValues[this.id] = 'checked';
            });
            localStorage.setItem("formValues", JSON.stringify(formValues));
        localStorage.setItem('wpsiDashboardDefaultsSet', 'set');
    }

    // Update storage checkbox value when checkbox value changes
    $checkboxes.on("change", function(){
        updateStorage();
    });

    function updateStorage(){
    $checkboxes.each(function(){
        formValues[this.id] = this.checked;
    });
        localStorage.setItem("formValues", JSON.stringify(formValues));
    }

    // Get checkbox values on pageload
    $.each(formValues, function(key, value) {
        $("#" + key).prop('checked', value);
    });

    // Hide screen options by default
    $("#wpsi-toggle-dashboard").hide();

    // Show/hide screen options on toggle click
    $('#wpsi-show-toggles').click(function(){
        if ($("#wpsi-toggle-dashboard").is(":visible") ){
            $("#wpsi-toggle-dashboard").hide();
            $("#wpsi-toggle-arrows").attr('class', 'dashicons dashicons-arrow-down');
        } else {
            $("#wpsi-toggle-dashboard").show();
            $("#wpsi-toggle-arrows").attr('class', 'dashicons dashicons-arrow-up');
        }
    });

    /**
     * select and delete functions
     */

    //set button to disabled
    $('#wpsi-delete-selected').attr('disabled', true);

    $('.dataTable tbody').on( 'click', 'tr', function () {
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
        var termIDs=[];

        $('.dataTable').each(function(){
            var table = $(this);
            //get all selected rows
            table.find('.wpsi-selected').each(function(){
                var row = $(this);
                termIDs.push($(this).find('.wpsi-term').data('term_id'));
                row.css('background-color', '#d7263d2e');
            });

        });
        console.log(termIDs);
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
                $('.dataTable').each(function(){
                    var table = $(this);
                    table.find('.wpsi-selected').each(function(){
                        var row = $(this);
                        row.remove();
                    });

                });
                $('#wpsi-delete-selected').attr('disabled', true);

            }
        });
    });
});

