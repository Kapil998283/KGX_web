// Admin Panel JavaScript

$(document).ready(function() {
    // Initialize tooltips
    $('[data-toggle="tooltip"]').tooltip();

    // Handle sidebar toggle on mobile
    $('.sidebar-toggle').click(function() {
        $('.admin-sidebar').toggleClass('active');
    });

    // Handle form submissions
    $('.admin-form').on('submit', function(e) {
        e.preventDefault();
        // Add your form submission logic here
    });

    // Handle delete confirmations
    $('.delete-btn').click(function(e) {
        if (!confirm('Are you sure you want to delete this item?')) {
            e.preventDefault();
        }
    });

    // Handle table row actions
    $('.action-btn').click(function() {
        const action = $(this).data('action');
        const id = $(this).data('id');
        
        switch(action) {
            case 'edit':
                // Handle edit action
                break;
            case 'delete':
                // Handle delete action
                break;
            case 'view':
                // Handle view action
                break;
        }
    });

    // Handle search functionality
    $('#search-input').on('keyup', function() {
        const value = $(this).val().toLowerCase();
        $('.admin-table tbody tr').filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
        });
    });

    // Handle pagination
    $('.pagination a').click(function(e) {
        e.preventDefault();
        const page = $(this).data('page');
        // Add your pagination logic here
    });
}); 