<?php

namespace XFMG\XF\Admin\Controller;

use XF\Mvc\FormAction;
use XF\Mvc\ParameterBag;

class Forum extends XFCP_Forum
{
	protected function nodeAddEdit(\XF\Entity\Node $node)
	{
		$reply = parent::nodeAddEdit($node);

		if ($reply instanceof \XF\Mvc\Reply\View)
		{
			$categoryTree = $this->repository('XFMG:Category')->createCategoryTree();
			$reply->setParam('xfmgCategoryTree', $categoryTree);
		}

		return $reply;
	}

	protected function saveTypeData(FormAction $form, \XF\Entity\Node $node, \XF\Entity\AbstractNode $data)
	{
		$result = parent::saveTypeData($form, $node, $data);

		/** @var \XF\Entity\Forum $data */
		$data->xfmg_media_mirror_category_id = $this->filter('xfmg_media_mirror_category_id', 'uint');

		return $result;
	}
}