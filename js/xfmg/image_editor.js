var XFMG = window.XFMG || {};

!function($, window, document, _undefined)
{
	"use strict";

	XFMG.ImageEditor = XF.Element.newHandler({

		options: {
			image: '.js-mediaImg',
			cropData: '.js-cropData',
			move: '.js-ctrlDragMove',
			crop: '.js-ctrlDragCrop',
			zoomIn: '.js-ctrlZoomIn',
			zoomOut: '.js-ctrlZoomOut',
			rotateLeft: '.js-ctrlRotateLeft',
			rotateRight: '.js-ctrlRotateRight',
			flipH: '.js-ctrlFlipH',
			flipV: '.js-ctrlFlipV',
			clear: '.js-ctrlClear'
		},

		$image: null,

		init: function()
		{
			var self = this,
				$form = this.$target,
				$image = $form.find(this.options.image),
				$cropData = $form.find(this.options.cropData);

			if (!$image.length || !$image.is('img'))
			{
				console.error('Image editor must contain an img element');
			}

			this.$image = $image;

			$image.cropper({
				viewMode: 2,
				autoCrop: false,
				ready: function()
				{
					$form.find(self.options.move + ', ' + self.options.crop).on('click', XF.proxy(self, 'dragMode'));
					$form.find(self.options.zoomIn + ', ' + self.options.zoomOut).on('click', XF.proxy(self, 'zoom'));
					$form.find(self.options.rotateLeft + ', ' + self.options.rotateRight).on('click', XF.proxy(self, 'rotate'));
					$form.find(self.options.flipH + ', ' + self.options.flipV).on('click', XF.proxy(self, 'flip'));

					$form.find(self.options.clear).on('click', XF.proxy(self, 'clear'));
				},
				crop: function(e)
				{
					var json = {
						scaleX: e.scaleX,
						scaleY: e.scaleY,
						rotate: e.rotate,
						x: e.x,
						y: e.y,
						width: e.width,
						height: e.height
					};

					$cropData.val(JSON.stringify(json));
				}
			});
		},

		dragMode: function(e)
		{
			var $button = $(e.currentTarget);

			if ($button.is(this.options.move))
			{
				this.$image.cropper('setDragMode', 'move');
			}
			else if ($button.is(this.options.crop))
			{
				this.$image.cropper('setDragMode', 'crop');
			}
		},

		zoom: function(e)
		{
			var $button = $(e.currentTarget);

			if ($button.is(this.options.zoomIn))
			{
				this.$image.cropper('zoom', 0.1);
			}
			else if ($button.is(this.options.zoomOut))
			{
				this.$image.cropper('zoom', -0.1);
			}
		},

		rotate: function(e)
		{
			var $button = $(e.currentTarget);

			if ($button.is(this.options.rotateLeft))
			{
				this.$image.cropper('rotate', -10);
			}
			else if ($button.is(this.options.rotateRight))
			{
				this.$image.cropper('rotate', 10);
			}
		},

		flip: function(e)
		{
			var $button = $(e.currentTarget);

			var scale = $button.data('scale');
			if ($button.is(this.options.flipH))
			{
				this.$image.cropper('scaleX', scale);
			}
			else if ($button.is(this.options.flipV))
			{
				this.$image.cropper('scaleY', scale);
			}

			if (scale === -1)
			{
				scale = 1;
			}
			else
			{
				scale = -1
			}

			$button.data('scale', scale);
		},

		clear: function(e)
		{
			this.$image.cropper('clear');
		}
	});

	XF.Element.register('image-editor', 'XFMG.ImageEditor');
}
(jQuery, window, document);