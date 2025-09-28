<?php

namespace XFMG\Service\Comment;

use XFMG\Entity\Comment;

class Deleter extends \XF\Service\AbstractService
{
	/**
	 * @var Comment
	 */
	protected $comment;

	protected $user;

	protected $alert = false;
	protected $alertReason = '';

	public function __construct(\XF\App $app, Comment $comment)
	{
		parent::__construct($app);
		$this->setComment($comment);
	}

	public function setComment(Comment $comment)
	{
		$this->comment = $comment;
	}

	public function getComment()
	{
		return $this->comment;
	}

	public function setUser(\XF\Entity\User $user = null)
	{
		$this->user = $user;
	}

	public function getUser()
	{
		return $this->user;
	}

	public function setSendAlert($alert, $reason = null)
	{
		$this->alert = (bool)$alert;
		if ($reason !== null)
		{
			$this->alertReason = $reason;
		}
	}

	public function delete($type, $reason = '')
	{
		$user = $this->user ?: \XF::visitor();

		$result = null;

		$wasVisible = $this->comment->comment_state == 'visible';

		if ($type == 'soft')
		{
			$result = $this->comment->softDelete($reason, $user);
		}
		else
		{
			$result = $this->comment->delete();
		}

		if ($result && $wasVisible && $this->alert && $this->comment->user_id != $user->user_id)
		{
			/** @var \XFMG\Repository\Comment $commentRepo */
			$commentRepo = $this->repository('XFMG:Comment');
			$commentRepo->sendModeratorActionAlert($this->comment, 'delete', $this->alertReason);
		}

		return $result;
	}
}