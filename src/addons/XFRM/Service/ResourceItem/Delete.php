<?php

namespace XFRM\Service\ResourceItem;

use XFRM\Entity\ResourceItem;

class Delete extends \XF\Service\AbstractService
{
	/**
	 * @var \XFRM\Entity\ResourceItem
	 */
	protected $resource;

	/**
	 * @var \XF\Entity\User|null
	 */
	protected $user;

	protected $addPost = false;
	protected $postDeleteReason = '';

	/**
	 * @var null|\XF\Entity\User
	 */
	protected $postByUser = null;

	protected $alert = false;
	protected $alertReason = '';

	public function __construct(\XF\App $app, ResourceItem $resource)
	{
		parent::__construct($app);
		$this->resource = $resource;

		if (!empty($app->options()->xfrmResourceDeleteThreadAction['add_post']))
		{
			$this->addPost = true;
		}
	}

	public function getResource()
	{
		return $this->resource;
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

	public function setAddPost($addPost)
	{
		$this->addPost = (bool)$addPost;
	}

	public function setPostByUser(\XF\Entity\User $user = null)
	{
		$this->postByUser = $user;
	}

	public function setPostDeleteReason($reason)
	{
		$this->postDeleteReason = $reason;
	}

	public function delete($type, $reason = '')
	{
		$user = $this->user ?: \XF::visitor();
		$wasVisible = $this->resource->isVisible();

		if ($type == 'soft')
		{
			$result = $this->resource->softDelete($reason, $user);
		}
		else
		{
			$result = $this->resource->delete();
		}

		$this->updateResourceThread();

		if ($result && $wasVisible && $this->alert && $this->resource->user_id != $user->user_id)
		{
			/** @var \XFRM\Repository\ResourceItem $resourceRepo */
			$resourceRepo = $this->repository('XFRM:ResourceItem');
			$resourceRepo->sendModeratorActionAlert($this->resource, 'delete', $this->alertReason);
		}

		return $result;
	}

	protected function updateResourceThread()
	{
		if (!$this->addPost)
		{
			return;
		}

		$resource = $this->resource;
		$thread = $resource->Discussion;
		if (!$thread)
		{
			return;
		}

		if ($this->postByUser)
		{
			$asUser = $this->postByUser;
		}
		else
		{
			$asUser = $resource->User ?: $this->repository('XF:User')->getGuestUser($resource->username);
		}

		\XF::asVisitor($asUser, function() use ($thread)
		{
			$replier = $this->setupResourceThreadReply($thread);
			if ($replier && $replier->validate())
			{
				$existingLastPostDate = $replier->getThread()->last_post_date;

				$post = $replier->save();
				$this->afterResourceThreadReplied($post, $existingLastPostDate);

				\XF::runLater(function() use ($replier)
				{
					$replier->sendNotifications();
				});
			}
		});
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

		return $replier;
	}

	protected function getThreadReplyMessage()
	{
		$resource = $this->resource;
		$username = $resource->User ? $resource->User->username : $resource->username;
		$phraseName = $this->postDeleteReason ? 'xfrm_resource_thread_delete_reason_x' : 'xfrm_resource_thread_delete';

		$phrase = \XF::phrase($phraseName, [
			'title' => $resource->title_,
			'tag_line' => $resource->tag_line_,
			'username' => $username,
			'reason' => $this->postDeleteReason
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
}