!function($, window, document)
{
    "use strict";

    XF.LFSTabs = XF.create({
        options: {
            isMenu: false
        },

        lfs: null,
        group: null,
        $target: null,
        tabList: [],
        selectedTab: null,
        $menuTab: null,
        $settingButton: null,

        __construct: function(lfs, group, $target, options) {
            if (typeof options === 'undefined') {
                options = {};
            }

            this.options = XF.applyDataOptions(this.options, $target.data(), options);

            this.tabList = [];

            var _this = this;
            this.lfs = lfs;
            this.group = group;
            this.$target = $target;

            $target.find('.tab:not(.menu-open)').each(function() {
                _this.tabList.push(new XF.LFSTab(_this.lfs, _this, $(this)));
            });

            if (this.options.isMenu) {
                this.$menuTab = $target.find('.tab.menu-open');
            }

            this.$settingButton = this.$target.closest('.tabs-container').find('.js-settingButton');

            if (!this.selectedTab) {
                this.first().select();
            }
        },

        selectTab: function(tab) {
            this.selectedTab = tab;

            if (this.$settingButton && this.$settingButton.length) {
                this.$settingButton.toggleClass('is-active', tab.options.canSetting).data('href', tab.options.settingHref);
                XF.Click.getElementHandler(this.$settingButton, 'overlay').loadUrl = tab.options.settingHref;
            }
        },

        remove: function(tab) {
            let index = this.indexOf(tab);
            if (index !== -1) {
                this.tabList[index].$target.remove(400);
                this.tabList.splice(index, 1);
                this.first().select();
            }
        },

        first: function() {
            return this.tabList[0];
        },

        next: function () {
            let nextIndex = this.indexOf(this.selectedTab) + 1;

            if (typeof this.tabList[nextIndex] !== 'undefined') {
                return this.tabList[nextIndex];
            }

            return this.first();
        },

        indexOf: function (tab) {
            return this.tabList.indexOf(tab);
        }
    });

    XF.LFSTab = XF.create({
        options: {
            tabId: '',
            canSetting: false,
            settingHref: ''
        },

        lfs: null,
        tabs: null,
        $target: null,
        selected: false,
        cache: {},

        __construct: function(lfs, tabs, $target, options) {
            if (typeof options === 'undefined') {
                options = {};
            }

            this.options = XF.applyDataOptions(this.options, $target.data(), options);

            this.lfs = lfs;
            this.tabs = tabs;
            this.$target = $target.click(XF.proxy(this, 'select'));

            if ($target.is('.is-selected')) {
                this.selected = true;
                this.tabs.selectTab(this);
                this.cache = {
                    'content': $(this.tabs.group.$groupScroller.html())
                };
            }
        },

        loadData: function(callback, selectChange, reloadPagination) {
            var _this = this,
                sendData = $.extend(true, this.tabs.group.ajaxData, {
                    tab_id: this.options.tabId
                }),
                tabCache = this.cache;

            XF.ajax('GET',
                this.lfs.options.updateLink,
                sendData,
                function(data) {
                    if (data.html && data.html.content) {
                        XF.setupHtmlInsert(data.html, function($html) {
                            if (!$.isEmptyObject(tabCache)) {
                                tabCache.content = $html;
                            }
                            else {
                                _this.cache = {
                                    'content': $html
                                };
                            }

                            _this.tabs.group.replaceContent($html, function(success) {
                                if (!success) {
                                    _this.tabs.remove(this);
                                }
                            }, selectChange, reloadPagination);
                        });
                    }

                    if (typeof callback === 'function') {
                        callback.call(null);
                    }
                },
                {
                    global: false
                }
            );
        },

        select: function() {
            if (this.tabs.group.updateLock) {
                return this;
            }

            var _this = this,
                selectedTab = this.tabs.selectedTab;

            if (selectedTab) {
                if (selectedTab === this) {
                    return this;
                }

                selectedTab.deselect();
            }

            this.tabs.group.updateLock = true;

            var callback = function() {
                _this.tabs.group.updateLock = false;
                _this.tabs.group.resetScroll();
            };

            if (this.tabs.options.isMenu) {
                this.tabs.$menuTab.find('.title').text(_this.$target.find('.title').text());
            }

            this.$target.addClass('is-selected');
            this.selected = true;
            this.tabs.selectTab(this);
            XF.Cookie.set('lfs_group_' + this.tabs.group.options.groupId + '_selected_tab', this.options.tabId);

            if ($.isEmptyObject(this.cache)) {
                this.tabs.group.loading();
            }
            else {
                this.tabs.group.replaceContent(this.cache.content, null, true, true);
            }

            this.loadData(callback, true, true);

            return this;
        },

        deselect: function() {
            this.$target.removeClass('is-selected');
            this.tabs.group.paginator.clearAppend();
        },

        isSelected: function() {
            return this.selected;
        }
    });
}
(window.jQuery, window, document);