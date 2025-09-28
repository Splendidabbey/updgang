<?php

namespace XFMG\XF\Pub\Controller;

use XF\Mvc\ParameterBag;

class GotoPage extends XFCP_GotoPage
{
	public function actionXfmgComment(ParameterBag $params)
	{
		$params->offsetSet('comment_id', $this->filter('id', 'uint'));
		return $this->rerouteController('XFMG:Comment', 'index', $params);
	}
}