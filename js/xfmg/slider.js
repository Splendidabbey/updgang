var XFMG = window.XFMG || {};

!function($, window, document, _undefined)
{
	"use strict";

	XFMG.ItemSlider = XF.Element.newHandler({
		options: {
			auto: false,
			loop: false,
			pager: false,
			item: 6,
			itemWide: 0,
			itemMedium: 0,
			itemNarrow: 0
		},

		init: function()
		{
			if ($.fn.lightSlider)
			{
				var responsive = [];

				if (this.options.itemWide)
				{
					responsive.push({
						breakpoint: 900,
						settings: {
							item: this.options.itemWide,
							slideMove: Math.min(this.options.itemWide, 2)
						}
					});
				}

				if (this.options.itemMedium)
				{
					responsive.push({
						breakpoint: 650,
						settings: {
							item: this.options.itemMedium,
							slideMove: 1
						}
					});
				}

				if (this.options.itemNarrow)
				{
					responsive.push({
						breakpoint: 480,
						settings: {
							item: this.options.itemNarrow,
							slideMove: 1
						}
					});
				}

				this.$target.find('img.xfmgThumbnail-image').removeAttr('loading');

				this.$target.lightSlider({
					auto: this.options.auto,
					loop: this.options.loop,
					pager: this.options.pager,
					item: this.options.item,
					addClass: 'lightSlider--loaded',
					slideMargin: 5,
					galleryMargin: 0,
					slideMove: Math.min(this.options.item, 2),
					speed: 400,
					rtl: XF.isRtl(),
					responsive : responsive
				});
			}
			else
			{
				console.error('Lightslider must be loaded first.');
			}
		}
	});

	XF.Element.register('item-slider', 'XFMG.ItemSlider');
}
(jQuery, window, document);