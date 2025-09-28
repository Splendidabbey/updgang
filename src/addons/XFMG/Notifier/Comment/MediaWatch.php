<?php

namespace XFMG\Notifier\Comment;

use XF\Notifier\AbstractNotifier;

class MediaWatch extends AbstractNotifier
{
	/**
	 * @var \XFMG\Entity\Comment
	 */
	protected $comment;

	public function __construct(\XF\App $app, \XFMG\Entity\Comment $comment)
	{
		parent::__construct($app);

		$this->comment = $comment;
	}

	public function canNotify(\XF\Entity\User $user)
	{
		$comment = $this->comment;

		if ($comment->content_type != 'xfmg_media')
		{
			return false;
		}

		if ($user->user_id == $comment->user_id || $user->isIgnoring($comment->user_id))
		{
			return false;
		}

		return true;
	}

	public function sendAlert(\XF\Entity\User $user)
	{
		$comment = $this->comment;

		return $this->basicAlert(
			$user,
			$comment->user_id,
			$comment->username,
			'xfmg_comment',
			$comment->comment_id,
			'insert'
		);
	}

	public function sendEmail(\XF\Entity\User $user)
	{
		if (!$user->email || $user->user_state != 'valid')
		{
			return false;
		}

		$comment = $this->comment;

		$params = [
			'comment' => $comment,
			'content' => $comment->Content,
			'receiver' => $user
		];

		$this->app()->mailer()->newMail()
			->setToUser($user)
			->setTemplate('xfmg_watched_media_comment', $params)
			->queue();

		return true;
	}

	public function getDefaultNotifyData()
	{
		$comment = $this->comment;
		$content = $comment->Content;

		if (!$content || $comment->content_type != 'xfmg_media')
		{
			return [];
		}

		$finder = $this->app()->finder('XFMG:MediaWatch');

		$finder->where('media_id', $comment->content_id)
			->where('User.user_state', '=', 'valid')
			->where('User.is_banned', '=', 0)
			->where('notify_on', 'comment')
			->whereOr(
				['send_alert', '>', 0],
				['send_email', '>', 0]
			);

		$activeLimit = $this->app()->options()->watchAlertActiveOnly;
		if (!empty($activeLimit['enabled']))
		{
			$finder->where('User.last_activity', '>=', \XF::$time - 86400 * $activeLimit['days']);
		}

		$notifyData = [];
		foreach ($finder->fetchColumns(['user_id', 'send_alert', 'send_email']) AS $watch)
		{
			$notifyData[$watch['user_id']] = [
				'alert' => (bool)$watch['send_alert'],
				'email' => (bool)$watch['send_email']
			];
		}

		return $notifyData;
	}
}