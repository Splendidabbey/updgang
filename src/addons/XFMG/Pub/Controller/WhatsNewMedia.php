<?php

namespace XFMG\Pub\Controller;

use XF\Pub\Controller\AbstractWhatsNewFindType;

class WhatsNewMedia extends AbstractWhatsNewFindType
{
	protected function getContentType()
	{
		return 'xfmg_media';
	}
}