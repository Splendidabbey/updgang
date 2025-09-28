<?php

namespace XFMG\Job\Upgrade;

use XF\Job\AbstractJob;

class LegacyTags110 extends AbstractJob
{
	protected $defaultData = [
		'position' => 0,
		'batch' => 10
	];

	public function run($maxRunTime)
	{
		$timer = new \XF\Timer($maxRunTime);

		$db = $this->app->db();

		$tagIds = $db->fetchAllColumn($db->limit('
			SELECT tag_id
			FROM xengallery_content_tag
			WHERE tag_id > ?
			ORDER BY tag_id
		', $this->data['batch']), $this->data['position']);
		if (!$tagIds)
		{
			return $this->complete();
		}

		/** @var \XF\Repository\Tag $tagRepo */
		$tagRepo = $this->app->repository('XF:Tag');
		$em = $this->app->em();

		foreach ($tagIds AS $oldTagId)
		{
			$this->data['position'] = $oldTagId;

			$xfmgTag = $db->fetchRow('SELECT * FROM xengallery_content_tag WHERE tag_id = ?', $oldTagId);
			$tagMap = $db->fetchAll('SELECT * FROM xengallery_content_tag_map WHERE tag_id = ?', $oldTagId);

			if (!$xfmgTag || !$tagMap)
			{
				continue;
			}

			/** @var \XF\Entity\Tag $tag */
			$tag = $tagRepo->createTag($xfmgTag['tag_name']);
			if (!$tag)
			{
				continue;
			}

			$mediaIds = [];
			$media = [];

			foreach ($tagMap AS $tagUse)
			{
				$mediaIds[] = $tagUse['media_id'];
			}

			if ($mediaIds)
			{
				$media = $em->findByIds('XFMG:MediaItem', $mediaIds);
			}

			foreach ($tagMap AS $tagUse)
			{
				if (!isset($media[$tagUse['media_id']]))
				{
					continue;
				}

				/** @var \XFMG\Entity\MediaItem $item */
				$item = $media[$tagUse['media_id']];

				try
				{
					$db->insert('xf_tag_content', [
						'content_type' => 'xengallery_media',
						'content_id' => $tagUse['media_id'],
						'tag_id' => $tag->tag_id,
						'add_user_id' => $item->user_id,
						'add_date' => $item->media_date,
						'visible' => ($item->media_state == 'visible'),
						'content_date' => $item->media_date
					]);
				}
				catch (\XF\Db\Exception $e) { continue; }

				$tagRepo->recalculateTagUsageCacheByContent('xfmg_media', $item->media_id);
				$tagRepo->rebuildContentTagCache('xfmg_media', $item->media_id);

				if ($timer->limitExceeded())
				{
					break;
				}
			}
		}

		return $this->resume();
	}

	public function getStatusMessage()
	{
		return sprintf('Rebuilding... Tags (%s)', \XF::language()->numberFormat($this->data['position']));
	}

	public function canCancel()
	{
		return false;
	}

	public function canTriggerByChoice()
	{
		return false;
	}
}