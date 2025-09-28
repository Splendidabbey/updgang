/** @param {jQuery} $ jQuery Object */
!function($, window, document) {
    "use strict";

    Date.prototype.addHours = function(h)
    {
        this.setTime(this.getTime() + (h * 60 * 60 * 1000));
        return this;
    };

    XF.DateTimePicker = XF.Element.newHandler({
        options: {
            weekStart: 0,
            minDate: null,
            maxDate: null,
            yearStart: 1970,
            yearEnd: 2060,
            format: 'd.m.Y H:i',
            formatDate: 'd.m.Y',
            formatTime: 'H:i',
            triggerFormat: 'd F Y H:i',
            step: 1,
            locale: 'en'
        },

        dateFormatter: null,

        init: function()
        {
            this.dateFormatter = new DateFormatter();

            $.datetimepicker.setLocale(this.options.locale);

            let config = {
                onChangeDateTime: XF.proxy(this, 'onChangeDateTime'),
                defaultDate: new Date().addHours(1),
                dayOfWeekStart: this.options.weekStart,
                minDate: this.options.minDate ? this.options.minDate : false,
                maxDate: this.options.maxDate ? this.options.maxDate : false,
                yearRange: this.options.yearRange,
                format: this.options.format,
                formatDate: this.options.formatDate,
                formatTime: this.options.formatTime,
                step: this.options.step
            };

            this.$target.datetimepicker(config);
        },

        onChangeDateTime: function (dt) {
            if (!this.$target.val()) {
                this.$target.val(this.dateFormatter.formatDate(dt, this.options.format));
            }
        }
    });

    XF.Element.register('date-time-picker', 'XF.DateTimePicker');
}
(jQuery, window, document);