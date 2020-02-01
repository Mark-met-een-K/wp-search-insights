jQuery(document).ready(function ($) {
    "use strict";

    /**
     * Datatables
     */
    $('.wpsi-table').each(function(){
        console.log(this);
        $(this).DataTable( {
            "pageLength": 5,
            conditionalPaging: true,
            //https://datatables.net/reference/option/dom
            "dom": 'frt<"table-footer"p><"clear">B',
            // buttons: [
            //     'csv', 'excel'
            // ],
            buttons: [
                { extend: 'csv', text: 'Download CSV' }
            ],
            "language": {
                "paginate": {
                    "previous": "First",
                    "next": "Next",
                },
                searchPlaceholder: "Filter",
                "search" : "",
                "emptyTable" : "No searches recorded yet!"
            },
            "order": [[ 1, "desc" ]],
        });
    });

    // Move export buttons to no results div
    var export_buttons =  $("#wpsi-recent-table_wrapper > div.dt-buttons").detach();
    $(".wpsi-nr-footer").append(export_buttons);

    var export_buttons2 =  $("#wpsi-recent-table_wrapper > div.wpsi-date-btn:nth-child(1)").detach();
    $(".wpsi-nr-footer").append(export_buttons2);


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

