<?php

namespace XFRM\ControllerPlugin;

use XF\ControllerPlugin\AbstractPlugin;
use XFRM\Entity\ResourceItem;

class ResourceIcon extends AbstractPlugin
{
	public function actionUpload(ResourceItem $resource)
	{
		/** @var \XFRM\Service\ResourceItem\Icon $iconService */
		$iconService = $this->service('XFRM:ResourceItem\Icon', $resource);

		$action = $this->filter('icon_action', 'str');

		if ($action == 'delete')
		{
			$iconService->deleteIcon();
		}
		else if ($action == 'custom')
		{
			$upload = $this->request->getFile('upload', false, false);
			if ($upload)
			{
				if (!$iconService->setImageFromUpload($upload))
				{
					throw $this->exception($this->error($iconService->getError()));
				}

				if (!$iconService->updateIcon())
				{
					throw $this->exception($this->error(\XF::phrase('xfrm_new_icon_could_not_be_applied_try_later')));
				}
			}
		}
	}
}