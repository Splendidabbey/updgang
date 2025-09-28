!function($, window, document)
{
    "use strict";

    XF.LFSGroup = XF.create({
        options: {
            groupId: '',
            carouselInterval: 0
        },

        lfs: null,
        tabs: null,
        $target: null,

        ajaxData: {},

        updateLock: false,

        __construct: function(lfs, $target, options) {
            this.options = XF.applyDataOptions(this.options, $target.data(), options);
            this.lfs = lfs;
            this.$target = $target;
            this.$groupContent = $target.find('.tabGroup-content');
            this.$groupScroller = $target.find('.tabGroup-scroller');
            this.$content = $target.find('.content');

            this.defineScrollPaginator();

            if (this.options.carouselInterval) {
                setInterval(XF.proxy(this, 'switchToNextTab'), this.options.carouselInterval * 1000);
            }

            this.tabs = new XF.LFSTabs(lfs, this, $target.find('ul.tabs'));

            $(document).on('lfs_ajax_extend-' + this.options.groupId, XF.proxy(this, 'extendAjaxData'));

            if (XF.Cookie.get('lfs_ajax_' + this.options.groupId)) {
                this.ajaxData = XF.Cookie.get('lfs_ajax_' + this.options.groupId);
            }
        },

        defineScrollPaginator: function() {
            this.paginator = new XF.LFSScrollPaginator(this, this.$groupScroller);
        },

        resetScroll: function() {
            this.$groupScroller.scrollTop(0);
        },

        update: function(appendSpinner, reloadPagination) {
            this.startUpdating(appendSpinner);

            this.tabs.selectedTab.loadData(XF.proxy(this, 'endUpdating'), false, reloadPagination);
        },

        $scrollerLoadingSpinner: null,

        startUpdating: function(appendSpinner) {
            this.updateLock = true;

            if (appendSpinner) {
                this.$groupContent.addClass('is-updating');
                this.$scrollerLoadingSpinner = this.getLoadingSpinner().appendTo(this.$groupScroller);
            }
        },

        endUpdating: function() {
            this.updateLock = false;
            this.$groupContent.removeClass('is-updating');

            if (this.$scrollerLoadingSpinner) {
                this.$scrollerLoadingSpinner.remove();
            }
        },

        loading: function() {
            this.$content.html('');
            this.getLoadingSpinner().appendTo(this.$groupScroller);
        },

        switchToNextTab: function() {
            this.tabs.next().select();
        },

        clearDifferentTimeout: null,

        replaceContent: function($html, callback, selectChange, reloadPagination) {
            let success = false;

            if ($html.html().replace(' ', '').length > 0) {
                this.$content.find('*:regex(data-xf-init, [a-zA-z]*tooltip)').trigger('tooltip:hide');

                if (! selectChange) {
                    if (this.markDifferentItems($html)) {
                        this.lfs.playSoundIfHas();
                    }
                }

                this.$content = $html.filter('.content').replaceAll(this.$content);
                XF.activate(this.$content);

                if (reloadPagination && $html.filter('.js-lfsPageNav').length) {
                    $html.filter('.js-lfsPageNav').replaceAll(this.paginator.$pageNav);
                    this.paginator.reloadContent();
                }

                if (this.clearDifferentTimeout) {
                    clearTimeout(this.clearDifferentTimeout);
                }

                this.clearDifferentTimeout = setTimeout(XF.proxy(this, 'clearDifferentItems'), this.lfs.options.differentTimeout);

                success = true;
            }

            this.$groupScroller.find('.js-loader').remove();

            if (typeof callback == 'function') {
                callback.call(null, success);
            }
        },

        markDifferentItems: function($html) {
            let $oldItems = this.$content.find('.structItem--lfsItem'),
                $newItems = $html.find('.structItem--lfsItem');

            $newItems.each(function () {
                let $item = $(this),
                    $oldItem = $oldItems.filter('#' + $item.attr('id'));

                if (! ($oldItem && $oldItem.data('date') === $item.data('date'))) {
                    $item.addClass('structItem--different');
                }
            });

            return $newItems.filter('.structItem--different').length > 0;
        },

        clearDifferentItems: function() {
            this.$content.find('.structItem--different').removeClass('structItem--different');
        },

        getLoadingSpinner: function() {
            return $('<div class="loader-block js-loader"><div class="loader"><div></div><div></div></div></div>');
        },

        extendAjaxData: function(e, data) {
            this.ajaxData = $.extend(true, this.ajaxData, data);
            XF.Cookie.set('lfs_ajax_' + this.options.groupId, JSON.stringify(data));

            this.update();
        }
    });
}
(window.jQuery, window, document);