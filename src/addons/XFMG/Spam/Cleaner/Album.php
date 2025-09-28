<?php

namespace XFMG\Spam\Cleaner;

use XF\Spam\Cleaner\AbstractHandler;

class Album extends AbstractHandler
{
	public function canCleanUp(array $options = [])
	{
		return !empty($options['action_threads']);
	}

	public function cleanUp(array &$log, &$error = null)
	{
		$app = \XF::app();

		$albumsFinder = $app->finder('XFMG:Album');
		$albums = $albumsFinder
			->where('user_id', $this->user->user_id)
			->fetch();

		if ($albums->count())
		{
			$albumIds = $albums->pluckNamed('album_id');
			$submitter = $app->container('spam.contentSubmitter');
			$submitter->submitSpam('xfmg_album', $albumIds);

			$deleteType = $app->options()->spamMessageAction == 'delete' ? 'hard' : 'soft';

			$log['xfmg_album'] = [
				'deleteType' => $deleteType,
				'albumIds' => []
			];

			foreach ($albums AS $albumId => $album)
			{
				$log['xfmg_album']['albumIds'][] = $albumId;

				/** @var \XFMG\Entity\Album $album */
				$album->setOption('log_moderator', false);
				if ($deleteType == 'soft')
				{
					$album->album_state = 'deleted';
					$album->save();
				}
				else
				{
					$album->delete();
				}
			}
		}

		return true;
	}

	public function restore(array $log, &$error = null)
	{
		$albumsFinder = \XF::app()->finder('XFMG:Album');

		if ($log['deleteType'] == 'soft')
		{
			$albums = $albumsFinder->where('album_id', $log['albumIds'])->fetch();
			foreach ($albums AS $album)
			{
				/** @var \XFMG\Entity\Album $album */
				$album->setOption('log_moderator', false);
				$album->album_state = 'visible';
				$album->save();
			}
		}

		return true;
	}
}