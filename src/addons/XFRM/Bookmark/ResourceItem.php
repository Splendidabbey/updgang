<?php

namespace XFRM\Bookmark;

use XF\Bookmark\AbstractHandler;
use XF\Mvc\Entity\Entity;

class ResourceItem extends AbstractHandler
{
	/**
	 * @return string
	 */
	public function getCustomIconTemplateName()
	{
		return 'public:xfrm_resource_bookmark_custom_icon';
	}

	public function getEntityWith()
	{
		$visitor = \XF::visitor();

		return ['Category', 'Category.Permissions|' . $visitor->permission_combination_id, 'User', 'Description'];
	}
}