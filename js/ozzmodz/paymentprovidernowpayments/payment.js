var OzzModz = window.OzzModz || {};
OzzModz.NowPayments = OzzModz.NowPayments || {};
OzzModz.$ = OzzModz.$ || window.jQuery || null;

;((window, document) =>
{
	"use strict";
	var $ = OzzModz.$;

	"use strict";

	OzzModz.NowPayments.CurrencyForm = XF.Element.newHandler({

		options: {
			apiKey: null,
			apiUrl: null,
			payAmount: '.js-nowPaymentsCurrencyAmount',
			cost: 0,
			currency: null,
		},

		select: null,
		payAmount: null,

		init: function ()
		{
			let target = this.target || this.$target.get(0);
			this.select = target.querySelector('select[name="pay_currency"]');
			if (!this.select)
			{
				console.log('No currency input');
				return;
			}

			this.payAmount = target.querySelector(this.options.payAmount);

			if (typeof XF.on !== "function")
			{
				$(this.select).on('change', this.change.bind(this));
				$(document).on('ajax:send', this.beforeAjaxSend.bind(this));
			}
			else
			{
				XF.on(this.select, 'change', this.change.bind(this));
				XF.on(document, 'ajax-submit:response', this.beforeAjaxSend.bind(this))
			}
		},

		change: function ()
		{
			let t = this;
			XF.ajax('get', this.options.apiUrl + '/v1/estimate', {
				amount: this.options.cost,
				currency_from: this.options.currency,
				currency_to: this.select.value
			}, function (data)
			{
				t.payAmount.textContent = data.pay_amount;
			}, {
				skipDefault: true,
				headers: {
					"x-api-key": this.options.apiKey, "accept": "application/json",
				},
				crossDomain: true,
			});
		},

		beforeAjaxSend: function (e, xhr, settings)
		{
			if (xhr.url)
			{
				settings = xhr;
				xhr = e;
			}

			if (settings.url)
			{
				let matches = settings.url.startsWith(this.options.apiUrl);
				if (matches && matches[0])
				{
					settings.url = settings.url.replace(new RegExp('callback=[^&]*&', 'gm'), '');
					settings.url = settings.url.replaceAll(new RegExp('(&(?:_xfRequestUri=[^&]*|_xfWithData=[^&]*|_xfToken=[^&]*|_xfResponseType=[^&]*)\\b)+', 'gm'), '');
				}
			}
		}
	});

	OzzModz.NowPayments.PaymentForm = XF.Element.newHandler({

		options: {
			apiKey: null,
			apiUrl: null,
			returnUrl: null,
			paymentId: 0,
			expirationSeconds: 0,
			expirationUrl: null,
			timerBlock: '.js-nowPaymentsTimer',
			payAmount: '.js-nowPaymentsPayAmount',
		},

		timeLeft: null,
		timerBlock: null,
		payAmount: null,

		countdown: null,

		init: function ()
		{
			let target = this.target || this.$target.get(0);

			if (this.options.expirationSeconds)
			{
				this.timerBlock = target.querySelector(this.options.timerBlock)
				if (this.timerBlock)
				{
					this.updateCountdown();
				}
			}

			this.updatePaymentStatus();
			this.payAmount = target.querySelector(this.options.payAmount);
		},

		updateCountdown: function ()
		{
			let duration = this.options.expirationSeconds;
			if (duration <= 0)
			{
				return;
			}

			let timer = duration, minutes, seconds;
			let t = this;

			this.countdown = setInterval(function ()
			{
				minutes = parseInt(timer / 60, 10);
				seconds = parseInt(timer % 60, 10);

				minutes = minutes < 10 ? "0" + minutes : minutes;
				seconds = seconds < 10 ? "0" + seconds : seconds;

				t.timerBlock.textContent = minutes + ":" + seconds;

				if (--timer < 0)
				{
					timer = duration;
					t.updateEstimate();
				}
			}, 1000);
		},

		updateEstimate: function ()
		{
			let t = this;

			XF.ajax('get', this.options.expirationUrl, {
				payment_id: t.options.paymentId,
			}, function (data)
			{
				if (data.pay_amount)
				{
					t.payAmount.textContent = data.pay_amount;
					XF.activate(t.payAmount);
				}
			}, {
				skipDefaultSuccess: true
			});
		},

		updatePaymentStatus: function ()
		{
			let t = this;
			let target = this.target || this.$target.get(0);

			setInterval(function ()
			{
				XF.ajax('get', target.action, {
					as_message: true,
					payment_id: t.options.paymentId
				}, function (data)
				{
					if (data.status === 'ok' && data.message)
					{
						let statusBlock = target.querySelector('.js-nowPaymentsStatusContainer');

						let successBlock = target.querySelector('.js-nowPaymentsSuccess');
						let errorBlock = target.querySelector('.js-nowPaymentsError');

						if (data.statusBlock === 'error')
						{
							if (successBlock)
							{
								successBlock.classList.add('u-hidden');
							}

							if (statusBlock)
							{
								statusBlock.classList.remove('u-hidden');
							}

							if (errorBlock)
							{
								errorBlock.classList.remove('u-hidden');
								errorBlock.innerHTML = data.message;
							}

							t.options.expirationSeconds = 0;
							clearInterval(t.countdown);
						}
						else if (data.statusBlock === 'error')
						{
							if (errorBlock)
							{
								errorBlock.classList.add('u-hidden');
							}

							if (statusBlock)
							{
								statusBlock.classList.remove('u-hidden');
							}

							if (successBlock)
							{
								successBlock.classList.remove('u-hidden');
								successBlock.innerHTML = data.message;
							}

							t.options.expirationSeconds = 0;
							clearInterval(t.countdown);
						}
					}

					if (data.redirect)
					{
						XF.redirect(data.redirect);
					}

				}, {
					skipDefault: true
				});
			}, 10000);
		},
	});

	XF.Element.register('now-payments-currency-form', 'OzzModz.NowPayments.CurrencyForm');
	XF.Element.register('now-payments-payment-form', 'OzzModz.NowPayments.PaymentForm');
})(window, document)
