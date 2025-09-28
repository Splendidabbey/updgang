<?php

namespace XFMG\Admin\Controller;

use XF\Admin\Controller\AbstractController;
use XF\Mvc\FormAction;
use XF\Mvc\ParameterBag;

class Media extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertAdminPermission('mediaGallery');
	}

	public function actionIndex()
	{
		return $this->plugin('XF:AdminSection')->actionView('xfmg');
	}
}