jQuery(document).ready(function ($) {
    "use strict";

    /**
     * Datatables
     */

    // Initialize Datatables
    $('#search-insights-recent-table').DataTable( {
        conditionalPaging: true,
        //https://datatables.net/reference/option/dom
        "dom": 'rt<"table-footer"iBp><"clear">',
        buttons: [
        'csv', 'excel'
        ],
        "language": {
            "paginate": {
                "previous": "<i class='icon-left-open'></i>",
                "next": "<i class='icon-right-open'></i>"
            },
            "emptyTable" : "No searches recorded yet!"
        },
        "order": [[ 1, "desc" ]]
    });

    $('#search-insights-most-popular-table').DataTable( {
        conditionalPaging: true,
        //https://datatables.net/reference/option/dom
        "dom": 'rt<"table-footer"iBp><"clear">',
        buttons: [
            'csv', 'excel'
        ],
        // "columns": [
        //     { "width": "20%" },
        //     { "width": "10%" },
        //     { "width": "10%" },
        // ],
        fixedHeader: {
            footer: true
        },
        "language": {
            "paginate": {
                "previous":"<i class='icon-left-open'></i>",
                "next": "<i class='icon-right-open'></i>"
            },
            "emptyTable" : "No searches recorded yet!"
        },
        "order": [[ 1, "desc" ]]
    });

    /**
     * Show/hide dashboard items
     */


    //Get the window hash for redirect to #settings after settings save
    var hash = "#" + window.location.hash.substr(1);

        $('ul.tabs li').click(function () {
        var tab_id = $(this).attr('data-tab');

        $('ul.tabs li').removeClass('current');
        $('.tab-content').removeClass('current');

        $(this).addClass('current');
        $("#" + tab_id).addClass('current');
    });

    // setTimeout(function() {
    var href = $('.tab-settings').attr('href');
    if (href === hash) {
        $('.tab-settings')[0].click();
        window.location.href = href; //causes the browser to refresh and load the requested url
    }
    // },15);

    /**
     * Checkboxes
     */

    // Save checkbox values

    var formValues = JSON.parse(localStorage.getItem('formValues')) || {};
    var $checkboxes = $("#wpsi-toggle-dashboard :checkbox");

    function updateStorage(){
        $checkboxes.each(function(){
            formValues[this.id] = this.checked;
        });

        localStorage.setItem("formValues", JSON.stringify(formValues));
    }

    $checkboxes.on("change", function(){
        updateStorage();
    });

// On page load
    $.each(formValues, function(key, value) {
        $("#" + key).prop('checked', value);
    });

    $("#wpsi-toggle-dashboard").hide();

    $('#wpsi-show-toggles').click(function(){
        if ($("#wpsi-toggle-dashboard").is(":visible") ){
            $("#wpsi-toggle-dashboard").hide();
            $("#wpsi-toggle-arrows").attr('class', 'dashicons dashicons-arrow-down');
        } else {
            $("#wpsi-toggle-dashboard").show();
            $("#wpsi-toggle-arrows").attr('class', 'dashicons dashicons-arrow-up');

        }

    });



});

