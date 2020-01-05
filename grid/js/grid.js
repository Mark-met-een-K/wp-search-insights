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

        // currentItems - niet gecheckte checkbox

        var a = document.querySelector('div[data-id="1"]');
        var b = document.querySelector('div[data-id="2"]');
        var c = document.querySelector('div[data-id="3"]');


        if (!document.getElementById("toggle_data_id_1").checked ) {
            console.log("removing class");
            a.classList.remove("muuri-active");
        } else {
            console.log("adding class");
            a.classList.add("muuri-active");
        }

        if (!document.getElementById("toggle_data_id_2").checked ) {
            b.classList.remove("muuri-active");
        } else {
            b.classList.add("muuri-active");
        }

        if (!document.getElementById("toggle_data_id_3").checked ) {
            c.classList.remove("muuri-active");
        } else {
            c.classList.add("muuri-active");
        }

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

        // if (document.getElementById('toggle_data_id_' + itemId + '').change) {
        //     initGrid();
        // }


        // if ($('toggle_data_id_' + itemId).checked) {
        //     console.log("Is checked, adding");
        //     console.log("currentItems");
        // }

        // https://codepen.io/JeffMaciejko/pen/OZOKGM
        // grid.hide([elemA, elemB], {instant: true})

        // grid.sort(newItems, {layout: 'instant'});
        grid.filter('.muuri-active');
    }

    // Bind action to checkbox change
    var checkboxes = document.getElementsByClassName('wpsi-toggle-items');

    for(var index in checkboxes){
        //bind event to each checkbox
        //refresh the grid on checkbox change
        checkboxes[index].onchange = initGrid();
    }

    // // Filter grid ------------------------------------------------
    // function filterGrid() {
    //
    //     // Get latest select values
    //     selectedCategory = categoryFilter.value;
    //
    //     // Reset filtered categories & types
    //     filteredCategories.splice(0,filteredCategories.length);
    //
    //
    //     // Types
    //     if( selectedType == 'all' ) {
    //         allTypes.forEach(function(item) {
    //             // exlude the actual 'all' value
    //             ( item.value !="all" ? filteredTypes.push(item.value) : '' );
    //         });
    //     } else {
    //         filteredTypes.push(typeFilter.value);
    //     }
    //     console.table(filteredTypes);
    //
    //     // Filter the grid
    //     // For each item in the grid array (which corresponds to a gallery item), check if the data-categories and data-types value match any of the values in the select field. If they do, return true
    //     grid.filter( (item) => {
    //         if (
    //             filteredCategories.some( (cat) => {
    //                 return (item.getElement().dataset.category).indexOf(cat) >= 0;
    //             })
    //         {
    //             // return items that match both IFs
    //             return true;
    //         }
    //
    //     });
    // } // end filter grid function
});