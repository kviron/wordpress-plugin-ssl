jQuery(document).ready(function ($) {
    $(document).on('click', '.rsssl-install-plugin', function () {
        var btn = $('button.rsssl-install-plugin');
        var loader = '<div class="rsssl-pro-loader"><div class="rect1"></div><div class="rect2"></div><div class="rect3"></div><div class="rect4"></div><div class="rect5"></div></div>';
        btn.html(loader);
        btn.attr('disabled', 'disabled');
        $.ajax({
            type: "GET",
            url: rsssl_admin.admin_url,
            dataType: 'json',
            data: ({
                step: 'download',
                action: 'rsssl_install_plugin',
            }),
            success: function (response) {
                $.ajax({
                    type: "GET",
                    url: rsssl_admin.admin_url,
                    dataType: 'json',
                    data: ({
                        step: 'activate',
                        action: 'rsssl_install_plugin',
                    }),
                    success: function (response) {
                        window.location = rsssl_admin.plugin_page_url;
                    }
                });
            }
        });
    });
});