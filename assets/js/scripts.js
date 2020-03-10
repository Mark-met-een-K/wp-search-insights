jQuery(document).ready(function ($) {
    "use strict";

    /**
     * Datatables
     */
    init_datatables();
    function init_datatables() {
        $('.wpsi-table').each(function () {
            $(this).DataTable({
                "dom": 'frt<"table-footer"p><"clear">B',
                "pageLength": 6,
                conditionalPaging: true,
                buttons: [
                    {extend: 'csv', text: 'Download CSV'}
                ],
                "language": {
                    "paginate": {
                        "previous": "Previous",
                        "next": "Next",
                    },
                    searchPlaceholder: "Search",
                    "search": "",
                    "emptyTable": "No searches recorded yet!"
                },
                "order": [[1, "desc"]],
            });
        });

        /**
         * Add dropdown for data filtering
         */
        $('.dataTables_filter').each(function(){
            $(this).append(wpsi.dateFilter)
        });

        wpsiInitDeleteCapability();

        // Move export buttons to no results div
        var export_buttons =  $("#wpsi-recent-table_wrapper > div.dt-buttons").addClass('csvDownloadBtn').detach();
        if (!$(".wpsi-nr-footer").find('.csvDownloadBtn').length){
            $(".wpsi-nr-footer").append(export_buttons);
        }

        // Move search term filter field outside of settings div
        var fiter_field =  $(".form-table > tbody:nth-child(1) > tr:nth-child(7)").detach();
        $("#filter-inner ").append(fiter_field);

    }

    $(".wpsi-date-container").html(wpsi.dateFilter);

    $(document).on('change', '.wpsi-date-filter', function(e){
        e.stopPropagation();

        var container = $(this).closest('.item-content');
        var isDataTable = (container.find('.dataTable').length);
        var range = container.find('.wpsi-date-filter').val();
        var type = $(this).closest('.wpsi-item').data('table_type');
        $.ajax({
            type: "GET",
            url: wpsi.ajaxurl,
            dataType: 'json',
            data: ({
                action: 'wpsi_get_datatable',
                range:range,
                type:type,
                token: wpsi.token
            }),
            success: function (response) {
                //get all occurrences on this page for this term id
                container.html(response.html);
                if (isDataTable) {
                    init_datatables();
                } else {
                    container.find(".wpsi-date-container").html(wpsi.dateFilter);
                }
                container.find('.wpsi-date-filter').val(range);

            }
        });
    });

    /**
     * Show/hide dashboard items
     */

    $('ul.tabs li').click(function () {
        var tab_id = $(this).attr('data-tab');
        // Sort and filter the grid
        if  (tab_id !== 'dashboard') {
            $('#wpsi-toggle-link-wrap').hide();
        } else {
            $('#wpsi-toggle-link-wrap').show();
        }

        $('ul.tabs li').removeClass('current');
        $('.tab-content').removeClass('current');

        $(this).addClass('current');
        $("#" + tab_id).addClass('current');
    });

    //Get the window hash for redirect to #settings after settings save
    var hash = "#" + window.location.hash.substr(1);
    var tab = window.location.hash.substr(1).replace('#top','');
    var href = $('.tab-'+tab).attr('href');
    if (href === hash) {
        $('.tab-'+tab)[0].click();
        window.location.href = href; //causes the browser to refresh and load the requested url
    }

    /**
     * Checkboxes
     */

    // Get grid toggle checkbox values
    var wpsi_grid_configuration = JSON.parse(localStorage.getItem('wpsi_grid_configuration')) || {};
    var checkboxes = $("#wpsi-toggle-dashboard :checkbox");

    // Enable all checkboxes by default to show all grid items. Set localstorage val when set so it only runs once.
    if (localStorage.getItem("wpsi_grid_initialized") === null) {
            checkboxes.each(function () {
                wpsi_grid_configuration[this.id] = 'checked';
            });
            localStorage.setItem("wpsi_grid_configuration", JSON.stringify(wpsi_grid_configuration));
        localStorage.setItem('wpsi_grid_initialized', 'set');
    }

    // Update storage checkbox value when checkbox value changes
    checkboxes.on("change", function(){
        updateStorage();
    });

    function updateStorage(){
        checkboxes.each(function(){
            wpsi_grid_configuration[this.id] = this.checked;
        });
        localStorage.setItem("wpsi_grid_configuration", JSON.stringify(wpsi_grid_configuration));
    }

    // Get checkbox values on pageload
    $.each(wpsi_grid_configuration, function(key, value) {
        $("#" + key).prop('checked', value);
    });

    // Hide screen options by default
    $("#wpsi-toggle-dashboard").hide();

    // Show/hide screen options on toggle click
    $('#wpsi-show-toggles').click(function(){
        if ($("#wpsi-toggle-dashboard").is(":visible") ){
            $("#wpsi-toggle-dashboard").slideUp();
            $("#wpsi-toggle-arrows").attr('class', 'dashicons dashicons-arrow-down-alt2');
        } else {
            $("#wpsi-toggle-dashboard").slideDown();
            $("#wpsi-toggle-arrows").attr('class', 'dashicons dashicons-arrow-up-alt2');
        }
    });

    /**
     * select and delete functions
     */
    function wpsiInitDeleteCapability() {
        //move button to location in table
        $(".table-footer").append($('#wpsi-delete-selected'));

        //set button to disabled
        $('#wpsi-delete-selected').attr('disabled', true);

        $('.dataTable tbody').on('click', 'tr', function (event) {
            $('#wpsi-delete-selected').attr('disabled', true);
            if ($(this).hasClass('wpsi-selected')) {
                $(this).removeClass('wpsi-selected');
            } else {
                $(this).addClass('wpsi-selected');
            }

            //if at least one row is selected, enable the delete button
            var table = $(this).closest('.search-insights-table').find('.dataTable');
            table.find('.wpsi-selected').each(function () {
                $('#wpsi-delete-selected').attr('disabled', false);
            });

        });

        $(document).on('click', '#wpsi-delete-selected', function () {
            var termIDs = [];

            $('.dataTable').each(function () {
                var table = $(this);
                //get all selected rows
                table.find('.wpsi-selected').each(function () {
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
                    $('.dataTable').each(function () {
                        var table = $(this).DataTable();
                        while ($('.wpsi-selected').length) {
                            table.row('.wpsi-selected').remove().draw(false);
                        }
                    });
                    $('#wpsi-delete-selected').attr('disabled', true);
                }
            });
        });
    }
});

