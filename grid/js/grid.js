jQuery(document).ready(function($) {

    initGrid();

    function initGrid() {

        var grid = new Muuri('.wpsi-grid', {
            dragEnabled: true,
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
            layoutOnInit: true
        })
        // .on('dragStart', function (item) {
            //     ++dragCounter;
            //     docElem.classList.add('dragging');
            //     item.getElement().style.width = item.getWidth() + 'px';
            //     item.getElement().style.height = item.getHeight() + 'px';
            // })
            // .on('dragEnd', function (item) {
            //     if (--dragCounter < 1) {
            //         docElem.classList.remove('dragging');
            //     }
            // })
            // .on('dragReleaseEnd', function (item) {
            //     item.getElement().style.width = '';
            //     item.getElement().style.height = '';
            //     columnGrids.forEach(function (muuri) {
            //         muuri.refreshItems();
            //     });
            // })
        .on('move', function () {
            saveLayout(grid);
        });

        var layout = window.localStorage.getItem('layout');
        if (layout) {
            loadLayout(grid, layout);
        } else {
            grid.layout(true);
        }
        // Must save the layout on first load, otherwise filtering the grid won't work on a new install.
        saveLayout(grid);
    }

    function serializeLayout(grid) {
        var itemIds = grid.getItems().map(function (item) {
            return item.getElement().getAttribute('data-id');
        });
        return JSON.stringify(itemIds);
    }

    function saveLayout(grid) {
        var layout = serializeLayout(grid);
        window.localStorage.setItem('layout', layout);
    }

    function loadLayout(grid, serializedLayout) {
        var layout = JSON.parse(serializedLayout);
        var currentItems = grid.getItems();
        // // Add or remove the muuri-active class for each checkbox. Class is used in filtering.
        $('.wpsi-item').each(function(){
            var toggle_id = $(this).data('id');
            console.log(toggle_id);
            if (localStorage.getItem("toggle_data_id_"+toggle_id) === null) {
                window.localStorage.setItem('toggle_data_id_'+toggle_id, 'checked');
                //a.prop("checked", true);
            }

            // Add or remove the active class when the checkbox is checked/unchecked
            if (window.localStorage.getItem('toggle_data_id_'+toggle_id) == 'checked') {
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

    // Set defaults for localstorage checkboxes


    // Reload the grid when checkbox value changes
    $('.wpsi-item').each(function(){
        var toggle_id = $(this).data('id');
        $('#toggle_data_id_'+toggle_id).change(function() {
            if (document.getElementById("toggle_data_id_"+toggle_id).checked ) {
                window.localStorage.setItem('toggle_data_id_'+toggle_id, 'checked');
            } else {
                window.localStorage.setItem('toggle_data_id_'+toggle_id, 'unchecked');
            }
            initGrid();
        });
    });
});