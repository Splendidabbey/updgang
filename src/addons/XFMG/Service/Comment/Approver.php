<?php

namespace XFMG\Service\Comment;

use XFMG\Entity\Comment;

class Approver extends \XF\Service\AbstractService
{
	/**
	 * @var Comment
	 */
	protected $comment;

	/**
	 * @var int
	 */
	protected $notifyRunTime = 3;

	public function __construct(\XF\App $app, Comment $comment)
	{
		parent::__construct($app);
		$this->comment = $comment;
	}

	public function getComment(): Comment
	{
		return $this->comment;
	}

	public function setNotifyRunTime(int $time)
	{
		$this->notifyRunTime = $time;
	}

	public function approve(): bool
	{
		if ($this->comment->comment_state != 'moderated')
		{
			return false;
		}

		$this->comment->comment_state = 'visible';
		$this->comment->save();

		$this->onApprove();
		return true;
	}

	protected function onApprove()
	{
		if ($this->comment->isLastComment())
		{
			/** @var \XFMG\Service\Comment\Notifier $notifier */
			$notifier = $this->service('XFMG:Comment\Notifier', $this->comment);
			$notifier->notifyAndEnqueue($this->notifyRunTime);
		}
	}
}
