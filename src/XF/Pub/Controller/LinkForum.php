<?php

namespace XF\Pub\Controller;

use XF\Mvc\ParameterBag;

use function is_int;

class LinkForum extends AbstractController
{
	public function actionIndex(ParameterBag $params)
	{
		if (!$params->node_id && !$params->node_name)
		{
			return $this->redirectPermanently($this->buildLink('forums'));
		}

		$linkForum = $this->assertViewableLinkForum($params->node_id ?: $params->node_name);

		return $this->redirectPermanently($linkForum->link_url);
	}

	/**
	 * @param int $nodeId
	 * @param array $extraWith
	 *
	 * @return \XF\Entity\LinkForum
	 *
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function assertViewableLinkForum($nodeIdOrName, array $extraWith = [])
	{
		if ($nodeIdOrName === null)
		{
			throw $this->exception($this->notFound(\XF::phrase('requested_forum_not_found')));
		}

		$visitor = \XF::visitor();
		$extraWith[] = 'Node.Permissions|' . $visitor->permission_combination_id;

		$finder = $this->em()->getFinder('XF:LinkForum');
		$finder->with('Node', true)->with($extraWith);
		if (is_int($nodeIdOrName) || $nodeIdOrName === (string)(int)$nodeIdOrName)
		{
			$finder->where('node_id', $nodeIdOrName);
		}
		else
		{
			$finder->where(['Node.node_name' => $nodeIdOrName, 'Node.node_type_id' => 'LinkForum']);
		}

		/** @var \XF\Entity\LinkForum $forum */
		$forum = $finder->fetchOne();
		if (!$forum)
		{
			throw $this->exception($this->notFound(\XF::phrase('requested_forum_not_found')));
		}
		if (!$forum->canView($error))
		{
			throw $this->exception($this->noPermission($error));
		}

		return $forum;
	}

	/**
	 * @return \XF\Repository\Node
	 */
	protected function getNodeRepo()
	{
		return $this->repository('XF:Node');
	}
}