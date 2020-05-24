jQuery(document).ready(function($) {

    var drag_enabled_or_disabled;

    // Disable drag on mobile
    if (!isMobileDevice()) {
        drag_enabled_or_disabled = true;
    } else {
        drag_enabled_or_disabled = false;
    }

    initGrid();

    function initGrid() {
        $('.wpsi-grid').each(function(){
           var grid_type = $(this).data('grid_type');
            initGridByClass(grid_type);
        });
    }

    /**
     * Init by classname
     * @param className
     */
    function initGridByClass(className){
        var grid = new Muuri('.wpsi-grid.'+className, {
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
            dragStartPredicate: function(item, e) {
                return e.target.className === 'wpsi-drag-handle';
            }
        })

            .on('move', function () {
                saveLayout(grid, className);
            });

        var layout = window.localStorage.getItem('wpsi_layout_'+className);
        if (layout) {
            loadLayout(grid, layout, className);
        } else {
            grid.layout(true);
        }
        // Must save the layout on first load, otherwise filtering the grid won't work on a new install.
        saveLayout(grid, className);
    }

    /**
     * Serialize the layout
     * @param grid
     * @returns {string}
     */

    function serializeLayout(grid) {
        var itemIds = grid.getItems().map(function (item) {
            return item.getElement().getAttribute('data-id');
        });
        return JSON.stringify(itemIds);
    }

    /**
     * Save the layout
     * @param grid
     * @param className
     */
    function saveLayout(grid, className) {
        var layout = serializeLayout(grid);
        window.localStorage.setItem('wpsi_layout_'+className, layout);
    }

    /**
     * Load a grid layout
     * @param grid
     * @param serializedLayout
     */

    function loadLayout(grid, serializedLayout) {
        var layout = JSON.parse(serializedLayout);
        var currentItems = grid.getItems();
        // // Add or remove the muuri-active class for each checkbox. Class is used in filtering.
        $('.wpsi-item').each(function(){
            var toggle_id = $(this).data('id');
            if (localStorage.getItem("wpsi_toggle_data_id_"+toggle_id) === null) {
                window.localStorage.setItem('wpsi_toggle_data_id_'+toggle_id, 'checked');
            }

            // // Add or remove the active class when the checkbox is checked/unchecked
            if (window.localStorage.getItem('wpsi_toggle_data_id_'+toggle_id) == 'checked') {
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
        grid.sort(newItems, {layout: 'instant'});
        grid.filter('.muuri-active');
    }


    // Reload the grid when checkbox value changes
    $('.wpsi-item').each(function(){
        var toggle_id = $(this).data('id');
        // Set defaults for localstorage checkboxes
        if (!window.localStorage.getItem('wpsi_toggle_data_id_'+toggle_id)) {
            window.localStorage.setItem('wpsi_toggle_data_id_'+toggle_id, 'checked');
        }
        $('#wpsi_toggle_data_id_'+toggle_id).change(function() {
            if (document.getElementById("wpsi_toggle_data_id_"+toggle_id).checked ) {
                window.localStorage.setItem('wpsi_toggle_data_id_'+toggle_id, 'checked');
            } else {
                window.localStorage.setItem('wpsi_toggle_data_id_'+toggle_id, 'unchecked');
            }
            initGrid();
        });
    });

    /**
     * check if it's a mobile device
     * @returns {boolean}
     */
    function isMobileDevice() {
        return (typeof window.orientation !== "undefined") || (navigator.userAgent.indexOf('IEMobile') !== -1);
    };
});
