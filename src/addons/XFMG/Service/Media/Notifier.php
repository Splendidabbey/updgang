<?php

namespace XFMG\Service\Media;

use XFMG\Entity\MediaItem;
use XF\Service\AbstractNotifier;

class Notifier extends AbstractNotifier
{
	/**
	 * @var MediaItem
	 */
	protected $mediaItem;

	public function __construct(\XF\App $app, MediaItem $mediaItem)
	{
		parent::__construct($app);

		$this->mediaItem = $mediaItem;
	}

	public static function createForJob(array $extraData)
	{
		$mediaItem = \XF::app()->find('XFMG:MediaItem', $extraData['mediaId']);
		if (!$mediaItem)
		{
			return null;
		}

		return \XF::service('XFMG:Media\Notifier', $mediaItem);
	}

	protected function getExtraJobData()
	{
		return [
			'mediaId' => $this->mediaItem->media_id
		];
	}

	protected function loadNotifiers()
	{
		$notifiers = [
			'mention' => $this->app->notifier('XFMG:Media\Mention', $this->mediaItem)
		];

		if ($this->mediaItem->album_id)
		{
			$notifiers['albumWatch'] = $this->app->notifier('XFMG:Media\AlbumWatch', $this->mediaItem, 'media');
		}
		else
		{
			$notifiers['categoryWatch'] = $this->app->notifier('XFMG:Media\CategoryWatch', $this->mediaItem, 'media');
		}

		return $notifiers;
	}

	protected function loadExtraUserData(array $users)
	{
		$permCombinationIds = [];
		foreach ($users AS $user)
		{
			$id = $user->permission_combination_id;
			$permCombinationIds[$id] = $id;
		}

		$this->app->permissionCache()->cacheMultipleContentPermsForContent(
			$permCombinationIds,
			'xfmg_category', $this->mediaItem->category_id
		);
	}

	protected function canUserViewContent(\XF\Entity\User $user)
	{
		return \XF::asVisitor(
			$user,
			function() { return $this->mediaItem->canView(); }
		);
	}
}