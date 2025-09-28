<?php

namespace XFMG\Bookmark;

use XF\Bookmark\AbstractHandler;
use XF\Mvc\Entity\Entity;

class Media extends AbstractHandler
{
	/**
	 * @return string
	 */
	public function getCustomIconTemplateName()
	{
		return 'public:xfmg_bookmark_custom_icon';
	}

	public function getEntityWith()
	{
		$visitor = \XF::visitor();

		return ['Album', 'Category', 'Category.Permissions|' . $visitor->permission_combination_id];
	}
}