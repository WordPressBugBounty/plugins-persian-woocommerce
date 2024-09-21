(function ($) {
    $(document).ready(function(){
        $(document).ajaxComplete(function() {

            $('select.components-select-control__input option').filter(function () {
                return $(this).text() === 'در هفته';
            }).remove();
        });
    });
    $(document).on('click', '.woocommerce-dropdown-button', function () {

        $('[id^="tab-panel-"][id$="-custom"]').css({'display':'none'})

    });

})(jQuery);
