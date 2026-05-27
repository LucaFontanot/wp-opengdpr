(function ($) {
    'use strict';

    $(function () {
        // Sync TinyMCE editors before form submission
        $(document).on('submit', 'form', function () {
            if (typeof tinyMCE !== 'undefined') {
                tinyMCE.triggerSave();
            }
        });

        if ($.fn.wpColorPicker) { $('.wpog-color').wpColorPicker(); }

        // Media picker
        $(document).on('click', '.wpog-media-pick', function (e) {
            e.preventDefault();
            var $btn = $(this);
            var frame = wp.media({ title: 'Select image', multiple: false });
            frame.on('select', function () {
                var url = frame.state().get('selection').first().toJSON().url;
                $btn.prev('input.wpog-media').val(url);
            });
            frame.open();
        });

        // Cookie row add/remove
        $(document).on('click', '.wpog-row-add', function () {
            var $btn = $(this);
            var cat = $btn.data('cat');
            var i = parseInt($btn.data('index'), 10);
            var fields = ['name', 'provider', 'duration', 'purpose', 'privacy'];
            var row = '<tr>';
            fields.forEach(function (f) {
                row += '<td><input type="' + (f === 'privacy' ? 'url' : 'text') + '" name="wpog[' + cat + '][cookies][' + i + '][' + f + ']" /></td>';
            });
            row += '<td><button type="button" class="button wpog-row-del">&times;</button></td></tr>';
            $btn.closest('.inside').find('table.wpog-cookies-table tbody').append(row);
            $btn.data('index', i + 1);
        });
        $(document).on('click', '.wpog-row-del,.wpog-script-del,.wpog-blocker-del', function () {
            $(this).closest('tr').remove();
        });

        // Script row add
        $('#wpog-script-add').on('click', function () {
            var $btn = $(this);
            var i = parseInt($btn.data('index'), 10);
            var cats = ['necessary', 'functional', 'analytics', 'marketing'];
            var types = [['inline', 'Inline'], ['src', 'External src'], ['iframe', 'Iframe']];
            var positions = ['head', 'body-top', 'body-bottom'];
            var html = '<tr>' +
                '<td><input type="text" name="wpog[scripts][' + i + '][name]" /></td>' +
                '<td><select name="wpog[scripts][' + i + '][category]">' + cats.map(function (c) { return '<option value="' + c + '">' + c + '</option>'; }).join('') + '</select></td>' +
                '<td><select name="wpog[scripts][' + i + '][type]">' + types.map(function (t) { return '<option value="' + t[0] + '">' + t[1] + '</option>'; }).join('') + '</select></td>' +
                '<td><select name="wpog[scripts][' + i + '][position]">' + positions.map(function (p) { return '<option value="' + p + '">' + p + '</option>'; }).join('') + '</select></td>' +
                '<td><textarea rows="3" class="large-text code" name="wpog[scripts][' + i + '][content]"></textarea></td>' +
                '<td><input type="checkbox" name="wpog[scripts][' + i + '][active]" value="1" checked /></td>' +
                '<td><button type="button" class="button wpog-script-del">&times;</button></td>' +
                '</tr>';
            $('#wpog-scripts-body').append(html);
            $btn.data('index', i + 1);
        });

        // Translation reset
        $(document).on('click', '.wpog-tr-reset', function () {
            var id = $(this).data('target');
            $('#' + id).val('');
        });

        // Blocker row add (includes path field)
        $('#wpog-blocker-add').on('click', function () {
            var $btn = $(this);
            var i    = parseInt($btn.data('index'), 10);
            var cats = ['necessary', 'functional', 'analytics', 'marketing'];
            var html = '<tr>' +
                '<td><input type="text" name="wpog[rules][' + i + '][domain]" placeholder="example.com" /></td>' +
                '<td><input type="text" name="wpog[rules][' + i + '][path]" placeholder="/path/to/script" style="width:140px;" /></td>' +
                '<td><select name="wpog[rules][' + i + '][category]">' +
                    cats.map(function (c) { return '<option value="' + c + '">' + c + '</option>'; }).join('') +
                '</select></td>' +
                '<td><input type="text" name="wpog[rules][' + i + '][note]" placeholder="Vendor / description" /></td>' +
                '<td><input type="checkbox" name="wpog[rules][' + i + '][active]" value="1" checked /></td>' +
                '<td><button type="button" class="button wpog-blocker-del">&times;</button></td>' +
                '</tr>';
            $('#wpog-blocker-body').append(html);
            $btn.data('index', i + 1);
        });

        // Detection dialogs — open
        $(document).on('click', '.wpog-open-cookie-dlg', function () {
            var $b = $(this);
            $('#wpog-dlg-cookie-id').val($b.data('id'));
            $('#wpog-dlg-cookie-name').val($b.data('name'));
            // Reset other fields so they don't carry over from a previous open.
            $('#wpog-dlg-cookie-provider, #wpog-dlg-cookie-duration, #wpog-dlg-cookie-purpose, #wpog-dlg-cookie-privacy').val('');
            $('#wpog-dlg-cookie').show();
            $('#wpog-dlg-cookie').find('input,select').first().trigger('focus');
        });
        $(document).on('click', '.wpog-open-blocker-dlg', function () {
            var $b = $(this);
            $('#wpog-dlg-blocker-id').val($b.data('id'));
            $('#wpog-dlg-blocker-domain').val($b.data('domain') || '');
            $('#wpog-dlg-blocker-path').val($b.data('path') || '');
            $('#wpog-dlg-blocker-note').val($b.data('note') || '');
            $('#wpog-dlg-blocker').show();
            $('#wpog-dlg-blocker').find('input,select').first().trigger('focus');
        });

        // Detection dialogs — close
        $(document).on('click', '.wpog-dlg-close', function () {
            $(this).closest('.wpog-dlg-overlay').hide();
        });
        // Click on backdrop to close
        $(document).on('click', '.wpog-dlg-overlay', function (e) {
            if ($(e.target).hasClass('wpog-dlg-overlay')) {
                $(this).hide();
            }
        });
        // Escape key to close
        $(document).on('keydown', function (e) {
            if (e.key === 'Escape') { $('.wpog-dlg-overlay').hide(); }
        });
    });
})(jQuery);
