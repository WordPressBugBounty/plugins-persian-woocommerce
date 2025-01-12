(function ($) {
    $('document').ready(function () {

        // WooCommerce custom image input
        if ($(".pw_file_input_row").length) {
            $('.pw_file_input_row').each(function () {
                let pw_file_uploader_id = $(this).attr('data-uploader-id')
                let pw_file_upload_button = '#' + pw_file_uploader_id + '_upload_button';
                let pw_preview_image = '#' + pw_file_uploader_id + '_image';
                let pw_input_url = '.' + pw_file_uploader_id + '_input';
                let pw_file_remove_button = '#' + pw_file_uploader_id + '_remove_button';

                $('body').on('click', pw_file_upload_button, function (e) {
                    e.preventDefault();

                    let pw_custom_uploader = wp.media({
                        title: 'درج فایل',
                        library: {
                            type: 'image'
                        },
                        button: {
                            text: 'انتخاب'
                        },
                        multiple: false
                    }).on('select', function () {
                        let pw_attachment = pw_custom_uploader.state().get('selection').first().toJSON();
                        $(pw_preview_image).attr('src', pw_attachment.url);
                        $(pw_input_url).val(pw_attachment.url);
                        $(pw_file_remove_button).show();
                    }).open();

                });

                $('body').on('click', pw_file_remove_button, function (e) {
                    e.preventDefault();
                    $(this).hide();
                    $(pw_input_url).val('');
                    $(pw_file_upload_button).show();
                    $(pw_preview_image).attr('src', '');

                    return false;
                });

            });
        }

    });
})(jQuery);