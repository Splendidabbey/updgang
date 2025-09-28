<?php

namespace XFRM\Spam\Cleaner;

use XF\Spam\Cleaner\AbstractHandler;

class ResourceRating extends AbstractHandler
{
	public function canCleanUp(array $options = [])
	{
		return !empty($options['delete_messages']);
	}

	public function cleanUp(array &$log, &$error = null)
	{
		$app = \XF::app();

		$ratings = $app->finder('XFRM:ResourceRating')
			->where('user_id', $this->user->user_id)
			->fetch();

		if ($ratings->count())
		{
			$submitter = $app->container('spam.contentSubmitter');
			$submitter->submitSpam('resource_rating', $ratings->keys());

			$deleteType = $app->options()->spamMessageAction == 'delete' ? 'hard' : 'soft';

			$log['resource_rating'] = [
				'deleteType' => $deleteType,
				'ratingIds' => []
			];

			foreach ($ratings AS $ratingId => $rating)
			{
				$log['resource_rating']['ratingIds'][] = $ratingId;

				/** @var \XFRM\Entity\ResourceRating $rating */
				$rating->setOption('log_moderator', false);
				if ($deleteType == 'soft')
				{
					$rating->softDelete();
				}
				else
				{
					$rating->delete();
				}
			}
		}

		return true;
	}

	public function restore(array $log, &$error = null)
	{
		if ($log['deleteType'] == 'soft')
		{
			$ratings = \XF::app()->finder('XFRM:ResourceRating')
				->where('resource_rating_id', $log['ratingIds'])
				->fetch();

			foreach ($ratings AS $rating)
			{
				/** @var \XFRM\Entity\ResourceRating $rating */
				$rating->setOption('log_moderator', false);
				$rating->rating_state = 'visible';
				$rating->save();
			}
		}

		return true;
	}
}