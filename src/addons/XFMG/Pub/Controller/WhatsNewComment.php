<?php

namespace XFMG\Pub\Controller;

use XF\Pub\Controller\AbstractWhatsNewFindType;

class WhatsNewComment extends AbstractWhatsNewFindType
{
	protected function getContentType()
	{
		return 'xfmg_comment';
	}
}