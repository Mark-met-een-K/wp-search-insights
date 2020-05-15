jQuery(document).ready(function ($) {
    "use strict";

    var deleteBtn = $('#wpsi-delete-selected');

    /**
     * Datatables
     */

    $('.item-content').each(function(){
        wpsiLoadData($(this));
    });

    function wpsiInitSingleDataTable(container) {
        var table = container.find('.wpsi-table');
        table.DataTable({
            "dom": 'frt<"table-footer"p><"clear">B',
            "pageLength": 6,
            conditionalPaging: true,
            buttons: [
                //{extend: 'csv', text: 'Download CSV'}
            ],
            "language": {
                "paginate": {
                    "previous": "Previous",
                    "next": "Next",
                },
                searchPlaceholder: "Search",
                "search": "",
                "emptyTable": "No searches recorded in selected period!"
            },
            "order": [[2, "desc"]],
        });

    }

    $(".wpsi-date-container").html(wpsi.dateFilter);

    $(document).on('change', '.wpsi-date-filter', function(e){
        console.log('test');
        e.stopPropagation();
        var container = $(this).closest('.item-container');
        var type = container.closest('.wpsi-item').data('table_type');
        var range = container.find('.wpsi-date-filter').val();
        console.log(range);
        localStorage.setItem('wpsi_range_'+type, range);
        wpsiLoadData(container.find('.item-content'));
    });

    function wpsiLoadData(container){
        var range;
        var type = container.closest('.wpsi-item').data('table_type');
        if (type === 'plugins' || type === 'tasks') return;
        var defaultRange = container.closest('.wpsi-item').data('default_range');
        var storedRange = localStorage.getItem('wpsi_range_'+type);
        if (storedRange === null ){
            range = defaultRange;
        } else {
            range = storedRange;
        }

        container.html('<div class="wpsi-skeleton"></div>');

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
                container.html(response.html);
                wpsiInitSingleDataTable(container);
                container.find(".wpsi-date-container").html(wpsi.dateFilter);
                container.find('.wpsi-date-filter').val(range);
                wpsiInitDeleteCapability();

            }
        });
    }

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
        $(".table-footer").append(deleteBtn);
        deleteBtn.show();

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
            var table = $(this).closest('.item-content').find('.dataTable');
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

    /**
     * Export
     */

    $(document).on('click', '#wpsi-start-export', wpsiExportData);
    if (wpsi.export_in_progress){
        wpsiExportData();
    }

    function wpsiExportData(){
        var downloadContainer = $('.wpsi-download-link');
        var button = $('#wpsi-start-export');
        button.prop('disabled', true);
        $.ajax({
            type: "GET",
            url: wpsi.ajaxurl,
            dataType: 'json',
            data: ({
                action: 'wpsi_start_export',
                token: wpsi.token,
            }),
            success: function (response) {
                console.log(response);
                if (response.percent < 100) {
                    downloadContainer.html(response.percent+'%');
                    wpsiExportData();
                } else {
                    var link = '<div><a href="'+response.path+'">'+wpsi.strings['download']+'</a></div>';
                    downloadContainer.html(link);
                    button.prop('disabled', false);
                }
            }
        });
    }
});

