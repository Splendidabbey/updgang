var DBTech = window.DBTech || {};
DBTech.Credits = window.DBTech.Credits || {};

!function($, window, document, _undefined)
{
	"use strict";

	// ################################## EDITOR DIALOG ###########################################
	DBTech.Credits.EditorDialogCharge = XF.extend(XF.EditorDialog, {
		_beforeShow: function (overlay)
		{
			$('#editor_dbtech_credits_charge_title').val('');
		},

		_init: function (overlay)
		{
			$('#editor_dbtech_credits_charge_form').submit(XF.proxy(this, 'submit'));
		},

		submit: function (e)
		{
			e.preventDefault();

			var ed = this.ed,
				overlay = this.overlay;

			ed.selection.restore();
			DBTech.Credits.EditorHelpers.insertCharge(ed, $('#editor_dbtech_credits_charge_title').val());

			overlay.hide();
		}
	});

	// ################################## EDITOR START ###########################################
	DBTech.Credits.editorStart = {
		started: false,
		custom: [],

		startAll: function()
		{
			if (!DBTech.Credits.editorStart.started)
			{
				DBTech.Credits.editorStart.registerCommands();
				DBTech.Credits.editorStart.registerDialogs();

				DBTech.Credits.editorStart.started = true;
			}
		},

		registerCommands: function()
		{
			var custom;

			try
			{
				custom = $.parseJSON($('.js-editorCustom').first().html()) || {};
			}
			catch (e)
			{
				console.error(e);
				custom = {};
			}

			if (typeof custom.charge !== 'undefined')
			{
				$.FE.RegisterCommand('xfCustom_charge', {
					title: custom.charge.title,
					icon: 'xfCustom_charge',
					undo: true,
					focus: true,
					callback: function()
					{
						XF.EditorHelpers.loadDialog(this, 'dbtechCreditsCharge');
					}
				});
			}
		},

		registerDialogs: function()
		{
			XF.EditorHelpers.dialogs.dbtechCreditsCharge = new DBTech.Credits.EditorDialogCharge('dbtechCreditsCharge');
		}
	};

	// ################################## EDITOR HELPER ###########################################
	DBTech.Credits.EditorHelpers = {
		insertCharge: function(ed, amount)
		{
			var open;
			if (amount)
			{
				open = '[CHARGE=' + amount + ']';
			}
			else
			{
				open = '[CHARGE]';
			}

			XF.EditorHelpers.wrapSelectionText(ed, open, '[/CHARGE]', true);
		}
	};

	$(document).one('editor:start', DBTech.Credits.editorStart.startAll);
}
(jQuery, window, document);