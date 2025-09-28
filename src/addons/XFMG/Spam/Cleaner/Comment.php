<?php

namespace XFMG\Spam\Cleaner;

use XF\Spam\Cleaner\AbstractHandler;

class Comment extends AbstractHandler
{
	public function canCleanUp(array $options = [])
	{
		return !empty($options['delete_messages']);
	}

	public function cleanUp(array &$log, &$error = null)
	{
		$app = \XF::app();

		$commentsFinder = $app->finder('XFMG:Comment');
		$comments = $commentsFinder
			->where('user_id', $this->user->user_id)
			->fetch();

		if ($comments->count())
		{
			$commentIds = $comments->pluckNamed('comment_id');
			$submitter = $app->container('spam.contentSubmitter');
			$submitter->submitSpam('xfmg_comment', $commentIds);

			$deleteType = $app->options()->spamMessageAction == 'delete' ? 'hard' : 'soft';

			$log['xfmg_comment'] = [
				'deleteType' => $deleteType,
				'commentIds' => []
			];

			foreach ($comments AS $commentId => $comment)
			{
				$log['xfmg_comment']['commentIds'][] = $commentId;

				/** @var \XFMG\Entity\Comment $comment */
				$comment->setOption('log_moderator', false);
				if ($deleteType == 'soft')
				{
					$comment->comment_state = 'deleted';
					$comment->save();
				}
				else
				{
					$comment->delete();
				}
			}
		}

		return true;
	}

	public function restore(array $log, &$error = null)
	{
		$commentsFinder = \XF::app()->finder('XFMG:Comment');

		if ($log['deleteType'] == 'soft')
		{
			$comments = $commentsFinder->where('comment_id', $log['commentIds'])->fetch();
			foreach ($comments AS $comment)
			{
				/** @var \XFMG\Entity\Comment $comment */
				$comment->setOption('log_moderator', false);
				$comment->comment_state = 'visible';
				$comment->save();
			}
		}

		return true;
	}
}