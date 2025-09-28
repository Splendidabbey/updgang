<?php

namespace XFMG\XF\Pub\Controller;

use XF\Mvc\ParameterBag;

class Thread extends XFCP_Thread
{
	public function actionIndex(ParameterBag $params)
	{
		$reply = parent::actionIndex($params);

		if ($reply instanceof \XF\Mvc\Reply\View && $reply->getParam('posts'))
		{
			$mediaRepo = $this->repository('XFMG:Media');
			$mediaRepo->addGalleryEmbedsToContent($reply->getParam('posts'));
		}

		return $reply;
	}
}