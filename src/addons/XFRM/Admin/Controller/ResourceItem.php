<?php

namespace XFRM\Admin\Controller;

use XF\Admin\Controller\AbstractController;
use XF\Mvc\FormAction;
use XF\Mvc\ParameterBag;

class ResourceItem extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertAdminPermission('resourceManager');
	}

	public function actionIndex()
	{
		return $this->plugin('XF:AdminSection')->actionView('xfrm');
	}
}