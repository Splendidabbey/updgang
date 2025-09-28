<?php

namespace XFMG\Service\Media;

use XFMG\Entity\MediaItem;

class Preparer extends \XF\Service\AbstractService
{
	/**
	 * @var MediaItem
	 */
	protected $mediaItem;

	protected $logIp = true;

	protected $mentionedUsers = [];

	public function __construct(\XF\App $app, MediaItem $mediaItem)
	{
		parent::__construct($app);
		$this->setMediaItem($mediaItem);
	}

	public function setMediaItem(MediaItem $mediaItem)
	{
		$this->mediaItem = $mediaItem;
	}

	public function getMediaItem()
	{
		return $this->mediaItem;
	}

	public function logIp($logIp)
	{
		$this->logIp = $logIp;
	}

	public function getMentionedUsers($limitPermissions = true)
	{
		if ($limitPermissions && $this->mediaItem)
		{
			$user = $this->mediaItem->User ?: $this->repository('XF:User')->getGuestUser();
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

	public function setDescription($description, $format = true)
	{
		$preparer = $this->getStructuredTextPreparer($format);
		$preparer->setConstraint('maxLength', $this->app->options()->messageMaxLength);
		$this->mediaItem->description = $preparer->prepare($description);

		$this->mentionedUsers = $preparer->getMentionedUsers();

		return $preparer->pushEntityErrorIfInvalid($this->mediaItem);
	}

	/**
	 * @param bool $format
	 *
	 * @return \XF\Service\StructuredText\Preparer
	 */
	protected function getStructuredTextPreparer($format = true)
	{
		/** @var \XF\Service\StructuredText\Preparer $preparer */
		$preparer = $this->service('XF:StructuredText\Preparer', 'xfmg_media', $this->mediaItem);
		if (!$format)
		{
			$preparer->disableAllFilters();
		}

		return $preparer;
	}

	public function checkForSpam()
	{
		$mediaItem = $this->mediaItem;

		/** @var \XF\Entity\User $user */
		$user = $mediaItem->User ?: $this->repository('XF:User')->getGuestUser($mediaItem->username);

		$message = $mediaItem->title . "\n" . $mediaItem->description;

		$router = $this->app->router('public');
		$checker = $this->app->spam()->contentChecker();
		$checker->check($user, $message, [
			'permalink' => $router->buildLink('canonical:media', $mediaItem),
			'content_type' => 'media'
		]);

		$decision = $checker->getFinalDecision();
		switch ($decision)
		{
			case 'moderated':
				$mediaItem->media_state = 'moderated';
				break;

			case 'denied':
				$checker->logSpamTrigger('xfmg_media', $mediaItem->media_id);
				$mediaItem->error(\XF::phrase('your_content_cannot_be_submitted_try_later'));
				break;
		}
	}

	public function afterInsert()
	{
		if ($this->logIp)
		{
			$ip = ($this->logIp === true ? $this->app->request()->getIp() : $this->logIp);
			$this->writeIpLog($ip);
		}

		$mediaItem = $this->mediaItem;
		$checker = $this->app->spam()->contentChecker();

		$checker->logContentSpamCheck('xfmg_media', $mediaItem->media_id);
		$checker->logSpamTrigger('xfmg_media', $mediaItem->media_id);
	}

	public function afterUpdate()
	{
		$mediaItem = $this->mediaItem;
		$checker = $this->app->spam()->contentChecker();

		$checker->logSpamTrigger('xfmg_media', $mediaItem->media_id);
	}

	protected function writeIpLog($ip)
	{
		$mediaItem = $this->mediaItem;

		/** @var \XF\Repository\IP $ipRepo */
		$ipRepo = $this->repository('XF:Ip');
		$ipEnt = $ipRepo->logIp($mediaItem->user_id, $ip, 'xfmg_media', $mediaItem->media_id);
		if ($ipEnt)
		{
			$mediaItem->fastUpdate('ip_id', $ipEnt->ip_id);
		}
	}
}