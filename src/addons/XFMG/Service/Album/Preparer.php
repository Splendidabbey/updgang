<?php

namespace XFMG\Service\Album;

use XFMG\Entity\Album;

class Preparer extends \XF\Service\AbstractService
{
	/**
	 * @var Album
	 */
	protected $album;

	protected $logIp = true;

	protected $mentionedUsers = [];

	public function __construct(\XF\App $app, Album $album)
	{
		parent::__construct($app);
		$this->setAlbum($album);
	}

	public function setAlbum(Album $album)
	{
		$this->album = $album;
	}

	public function getAlbum()
	{
		return $this->album;
	}

	public function logIp($logIp)
	{
		$this->logIp = $logIp;
	}

	public function getMentionedUsers($limitPermissions = true)
	{
		if ($limitPermissions && $this->album)
		{
			$user = $this->album->User ?: $this->repository('XF:User')->getGuestUser();
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
		$this->album->description = $preparer->prepare($description);

		$this->mentionedUsers = $preparer->getMentionedUsers();

		return $preparer->pushEntityErrorIfInvalid($this->album);
	}

	/**
	 * @param bool $format
	 *
	 * @return \XF\Service\StructuredText\Preparer
	 */
	protected function getStructuredTextPreparer($format = true)
	{
		/** @var \XF\Service\StructuredText\Preparer $preparer */
		$preparer = $this->service('XF:StructuredText\Preparer', 'xfmg_album', $this->album);
		if (!$format)
		{
			$preparer->disableAllFilters();
		}

		return $preparer;
	}

	public function checkForSpam()
	{
		$album = $this->album;

		/** @var \XF\Entity\User $user */
		$user = $album->User ?: $this->repository('XF:User')->getGuestUser($album->username);

		$message = $album->title . "\n" . $album->description;

		$router = $this->app->router('public');
		$checker = $this->app->spam()->contentChecker();
		$checker->check($user, $message, [
			'permalink' => $router->buildLink('canonical:media/albums', $album),
			'content_type' => 'album'
		]);

		$decision = $checker->getFinalDecision();
		switch ($decision)
		{
			case 'moderated':
				$album->album_state = 'moderated';
				break;

			case 'denied':
				$checker->logSpamTrigger('xfmg_album', $album->album_id);
				$album->error(\XF::phrase('your_content_cannot_be_submitted_try_later'));
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

		$album = $this->album;
		$checker = $this->app->spam()->contentChecker();

		$checker->logContentSpamCheck('xfmg_album', $album->album_id);
		$checker->logSpamTrigger('xfmg_album', $album->album_id);
	}

	public function afterUpdate()
	{
		$album = $this->album;
		$checker = $this->app->spam()->contentChecker();

		$checker->logSpamTrigger('xfmg_album', $album->album_id);
	}

	protected function writeIpLog($ip)
	{
		$album = $this->album;

		/** @var \XF\Repository\IP $ipRepo */
		$ipRepo = $this->repository('XF:Ip');
		$ipEnt = $ipRepo->logIp($album->user_id, $ip, 'xfmg_album', $album->album_id);
		if ($ipEnt)
		{
			$album->fastUpdate('ip_id', $ipEnt->ip_id);
		}
	}
}