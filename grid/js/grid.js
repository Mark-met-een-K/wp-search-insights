jQuery(document).ready(function($) {
    initGrid();
    console.log("initialized");

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
            layoutOnInit: false
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
        var currentItemIds = currentItems.map(function (item) {
            return item.getElement().getAttribute('data-id')
        });
        var newItems = [];
        var itemId;
        var itemIndex;

        window.$ = document.querySelectorAll.bind(document);

        for (var i = 0; i < layout.length; i++) {
            itemId = layout[i];
            itemIndex = currentItemIds.indexOf(itemId);
            if (itemIndex > -1) {
                    newItems.push(currentItems[itemIndex])
            }
        }

        // if ($('toggle_data_id_' + itemId).checked) {
        //     console.log("Is checked, adding");
        //     console.log("currentItems");
        // }

        // https://codepen.io/JeffMaciejko/pen/OZOKGM
        // grid.hide([elemA, elemB], {instant: true})

         grid.sort(newItems, {layout: 'instant'});
         // grid.filter('.grid-active');
    }
});