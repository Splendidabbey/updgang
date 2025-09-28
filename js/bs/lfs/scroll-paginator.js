!function($, window, document)
{
    "use strict";

    XF.LFSScrollPaginator = XF.create({
        options: {
            append: '',
            filter: ''
        },

        $append: null,

        loading: false,

        __construct: function(group, $target) {
            this.options = XF.applyDataOptions(this.options, $target.data());

            this.group = group;
            this.$target = $target;

            this.init();
        },

        init: function() {
            this.loadPageNav();

            if (! this.$pageNav.length) {
                return;
            }

            this.loadElements();

            this.$target.on('scroll', XF.proxy(this, 'onScroll'))
                .on('lfs:group-content-updated', XF.proxy(this, 'reloadContent'));

            if (this.$target.height() !== this.$target.get(0).scrollHeight) {
                this.onScroll();
            }
        },

        loadPageNav: function() {
            this.$pageNav = this.$target.find('.js-lfsPageNav').first();
        },

        loadElements: function() {
            this.$append = this.$target.find(this.options.append);

            this.detectPageParams();
        },

        reloadContent: function() {
            this.loadPageNav();

            if (! this.$pageNav.length) {
                return;
            }

            this.loadElements();
        },

        currentPage: 0,
        lastPage: 0,

        detectPageParams: function() {
            this.currentPage = parseInt(this.$pageNav.find('.pageNav-page--current').find('a').text(), 10);
            this.lastPage = parseInt(this.$pageNav.find('.pageNav-page').last().find('a').text(), 10);
        },

        onScroll: function() {
            if (this.isBottom() && !this.isLastPage() && !this.group.updateLock) {
                this.load();
            }
        },

        load: function() {
            if (this.loading) {
                return;
            }

            this.loading = true;

            this.appendLoadingSpinner();

            this.sendRequest(this.$pageNav.find('.pageNav-page--current').next().find('a').attr('href'));
        },

        requestUrl: '',

        sendRequest: function(url) {
            this.requestUrl = url;

            XF.ajax('GET', url, {}, XF.proxy(this, 'appendHtml'));
        },

        appendHtml: function(data) {
            if (data.hasItems) {
                var _this = this;
                XF.setupHtmlInsert(data.html, function($html) {
                    _this.$pageNav.html($html.filter('.js-lfsPageNav').first().html());

                    _this.detectPageParams();

                    _this.clearLoadingSpinner();

                    XF.activate(_this.$append.append($html.filter(_this.options.filter)));
                });
            }
            else {
                this.lastPage = this.currentPage;

                this.clearLoadingSpinner();
            }

            this.loading = false;
        },

        clearAppend: function() {
            if (this.$append && this.$append.length) {
                this.$append.html('');
            }
        },

        isBottom: function () {
            return this.$target.scrollTop() + this.$target.height() >= this.$target.get(0).scrollHeight - 1;
        },

        isLastPage: function () {
            return this.currentPage === this.lastPage;
        },

        appendLoadingSpinner: function() {
            this.$target.append(this.getLoadingSpinner()).scrollTop(this.$target.get(0).scrollHeight);
        },

        clearLoadingSpinner: function() {
            this.$target.find('.js-loadingSpinner').remove();
        },

        getLoadingSpinner: function() {
            return $(
                "<div class=\"page-loading-spinner js-loadingSpinner\">" +
                    "<div class=\"spins\">" +
                        "<div></div><div></div><div></div><div></div>" +
                    "</div>" +
                "</div>"
            );
        },
    });
}
(window.jQuery, window, document);