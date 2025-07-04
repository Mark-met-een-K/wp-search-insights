jQuery(document).ready(function ($) {
    "use strict";

    var wpsiScreensizeHideColumn = 768;
    var wpsiScreensizeLowerMobile = 480;
    var wpsiMobileRowCount = 5;
    var wpsiDefaultRowCount = 7;
    var wpsiDefaultPagingType = 'simple_numbers';
    var wpsiMobilePagingType = 'simple';
    var ignoreBtn = $('#wpsi-ignore-selected');
    var deleteBtn = $('#wpsi-delete-selected');
    var lastSelectedPage = 0;

    // Default Popular Searches filter value
    var wpsiPopularSearchFilter = localStorage.getItem('wpsi_popular_filter') || 'with_results';

    $(window).on('resize', function () {
        wpsi_resize_datatables();
    });

    /**
     * Hide columns based on screen size, and redraw
     */
    function wpsi_resize_datatables() {
        $('.item-content').each(function () {
            if ($(this).closest('.wpsi-item').hasClass('wpsi-load-ajax')) {
                var table = $(this).find('table').DataTable();
                // When column is still at index 2
                var whenColumn = table.column(2);
                var win = $(window);

                if (win.width() > wpsiScreensizeHideColumn) {
                    if (!whenColumn.visible()) whenColumn.visible(true);
                } else {
                    whenColumn.visible(false);
                }

                table.columns.adjust().draw();

                // For mobile, we lower the number of rows
                if (win.width() < wpsiScreensizeLowerMobile) {
                    table.page.len(wpsiMobileRowCount).draw();
                } else {
                    table.page.len(wpsiDefaultRowCount).draw();
                }
            }
        });
    }

    /**
     * Ajax loading of tables
     */

    window.wpsiLoadAjaxTables = function () {
        // Reset pagination to first page when date range changes
        lastSelectedPage = 0;

        $('.item-content').each(function () {
            if ($(this).closest('.wpsi-item').hasClass('wpsi-load-ajax')) {
                // IMPORTANT: Reset the stored total rows count on date range change
                $(this).data('total-rows', 0);
                wpsiLoadData($(this), 1, 0);
            }

            if ($(this).closest('.wpsi-item').data('table_type') === 'popular') {
                $(this).find('#wpsi-popular-filter-select').val(wpsiPopularSearchFilter);
            }

        });
    };

    window.wpsiLoadAjaxTables();

    function wpsiInitSingleDataTable(container) {
        var table = container.find('.wpsi-table');
        if (!table.length) return;

        var win = $(window);
        var pageLength = win.width() < wpsiScreensizeLowerMobile ? wpsiMobileRowCount : wpsiDefaultRowCount;
        var pagingType = win.width() < wpsiScreensizeLowerMobile ? wpsiMobilePagingType : wpsiDefaultPagingType;
        var whenColumnVisible = win.width() >= wpsiScreensizeHideColumn;

        // Define column widths with clear mapping to columns
        var columnWidths = {
            0: "20%",  // Term column
            1: "10%",   // Results column
            2: "20%",  // When column
            3: "15%",  // From column
            4: "35%"   // Landing Page column (if present)
        };

        // Get the actual number of columns
        var columnCount = table.find('thead th').length;
        if (!columnCount) return;

        // Create column configurations
        var columnsConfig = [];
        for (var i = 0; i < columnCount; i++) {
            columnsConfig.push({
                width: columnWidths[i] || "15%" // Default to 15% if not specified
            });
        }

        // Define column behavior
        var columnDefsConfig = [
            // Configure When column (index 2) for responsive visibility
            {
                targets: 2,
                type: "num",
                visible: whenColumnVisible,
            }
        ];

        // Make all except first column unsearchable
        var nonSearchableColumns = [];
        for (var j = 1; j < columnCount; j++) {
            nonSearchableColumns.push(j);
        }

        if (nonSearchableColumns.length) {
            columnDefsConfig.push({
                targets: nonSearchableColumns,
                searchable: false
            });
        }

        try {

            // Set the amount of pagination items shown. Default 7 will overflow with large numbers (100+)
            $.fn.DataTable.ext.pager.numbers_length = 6;

            var dt = table.DataTable({
                dom: '<"wpsi-dt-container"<"wpsi-dt-header"r><"wpsi-dt-body"t><"wpsi-table-footer"<"wpsi-dt-pagination"p><"wpsi-page-nr">><"clear">B>',
                pageLength: pageLength,
                pagingType: pagingType,
                stateSave: false,
                columns: columnsConfig,
                columnDefs: columnDefsConfig,
                buttons: [],
                conditionalPaging: true,
                language: {
                    paginate: {
                        previous: wpsi.localize.previous,
                        next: wpsi.localize.next,
                    },
                    searchPlaceholder: wpsi.localize.search,
                    search: "",
                    emptyTable: wpsi.localize.no_searches
                },
                order: [[2, "desc"]],
                drawCallback: function () {
                    // Update the search count in the title (if this is the All Searches table)
                    if (container.closest('.wpsi-item').data('table_type') === 'all') {
                        updateSearchCount(this.api());
                    }

                    if (!container.find('.wpsi-loading-overlay').length) return;

                    if (container.data('loading-complete')) {
                        container.find('.wpsi-loading-overlay').fadeOut(200, function () {
                            $(this).remove();
                        });
                    }
                },
                initComplete: function () {
                    table.removeClass('dataTable').addClass('wpsi-dataTable');
                    table.removeClass('dt-search').addClass('wpsi-dt-search');
                    // Update the search count on initialization
                    if (container.closest('.wpsi-item').data('table_type') === 'all') {
                        updateSearchCount(this.api());
                    }

                }
            });

            // Function to update search count in header
            function updateSearchCount(api) {
                var info = api.page.info();

                var totalCount = info.recordsTotal;
                var filteredCount = info.recordsDisplay;

                var countText = '';
                if (totalCount > 0) {
                    if (filteredCount < totalCount) {
                        // Show filtered count out of total
                        countText = '(' + filteredCount + ' / ' + totalCount + ')';
                    } else {
                        // Just show total count
                        countText = '(' + totalCount + ')';
                    }
                }

                setTimeout(function () {
                    $('#wpsi-total-count, .wpsi-total-search-count').text(countText);
                }, 50);
            }

            // Update count when search is performed
            dt.on('search.dt', function () {
                if (container.closest('.wpsi-item').data('table_type') === 'all') {
                    updateSearchCount(dt);
                }
            });

            // Add a click handler to fix the sorting cycle
            $(dt.table().header()).on('click', 'th', function () {
                const column = dt.column(this);
                const currentOrder = dt.order()[0];

                if (currentOrder && currentOrder[0] === column.index() && currentOrder[1] === '') {
                    dt.order([column.index(), $(this).hasClass('sorting_asc') ? 'desc' : 'asc']).draw();
                    return false;
                }
            });

            // Position search field in header for "All Searches" table
            if (container.closest('.wpsi-item').data('table_type') === 'all') {
                var $header = container.closest('.wpsi-item').find('.wpsi-item-header');

                // Always remove existing search field to ensure clean initialization
                $header.find('.dt-search').remove();

                var searchDiv = $('<div class="dt-search wpsi-search-container"></div>');
                var searchWrapper = $('<div class="wpsi-search-input-wrapper"></div>');
                var searchInput = $('<input type="search" class="dt-input" placeholder="' + wpsi.localize['search'] + '">');
                var searchIcon = $('<span class="wpsi-search-icon dashicons dashicons-search"></span>');

                // Initialize with current search term if exists
                var currentSearch = '';
                try {
                    currentSearch = dt.search() || '';
                } catch (e) {
                    // Handle case where dt might not be fully initialized
                }

                searchInput.val(currentSearch).on('keyup', function () {
                    dt.search(this.value).draw();
                });

                searchWrapper.append(searchInput).append(searchIcon);
                searchDiv.append(searchWrapper);
                $header.append(searchDiv);

                dt.on('search.dt', function () {
                    searchInput.val(dt.search());
                });
            }

            // Handle pagination display for mobile
            container.find('.wpsi-table').on('page.dt', function () {
                var tbl = $(this).closest('table').DataTable();
                var info = tbl.page.info();
                lastSelectedPage = parseInt(info.page, 10);

                if (win.width() < wpsiScreensizeLowerMobile) {
                    var currentPage = lastSelectedPage + 1;
                    $(".wpsi-page-nr").text(parseInt(currentPage, 10) + "/" + parseInt(info.pages, 10));
                }
            });

        } catch (e) {
            console.warn('DataTables initialization error:', e);
            table.addClass('wpsi-no-datatables');
            table.wrap('<div class="wpsi-table-wrapper" style="max-height:500px;overflow-y:auto;"></div>');
        }
    }

    function wpsiLoadData(container, page, received) {
        var type = container.closest('.wpsi-item').data('table_type');
        var isAllSearchesBlock = (type === 'all');
        var batchSize = parseInt(wpsi.batch, 10);

        // Store total directly in a variable outside the AJAX scope
        var totalRows = parseInt(container.data('total-rows') || 0, 10);

        var filter = type === 'popular' ? wpsiPopularSearchFilter : null;

        // Ensure container has position relative for overlay positioning
        if (isAllSearchesBlock) {
            container.css('position', 'relative');
        }

        // Create or update the loading status display
        function updateLoadingStatus(percent) {
            // Only show detailed loading overlay for "All Searches" block
            // AND only when multiple batches are needed

            if (!isAllSearchesBlock || parseInt(totalRows, 10) <= parseInt(batchSize, 10)) return;

            // Check if overlay exists
            var overlay = container.find('.wpsi-loading-overlay');

            if (overlay.length === 0) {
                // Create full overlay with clean structure
                var statusHtml =
                    '<div class="wpsi-loading-overlay">' +
                    '<div class="wpsi-loading-content">' +
                    '<div class="wpsi-loading-spinner"></div>' +
                    '<div class="wpsi-loading-text">' +
                    wpsi.strings.loading_text +
                    ' <span class="wpsi-loading-percentage">' + percent + '%</span>' +
                    '</div>' +
                    '<div class="wpsi-loading-progress-container">' +
                    '<div class="wpsi-loading-progress" style="width:' + percent + '%"></div>' +
                    '</div>' +
                    '</div>' +
                    '</div>';

                // Append the overlay to the container
                container.append(statusHtml);
            } else {
                // Update existing overlay
                overlay.find('.wpsi-loading-percentage').text(percent + '%');
                overlay.find('.wpsi-loading-progress').css('width', percent + '%');
            }
        }

        // Hide loading status
        function hideLoadingStatus() {
            container.find('.wpsi-loading-overlay').fadeOut(200, function () {
                $(this).remove();
            });
        }

        // Show initial state for page 1
        if (page === 1) {
            // For all blocks, start with skeleton loading screen
            container.html(wpsi.skeleton);
            container.data('total-rows', parseInt(totalRows, 10));
            // We don't add the overlay yet - will add after first response
            // if we determine multiple batches are needed
        }

        // Date range handling - preserved from original code
        var unixStart = localStorage.getItem('wpsi_range_start');
        var unixEnd = localStorage.getItem('wpsi_range_end');
        if (unixStart === null || unixEnd === null) {
            unixStart = moment().startOf('day').unix();
            unixEnd = moment().endOf('day').unix();

            localStorage.setItem('wpsi_range_start', unixStart);
            localStorage.setItem('wpsi_range_end', unixEnd);
        }

        unixStart = parseInt(unixStart);
        unixEnd = parseInt(unixEnd);

        // Keep the failsafe that was added in the new version
        if (unixStart > unixEnd || isNaN(unixStart) || isNaN(unixEnd)) {
            unixStart = moment().startOf('day').unix();
            unixEnd = moment().endOf('day').unix();
            localStorage.setItem('wpsi_range_start', unixStart);
            localStorage.setItem('wpsi_range_end', unixEnd);
        }

        $.ajax({
            type: "GET",
            url: wpsi.ajaxurl,
            dataType: 'json',
            data: ({
                action: 'wpsi_get_datatable',
                start: unixStart,
                end: unixEnd,
                page: page,
                type: type,
                filter: filter,
                token: wpsi.tokens.get_datatable
            }),
            success: function (response) {
                // Update counts
                var newReceived = parseInt(received, 10) + parseInt(response.batch, 10);

                if (type === 'popular' && filter) {
                    container.find('#wpsi-popular-filter-select').val(filter);
                }

                // Update stored total if this response has a higher value
                if (parseInt(response.total_rows, 10) > parseInt(totalRows, 10)) {
                    totalRows = parseInt(response.total_rows, 10);
                    container.data('total-rows', totalRows);
                }

                if (page === 1) {
                    // Replace content
                    container.html(response.html);

                    if (type === 'all' && !container.find('.wpsi-trending-no-datatables').length) {
                        wpsiInitSingleDataTable(container);
                        wpsiInitDeleteCapability();
                        wpsiInitIgnoreCapability();
                    }

                    // If this is page 1 and we determine we need multiple batches,
                    // NOW we show the loading overlay (after first batch is displayed)
                    if (isAllSearchesBlock && totalRows > batchSize) {
                        var percentage = Math.min(Math.round((parseInt(newReceived, 10) / parseInt(totalRows, 10)) * 100), 100);
                        updateLoadingStatus(percentage);
                    }
                } else {
                    // Process additional rows for page > 1
                    var table = container.find('table').DataTable();

                    for (var key in response.html) {
                        if (response.html.hasOwnProperty(key)) {
                            var row = $(response.html[key]);
                            table.row.add(row);
                        }
                    }

                    // Call draw to update result count and update pagination
                    table.draw();
                }

                // Determine if more data to load
                if (parseInt(totalRows, 10) > parseInt(newReceived, 10)) { // CHANGE: Parse both values
                    // Calculate percentage
                    var percentage = Math.min(Math.round((parseInt(newReceived, 10) / parseInt(totalRows, 10)) * 100), 100);
                    updateLoadingStatus(percentage);

                    page++;
                    wpsiLoadData(container, page, newReceived);
                } else {
                    // All done - hide loading status if it exists
                    hideLoadingStatus();
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
        $(".wpsi-table-footer").append(deleteBtn);
        deleteBtn.show();

        //set button to disabled
        $('#wpsi-delete-selected').attr('disabled', true);
        $('#wpsi-ignore-selected').attr('disabled', true);

        $('.wpsi-dataTable tbody').on('click', 'tr', function () {

            $('#wpsi-delete-selected').attr('disabled', true);
            $('#wpsi-ignore-selected').attr('disabled', true);
            if ($(this).hasClass('wpsi-selected')) {
                $(this).removeClass('wpsi-selected');
            } else {
                $(this).addClass('wpsi-selected');
            }

            //if at least one row is selected, enable the delete button
            var table = $(this).closest('.item-content').find('.wpsi-dataTable');
            table.find('.wpsi-selected').each(function () {
                $('#wpsi-delete-selected').attr('disabled', false);
                $('#wpsi-ignore-selected').attr('disabled', false);
            });

        });

        $(document).on('click', '#wpsi-delete-selected', function () {
            var termIDs = [];

            $('.wpsi-dataTable').each(function () {
                var table = $(this);
                //get all selected rows
                table.find('.wpsi-selected').each(function () {
                    var row = $(this);
                    var termId = $(this).find('.wpsi-term').data('term_id');
                    if (termId && Number.isInteger(Number(termId))) {
                        termIDs.push(parseInt(termId, 10));
                    }
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
                    token: wpsi.tokens.delete_terms
                }),
                success: function () {
                    //get all occurrences on this page for this term id
                    $('.wpsi-dataTable').each(function () {
                        var table = $(this).DataTable();
                        while ($('.wpsi-selected').length) {
                            table.row('.wpsi-selected').remove().draw(false);
                        }
                    });
                    $('#wpsi-delete-selected').attr('disabled', true);
                    $('#wpsi-ignore-selected').attr('disabled', true);
                }
            });
        });
    }

    /**
     * select and delete functions
     */
    function wpsiInitIgnoreCapability() {
        //move button to location in table
        $(".wpsi-table-footer").append(ignoreBtn);
        ignoreBtn.show();

        //set button to disabled
        $('#wpsi-ignore-selected').attr('disabled', true);

        $(document).on('click', '#wpsi-ignore-selected', function () {
            var termIDs = [];

            $('.wpsi-dataTable').each(function () {
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
                    action: 'wpsi_ignore_terms',
                    term_ids: JSON.stringify(termIDs),
                    token: wpsi.tokens.ignore_terms
                }),
                success: function () {
                    //get all occurrences on this page for this term id
                    $('.wpsi-dataTable').each(function () {
                        var table = $(this).DataTable();
                        while ($('.wpsi-selected').length) {
                            table.row('.wpsi-selected').remove().draw(false);
                        }
                    });
                    $('#wpsi-delete-selected').attr('disabled', true);
                    $('#wpsi-ignore-selected').attr('disabled', true);
                }
            });
        });
    }

    /**
     * Export
     */

    // Initialize date picker when export modal opens
    $(document).on('click', '[data-target="wpsi_export_modal"]', function () {
        resetExportModal();
        initExportDatepicker();
    });

    // Reset modal when closed via any method
    $(document).on('click', '.wpsi-modal-close, .wpsi-modal-cancel', function () {
        if ($(this).closest('#wpsi_export_modal').length) {
            resetExportModal();
        }
    });

    // Prevent datepicker clicks from closing the modal
    $(document).on('click mousedown', '.daterangepicker', function (e) {
        e.stopPropagation();
    });

    // Reset modal state
    function resetExportModal() {
        var modal = $('#wpsi_export_modal');
        var progressContainer = modal.find('.wpsi-export-progress');
        var startButton = modal.find('.wpsi-export-start-button');
        var downloadButton = modal.find('.wpsi-export-download-button');
        var downloadLink = modal.find('.wpsi-download-text-link');

        // Hide progress container
        progressContainer.hide();

        // Reset progress bar
        modal.find('.wpsi-export-progress-bar-inner').css('width', '0%');
        modal.find('.wpsi-export-percentage').text('0%');

        // Reset buttons
        startButton.show().prop('disabled', false).css('opacity', '1');
        downloadButton.hide().attr('href', '#');

        // Remove text download link if exists
        if (downloadLink.length) {
            downloadLink.remove();
        }
    }

    // Simple datepicker initialization
    function initExportDatepicker() {
        var dateContainer = $('.wpsi-export-modal-date');

        // Only initialize if not already initialized
        if (dateContainer.length && !dateContainer.hasClass('initialized')) {
            var unixStart = localStorage.getItem('wpsi_range_start');
            var unixEnd = localStorage.getItem('wpsi_range_end');

            if (unixStart === null || unixEnd === null) {
                unixStart = moment().startOf('day').unix();
                unixEnd = moment().endOf('day').unix();
                localStorage.setItem('wpsi_range_start', unixStart);
                localStorage.setItem('wpsi_range_end', unixEnd);
            }

            // Update the display text
            dateContainer.find('span').html(
                moment.unix(unixStart).format('MMMM D, YYYY') + ' - ' +
                moment.unix(unixEnd).format('MMMM D, YYYY')
            );

            // Get activation time for "All time" option
            var wpsiPluginActivated = wpsi.activation_time || '0';

            // Create date objects
            var todayStart = moment().endOf('day').subtract(1, 'days').add(1, 'minutes');
            var todayEnd = moment().endOf('day');
            var yesterdayStart = moment().endOf('day').subtract(2, 'days').add(1, 'minutes');
            var yesterdayEnd = moment().endOf('day').subtract(1, 'days');
            var lastWeekStart = moment().startOf('day').subtract(6, 'days');
            var lastWeekEnd = moment().endOf('day');

            // Initialize daterangepicker
            dateContainer.daterangepicker({
                ranges: {
                    'Today': [todayStart, todayEnd],
                    'Yesterday': [yesterdayStart, yesterdayEnd],
                    'Last 7 Days': [lastWeekStart, lastWeekEnd],
                    'Last 30 Days': [moment().subtract(29, 'days').startOf('day'), moment().endOf('day')],
                    'This Month': [moment().startOf('month'), moment().endOf('month')],
                    'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
                    'All time': [moment.unix(wpsiPluginActivated), moment()]
                },
                "locale": {
                    "format": "MM/DD/YYYY",
                    "separator": " - ",
                    "applyLabel": wpsi.localize.apply_label || "Apply",
                    "cancelLabel": wpsi.localize.cancel_label || "Cancel",
                    "fromLabel": wpsi.localize.from_label || "From",
                    "toLabel": wpsi.localize.to_label || "To",
                    "customRangeLabel": wpsi.localize.custom_label || "Custom",
                    "weekLabel": wpsi.localize.week_label || "W",
                    "daysOfWeek": wpsi.localize.days_of_week || ["Mo", "Tu", "We", "Th", "Fr", "Sa", "Su"],
                    "monthNames": wpsi.localize.month_names || [
                        "January", "February", "March", "April", "May", "June",
                        "July", "August", "September", "October", "November", "December"
                    ],
                },
                "alwaysShowCalendars": true,
                startDate: moment.unix(unixStart),
                endDate: moment.unix(unixEnd),
                opens: "left",
                parentEl: ".wpsi-modal-body",
            }, function (start, end) {
                // Update display
                dateContainer.find('span').html(start.format('MMMM D, YYYY') + ' - ' + end.format('MMMM D, YYYY'));

                // Store the selected dates
                localStorage.setItem('wpsi_range_start', start.unix());
                localStorage.setItem('wpsi_range_end', end.unix());

                // Reset the export button state
                var startButton = $('#wpsi_export_modal').find('.wpsi-export-start-button');
                startButton.prop('disabled', false).css('opacity', '1');

                // Reset export modal state
                resetExportModal();
            });

            dateContainer.on('show.daterangepicker', function () {
                $('body').addClass('daterangepicker-open');
            });

            dateContainer.on('hide.daterangepicker', function () {
                $('body').removeClass('daterangepicker-open');
            });

            // Mark as initialized
            dateContainer.addClass('initialized');
        }
    }

    // Handle the export start button
    $(document).on('click', '.wpsi-export-start-button', function (e) {
        e.preventDefault();

        var modal = $(this).closest('.wpsi-modal');
        var progressContainer = modal.find('.wpsi-export-progress');
        var progressBar = modal.find('.wpsi-export-progress-bar-inner');
        var percentageText = modal.find('.wpsi-export-percentage');
        var downloadButton = modal.find('.wpsi-export-download-button');
        var startButton = $(this);

        // Show progress container, disable start button
        progressContainer.show();
        startButton.prop('disabled', true).css('opacity', '0.5');

        // Get date range
        var unixStart = localStorage.getItem('wpsi_range_start');
        var unixEnd = localStorage.getItem('wpsi_range_end');

        // Start export process
        startExport(unixStart, unixEnd, progressBar, percentageText, downloadButton, startButton, progressContainer);
    });

    function startExport(unixStart, unixEnd, progressBar, percentageText, downloadButton, startButton, progressContainer) {
        $.ajax({
            type: "GET",
            url: wpsi.ajaxurl,
            dataType: 'json',
            data: {
                action: 'wpsi_start_export',
                date_from: unixStart,
                date_to: unixEnd,
                token: wpsi.tokens.start_export
            },
            success: function (response) {
                if (response.success === false) {
                    // Handle error
                    percentageText.html('<span style="color: #d63638;">Export failed</span>');
                    startButton.prop('disabled', false).css('opacity', '1');
                    return;
                }

                var percent = parseInt(response.percent, 10);

                // Update progress bar
                progressBar.css('width', percent + '%');
                percentageText.text(percent + '%');

                if (percent < 100) {
                    // Continue export process
                    startExport(unixStart, unixEnd, progressBar, percentageText, downloadButton, startButton, progressContainer);
                } else {
                    // Export complete
                    progressBar.css('width', '100%');

                    // Instead of a separate div, include the download link inline with the completion text
                    percentageText.html(
                        '<span style="color: #00a32a;">Export complete!</span> ' +
                        '<a href="' + response.path + '" class="wpsi-inline-download-link">' +
                        wpsi.strings.download + '</a>'
                    );

                    // Hide start button completely
                    startButton.hide();

                    // Show download button with correct link
                    downloadButton.attr('href', response.path).show();
                }
            },
            error: function () {
                percentageText.html('<span style="color: #d63638;">Export failed</span>');
                startButton.prop('disabled', false).css('opacity', '1');
            }
        });
    }

    /**
     * Set hover on tips tricks
     */

    $(".wpsi-tips-tricks-element a").hover(function () {
        $(this).find('.wpsi-bullet').css("background-color", "#d7263d");
    }, function () {
        $(this).find('.wpsi-bullet').css("background-color", ""); //to remove property set it to ''
    });

    (function ($) {
        // Store timeout IDs to clear them when needed
        var toastTimeouts = {
            hide: null,
            remove: null
        };

        // Setup toast function
        function setupToast() {
            if ($('.wpsi-toast').length === 0) {
                $('body').append(`
                <div class="wpsi-toast">
                    <div class="wpsi-toast-content">
                        <div class="wpsi-toast-icon">
                            <span class="dashicons"></span>
                        </div>
                        <div class="wpsi-toast-message"></div>
                    </div>
                    <div class="wpsi-toast-progress"></div>
                </div>
            `);
            }
            return {
                $toast: $('.wpsi-toast'),
                $message: $('.wpsi-toast-message'),
                $icon: $('.wpsi-toast-icon .dashicons')
            };
        }

        // Show toast function
        function showToast(type, message) {
            // Clear any existing timeouts
            if (toastTimeouts.hide) clearTimeout(toastTimeouts.hide);
            if (toastTimeouts.remove) clearTimeout(toastTimeouts.remove);

            var toast = setupToast();

            // Reset classes and set new ones
            toast.$toast.removeClass('wpsi-toast-success wpsi-toast-error wpsi-toast-hide');

            if (type === 'saving') {
                toast.$message.text(message || 'Saving settings...');
                toast.$icon.attr('class', 'dashicons dashicons-update').css('animation', 'rotation 2s infinite linear');
            } else if (type === 'success') {
                toast.$toast.addClass('wpsi-toast-success');
                toast.$message.text(message || 'Settings saved successfully');
                toast.$icon.attr('class', 'dashicons dashicons-yes-alt').css('animation', '');
            } else if (type === 'error') {
                toast.$toast.addClass('wpsi-toast-error');
                toast.$message.text(message || 'Error saving settings');
                toast.$icon.attr('class', 'dashicons dashicons-warning').css('animation', '');
            }

            // Show the toast with a small delay to ensure CSS transitions work
            setTimeout(function () {
                toast.$toast.addClass('wpsi-toast-show');
            }, 10);

            // For success and error messages, set hide timeout
            if (type !== 'saving') {
                toastTimeouts.hide = setTimeout(function () {
                    toast.$toast.addClass('wpsi-toast-hide');

                    toastTimeouts.remove = setTimeout(function () {
                        toast.$toast.removeClass('wpsi-toast-show wpsi-toast-hide');
                    }, 500); // Match your CSS transition duration
                }, 3000);
            }
        }

        // Add save button feedback
        function showSaveButtonFeedback(formElement, isSuccess) {
            var $saveButton = formElement.find('input[type="submit"]');
            var $feedback = $saveButton.siblings('.wpsi-save-feedback');

            if ($feedback.length === 0) {
                $feedback = $('<span class="wpsi-save-feedback"></span>');
                $saveButton.after($feedback);
            }

            if (isSuccess) {
                $feedback.text('✓ Saved!').addClass('wpsi-save-success').removeClass('wpsi-save-error');
            } else {
                $feedback.text('✗ Error!').addClass('wpsi-save-error').removeClass('wpsi-save-success');
            }

            // Make it visible with animation
            $feedback.addClass('wpsi-feedback-visible');

            // Hide after 3 seconds
            setTimeout(function () {
                $feedback.removeClass('wpsi-feedback-visible');
            }, 3000);
        }

        // Improved settings form submission handler
        $(document).on('submit', 'form.wpsi-settings-form', function (e) {
            if ($(this).find('input[name^="wpsi_"]').length > 0) {
                e.preventDefault();
                e.stopPropagation();

                var $form = $(this);
                var formData = {};

                // Collect form data
                $form.find('input, select, textarea').each(function () {
                    var input = $(this);
                    var name = input.attr('name');

                    if (!name) return;

                    if (input.is(':checkbox')) {
                        formData[name] = input.is(':checked') ? 1 : 0;
                    } else if (input.is(':radio')) {
                        if (input.is(':checked')) {
                            formData[name] = input.val();
                        }
                    } else {
                        formData[name] = input.val();
                    }
                });

                // Show saving toast
                showToast('saving');

                // Send AJAX request
                $.ajax({
                    url: wpsi.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wpsi_save_settings',
                        form_data: formData,
                        security: wpsi.tokens.save_settings
                    },
                    success: function (response) {
                        if (response.success) {
                            showToast('success', response.data.message);
                            showSaveButtonFeedback($form, true);
                        } else {
                            showToast('error', response.data.message);
                            showSaveButtonFeedback($form, false);
                        }
                    },
                    error: function (xhr) {
                        var errorMessage = xhr.status === 429 ?
                            'Too many requests, please try again later' :
                            'Error saving settings';

                        showToast('error', errorMessage);
                        showSaveButtonFeedback($form, false);
                    }
                });

                return false;
            }
        });
    })(jQuery);

    // Popular searches filter and pagination
    $(document).on('change', '#wpsi-popular-filter-select', function () {
        var filter = $(this).val();
        wpsiPopularSearchFilter = filter;
        // Update all instances of the filter dropdown (in case there are multiple)
        $('.wpsi-item[data-table_type="popular"] #wpsi-popular-filter-select').val(filter);
        // Store in localStorage
        localStorage.setItem('wpsi_popular_filter', filter);

        $.ajax({
            type: "POST",
            url: wpsi.ajaxurl,
            dataType: 'json',
            data: {
                action: 'wpsi_save_filter_preference',
                filter: filter,
                token: wpsi.tokens.save_filter_preference
            }
        });

        var container = $(this).closest('.wpsi-item').find('.item-content');

        // Show a loading indicator
        container.html(wpsi.skeleton);

        // Get date range
        var unixStart = localStorage.getItem('wpsi_range_start');
        var unixEnd = localStorage.getItem('wpsi_range_end');

        // Make AJAX request with the new filter
        $.ajax({
            type: "GET",
            url: wpsi.ajaxurl,
            dataType: 'json',
            data: {
                action: 'wpsi_get_datatable',
                start: unixStart,
                end: unixEnd,
                page: 1, // Reset to first page when filter changes
                type: 'popular',
                filter: filter,
                token: wpsi.tokens.get_datatable
            },
            success: function (response) {
                if (response.success) {
                    container.html(response.html);
                }
            }
        });
    });

    // Popular searches pagination
    $(document).on('click', '.wpsi-pagination-btn', function () {
        if ($(this).attr('disabled')) return;

        var page = $(this).data('page');
        var container = $(this).closest('.item-content');
        var filter = $('#wpsi-popular-filter-select').val();

        // Show loading only in the rows area
        container.html(wpsi.skeleton);

        // Get date range
        var unixStart = localStorage.getItem('wpsi_range_start');
        var unixEnd = localStorage.getItem('wpsi_range_end');

        // Make AJAX request for the new page
        $.ajax({
            type: "GET",
            url: wpsi.ajaxurl,
            dataType: 'json',
            data: {
                action: 'wpsi_get_datatable',
                start: unixStart,
                end: unixEnd,
                page: page,
                type: 'popular',
                filter: filter,
                token: wpsi.tokens.get_datatable
            },
            success: function (response) {
                if (response.success) {
                    container.html(response.html);
                }
            }
        });
    });

});
