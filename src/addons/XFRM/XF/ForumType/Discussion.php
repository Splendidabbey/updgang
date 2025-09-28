<?php

namespace XFRM\XF\ForumType;

use XF\Entity\Forum;

class Discussion extends XFCP_Discussion
{
	public function getExtraAllowedThreadTypes(Forum $forum): array
	{
		$allowed = parent::getExtraAllowedThreadTypes($forum);
		$allowed[] = 'resource';

		return $allowed;
	}

	public function getCreatableThreadTypes(Forum $forum): array
	{
		$creatable = parent::getCreatableThreadTypes($forum);
		$this->removeResourceTypeFromList($creatable);

		return $creatable;
	}

	public function getFilterableThreadTypes(Forum $forum): array
	{
		$filterable = parent::getFilterableThreadTypes($forum);

		$resourceTarget = \XF::db()->fetchOne("
			SELECT 1
			FROM xf_rm_category
			WHERE thread_node_id = ?
			LIMIT 1
		", $forum->node_id);
		if (!$resourceTarget)
		{
			$this->removeResourceTypeFromList($filterable);
		}

		return $filterable;
	}

	protected function removeResourceTypeFromList(array &$list)
	{
		$resourceKey = array_search('resource', $list);
		if ($resourceKey !== false)
		{
			unset($list[$resourceKey]);
		}
	}
}