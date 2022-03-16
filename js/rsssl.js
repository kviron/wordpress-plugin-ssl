jQuery(document).ready(function ($) {
    'use strict';
    var rsssl_interval = 3000;
    var progress = rsssl_ajax.progress;
    var progressBar = $('#rsssl-scan-list').find('.progress-bar');
    //need to count before the datatable is initialized. Otherwise the count is the page size.
    var rsssl_scan_results = $('#rsssl-scan-results tr').length;

    rsslInitScanDatatable();
    initCspTable();
    var scan_paginate;
    function rsslInitScanDatatable(){
        var scan_table = $('#rsssl-scan-results').DataTable( {
            "pageLength": 4,
            "info": false,
            "language" : {
                "emptyTable": rsssl_ajax.emptyScanTable,
                "paginate": {
                    "first": rsssl_ajax.first,
                    "previous": rsssl_ajax.previous,
                    "next": rsssl_ajax.next,
                    "last": rsssl_ajax.last,
                },
            },
        });

        var pageLength = scan_table.page.len();
        scan_paginate = $("#rsssl-scan-results_paginate").detach()

        if (rsssl_scan_results > pageLength) {
            $("#rsssl-scan-pagination").append(scan_paginate);
        }
    }

    $.extend( $.fn.dataTable.defaults, {
        "bSort": false,
        "stateSave": true,
        "paging":   true,
        "ordering": false,
        "conditionalPaging": true,
        "bAutoWidth": false,
        "autoWidth": false,
        "language" : {
            "search": "",
            "sSearch" : "",
            "searchPlaceholder": rsssl_ajax.searchPlaceholder,
            "paginate": {
                "first": rsssl_ajax.first,
                "previous": rsssl_ajax.previous,
                "next": rsssl_ajax.next,
                "last": rsssl_ajax.last,
            },
        },
    } );

    function initCspTable() {
        $('#rsssl-csp-table').DataTable({
            "pageLength": 8,
            "bSort": false,
            "bFilter" : false,
            "language": {
                "emptyTable": rsssl_ajax.emptyCspTable,
            },
        });
        var datatable_csp_paginate = $("#rsssl-csp-table_paginate").detach();
        var csp_footer = $(".rsssl-content-security-policy  .rsssl-grid-item-footer");
        csp_footer.html('');
        csp_footer.append(datatable_csp_paginate);
        var datatable_csp_info = $("#rsssl-csp-table_info").detach();
        csp_footer.append('<div id="rsssl-csp-entries"></div>');
        $('#rsssl-csp-entries').append(datatable_csp_info);
    }

    $('#rsssl_content_security_policy_toggle').on('change', function () {
        var value = $(this).val();
        var token = $(this).data("token");
        $.post(
            rsssl_ajax.ajaxurl,
            {
                action: 'rsssl_update_csp_toggle_option',
                rsssl_csp_toggle_value: value,
                token: token,
            },
            function (response) {
                $('.rsssl-csp-table-container').html('');
                loadCspTable(token);
            }
        );
    });

    $('#rsssl-security-headers-method').on('change', function () {
        var value = $(this).val();
        var token = $(this).data("token");
        $.post(
            rsssl_ajax.ajaxurl,
            {
                action: 'rsssl_update_security_headers_method_value',
                rsssl_security_headers_method_value: value,
                token: token,
            },
            function (response) {
                // $('.rsssl-csp-table-container').html('');
                // loadCspTable(token);
            }
        );
    });

    function loadCspTable(token) {
        $.post(
            rsssl_ajax.ajaxurl,
            {
                action: 'rsssl_load_csp_table',
                token: token,
            },
            function (response) {
                $('.rsssl-csp-table-container').html(response);
                initCspTable();
            }
        );
    }

    var permissions_policy_table = $('#rsssl-permission-policy-table').DataTable( {
        "info": false,
        "pageLength": 6,
    });

    // Add hidden fields to submit all rows in table. Without this, only the current page will be posted!
    $('form').on('submit', function(e){
        var $form = $(this);

        // Iterate over all radio vals in the table
        permissions_policy_table.$('input[type="radio"]').each(function(){
            // If checkbox doesn't exist in DOM
            if(!$.contains(document, this)){
                // If checkbox is checked
                if(this.checked){
                    // Create a hidden element
                    $form.append(
                        $('<input>')
                            .attr('type', 'hidden')
                            .attr('name', this.name)
                            .val(this.value)
                    );
                }
            }
        });
    });

    progressBar.css({width: progress + '%'});

    setup_scan();

    function setup_scan() {
        get_scan_progress();
        window.setInterval(function () {
            get_scan_progress()
        }, rsssl_interval);
    }

    $('#rsssl-permission-policy-table input:radio').click(function() {
        $('.rsssl-button-save').prop('disabled', false);
    });

    $('.paginate_button').click(function() {
        $('.rsssl-button-save').prop('disabled', false);
    });

    var fp_paginate = $("#rsssl-permission-policy-table_paginate").detach();
    $(".rsssl-permissions-policy .rsssl-grid-item-footer").append(fp_paginate);
    var datatable_documentation_paginate = $("#rsssl-documentation-table_paginate").detach();
    $("#rsssl-documentation-pagination-placeholder").append(datatable_documentation_paginate);

    var progress_paging = $("#rsssl-scan-results_info").detach();
    $("#progress-page-count").append(progress_paging);

    // Required to line out 'Advanced settings' heading
    $( ".rsssl-advanced-settings" ).closest( "th" ).css( "display", "block" );

    function get_scan_progress() {
        if (progress >= 100) return;
        if (progress==0) progressBar.css({width: '1%'});
        progressBar.removeClass('progress-bar-success');
        $('#rsssl-scan-output').html("");
        $("#rsssl-scan-pagination #rsssl-scan-results_paginate").remove();
        $.post(
            rsssl_ajax.ajaxurl,
            {
                action: 'get_scan_progress'
            },
            function (response) {
                var obj;
                if (response) {
                    obj = jQuery.parseJSON(response);
                    progress = parseInt(obj['progress']);

                    if (progress >= 100) {
                        progressBar.html(obj['action']);
                        progressBar.css({width: progress + '%'});
                        progressBar.addClass('progress-bar-success');
                        $('#rsssl-scan-output').html(decodeURIComponent(obj['output']));
                        rsslInitScanDatatable();
                        $('#rsssl_stop_scan').hide();
                    } else {
                        progressBar.html(obj['action']);
                        progressBar.css({width: progress + '%'});
                    }
                }
            }
        );
    }

    /* Start fix post */
    $(document).on('click', "#start-fix-post", function (e) {

        /*Show loader css after clicking fix button*/
        var btn = $(this);
        var btnContent = btn.html();
        btn.html('<div class="rsssl-loader"><div class="rect1"></div><div class="rect2"></div><div class="rect3"></div><div class="rect4"></div><div class="rect5"></div></div>');

        btn.prop('disabled', true);
        var post_id = $(this).data('id');
        var path = $(this).data('path');
        var url = $(this).data('url');
        var action = 'fix_post';
        var token = $(this).data("token");
        var caller = $(this).data("results_id");
        $.post(
            rsssl_ajax.ajaxurl,
            {
                action: action,
                token: token,
                url: url,
                path: path,
                post_id: post_id,
            },

            function (response) {
                btn.html(btnContent);
                btn.prop('disabled', false);

                if (response.success) {
                    rsssl_remove_from_results(caller);
                    $("#fix-post-modal").hide();
                    rsssl_RemoveBackdrop();
                } else {
                    $("#fix-post-modal").append(response.error);
            }
        });
    });

    $(document).on('click', "#start-fix-postmeta", function (e) {
        /*Show loader css after clicking fix button*/
        var btn = $(this);
        var btnContent = btn.html();
        btn.html('<div class="rsssl-loader"><div class="rect1"></div><div class="rect2"></div><div class="rect3"></div><div class="rect4"></div><div class="rect5"></div></div>');
        btn.prop('disabled', true);
        var post_id = $(this).data('id');
        var path = $(this).data('path');
        var url = $(this).data('url');
        var action = 'fix_postmeta';
        var token = $(this).data("token");
        var caller = $(this).data("results_id");
        console.log(caller);
        $.post(
            rsssl_ajax.ajaxurl,
            {
                action: action,
                token: token,
                url: url,
                path: path,
                post_id: post_id,
            },
            function (response) {
                btn.html(btnContent);
                btn.prop('disabled', false);

                if (response.success) {
                    rsssl_remove_from_results(caller);
                    $("#fix-postmeta-modal").hide();
                    rsssl_RemoveBackdrop();
                } else {
                    $("#fix-postmeta-modal").append(response.error);
                }
            }
        );
    });

    $(document).on('click', "#start-fix-file", function (e) {

        /*Show loader css after clicking fix button*/
        var btn = $(this);
        var btnContent = btn.html();
        btn.html('<div class="rsssl-loader"><div class="rect1"></div><div class="rect2"></div><div class="rect3"></div><div class="rect4"></div><div class="rect5"></div></div>');

        btn.prop('disabled', true);
        var post_id = $(this).data('id');
        var path = $(this).data('path');
        var url = $(this).data('url');
        var action = 'fix_file';
        var token = $(this).data("token");
        var caller = $(this).data("results_id");

        $.post(
            rsssl_ajax.ajaxurl,
            {
                action: action,
                post_id: post_id,
                token: token,
                url: url,
                path: path,
            },
            function (response) {
                btn.html(btnContent);
                btn.prop('disabled', false);

                if (response.success) {
                    rsssl_remove_from_results(caller);
                    $("#fix-file-modal").hide();
                    rsssl_RemoveBackdrop();
                } else {
                    $("#fix-file-modal").append(response.error);
                }
            }
        );
    });

    $(document).on('click', "#rsssl_pro_disable_for_other_users", function (e) {
        var checkbox_value;
        if ($('#rsssl_pro_disable_for_other_users').is(":checked")) {
            checkbox_value = 'checked';
        } else {
            checkbox_value = 'unchecked';
        }

            $.post(
            rsssl_ajax.ajaxurl,
            {
                action: 'maybe_update_disable_license',
                value: checkbox_value,
            },
        );
    });

    $(document).on('click', "#start-ignore-url", function (e) {

        /*Show loader css after clicking fix button*/
        var btn = $(this);
        var btnContent = btn.html();

        btn.html('<div class="rsssl-loader"><div class="rect1"></div><div class="rect2"></div><div class="rect3"></div><div class="rect4"></div><div class="rect5"></div></div>');

        btn.prop('disabled', true);
        var post_id = $(this).data('id');
        var path = $(this).data('path');
        var action = 'ignore_url';
        var url = $(this).data('url');
        var token = $(this).data("token");
        var caller = $(this).data("results_id");

        $.post(
            rsssl_ajax.ajaxurl,
            {
                action: action,
                path: path,
                token: token,
                url: url,
                post_id: post_id,
            },

            function (response) {
                btn.html(btnContent);
                btn.prop('disabled', false);

                if (response.success) {
                    rsssl_remove_from_results(caller);
                    $(".rsssl-modal").hide();
                    rsssl_RemoveBackdrop();
                } else {
                    $("#details-modal").append(response.error);
                }
            }
        );
    });

    $(document).on('click', "#start-fix-widget", function (e) {

        /*Show loader css after clicking fix button*/
        var btn = $(this);
        var btnContent = btn.html();
        btn.html('<div class="rsssl-loader"><div class="rect1"></div><div class="rect2"></div><div class="rect3"></div><div class="rect4"></div><div class="rect5"></div></div>');

        btn.prop('disabled', true);
        var widget_id = $(this).data('id');
        var path = $(this).data('path');
        var url = $(this).data('url');
        var action = 'fix_widget';
        var token = $(this).data("token");
        var caller = $(this).data("results_id");

        $.post(
            rsssl_ajax.ajaxurl,
            {
                action: action,
                //post_id: post_id,
                token: token,
                url: url,
                path: path,
                widget_id: widget_id,
            },
            function (response) {
                btn.html(btnContent);
                btn.prop('disabled', false);

                if (response.success) {
                    rsssl_remove_from_results(caller);
                    $(".rsssl-modal").hide();
                    rsssl_RemoveBackdrop();
                } else {
                    $("#fix-widget-modal").append(response.error);
                }

            }
        );
        $(this).prop('disabled', false);
    });

    //Content Security Policy
    $(document).on("click", ".start-add-to-csp", function () {

        /*Show loader css after clicking button*/
        var btn = $(this);
        var id = btn.attr('data-id');
        // var btnContent = btn.html();
        btn.html('<div class="rsssl-loader"><div class="rect1"></div><div class="rect2"></div><div class="rect3"></div><div class="rect4"></div><div class="rect5"></div></div>');
        btn.prop('disabled', true);

        var action = 'rsssl_update_in_policy_value';
        var token = $(this).data("token");
        var add_revoke = 'add';

        $.post(
            rsssl_ajax.ajaxurl,
            {
                action: action,
                token: token,
                id: id,
                add_revoke: add_revoke,
            },
            function (response) {
                btn.closest('tr').remove();
            }
        );
    });

    $(document).on("click", "#start-revoke-from-csp", function () {

        /*Show loader css after clicking button*/
        var btn = $(this);
        var btnContent = btn.html();
        var id = btn.attr('data-id');
        var add_revoke = 'revoke';
        btn.html('<div class="rsssl-loader"><div class="rect1"></div><div class="rect2"></div><div class="rect3"></div><div class="rect4"></div><div class="rect5"></div></div>');
        btn.prop('disabled', true);

        var action = 'rsssl_update_in_policy_value';
        var token = $(this).data("token");

        $.post(
            rsssl_ajax.ajaxurl,
            {
                action: action,
                token: token,
                id: id,
                add_revoke: add_revoke,
            },
            function (response) {
                btn.html(btnContent);
                btn.prop('disabled', false);
                $(".rsssl-modal").hide();
                rsssl_remove_csp_from_results(id);
                rsssl_RemoveBackdrop();
                }
        );
    });

    $(document).on("click", "#start-revoke-delete-from-csp", function () {

        /*Show loader css after clicking button*/
        var btn = $(this);
        var btnContent = btn.html();
        var id = btn.attr('data-id');
        var add_revoke = 'revoke';
        btn.html('<div class="rsssl-loader"><div class="rect1"></div><div class="rect2"></div><div class="rect3"></div><div class="rect4"></div><div class="rect5"></div></div>');
        btn.prop('disabled', true);

        var action = 'rsssl_update_in_policy_value';
        var token = $(this).data("token");

        $.post(
            rsssl_ajax.ajaxurl,
            {
                action: action,
                token: token,
                id: id,
                add_revoke: add_revoke,
            },
            function (response) {
                rsssl_delete_from_csp(id, token);
                btn.prop('disabled', false);
                btn.html(btnContent);
                rsssl_remove_csp_from_results(id);
                rsssl_RemoveBackdrop();
            }
        );
    });

    function rsssl_delete_from_csp(id, token) {
        $.post(
            rsssl_ajax.ajaxurl,
            {
                action: 'rsssl_delete_from_csp',
                token: token,
                id: id,
            },
            function (response) {
                $("#revoke-csp-modal").hide();
                rsssl_remove_csp_from_results(id);
            }
        );
    }

    $(document).on('click', "#start-roll-back", function (e) {
        $(this).prop('disabled', true);
        var token = $(this).data("token");
        $.post(
            rsssl_ajax.ajaxurl,
            {
                action: 'roll_back',
                token: token,
            },
            function (response) {
                $("#roll-back-modal").append(response.error);
            }
        );
        $(this).prop('disabled', false);
    });

    $("#start-fix-cssjs").click(function (e) {
        $(this).prop('disabled', true);

        var path = $(this).data('path');
        var url = $(this).data('url');
        var token = $(this).data("token");
        var caller = $(this).data("results_id");
        $.post(
            rsssl_ajax.ajaxurl,
            {
                action: 'fix_cssjs',
                token: token,
                url: url,
                path: path,
            },
            function (response) {
                if (response.success) {
                    rsssl_remove_from_results(caller);
                    $("#fix-cssjs-modal").modal('hide');
                } else {
                    $("#fix-cssjs-modal").append(response.error);
                }
            }
        );
        $(this).prop('disabled', false);
    });

    /**
     * Remove an entry in the datatables list based on an id.
     * @param results_id
     */
    function rsssl_remove_from_results(results_id) {
        $('#rsssl-scan-results [data-results_id='+results_id+']').closest('tr').addClass('rsssl-remove-row');
        var table = $('#rsssl-scan-results').DataTable();
        table.row('.rsssl-remove-row').remove().draw(false);
    }

    function rsssl_remove_csp_from_results(id) {
        $('#rsssl-csp-table [data-id='+id+']').closest('tr').addClass('rsssl-remove-row');
        var table = $('#rsssl-csp-table').DataTable();
        table.row('.rsssl-remove-row').remove().draw(false);
    }

    /*handle options change in advance field*/
    $(document).on('change', '#rsssl_show_ignore_urls', function () {
        $("#rsssl_scan_form").append('<input type="hidden" name="rsssl_no_scan" value="rsssl_no_scan" />');
        $("#rsssl_scan_form").submit();
    });

    rsssl_pro_show_hide_preload();
    $(document).on('change', 'input[name=rsssl_hsts]', function(){
        rsssl_pro_show_hide_preload();
    });


    function rsssl_pro_show_hide_preload() {
        if ($("input[name='rsssl_hsts").is(':checked')) {
            $('input[name="rsssl_hsts_preload"]').closest('tr').show();
            $('.hsts_preload_expl').show();
        } else {
            $('input[name="rsssl_hsts_preload"]').closest('tr').hide();
            $('.hsts_preload_expl').hide();
        }
    }

    rsssl_pro_show_hide_permissions_policy();
    function rsssl_pro_show_hide_permissions_policy() {
        if ($("input[name='rsssl_turn_on_permissions_policy']").is(':checked')) {
            $('#permissions-policy-settings').closest('tr').show("slow");
        } else {
            $('#permissions-policy-settings').closest('tr').hide();
        }
    }

    $(document).on('change', 'input[name="rsssl_turn_on_permissions_policy"]', function () {
        rsssl_pro_show_hide_permissions_policy();
    });

    $(document).on('change', 'input[name="rlrsssl_options[hsts]"]', function () {
        rsssl_pro_show_hide_preload();
    });

    /**
     * Modals
     */

    rsssl_addEvent('click', '.fix-button', function(e) {

        let modal = document.getElementById(e.target.dataset.target);
        let modalBtns = modal.getElementsByClassName('button');

        // Assign vals from fix button to modal button
        let postId = e.target.dataset.id;
        modalBtns[0].setAttribute('data-id', postId);
        let url = e.target.dataset.url;
        modalBtns[0].setAttribute('data-url', url);
        let path = e.target.dataset.path;
        modalBtns[0].setAttribute('data-path', path);

        rsssl_AddBackdrop();
        // Now unhide the modal
        modal.style.display = 'block';

    });

    // on click details button
    rsssl_addEvent('click', '.details-button', function(e) {

        let modal = document.getElementById(e.target.dataset.target);
        let modalBtns = modal.getElementsByClassName('button');

        let editBtn = modalBtns[0];
        let helpBtn = modalBtns[1];
        let ignoreBtn = modalBtns[2];

        let postId = e.target.dataset.id;
        let url = e.target.dataset.url;
        let token = e.target.dataset.token;
        let editLink = e.target.dataset.editLink;
        let helpLink = e.target.dataset.helpLink;

        let description = e.target.dataset.description;
        let modalContent = modal.querySelector('.rsssl-modal-content');
        modalContent.innerHTML = description;

        editBtn.setAttribute('data-id', postId);

        if ( editLink === 'FILE_EDIT_BLOCKED' ) {
            editBtn.disabled = true;
        } else {
            editBtn.href = editLink;
        }

        helpBtn.setAttribute('data-id', postId);
        helpBtn.href = helpLink;

        ignoreBtn.setAttribute('data-id', postId);
        ignoreBtn.setAttribute('data-url', url);
        ignoreBtn.setAttribute('data-token', token);

        rsssl_AddBackdrop();

        modal.style.display = 'block';

    });

    rsssl_addEvent('click', '.revoke-from-csp', function(e) {

        let modal = document.getElementById(e.target.dataset.target);
        let modalBtns = modal.getElementsByClassName('button');

        let id = e.target.dataset.id;
        modalBtns[0].setAttribute('data-id', id);
        modalBtns[1].setAttribute('data-id', id);

        rsssl_AddBackdrop();

        modal.style.display = 'block';
    });

    // Close modal
    rsssl_addEvent('click', '.rsssl-modal-close', function(e) {

        let modal = e.target.closest('.rsssl-modal');
        let alert = modal.querySelector('.alert');

        if ( alert ) {
            modal.querySelector('.alert').remove();
        }

        modal.style.display = 'none';

        rsssl_RemoveBackdrop();
    });
});