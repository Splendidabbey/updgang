<?php

namespace XFMG\Alert;

use XF\Alert\AbstractHandler;

class Album extends AbstractHandler
{
	public function getEntityWith()
	{
		$visitor = \XF::visitor();

		return ['Category', 'Category.Permissions|' . $visitor->permission_combination_id];
	}

	public function getOptOutActions()
	{
		return [
			'share_view',
			'share_add',
			'mention',
			'reaction'
		];
	}

	public function getOptOutDisplayOrder()
	{
		return 205;
	}
}