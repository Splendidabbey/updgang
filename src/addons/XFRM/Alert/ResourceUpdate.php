<?php

namespace XFRM\Alert;

use XF\Alert\AbstractHandler;

class ResourceUpdate extends AbstractHandler
{
	public function getEntityWith()
	{
		$visitor = \XF::visitor();

		return ['Resource', 'Resource.Category', 'Resource.Category.Permissions|' . $visitor->permission_combination_id];
	}

	public function getOptOutActions()
	{
		return [
			'insert',
			'mention',
			'reaction'
		];
	}

	public function getOptOutDisplayOrder()
	{
		return 300;
	}
}