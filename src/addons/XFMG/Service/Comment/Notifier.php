<?php

namespace XFMG\Service\Comment;

use XF\Service\AbstractNotifier;
use XFMG\Entity\Comment;

class Notifier extends AbstractNotifier
{
	/**
	 * @var Comment
	 */
	protected $comment;

	public function __construct(\XF\App $app, Comment $comment)
	{
		parent::__construct($app);

		$this->comment = $comment;
	}

	public static function createForJob(array $extraData)
	{
		$comment = \XF::app()->find('XFMG:Comment', $extraData['commentId']);
		if (!$comment)
		{
			return null;
		}

		return \XF::service('XFMG:Comment\Notifier', $comment);
	}

	protected function getExtraJobData()
	{
		return [
			'commentId' => $this->comment->comment_id
		];
	}

	protected function loadNotifiers()
	{
		$notifiers = [
			'mention' => $this->app->notifier('XFMG:Comment\Mention', $this->comment),
			'quote' => $this->app->notifier('XFMG:Comment\Quote', $this->comment)
		];

		if ($this->comment->content_type == 'xfmg_media')
		{
			$notifiers['mediaWatch'] = $this->app->notifier('XFMG:Comment\MediaWatch', $this->comment);
		}
		else
		{
			$notifiers['albumWatch'] = $this->app->notifier('XFMG:Comment\AlbumWatch', $this->comment);
		}

		return $notifiers;
	}

	protected function loadExtraUserData(array $users)
	{
		return;
	}

	protected function canUserViewContent(\XF\Entity\User $user)
	{
		return \XF::asVisitor(
			$user,
			function() { return $this->comment->canView(); }
		);
	}
}