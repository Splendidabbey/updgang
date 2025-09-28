<?php

namespace XFMG\Spam\Cleaner;

use XF\Spam\Cleaner\AbstractHandler;

class Media extends AbstractHandler
{
	public function canCleanUp(array $options = [])
	{
		return !empty($options['action_threads']);
	}

	public function cleanUp(array &$log, &$error = null)
	{
		$app = \XF::app();

		$mediaItemsFinder = $app->finder('XFMG:MediaItem');
		$mediaItems = $mediaItemsFinder
			->where('user_id', $this->user->user_id)
			->fetch();

		if ($mediaItems->count())
		{
			$mediaIds = $mediaItems->pluckNamed('media_id');
			$submitter = $app->container('spam.contentSubmitter');
			$submitter->submitSpam('xfmg_media', $mediaIds);

			$deleteType = $app->options()->spamMessageAction == 'delete' ? 'hard' : 'soft';

			$log['xfmg_media'] = [
				'deleteType' => $deleteType,
				'mediaIds' => []
			];

			foreach ($mediaItems AS $mediaId => $mediaItem)
			{
				$log['xfmg_media']['mediaIds'][] = $mediaId;

				/** @var \XFMG\Entity\MediaItem $mediaItem */
				$mediaItem->setOption('log_moderator', false);
				if ($deleteType == 'soft')
				{
					$mediaItem->media_state = 'deleted';
					$mediaItem->save();
				}
				else
				{
					$mediaItem->delete();
				}
			}
		}

		return true;
	}

	public function restore(array $log, &$error = null)
	{
		$mediaItemsFinder = \XF::app()->finder('XFMG:MediaItem');

		if ($log['deleteType'] == 'soft')
		{
			$mediaItems = $mediaItemsFinder->where('media_id', $log['mediaIds'])->fetch();
			foreach ($mediaItems AS $mediaItem)
			{
				/** @var \XFMG\Entity\MediaItem $mediaItem */
				$mediaItem->setOption('log_moderator', false);
				$mediaItem->media_state = 'visible';
				$mediaItem->save();
			}
		}

		return true;
	}
}