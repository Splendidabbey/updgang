!function($, window, document)
{
    "use strict";

    XF.LFSAjaxDataReplacerClick = XF.Click.newHandler({
        eventNameSpace: 'XFLFSAjaxDataReplacerClick',

        options: {
            name: '',
            val: '',
            groupId: ''
        },

        init: function() {},

        click: function() {
            if (this.options.groupId && this.options.name && this.options.val) {
                var data = {};
                data[this.options.name] = this.options.val;

                $(document).trigger('lfs_ajax_extend-' + this.options.groupId, [data]);
            }
        }
    });

    XF.LFSMuteClick = XF.Click.newHandler({
        eventNameSpace: 'XFLFSMuteClick',

        options: {
            widgetId: 0
        },

        init: function() {},

        click: function() {
            this.$target.toggleClass('is-muted');
            XF.Cookie.set('lfs_mute_' + this.options.widgetId, this.$target.hasClass('is-muted'));
        }
    });

    XF.Click.register('lfs-ajax-data-replacer', 'XF.LFSAjaxDataReplacerClick');
    XF.Click.register('lfs-mute', 'XF.LFSMuteClick');
}
(window.jQuery, window, document);