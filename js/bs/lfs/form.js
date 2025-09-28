!function($, window, document)
{
    "use strict";

    XF.LFSSubmitRefresh = XF.Element.newHandler({
        init: function() {
            this.$target.on('submit', function () {
                setTimeout(function () {
                    $(document).trigger('lfs:refresh', [true]);
                }, 150);
            })
        }
    });

    XF.Element.register('lfs-submit-refresh', 'XF.LFSSubmitRefresh');
}
(window.jQuery, window, document);