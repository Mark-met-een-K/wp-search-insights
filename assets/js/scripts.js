jQuery(document).ready(function ($) {
    "use strict";

    var deleteBtn = $('#wpsi-delete-selected');

    /**
     * Ajax loading of tables
     */

    window.wpsiLoadAjaxTables = function() {
        $('.item-content').each(function () {
            if ($(this).closest('.wpsi-item').hasClass('wpsi-load-ajax')) {
                wpsiLoadData($(this), 1, 0);
            }
        });
    };
    window.wpsiLoadAjaxTables();

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
                    "previous": wpsi.localize['previous'],
                    "next": wpsi.localize['next'],
                },
                searchPlaceholder: "Search",
                "search": "",
                "emptyTable": "No searches recorded in selected period!"
            },
            "order": [[2, "desc"]],
        });
    }

    function wpsiLoadData(container, page, received){
        var type = container.closest('.wpsi-item').data('table_type');
        container.html(wpsi.skeleton);
        var unixStart = localStorage.getItem('wpsi_range_start');
        var unixEnd = localStorage.getItem('wpsi_range_end');
        if (unixStart === null || unixEnd === null ) {
            unixStart = moment().subtract(1, 'week').unix();
            unixEnd = moment().unix();
            localStorage.setItem('wpsi_range_start', unixStart);
            localStorage.setItem('wpsi_range_end', unixEnd);
        }
        unixStart = parseInt(unixStart);
        unixEnd = parseInt(unixEnd);
        $.ajax({
            type: "GET",
            url: wpsi.ajaxurl,
            dataType: 'json',
            data: ({
                action: 'wpsi_get_datatable',
                start:unixStart,
                end:unixEnd,
                page:page,
                type:type,
                token: wpsi.token
            }),
            success: function (response) {
                //this only on first page of table
                if (page===1){
                    container.html(response.html);
                    if (type==='all') {
                        wpsiInitSingleDataTable(container);
                        wpsiInitDeleteCapability();
                    }

                } else {
                    var table = container.find('table').DataTable();
                    var rowCount = response.html.length;
                    for (var key in response.html) {
                        if (response.html.hasOwnProperty(key)) {
                            var row = $(response.html[key]);
                            //only redraw on last row
                            if (parseInt(key) >= (rowCount-1) ) {
                                table.row.add(row).draw();
                            } else {
                                table.row.add(row);
                            }
                        }
                    }
                }

                received += response.batch;
                if (response.total_rows > received) {
                    page++;
                    wpsiLoadData(container, page , received);
                } else {
                    page = 1;
                }

            }
        });
    }


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
        var date_from = $('input[name=wpsi-export-from]').val();
        var date_to = $('input[name=wpsi-export-to]').val();
        button.prop('disabled', true);
        $.ajax({
            type: "GET",
            url: wpsi.ajaxurl,
            dataType: 'json',
            data: ({
                action: 'wpsi_start_export',
                date_from: date_from,
                date_to: date_to,
                token: wpsi.token,
            }),
            success: function (response) {
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

