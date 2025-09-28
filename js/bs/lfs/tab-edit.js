!function($, window, document) 
{
	"use strict";
	
	XF.OptionsLoader = XF.Element.newHandler({
        options: {
            replace: '',
			link: '',
			isLoaded: false,
			loadWhenEmpty: false
        },

        init: function() {
            this.$replace = $(this.options.replace);

			this.$target.change(XF.proxy(this, 'loadOptions'));
			
			if (!this.options.isLoaded) {
				this.loadOptions();
			}
        },
		
		loadOptions: function() {
			var _this = this;
			
			if (this.$target.val().length < 1 && !this.options.loadWhenEmpty) {
				this.$replace.html('');
				return;
			}

			let params = {};
			params[this.$target.attr('name')] = this.$target.val();
			
			XF.ajax('GET',
				this.options.link,
				params,
				function(data) {
					if (data.html && data.html.content) {
						XF.setupHtmlInsert(data.html, function($html) {
							XF.activate(_this.$replace.html($html));
						});
					}
					else {
						_this.$replace.html('');
					}
				}
			);
		}
    });

	XF.Element.register('options-loader', 'XF.OptionsLoader');
}
(window.jQuery, window, document);