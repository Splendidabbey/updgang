<?php

namespace XFRM\Repository;

use XF\Repository\AbstractField;

class ResourceReviewField extends AbstractField
{
	protected function getRegistryKey()
	{
		return 'xfrmResourceReviewFields';
	}

	protected function getClassIdentifier()
	{
		return 'XFRM:ResourceReviewField';
	}

	public function getDisplayGroups()
	{
		return [
			'above_review' => \XF::phrase('xfrm_above_review'),
			'below_review' => \XF::phrase('xfrm_below_review')
		];
	}
}