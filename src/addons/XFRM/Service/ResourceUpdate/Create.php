<?php

namespace XFRM\Service\ResourceUpdate;

use XF\Entity\User;
use XFRM\Entity\ResourceUpdate;

class Create extends \XF\Service\AbstractService
{
	use \XF\Service\ValidateAndSavableTrait;

	/**
	 * @var \XFRM\Entity\ResourceItem
	 */
	protected $resource;

	/**
	 * @var ResourceUpdate
	 */
	protected $update;

	/**
	 * @var \XFRM\Service\ResourceUpdate\Preparer
	 */
	protected $updatePreparer;

	/**
	 * @var \XF\Service\Thread\Replier|null
	 */
	protected $threadReplier;

	protected $performValidations = true;

	public function __construct(
		\XF\App $app,
		\XFRM\Entity\ResourceItem $resource,
		User $teamUser = null
	)
	{
		parent::__construct($app);

		$this->resource = $resource;
		$this->update = $resource->getNewUpdate($teamUser);
		$this->updatePreparer = $this->service('XFRM:ResourceUpdate\Preparer', $this->update);
		$this->setUpdateDefaults();
	}

	public function getResource()
	{
		return $this->resource;
	}

	public function getUpdate()
	{
		return $this->update;
	}

	public function getUpdatePreparer()
	{
		return $this->updatePreparer;
	}

	public function logIp($logIp)
	{
		$this->updatePreparer->logIp($logIp);
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
		$this->logIp(false);
		$this->setPerformValidations(false);
	}

	protected function setUpdateDefaults()
	{
		$category = $this->resource->Category;
		$this->update->message_state = $category->getNewContentState($this->resource);
	}

	public function setMessage($message, $format = true)
	{
		return $this->updatePreparer->setMessage($message, $format, $this->performValidations);
	}

	public function setTitle($title)
	{
		$this->update->title = $title;
	}

	public function setAttachmentHash($hash)
	{
		$this->updatePreparer->setAttachmentHash($hash);
	}

	public function checkForSpam()
	{
		if ($this->update->message_state == 'visible' && \XF::visitor()->isSpamCheckRequired())
		{
			$this->updatePreparer->checkForSpam();
		}
	}

	protected function finalSetup()
	{

	}

	protected function _validate()
	{
		$this->finalSetup();

		$update = $this->update;

		$update->preSave();
		$errors = $update->getErrors();

		return $errors;
	}

	protected function _save()
	{
		$update = $this->update;
		$resource = $this->resource;

		$db = $this->db();
		$db->beginTransaction();

		$update->save(true, false);

		$this->updatePreparer->afterInsert();

		if ($resource->discussion_thread_id && $resource->Discussion)
		{
			$existingLastPostDate = null;

			$createReply = function() use ($resource, &$existingLastPostDate)
			{
				$replier = $this->setupResourceThreadReply($resource->Discussion);
				if ($replier && $replier->validate())
				{
					$existingLastPostDate = $replier->getThread()->last_post_date;

					$post = $replier->save();
					$this->threadReplier = $replier;

					return $post;
				}

				return null;
			};

			// Make sure the post is made as the resource owner, but allow the later actions to apply
			// to the team member making the update, like marking the thread as read.

			$post = $resource->User
				? \XF::asVisitor($resource->User, $createReply)
				: $createReply();

			if ($post)
			{
				$this->afterResourceThreadReplied($post, $existingLastPostDate);
			}
		}

		$db->commit();

		return $update;
	}

	protected function setupResourceThreadReply(\XF\Entity\Thread $thread)
	{
		if (!$thread->Forum)
		{
			// thread has been orphaned somehow?
			return null;
		}

		/** @var \XF\Service\Thread\Replier $replier */
		$replier = $this->service('XF:Thread\Replier', $thread);
		$replier->setIsAutomated();

		$replier->setMessage($this->getThreadReplyMessage(), false);
		$replier->getPost()->message_state = $this->update->message_state;

		return $replier;
	}

	protected function getThreadReplyMessage()
	{
		$resource = $this->resource;
		$update = $this->update;

		$snippet = $this->app->bbCode()->render(
			$this->app->stringFormatter()->wholeWordTrim($update->message, 500),
			'bbCodeClean',
			'post',
			null
		);

		$phrase = \XF::phrase('xfrm_resource_thread_update', [
			'title' => $update->title_,
			'resource_title' => $resource->title_,
			'username' => $resource->User ? $resource->User->username : $resource->username,
			'snippet' => $snippet,
			'resource_link' => $this->app->router('public')->buildLink('canonical:resources', $resource),
			'update_link' => $this->app->router('public')->buildLink('canonical:resources/update', $update)
		]);

		return $phrase->render('raw');
	}

	protected function afterResourceThreadReplied(\XF\Entity\Post $post, $existingLastPostDate)
	{
		$thread = $post->Thread;

		if (\XF::visitor()->user_id && $post->Thread->getVisitorReadDate() >= $existingLastPostDate)
		{
			$this->repository('XF:Thread')->markThreadReadByVisitor($thread);
		}
	}

	public function sendNotifications()
	{
		if ($this->update->isVisible())
		{
			/** @var \XFRM\Service\ResourceUpdate\Notify $notifier */
			$notifier = $this->service('XFRM:ResourceUpdate\Notify', $this->update, 'update');
			$notifier->setMentionedUserIds($this->updatePreparer->getMentionedUserIds());
			$notifier->notifyAndEnqueue(3);
		}

		if ($this->threadReplier)
		{
			$this->threadReplier->sendNotifications();
		}
	}
}