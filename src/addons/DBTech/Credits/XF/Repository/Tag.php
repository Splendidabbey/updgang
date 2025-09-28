<?php /** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Credits\XF\Repository;

use DBTech\Credits\Helper;

class Tag extends XFCP_Tag
{
	/**
	 * @param array $tagIds
	 * @param $contentType
	 * @param $contentId
	 * @param $contentDate
	 * @param $contentVisible
	 * @param $addUserId
	 *
	 * @return array
	 * @throws \Exception
	 */
	protected function addTagIdsToContent(array $tagIds, $contentType, $contentId, $contentDate, $contentVisible, $addUserId)
	{
		$insertedIds = parent::addTagIdsToContent($tagIds, $contentType, $contentId, $contentDate, $contentVisible, $addUserId);

		$handler = $this->getTagHandler($contentType, true);
		if (!$handler)
		{
			return $insertedIds;
		}

		$content = $handler->getContent($contentId);
		if (!$content)
		{
			return $insertedIds;
		}

		$nodeId = 0;
		switch ($contentType)
		{
			case 'tl_group':
				return $insertedIds;

			case 'thread':
				$nodeId = $content->node_id;
				break;
		}

		/** @var \DBTech\Credits\XF\Entity\User $addUser */
		$addUser = Helper::find(\XF\Entity\User::class, $addUserId);

		$eventTriggerRepo = Helper::repository(\DBTech\Credits\Repository\EventTrigger::class);
		$tagHandler = $eventTriggerRepo->getHandler('tag');

		foreach ($insertedIds AS $addId)
		{
			$tagHandler
				->apply($addId, [
					'node_id' => $nodeId,
					'owner_id' => $content->user_id,
					'content_type' => $contentType,
					'content_id' => $contentId
				], $addUser)
			;
		}

		return $insertedIds;
	}

	/**
	 * @param array $tagIds
	 * @param $contentType
	 * @param $contentId
	 *
	 * @throws \Exception
	 */
	protected function removeTagIdsFromContent(array $tagIds, $contentType, $contentId)
	{
		if ($tagIds)
		{
			$handler = $this->getTagHandler($contentType, true);
			if (!$handler)
			{
				parent::removeTagIdsFromContent($tagIds, $contentType, $contentId);

				return;
			}

			$content = $handler->getContent($contentId);
			if (!$content)
			{
				parent::removeTagIdsFromContent($tagIds, $contentType, $contentId);

				return;
			}

			$db = $this->db();
			$deletedTags = $db->fetchAll("
				SELECT *
				FROM xf_tag_content
				WHERE tag_id IN (" . $db->quote($tagIds) . ")
					AND content_type = ?
					AND content_id = ?
			", [$contentType, $contentId]);

			$nodeId = 0;
			if ($contentType == 'thread')
			{
				$nodeId = $content->node_id;
			}

			$eventTriggerRepo = Helper::repository(\DBTech\Credits\Repository\EventTrigger::class);
			$tagHandler = $eventTriggerRepo->getHandler('tag');

			foreach ($deletedTags AS $tag)
			{
				/** @var \DBTech\Credits\XF\Entity\User $addUser */
				$addUser = Helper::find(\XF\Entity\User::class, $tag['add_user_id']);

				$tagHandler
					->undo($tag['tag_id'], [
						'node_id' => $nodeId,
						'owner_id' => $content->user_id,
						'content_type' => $contentType,
						'content_id' => $contentId
					], $addUser)
				;
			}
		}

		parent::removeTagIdsFromContent($tagIds, $contentType, $contentId);
	}
}