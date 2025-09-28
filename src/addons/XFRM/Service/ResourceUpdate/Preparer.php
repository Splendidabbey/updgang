<?php

namespace XFRM\Service\ResourceUpdate;

use XFRM\Entity\ResourceUpdate;

class Preparer extends \XF\Service\AbstractService
{
	/**
	 * @var ResourceUpdate
	 */
	protected $update;

	protected $attachmentHash;

	protected $logIp = true;

	protected $mentionedUsers = [];

	public function __construct(\XF\App $app, ResourceUpdate $update)
	{
		parent::__construct($app);
		$this->update = $update;
	}

	public function getUpdate()
	{
		return $this->update;
	}

	public function logIp($logIp)
	{
		$this->logIp = $logIp;
	}

	public function getMentionedUsers($limitPermissions = true)
	{
		if ($limitPermissions)
		{
			/** @var \XF\Entity\User $user */
			$user = $this->update->Resource->User ?: $this->repository('XF:User')->getGuestUser();
			return $user->getAllowedUserMentions($this->mentionedUsers);
		}
		else
		{
			return $this->mentionedUsers;
		}
	}

	public function getMentionedUserIds($limitPermissions = true)
	{
		return array_keys($this->getMentionedUsers($limitPermissions));
	}

	public function setMessage($message, $format = true, $checkValidity = true)
	{
		$preparer = $this->getMessagePreparer($format);
		$this->update->message = $preparer->prepare($message, $checkValidity);
		$this->update->embed_metadata = $preparer->getEmbedMetadata();

		$this->mentionedUsers = $preparer->getMentionedUsers();

		return $preparer->pushEntityErrorIfInvalid($this->update);
	}

	/**
	 * @param bool $format
	 *
	 * @return \XF\Service\Message\Preparer
	 */
	protected function getMessagePreparer($format = true)
	{
		$options = $this->app->options();

		// If we have both a max image and update length, then scale the image/media limit based on that.
		// Otherwise, place very high limits on each that are unlikely to ever legitimately be hit.
		if ($options->messageMaxLength && $options->xfrmUpdateMaxLength)
		{
			$ratio = ceil($options->xfrmUpdateMaxLength / $options->messageMaxLength);
			$maxImages = $options->messageMaxImages * $ratio;
			$maxMedia = $options->messageMaxMedia * $ratio;
		}
		else
		{
			$maxImages = 100;
			$maxMedia = 30;
		}

		/** @var \XF\Service\Message\Preparer $preparer */
		$preparer = $this->service('XF:Message\Preparer', 'resource_update', $this->update);
		$preparer->setConstraint('maxLength', $options->xfrmUpdateMaxLength);
		$preparer->setConstraint('maxImages', $maxImages);
		$preparer->setConstraint('maxMedia', $maxMedia);

		if (!$format)
		{
			$preparer->disableAllFilters();
		}

		return $preparer;
	}

	public function setAttachmentHash($hash)
	{
		$this->attachmentHash = $hash;
	}

	public function checkForSpam()
	{
		$update = $this->update;
		$resource = $update->Resource;

		/** @var \XF\Entity\User $user */
		$user = $resource->User ?: $this->repository('XF:User')->getGuestUser($resource->username);

		$message = $update->title . "\n" . $update->message;

		$checker = $this->app->spam()->contentChecker();
		$checker->check($user, $message, [
			'permalink' => $this->app->router('public')->buildLink('canonical:resources', $resource),
			'content_type' => 'resource_update'
		]);

		$decision = $checker->getFinalDecision();
		switch ($decision)
		{
			case 'moderated':

				if ($update->isDescription())
				{
					$resource->resource_state = 'moderated';
				}
				else
				{
					$update->message_state = 'moderated';
				}
				break;

			case 'denied':
				$checker->logSpamTrigger($update->isDescription() ? 'resource' : 'resource_update', null);
				$update->error(\XF::phrase('your_content_cannot_be_submitted_try_later'));
				break;
		}
	}

	public function afterInsert()
	{
		if ($this->attachmentHash)
		{
			$this->associateAttachments($this->attachmentHash);
		}

		if ($this->logIp)
		{
			$ip = ($this->logIp === true ? $this->app->request()->getIp() : $this->logIp);
			$this->writeIpLog($ip);
		}

		$update = $this->update;
		$checker = $this->app->spam()->contentChecker();

		if ($update->isDescription())
		{
			$checker->logContentSpamCheck('resource', $update->resource_id);
			$checker->logSpamTrigger('resource', $update->resource_id);
		}
		else
		{
			$checker->logContentSpamCheck('resource_update', $update->resource_update_id);
			$checker->logSpamTrigger('resource_update', $update->resource_update_id);
		}
	}

	public function afterUpdate()
	{
		if ($this->attachmentHash)
		{
			$this->associateAttachments($this->attachmentHash);
		}

		$update = $this->update;
		$checker = $this->app->spam()->contentChecker();

		if ($update->isDescription())
		{
			$checker->logSpamTrigger('resource', $update->resource_id);
		}
		else
		{
			$checker->logSpamTrigger('resource_update', $update->resource_update_id);
		}
	}

	protected function associateAttachments($hash)
	{
		$update = $this->update;

		/** @var \XF\Service\Attachment\Preparer $inserter */
		$inserter = $this->service('XF:Attachment\Preparer');
		$associated = $inserter->associateAttachmentsWithContent($hash, 'resource_update', $update->resource_update_id);
		if ($associated)
		{
			$update->fastUpdate('attach_count', $update->attach_count + $associated);
		}
	}

	protected function writeIpLog($ip)
	{
		$update = $this->update;

		/** @var \XF\Repository\IP $ipRepo */
		$ipRepo = $this->repository('XF:Ip');
		$ipEnt = $ipRepo->logIp($update->Resource->user_id, $ip, 'resource_update', $update->resource_update_id);
		if ($ipEnt)
		{
			$update->fastUpdate('ip_id', $ipEnt->ip_id);
		}
	}
}