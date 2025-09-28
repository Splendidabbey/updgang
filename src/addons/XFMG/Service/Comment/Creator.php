<?php

namespace XFMG\Service\Comment;

use XF\Service\AbstractService;

class Creator extends AbstractService
{
	use \XF\Service\ValidateAndSavableTrait;

	/**
	 * @var \XFMG\Entity\MediaItem|\XFMG\Entity\Album
	 */
	protected $content;

	/**
	 * @var \XFMG\Entity\Comment
	 */
	protected $comment;

	/**
	 * @var Preparer
	 */
	protected $commentPreparer;

	protected $performValidations = true;

	protected $isPreRegAction = false;

	public function __construct(\XF\App $app, \XF\Mvc\Entity\Entity $content)
	{
		parent::__construct($app);
		$this->setContent($content);
		$this->setCommentDefaults();
	}

	protected function setContent(\XF\Mvc\Entity\Entity $content)
	{
		$this->content = $content;
		$this->comment = $this->content->getNewComment();
		$this->commentPreparer = $this->service('XFMG:Comment\Preparer', $this->comment);

		$this->setUser(\XF::visitor());
	}

	public function getContent()
	{
		return $this->content;
	}

	public function getComment()
	{
		return $this->comment;
	}

	public function getCommentPreparer()
	{
		return $this->commentPreparer;
	}

	public function setPerformValidations($perform)
	{
		$this->performValidations = (bool)$perform;
	}

	public function getPerformValidations()
	{
		return $this->performValidations;
	}

	public function setIsAutomated()
	{
		$this->setPerformValidations(false);
		$this->logIp(false);
	}

	public function setIsPreRegAction(bool $isPreRegAction)
	{
		$this->isPreRegAction = $isPreRegAction;
	}

	public function setUser(\XF\Entity\User $user)
	{
		$this->comment->user_id = $user->user_id;
		$this->comment->username = $user->username;
	}

	public function setGuestUser($username)
	{
		$this->comment->user_id = 0;
		$this->comment->username = $username;
	}

	public function logIp($logIp)
	{
		$this->commentPreparer->logIp($logIp);
	}

	protected function setCommentDefaults()
	{
		$this->comment->comment_state = $this->content->getNewCommentState();
	}

	public function setMessage($message, $format = true)
	{
		return $this->commentPreparer->setMessage($message, $format, $this->performValidations);
	}

	public function checkForSpam()
	{
		if ($this->comment->comment_state == 'visible' && \XF::visitor()->isSpamCheckRequired())
		{
			$this->commentPreparer->checkForSpam();
		}
	}

	protected function finalSetup()
	{
		$this->comment->comment_date = time();
	}

	protected function _validate()
	{
		$this->finalSetup();

		$comment = $this->comment;

		if (!$comment->user_id && !$this->isPreRegAction)
		{
			/** @var \XF\Validator\Username $validator */
			$validator = $this->app->validator('Username');
			$comment->username = $validator->coerceValue($comment->username);
			if (!$validator->isValid($comment->username, $error))
			{
				return [$validator->getPrintableErrorValue($error)];
			}
		}
		else if ($this->isPreRegAction && !$comment->username)
		{
			// need to force a value here to avoid a presave error
			$comment->username = 'preRegAction-' . \XF::$time;
		}

		$comment->preSave();
		return $comment->getErrors();
	}

	protected function _save()
	{
		if ($this->isPreRegAction)
		{
			throw new \LogicException("Pre-reg action comments cannot be saved");
		}

		$comment = $this->comment;
		$comment->save();

		$this->commentPreparer->afterInsert();

		return $comment;
	}

	public function sendNotifications()
	{
		if ($this->comment->isVisible())
		{
			/** @var \XFMG\Service\Comment\Notifier $notifier */
			$notifier = $this->service('XFMG:Comment\Notifier', $this->comment);
			$notifier->setMentionedUserIds($this->commentPreparer->getMentionedUserIds());
			$notifier->setQuotedUserIds($this->commentPreparer->getQuotedUserIds());
			$notifier->notifyAndEnqueue(3);
		}
	}
}