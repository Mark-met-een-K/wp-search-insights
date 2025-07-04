// Updated grid.js with proper instance tracking

// Store grid instances globally
window.wpsiGridInstances = {};

window.addEventListener('wpsiSwitchTab', function (tab) {
    wpsiInitGrid();
});

jQuery(document).ready(function ($) {
    var drag_enabled_or_disabled = !wpsiIsMobileDevice();

    wpsiInitGrid();

    function wpsiInitGrid() {
        $('.wpsi-grid').each(function () {
            var grid_type = $(this).data('grid_type');
            wpsiInitGridByClass(grid_type);
        });
    }

    /**
     * Init by classname
     * @param className
     */
    function wpsiInitGridByClass(className) {
        // Check if this grid class already has an instance
        if (window.wpsiGridInstances[className]) {
            // Grid instance exists, just refresh layout
            window.wpsiGridInstances[className].refreshItems().layout();
            // Update visibility based on toggle state
            updateGridVisibility(window.wpsiGridInstances[className], className);
            return;
        }

        // Only create a new instance if one doesn't exist
        window.wpsiGridInstances[className] = new Muuri('.wpsi-grid.' + className, {
            dragEnabled: drag_enabled_or_disabled,
            dragSortHeuristics: {
                sortInterval: 50,
                minDragDistance: 10,
                minBounceBackAngle: 1
            },
            dragPlaceholder: {
                enabled: false,
                duration: 400,
                createElement: function (item) {
                    return item.getElement().cloneNode(true);
                }
            },
            dragReleaseDuration: 400,
            dragReleseEasing: 'ease',
            layoutOnInit: true,
            layoutDuration: 0, // Set to 0 for instant layout with no animation
            layoutEasing: 'ease',
            dragStartPredicate: function (item, e) {
                return e.target.className === 'wpsi-drag-handle';
            }
        })

        .on('move', function () {
            wpsiSaveLayout(window.wpsiGridInstances[className], className);
        });

        wpsiloadLayout(window.wpsiGridInstances[className], className);
        // Must save the layout on first load, otherwise filtering the grid won't work on a new install.
        wpsiSaveLayout(window.wpsiGridInstances[className], className);
    }

    /**
     * Update grid item visibility based on toggle status
     */
    function updateGridVisibility(grid, className) {
        $('.wpsi-item').each(function () {
            var toggle_id = $(this).data('id');

            // Ensure layout includes this item if it's not already present
            var layout = JSON.parse(localStorage.getItem('wpsi_layout_' + className) || '[]');
            if (!layout.includes(toggle_id.toString())) {
                layout.push(toggle_id.toString());
                localStorage.setItem('wpsi_layout_' + className, JSON.stringify(layout));
            }

            // Apply active class based on toggle state
            if (localStorage.getItem('wpsi_toggle_data_id_' + toggle_id) === 'checked') {
                $(this).addClass("muuri-active").removeClass('wpsi-should-hide');
            } else {
                $(this).removeClass("muuri-active").addClass('wpsi-should-hide');
            }
        });

        // Filter the grid to show only active items
        grid.filter('.muuri-active');
    }

    /**
     * Serialize the layout
     * @param grid
     * @returns {string}
     */
    function wpsiSerializeLayout(grid) {
        var itemIds = grid.getItems().map(function (item) {
            var id = item.getElement().getAttribute('data-id');
            // Sanitize to prevent potential XSS
            return id ? id.replace(/[^a-zA-Z0-9_-]/g, '') : null;
        }).filter(Boolean);

        return JSON.stringify(itemIds);
    }

    /**
     * Save the layout
     * @param grid
     * @param className
     */
    function wpsiSaveLayout(grid, className) {
        var layout = wpsiSerializeLayout(grid);

        // Validate className to prevent key pollution
        className = className.replace(/[^a-zA-Z0-9_-]/g, '');

        // Validate layout is valid JSON array
        try {
            JSON.parse(layout);
            window.localStorage.setItem('wpsi_layout_' + className, layout);
        } catch (e) {
            console.error('Invalid layout data');
        }
    }

    /**
     * Load a grid layout
     * @param grid
     * @param className
     */
    function wpsiloadLayout(grid, className) {
        if (className === 'tips_tricks') return;
        var serializedLayout = window.localStorage.getItem('wpsi_layout_' + className);
        if (serializedLayout) {
            var layout = JSON.parse(serializedLayout);
            var currentItems = grid.getItems();

            // Add or remove the muuri-active class for each checkbox. Class is used in filtering.
            $('.wpsi-item').each(function () {
                var toggle_id = $(this).data('id');

                //if the layout has less blocks then there actually are, we add it here. Otherwise it ends up floating over another block
                if (!layout.includes(toggle_id.toString())) layout.push(toggle_id.toString());

                var sanitized_toggle_id = toggle_id.toString().replace(/[^0-9]/g, '');
                if (localStorage.getItem("wpsi_toggle_data_id_" + sanitized_toggle_id) === null) {
                    window.localStorage.setItem('wpsi_toggle_data_id_' + toggle_id, 'checked');
                }

                // Add or remove the active class when the checkbox is checked/unchecked
                if (window.localStorage.getItem('wpsi_toggle_data_id_' + toggle_id) === 'checked') {
                    $(this).addClass("muuri-active");
                } else {
                    $(this).removeClass("muuri-active");
                }
            });

            var currentItemIds = currentItems.map(function (item) {
                return item.getElement().getAttribute('data-id')
            });
            var newItems = [];
            var itemId;
            var itemIndex;

            for (var i = 0; i < layout.length; i++) {
                itemId = layout[i];
                itemIndex = currentItemIds.indexOf(itemId);
                if (itemIndex > -1) {
                    newItems.push(currentItems[itemIndex])
                }
            }

            // Sort and filter the grid
            try {
                grid.sort(newItems, {layout: 'instant'});
                grid.filter('.muuri-active');
            } catch (err) {
                // If sorting fails, clear the layout and try again
                window.localStorage.removeItem('wpsi_layout_' + className);
                console.warn('Layout reset due to error:', err);
            }
        }

        grid.layout(true);
    }

    // Handle tab switching
    $('ul.tabs li').click(function () {
        var tab_id = $(this).attr('data-tab');
        tab_id = tab_id ? tab_id.replace(/[^a-zA-Z0-9_-]/g, '') : '';

        // Sort and filter the grid
        if (tab_id !== 'dashboard') {
            $('#wpsi-toggle-link-wrap').hide();
            $('.wpsi-date-container.wpsi-table-range').hide();
        } else {
            $('#wpsi-toggle-link-wrap').show();
            $('.wpsi-date-container.wpsi-table-range').show();
        }

        $('ul.tabs li').removeClass('current');
        $('.tab-content').removeClass('current');

        $(this).addClass('current');
        $("#" + tab_id).addClass('current');

        // Instead of re-initializing everything, just refresh existing grids with instant layout
        Object.keys(window.wpsiGridInstances).forEach(function(gridClass) {
            if (window.wpsiGridInstances[gridClass]) {
                // Use instant layout to prevent animations
                window.wpsiGridInstances[gridClass].refreshItems().layout(true, function() {
                    // Force items to their final positions immediately
                    window.wpsiGridInstances[gridClass].getItems().forEach(function(item) {
                        // Apply direct style to position items without animation
                        var element = item.getElement();
                        var position = item.getPosition();
                        element.style.transform = 'translate(' + position.left + 'px, ' + position.top + 'px)';
                    });
                });
            }
        });
    });

    // Reload the grid when checkbox value changes
    $('.wpsi-item').each(function () {
        var toggle_id = $(this).data('id');
        // Set defaults for localstorage checkboxes
        if (!window.localStorage.getItem('wpsi_toggle_data_id_' + toggle_id)) {
            window.localStorage.setItem('wpsi_toggle_data_id_' + toggle_id, 'checked');
        }
        var sanitized_toggle_id = toggle_id.toString().replace(/[^0-9]/g, '');
        $('#wpsi_toggle_data_id_' + sanitized_toggle_id).change(function () {
            if (document.getElementById("wpsi_toggle_data_id_" + toggle_id).checked) {
                window.localStorage.setItem('wpsi_toggle_data_id_' + toggle_id, 'checked');
            } else {
                window.localStorage.setItem('wpsi_toggle_data_id_' + toggle_id, 'unchecked');
            }

            // Just update visibility on the existing grids instead of re-initializing
            Object.keys(window.wpsiGridInstances).forEach(function(gridClass) {
                if (window.wpsiGridInstances[gridClass]) {
                    updateGridVisibility(window.wpsiGridInstances[gridClass], gridClass);
                }
            });
        });
    });

    //Get the window hash for redirect to #settings after settings save
    var hash = window.location.hash;
    var sanitizedHash = hash.replace(/[^#a-zA-Z0-9_-]/g, '');
    var tab = sanitizedHash.substr(1).replace('#top', '');
    var href = $('.tab-' + tab).attr('href');
    if (href === hash) {
        $('.tab-' + tab)[0].click();
        window.location.href = href; //causes the browser to refresh and load the requested url
    }

    /**
     * check if it's a mobile device
     * @returns {boolean}
     */
    function wpsiIsMobileDevice() {
        return (typeof window.orientation !== "undefined") || (navigator.userAgent.indexOf('IEMobile') !== -1);
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
    checkboxes.on("change", function () {
        updateStorage();
    });

    function updateStorage() {
        checkboxes.each(function () {
            wpsi_grid_configuration[this.id] = this.checked;
        });
        localStorage.setItem("wpsi_grid_configuration", JSON.stringify(wpsi_grid_configuration));
    }

    // Get checkbox values on pageload
    $.each(wpsi_grid_configuration, function (key, value) {
        $("#" + key).prop('checked', value);
    });

    // Hide screen options by default
    $("#wpsi-toggle-dashboard").hide();

    // Show/hide screen options on toggle click
    $('#wpsi-show-toggles').click(function () {
        if ($("#wpsi-toggle-dashboard").is(":visible")) {
            $("#wpsi-toggle-dashboard").slideUp();
            $("#wpsi-toggle-arrows").attr('class', 'dashicons dashicons-arrow-down-alt2');
        } else {
            $("#wpsi-toggle-dashboard").slideDown();
            $("#wpsi-toggle-arrows").attr('class', 'dashicons dashicons-arrow-up-alt2');
        }
    });

    setTimeout(function() {
        document.documentElement.classList.add('wpsi-grid-loaded');
    }, 50); // Small delay to ensure grid is fully initialized

});
