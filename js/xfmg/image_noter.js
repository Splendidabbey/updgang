var XFMG = window.XFMG || {};

!function($, window, document, _undefined)
{
	"use strict";

	XFMG.ImageNoter = XF.Element.newHandler({

		options: {
			image: '.js-mediaImage',
			toggleId: '#js-noterToggle',
			editUrl: null
		},

		$image: null,
		$toggle: null,
		$cropBox: null,
		$editingNote: null,

		active: false,
		trigger: null,
		tooltip: null,

		init: function()
		{
			var $container = this.$target,
				$image = $container.find(this.options.image),
				$toggle = $(this.options.toggleId);

			if (!$image.length || !$image.is('img'))
			{
				console.error('Image noter must contain an img element');
			}

			this.$image = $image;

			if (!$image.prop('complete'))
			{
				$image.on('load', XF.proxy(this, 'prepareNotes'));
			}
			else
			{
				this.prepareNotes();
			}

			$(window).onPassive('resize', XF.proxy(this, 'prepareNotes'));

			// no toggle == no permission to add notes
			if ($toggle.length)
			{
				this.$toggle = $toggle;
				$toggle.on('click', XF.proxy(this, 'toggleNewNoteEditor'));
			}
		},

		prepareNotes: function()
		{
			var self = this,
				$image = this.$image,
				width = $image[0].width,
				naturalWidth = $image[0].naturalWidth,
				multiplier = (naturalWidth / width),
				$notes = this.$target.find('.js-mediaNote');

			$notes.each(function()
			{
				var $note = $(this),
					coords = $note.data('note-data');

				for (var key in coords)
				{
					if (!coords.hasOwnProperty(key))
					{
						continue;
					}

					// we need to keep the original values so adjusted values should be suffixed with _
					coords[key + '_'] = (coords[key] / multiplier);
				}

				// adjusted values based on multiplier
				$note.css({
					left: coords['tag_x1_'],
					top: coords['tag_y1_'],
					width: coords['tag_width_'],
					height: coords['tag_height_']
				});

				if (!$note.data('prepared'))
				{
					self.initNote($note);
				}
			});
		},

		initNote: function($note)
		{
			var $element = this.getTooltipElement($note);

			var tooltip = new XF.TooltipElement($element.clone().contents(), {
				extraClass: 'tooltip--mediaNote tooltip--mediaNote--plain',
				noTouch: false,
				html: true
			});

			var $mediaContainer = this.$target.find('.media-container-image').first(),
				self = this;

			tooltip.addSetupCallback(function($tooltipEl)
			{
				$tooltipEl.on('mouseenter', function()
				{
					$mediaContainer.addClass('is-tooltip-active');
				});

				$tooltipEl.on('mouseleave', function()
				{
					$mediaContainer.removeClass('is-tooltip-active');
				});

				$tooltipEl.find('.js-mediaNoteTooltipEdit').on('click', XF.proxy(self, 'editNote', $note));
			});

			var trigger = new XF.TooltipTrigger($note, tooltip, {
				maintain: true,
				trigger: 'hover focus click'
			});

			trigger.init();

			$note.data('prepared', true);
			$note.data('tooltip', tooltip);
			$note.data('trigger', trigger);
			$note.show();

			$element.remove();
		},

		getTooltipElement: function($note)
		{
			return XF.findRelativeIf('< .js-mediaContainerImage | .js-mediaNoteTooltip' + $note.data('note-id'), $note);
		},

		editNote: function($note)
		{
			var coords = $note.data('note-data'),
				self = this;

			$note.trigger('tooltip:hide');

			this.$image.cropper({
				viewMode: 2,
				dragMode: 'none',
				aspectRatio: 1,
				modal: false,
				highlight: false,
				movable: false,
				rotatable: false,
				scalable: false,
				zoomable: false,
				toggleDragModeOnDblclick: false,
				data: {
					x: coords['tag_x1'],
					y: coords['tag_y1'],
					width: coords['tag_width'],
					height: coords['tag_height']
				},
				ready: function()
				{
					self.$editingNote = $note;
					self.$cropBox = self.$target.find('.cropper-crop-box').first();

					if (!self.tooltip && !self.trigger)
					{
						self.tooltip = new XF.TooltipElement(XF.proxy(self, 'getEditNoteTooltipContent'), {
							extraClass: 'tooltip--mediaNote',
							html: true,
							loadRequired: true
						});
						self.trigger = new XF.TooltipTrigger(self.$cropBox, self.tooltip, {
							maintain: true,
							trigger: '',
							onShow: function(trigger, tooltip)
							{
								var $tooltip = tooltip.$tooltip;

								$tooltip.on('tooltip:shown', function()
								{
									$tooltip.find('textarea').trigger('autosize');
								});
							}
						});

						self.trigger.init();
						self.$cropBox.trigger('tooltip:show');

						// seems to workaround issue where cropend doesn't fire after the first crop
						self.$image.trigger('cropend');
					}
				},
				cropstart: XF.proxy(this, 'editNoteCropstart'),
				cropend: XF.proxy(this, 'editNoteCropend')
			});
		},

		editNoteCropstart: function(e)
		{
			if (!this.trigger)
			{
				return;
			}

			this.$cropBox.trigger('tooltip:hide');
		},

		editNoteCropend: function(e)
		{
			var self = this;

			this.$cropBox.trigger('tooltip:show');
			this.tooltip.$tooltip.on('tooltip:shown', function(e)
			{
				var $tooltip = $(e.target),
					coords = self.getCoordsFromCropper();

				$tooltip.find('.js-noteData').val(JSON.stringify(coords));
			});
		},

		getEditNoteTooltipContent: function(onContent)
		{
			var self = this,
				options = {
					skipDefault: true,
					skipError: true,
					global: false
				};

			XF.ajax(
				'get', this.options.editUrl, { note_id: this.$editingNote.data('note-id') },
				function(data) { self.editNoteLoaded(data, onContent); },
				options
			);
		},

		editNoteLoaded: function(data, onContent)
		{
			if (!data.html)
			{
				return;
			}

			var self = this;
			XF.setupHtmlInsert(data.html, function($html, container, onComplete)
			{
				onContent($html);

				var $cancel = $html.find('.js-cancelButton');
				$cancel.on('click', XF.proxy(self, 'editNoteCancel'));

				$html.on('ajax-submit:response', XF.proxy(self, 'editNoteHandle'));
			});
		},

		editNoteCancel: function(message)
		{
			if (this.$cropBox)
			{
				this.$cropBox.trigger('tooltip:hide');
				this.$cropBox = null;
			}

			if (this.tooltip)
			{
				this.tooltip.destroy();
				this.tooltip = null;
				this.trigger = null;
			}

			this.$image.cropper('destroy');
			this.$editingNote = null;

			if (typeof message === 'string')
			{
				XF.flashMessage(message, 3000);
			}
		},

		editNoteHandle: function(e, data)
		{
			if (data.errors || data.exception)
			{
				return;
			}

			e.preventDefault();

			var noteTooltip = this.$editingNote.data('tooltip');
			noteTooltip.destroy();

			this.$editingNote.remove();

			if (data.deleted)
			{
				this.editNoteCancel(data.message);
			}
			else
			{
				var self = this;
				XF.setupHtmlInsert(data.html, function($html, container, onComplete)
				{
					var $imageContainer = self.$target.find('.js-mediaContainerImage');
					$imageContainer.prepend($html);
					self.editNoteCancel(data.message);
					XF.activate($html);
				});

				setTimeout(function()
				{
					self.prepareNotes();
				}, 500);
			}
		},

		toggleNewNoteEditor: function()
		{
			if (this.active)
			{
				this.disableNewNoteEditor();
			}
			else
			{
				this.enableNewNoteEditor();
			}
		},

		disableNewNoteEditor: function(message)
		{
			if (!this.active)
			{
				return;
			}

			if (this.$cropBox)
			{
				this.$cropBox.trigger('tooltip:hide');
			}

			if (this.tooltip)
			{
				this.tooltip.destroy();
				this.tooltip = null;
				this.trigger = null;
			}

			this.$image.cropper('destroy');

			var $toggle = this.$toggle;

			$toggle.find('.button-text').html(XF.htmlspecialchars($toggle.data('inactive-label')));
			$toggle.removeClass('button--icon--' + $toggle.data('active-icon'));
			$toggle.addClass('button--icon--' + $toggle.data('inactive-icon'));

			if (message)
			{
				XF.flashMessage(message, 3000);
			}
			else
			{
				XF.flashMessage($toggle.data('inactive-message'), 3000);
			}
			this.active = false;
		},

		enableNewNoteEditor: function()
		{
			if (this.active)
			{
				return;
			}

			this.$image.cropper({
				viewMode: 2,
				dragMode: 'crop',
				aspectRatio: 1,
				modal: false,
				highlight: false,
				autoCrop: false,
				movable: false,
				rotatable: false,
				scalable: false,
				zoomable: false,
				toggleDragModeOnDblclick: false,
				ready: XF.proxy(this, 'newNoteReady'),
				cropstart: XF.proxy(this, 'newNoteCropstart'),
				cropend: XF.proxy(this, 'newNoteCropend')
			});
		},

		newNoteReady: function()
		{
			var $toggle = this.$toggle;

			$toggle.find('.button-text').html(XF.htmlspecialchars($toggle.data('active-label')));
			$toggle.removeClass('button--icon--' + $toggle.data('inactive-icon'));
			$toggle.addClass('button--icon--' + $toggle.data('active-icon'));

			this.$cropBox = this.$target.find('.cropper-crop-box').first();

			XF.flashMessage($toggle.data('active-message'), 3000);
			this.active = true;
		},

		newNoteCropstart: function(e)
		{
			if (!this.trigger)
			{
				return;
			}

			this.$cropBox.trigger('tooltip:hide');
		},

		newNoteCropend: function(e)
		{
			if (!this.tooltip && !this.trigger)
			{
				this.tooltip = new XF.TooltipElement(XF.proxy(this, 'getNewNoteTooltipContent'), {
					extraClass: 'tooltip--mediaNote',
					html: true,
					loadRequired: true
				});
				this.trigger = new XF.TooltipTrigger(this.$cropBox, this.tooltip, {
					maintain: true,
					trigger: ''
				});

				this.trigger.init();
			}

			var self = this;

			this.$cropBox.trigger('tooltip:show');
			this.tooltip.$tooltip.on('tooltip:shown', function(e)
			{
				var $tooltip = $(e.target),
					coords = self.getCoordsFromCropper();

				$tooltip.find('.js-noteData').val(JSON.stringify(coords));
			});
		},

		getNewNoteTooltipContent: function(onContent)
		{
			var self = this,
				options = {
					skipDefault: true,
					skipError: true,
					global: false
				};

			XF.ajax(
				'get', this.options.editUrl, {},
				function(data) { self.newNoteLoaded(data, onContent); },
				options
			);
		},

		newNoteLoaded: function(data, onContent)
		{
			if (!data.html)
			{
				return;
			}

			var self = this;
			XF.setupHtmlInsert(data.html, function($html, container, onComplete)
			{
				onContent($html);

				var $cancel = $html.find('.js-cancelButton');
				$cancel.on('click', XF.proxy(self, 'newNoteCancel'));

				$html.on('ajax-submit:response', XF.proxy(self, 'newNoteHandle'));
			});
		},

		newNoteCancel: function()
		{
			this.$cropBox.trigger('tooltip:hide');
			this.$image.cropper('clear');
		},

		newNoteHandle: function(e, data)
		{
			if (data.errors || data.exception)
			{
				return;
			}

			e.preventDefault();

			var self = this;
			XF.setupHtmlInsert(data.html, function($html, container, onComplete)
			{
				var $imageContainer = self.$target.find('.js-mediaContainerImage');
				$imageContainer.prepend($html);
				self.disableNewNoteEditor(data.message);
				XF.activate($html);
			});

			setTimeout(function()
			{
				self.prepareNotes();
			}, 500);
		},

		getCoordsFromCropper: function()
		{
			var $image = this.$image,
				data = $image.cropper('getData');

			// naming mostly for XFMG 1.x backwards compatibility
			return {
				tag_x1:	data.x,
				tag_y1: data.y,
				tag_width: data.width,
				tag_height: data.height
			};
		}
	});

	XF.Element.register('image-noter', 'XFMG.ImageNoter');
}
(jQuery, window, document);