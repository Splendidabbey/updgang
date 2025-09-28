<?php

/*
 * Created on 10.12.2022
 * HomePage: https://xentr.net
 * Copyright (c) 2019 XENTR | XenForo Add-ons - Styles -  All Rights Reserved
 */

namespace XENTR\ForumStats\Widget;

use XF\Widget\AbstractWidget;

class ForumStatistics extends AbstractWidget
{
	public function render()
	{
		$viewParams = [
			'forumStatistics' => $this->app->forumStatistics
		];
		return $this->renderer('xentr__forum_statistics', $viewParams);
	}

	public function getOptionsTemplate()
	{
		return null;
	}
}