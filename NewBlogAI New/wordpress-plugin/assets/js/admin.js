jQuery(document).ready(function($) {
    // Standard WordPress Tab Switching for NewsBlogify Dashboard
    $('.newsblogify-nav-tab').on('click', function(e) {
        e.preventDefault();
        var targetTab = $(this).data('tab');

        $('.newsblogify-nav-tab').removeClass('nav-tab-active');
        $('.newsblogify-tab-content').removeClass('active');

        $(this).addClass('nav-tab-active');
        $('#tab-' + targetTab).addClass('active');
    });
});
