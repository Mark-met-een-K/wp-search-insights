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
            ],
            "language": {
                "paginate": {
                    "previous": wpsi.localize['previous'],
                    "next": wpsi.localize['next'],
                },
                searchPlaceholder: "Search",
                "search": "",
                "emptyTable": wpsi.localize['no-searches']
            },
            "order": [[2, "desc"]],
        });
    }

    $(".wpsi-date-container").html(wpsi.dateFilter);

    $(document).on('change', '.wpsi-date-filter', function(e){
        e.stopPropagation();
        var container = $(this).closest('.item-container');
        var type = container.closest('.wpsi-item').data('table_type');
        var range = container.find('.wpsi-date-filter').val();
        localStorage.setItem('wpsi_range_'+type, range);
        wpsiLoadData(container.find('.item-content'));
    });

    function wpsiLoadData(container){
        var range;
        var type = container.closest('.wpsi-item').data('table_type');

        var defaultRange = container.closest('.wpsi-item').data('default_range');
        var storedRange = localStorage.getItem('wpsi_range_'+type);
        if (storedRange === null ){
            range = defaultRange;
        } else {
            range = storedRange;
        }

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
});

