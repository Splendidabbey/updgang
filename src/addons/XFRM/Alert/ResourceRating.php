<?php

namespace XFRM\Alert;

use XF\Alert\AbstractHandler;

class ResourceRating extends AbstractHandler
{
	public function getEntityWith()
	{
		$visitor = \XF::visitor();

		return ['Resource', 'Resource.Category', 'Resource.Category.Permissions|' . $visitor->permission_combination_id];
	}

	public function getOptOutActions()
	{
		return [
			'review',
			'reply'
		];
	}

	public function getOptOutDisplayOrder()
	{
		return 305;
	}
}