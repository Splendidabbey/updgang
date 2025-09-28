var XFMG = window.XFMG || {};

!function($, window, document, _undefined)
{
	"use strict";

	XF.Inserter = XF.extend(XF.Inserter, {

		__backup: {
			'_applyReplace': '__applyReplace'
		},

		inProgress: false,

		_applyReplace: function(selectorOld, $old, $new)
		{
			if (selectorOld != '.js-filmStrip')
			{
				this.__applyReplace(selectorOld, $old, $new);
				return;
			}

			if (this.inProgress)
			{
				return;
			}

			this.inProgress = true;
			var self = this;

			if ($old.length)
			{
				var $oldButtons = $old.find('.js-filmStrip-button');
				$oldButtons.addClass('is-loading');

				var $oldItems = $old.find('.js-filmStrip-item');
				$oldItems.addClassTransitioned('itemList-item--fading', function()
				{
					if ($new.length)
					{
						var $newButtons = $new.find('.js-filmStrip-button');
						$newButtons.addClass('is-loading');

						$old.css('visibility', 'hidden');
						$new.css('visibility', 'hidden');

						var $newItems = $new.find('.js-filmStrip-item');
						$newItems.addClass('itemList-item--fading');

						$old.replaceWith($new);
						$new.css('visibility', 'visible');

						$newItems.removeClassTransitioned('itemList-item--fading', function()
						{
							$newButtons.removeClass('is-loading');
							self.inProgress = false;
						});
					}
				});
			}
		}
	});
}
(jQuery, window, document);