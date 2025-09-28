<?php

namespace XFMG\Service\Album;

use XF\Mvc\Entity\ArrayCollection;
use XF\Service\AbstractService;
use XF\Util\Arr;
use XFMG\Entity\Album;

class SharedUserManager extends AbstractService
{
	/**
	 * @var Album
	 */
	protected $album;

	/**
	 * @var ArrayCollection
	 */
	protected $usersToAdd;

	protected $type;

	protected $alertUserIds = [];

	public function __construct(\XF\App $app, Album $album, $usersToAdd, $type)
	{
		parent::__construct($app);

		$this->album = $album;
		$this->type = $type;
		$this->usersToAdd = $app->em()->getEmptyCollection();

		if ($usersToAdd)
		{
			$this->addUsersByName($usersToAdd);
		}
	}

	public function countUsers()
	{
		return $this->usersToAdd->count();
	}

	public function resetUsers()
	{
		$this->usersToAdd = $this->app->em()->getEmptyCollection();
	}

	public function addUsersByName($usersToAdd)
	{
		if (is_string($usersToAdd))
		{
			$usersToAdd = preg_split('#\s*,\s*#', $usersToAdd, -1, PREG_SPLIT_NO_EMPTY);
		}

		/** @var \XF\Repository\User $userRepo */
		$userRepo = $this->repository('XF:User');
		$users = $userRepo->getUsersByNames($usersToAdd, $null, ['Privacy']);

		if (isset($users[$this->album->user_id]))
		{
			unset($users[$this->album->user_id]); // Album owner is implicitly included
		}

		$this->usersToAdd = $this->usersToAdd->merge($users);
	}

	public function addUsersById($userIds)
	{
		if (!is_array($userIds))
		{
			$userIds = [$userIds];
		}

		$users = $this->em()->findByIds('XF:User', $userIds, ['Privacy']);

		if (isset($users[$this->album->user_id]))
		{
			unset($users[$this->album->user_id]); // Album owner is implicitly included
		}

		$this->usersToAdd = $this->usersToAdd->merge($users);
	}

	public function saveSharedUsers()
	{
		$type = $this->type;
		$album = $this->album;
		$existing = !empty($album[$type . '_users']) ? $album[$type . '_users'] : [];
		$toAdd = $this->usersToAdd->toArray();
		$toAlert = [];

		foreach ($toAdd AS $userId => $user)
		{
			if (in_array($userId, $existing))
			{
				continue; // already shared with
			}

			$sharedUser = $this->em()->create('XFMG:SharedMap' . ucfirst($type));
			$sharedUser->album_id = $album->album_id;
			$sharedUser->user_id = $user->user_id;
			$sharedUser->save();

			$toAlert[] = $userId;
		}

		/** @var \XF\Repository\UserAlert $alertRepo */
		$alertRepo = $this->app->repository('XF:UserAlert');

		$toRemove = array_diff($existing, array_keys($toAdd));
		foreach ($toRemove AS $userId)
		{
			if (!in_array($userId, $existing))
			{
				continue;
			}

			if (isset($album['SharedMap' . ucfirst($type)][$userId]))
			{
				$album['SharedMap' . ucfirst($type)][$userId]->delete();
			}

			$alertRepo->fastDeleteAlertsToUser($userId, 'xfmg_album', $album->album_id, 'shared_' . $type);
		}

		$userIds = array_keys($toAdd);
		sort($userIds);

		$this->alertUserIds = $toAlert;

		return $userIds;
	}

	public function notifyUsers()
	{
		$alertUsers = $this->em()->findByIds('XF:User', $this->alertUserIds);
		$album = $this->album;

		/** @var \XF\Repository\UserAlert $alertRepo */
		$alertRepo = $this->repository('XF:UserAlert');

		foreach ($alertUsers AS $userId => $user)
		{
			$canView = \XF::asVisitor($user, function() use ($album) { return $album->canView(); });
			if ($canView)
			{
				$alertRepo->alert(
					$user,
					$album->user_id,
					$album->username,
					'xfmg_album',
					$album->album_id,
					'share_' . $this->type,
					[],
					['autoRead' => false]
				);
			}
		}
	}
}