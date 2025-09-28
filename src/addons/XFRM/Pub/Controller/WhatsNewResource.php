<?php

namespace XFRM\Pub\Controller;

use XF\Pub\Controller\AbstractWhatsNewFindType;

class WhatsNewResource extends AbstractWhatsNewFindType
{
	protected function getContentType()
	{
		return 'resource';
	}
}