jQuery(document).ready(function ($) {
    "use strict";

    // Force modals to the end of the body for proper stacking
    setTimeout(function () {
        $('.wpsi-modal').appendTo('body');
    }, 500);

    function resetModal(modalId) {
        // Reset the specific modal based on its ID
        if (modalId === 'wpsi_export_modal') {
            // Dispatch an event for the export modal to listen to
            jQuery(document).trigger('wpsi_export_modal_reset');
        }
    }

    // When a trigger button is clicked, show the modal
    $(document).on('click', '.wpsi-modal-trigger', function (e) {
        e.preventDefault();
        var targetModal = $(this).data('target');

        // Sanitize ID to prevent CSS selector injection
        targetModal = targetModal.replace(/[^a-zA-Z0-9_-]/g, '');
        var $modal = $('#' + targetModal);

        if ($modal.length) {
            // Use addClass instead of show for better control
            $modal.addClass('wpsi-show-modal');
            $('body').addClass('wpsi-modal-open');
        }
    });

    // Close modal when clicking the X
    $(document).on('click', '.wpsi-modal-close, .wpsi-modal-cancel', function () {
        $(this).closest('.wpsi-modal').removeClass('wpsi-show-modal');
        $('body').removeClass('wpsi-modal-open');
        var modalId = $(this).closest('.wpsi-modal').attr('id');
        resetModal(modalId);
    });

    $(document).on('click mousedown mouseup', '.daterangepicker, .daterangepicker *', function (e) {
        e.stopPropagation();
    });

    // Close modal when clicking Cancel button
    $(document).on('click', '.wpsi-modal-cancel', function () {
        $(this).closest('.wpsi-modal').removeClass('wpsi-show-modal');
        $('body').removeClass('wpsi-modal-open');
        resetModal($(this).attr('id'));
    });

    // Close modal when clicking outside of it
    $(document).on('click', '.wpsi-modal', function (e) {
        if (e.target === this) {
            $(this).removeClass('wpsi-show-modal');
            $('body').removeClass('wpsi-modal-open');
            resetModal($(this).attr('id'));
        }
    });

    // Close on escape key
    $(document).on('keydown', function (e) {
        if (e.key === 'Escape' && $('.wpsi-modal.wpsi-show-modal').length) {
            $('.wpsi-modal.wpsi-show-modal').removeClass('wpsi-show-modal');
            $('body').removeClass('wpsi-modal-open');
            resetModal($(this).attr('id'));
        }
    });
});