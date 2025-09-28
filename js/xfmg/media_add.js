var XFMG = window.XFMG || {};

!function($, window, document, _undefined)
{
	"use strict";

	XFMG.attachmentManager = null;

	XFMG.MediaManager = XF.Element.newHandler({

		// Some option defaults set on the attachment-manager element
		options: {
			mediaActionUrl: null,
			onInsertHandler: '.js-mediaOnInsertHandler'
		},

		init: function()
		{
			XFMG.attachmentManager = XF.Element.applyHandler(this.$target, 'attachment-manager', this.options);

			// Merge options from attachment manager
			this.options = $.extend({}, this.options, XFMG.attachmentManager.options);

			var $filesContainer = XFMG.attachmentManager.$filesContainer;
			$filesContainer.on('click', this.options.actionButton, XF.proxy(this, 'actionButtonClick'));
		},

		actionButtonClick: function(e)
		{
			e.preventDefault();

			var $target = $(e.target);
			if (!$target.is('button'))
			{
				$target = $target.closest('button');
			}

			var	action = $target.attr('data-action'),
				$row = $target.closest(this.options.fileRow);

			switch (action)
			{
				case 'delete':
					this.deleteMediaItem($row);
					break;
			}
		},

		deleteMediaItem: function($row)
		{
			var tempMediaId = $row.data('temp-media-id'),
				mediaActionUrl = this.options.mediaActionUrl;

			if (!tempMediaId)
			{
				return;
			}

			if (mediaActionUrl)
			{
				XF.ajax(
					'post',
					mediaActionUrl,
					{ delete: tempMediaId },
					function (data)
					{
						if (data.delete)
						{
							XFMG.attachmentManager.removeFileRow($row);
						}
					},
					{ skipDefaultSuccess: true }
				);
			}
			else
			{
				XFMG.attachmentManager.removeFileRow($row);
			}
		}
	});

	XFMG.LinkChecker = XF.Element.newHandler({

		options: {
			pasteInput: '.js-pasteInput',
			pasteError: '.js-pasteError'
		},

		$pasteInput: null,

		$pasteError: null,

		attachmentManager: null,

		init: function()
		{
			var $target = this.$target;

			this.attachmentManager = XFMG.attachmentManager;

			this.$pasteInput = $target.find(this.options.pasteInput);
			if (!this.$pasteInput.length)
			{
				console.error('No input to monitor for pasted text.');
				return;
			}

			this.$pasteError = $target.find(this.options.pasteError);

			this.$pasteInput.on('paste', XF.proxy(this, 'paste'));
			$target.on('ajax-submit:before', XF.proxy(this, 'beforeSubmit'));
			$target.on('ajax-submit:response', XF.proxy(this, 'complete'));
			$target.on('ajax-submit:error', XF.proxy(this, 'error'));
		},

		paste: function(e)
		{
			var $pasteError = this.$pasteError,
				self = this;

			setTimeout(function()
			{
				self.$target.submit();
				$pasteError.removeClassTransitioned('is-active');
			}, 100);
		},

		beforeSubmit: function(e, config)
		{
			if (this.attachmentManager == null)
			{
				config.preventSubmit = true;
				console.error("No attachment manager available on %o.", this.$target);
			}
		},

		complete: function(e, data)
		{
			if (data.errors || data.exception)
			{
				return;
			}

			e.preventDefault();

			this.$pasteInput.val('');

			XF.hideOverlays();

			this.attachmentManager.insertUploadedRow(data.attachment);
		},

		error: function(e, data)
		{
			var $pasteError = this.$target.find(this.options.pasteError);

			if ($pasteError.length)
			{
				e.preventDefault();

				$pasteError.find('div').text(data.errors[0]);
				$pasteError.addClassTransitioned('is-active');

				this.$pasteInput.val('');
			}
		}
	});

	XF.Element.register('media-manager', 'XFMG.MediaManager');
	XF.Element.register('link-checker', 'XFMG.LinkChecker');
}
(jQuery, window, document);