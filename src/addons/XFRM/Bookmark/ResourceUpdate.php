<?php

namespace XFRM\Bookmark;

use XF\Bookmark\AbstractHandler;
use XF\Mvc\Entity\Entity;

class ResourceUpdate extends AbstractHandler
{
	/**
	 * @return string
	 */
	public function getCustomIconTemplateName()
	{
		return 'public:xfrm_resource_update_bookmark_custom_icon';
	}

	public function getEntityWith()
	{
		return ['Resource', 'Resource.Category'];
	}
}