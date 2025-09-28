jQuery.expr[':'].regex = function(elem, index, match) {
    var matchParams = match[3].split(','),
        validLabels = /^(data|css):/,
        attr = {
            method: matchParams[0].match(validLabels) ? 
                        matchParams[0].split(':')[0] : 'attr',
            property: matchParams.shift().replace(validLabels,'')
        },
        regexFlags = 'ig',
        regex = new RegExp(matchParams.join('').replace(/^\s+|\s+$/g,''), regexFlags);
    return regex.test(jQuery(elem)[attr.method](attr.property));
}

!function($, window, document) 
{
	"use strict";
	
	XF.LFS = XF.Element.newHandler({
		options: {
			updateInterval: 15,
			updateLink: '/index.php?lfs/tab',
			differentTimeout: 600,
			pollUnfocused: false,
			widgetKey: '',
			widgetId: 0
		},
		
		groups: [],
		prevTypeWindow: '',
		isActiveWindow: true,
		
		init: function() {
			var _this = this;

			this.groups = [];

			this.$target.find('.tabGroup').each(function() {
				_this.groups.push(new XF.LFSGroup(_this, $(this)));
			});

			this.$target.find('.js-refreshButton').click(XF.proxy(this, 'refresh'));
			
			if (this.options.updateInterval !== 0) {
                this.updateInterval = setInterval(XF.proxy(this, 'update'), this.options.updateInterval * 1000);
            }

			this.$sound = this.$target.find('.js-sound');

			$(document).on('lfs:refresh', XF.proxy(this, 'update'));
		},

		refresh: function(e) {
			this.update(e, true);
		},
		
		update: function(e, appendSpinner, reloadPagination) {
			if (! (document.hasFocus() || this.options.pollUnfocused)) {
				return;
			}
			
			$.each(this.groups, function(i, group) {
				if (!group.updateLock) {
					group.update(appendSpinner, reloadPagination);
				}
			});
		},

		playSoundIfHas: function () {
			if (XF.Cookie.get('lfs_mute_' + this.options.widgetId) === 'true'){
				return;
			}

			if (this.$sound.length) {
				this.$sound[0].play();
			}
		}
	});
	
	XF.Element.register('lfs', 'XF.LFS');
}
(window.jQuery, window, document);