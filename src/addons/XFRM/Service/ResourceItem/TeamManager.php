<?php

namespace XFRM\Service\ResourceItem;

use XF\Entity\User;
use XF\Mvc\Entity\AbstractCollection;
use XF\Service\AbstractService;
use XF\Service\ValidateAndSavableTrait;
use XF\Util\Arr;
use XFRM\Entity\ResourceItem;

class TeamManager extends AbstractService
{
	use ValidateAndSavableTrait;

	/**
	 * @var ResourceItem
	 */
	protected $resource;

	/**
	 * @var bool
	 */
	protected $autoSendNotifications = true;

	/**
	 * @var \XF\Entity\User[]|AbstractCollection
	 */
	protected $addMembers;

	/**
	 * @var \XF\Entity\User[]|AbstractCollection
	 */
	protected $removeMembers;

	/**
	 * @var bool
	 */
	protected $sendLeaveNotification = false;

	/**
	 * @var array
	 */
	protected $errors = [];

	public function __construct(\XF\App $app, ResourceItem $resource)
	{
		parent::__construct($app);

		$this->resource = $resource;
		$this->addMembers = $this->em()->getEmptyCollection();
		$this->removeMembers = $this->em()->getEmptyCollection();
	}

	public function getResource(): ResourceItem
	{
		return $this->resource;
	}

	public function getCurrentTeamMemberCount(): int
	{
		return $this->resource->TeamMembers->count();
	}

	public function getNewTeamMemberCount(): int
	{
		$currentCount = $this->getCurrentTeamMemberCount();
		$addingCount = $this->addMembers ? $this->addMembers->count() : 0;
		$removingCount = $this->removeMembers ? $this->removeMembers->count() : 0;
		return $currentCount + $addingCount - $removingCount;
	}

	public function setAutoSendNotifications(bool $send)
	{
		$this->autoSendNotifications = $send;
	}

	/**
	 * @param AbstractCollection|string $members Collection of users or comma-separated usernames
	 */
	public function addMembers($members)
	{
		if (is_string($members))
		{
			/** @var \XF\Repository\User $userRepo */
			$userRepo = $this->repository('XF:User');
			$usernames = Arr::stringToArray($members, '/\s*,\s*/');
			$users = $userRepo->getUsersByNames($usernames, $notFound);

			if ($notFound)
			{
				$this->errors[] = \XF::phrase(
					'following_members_not_found_x',
					['members' => implode(', ', $notFound)]
				);
			}
		}
		else if ($members instanceof AbstractCollection)
		{
			$users = $members->filter(function($member)
			{
				return $member instanceof User;
			});
		}
		else
		{
			throw new \InvalidArgumentException(
				'Members must be a collection of users or a string of comma-separated usernames'
			);
		}

		$addMembers = [];
		$invalidMembers = [];
		foreach ($users AS $userId => $user)
		{
			if (
				$userId == $this->resource->user_id ||
				$this->resource->TeamMembers[$userId]
			)
			{
				continue;
			}

			if (!$this->resource->canTeamMemberBeAdded($user))
			{
				$invalidMembers[$userId] = $user->username;
				continue;
			}

			$addMembers[$userId] = $user;
		}

		if ($invalidMembers)
		{
			$this->errors[] = \XF::phrase(
				'xfrm_you_may_not_add_following_team_members_to_this_resource_x',
				['names' => implode(', ', $invalidMembers)]
			);
		}

		$this->addMembers = $this->em()->getBasicCollection($addMembers);
	}

	/**
	 * @var \XF\Entity\User[]|AbstractCollection
	 */
	public function getAddMembers(): AbstractCollection
	{
		return $this->addMembers;
	}

	/**
	 * @param AbstractCollection|int[] $members Collection of users or array of user IDs
	 */
	public function removeMembers($members)
	{
		if (is_array($members))
		{
			$userIds = array_intersect(
				$members,
				$this->resource->TeamMembers->keys()
			);
			$users = $this->em()->findByIds('XF:User', $userIds);
		}
		else if ($members instanceof AbstractCollection)
		{
			$users = $members->filter(function($member)
			{
				return $member instanceof User;
			});
		}
		else
		{
			throw new \InvalidArgumentException(
				'Members must be a collection of users or an array of user IDs'
			);
		}

		$this->removeMembers = $users;
	}

	public function leaveTeam(\XF\Entity\User $user, bool $sendLeaveNotification = true)
	{
		if ($this->removeMembers->count())
		{
			throw new \LogicException("Only one of leaveTeam and removeMembers should be called");
		}

		$this->removeMembers = $this->em()->getBasicCollection([$user]);

		$this->sendLeaveNotification = $sendLeaveNotification;
	}

	/**
	 * @var \XF\Entity\User[]|AbstractCollection
	 */
	public function getRemoveMembers(): AbstractCollection
	{
		return $this->removeMembers;
	}

	public function setSendLeaveNotification(bool $send)
	{
		$this->sendLeaveNotification = $send;
	}

	public function getSendLeaveNotification(): bool
	{
		return $this->sendLeaveNotification;
	}

	protected function _validate(): array
	{
		if ($this->addMembers && $this->addMembers->count())
		{
			$maxAllowed = $this->resource->getMaxTeamMemberCount();
			$newCount = $this->getNewTeamMemberCount();
			if ($newCount > $maxAllowed)
			{
				$this->errors[] = \XF::phrase(
					'xfrm_you_may_only_add_x_team_members_to_this_resource',
					['count' => $maxAllowed]
				);
			}
		}

		return $this->errors;
	}

	protected function _save(): bool
	{
		$this->db()->beginTransaction();

		foreach ($this->addMembers AS $addMember)
		{
			$teamMember = $this->em()->create('XFRM:ResourceTeamMember');
			$teamMember->resource_id = $this->resource->resource_id;
			$teamMember->user_id = $addMember->user_id;
			$teamMember->save(true, false);
		}

		foreach ($this->removeMembers AS $removeMember)
		{
			$teamMember = $this->resource->TeamMembers[$removeMember->user_id];
			if (!$teamMember)
			{
				continue;
			}

			$teamMember->delete(true, false);
		}

		$this->db()->commit();

		if ($this->autoSendNotifications)
		{
			$this->sendNotifications();
		}

		return true;
	}

	public function sendNotifications()
	{
		foreach ($this->addMembers AS $addMember)
		{
			/** @var \XF\Repository\UserAlert $alertRepo */
			$alertRepo = $this->repository('XF:UserAlert');
			$alertRepo->alert(
				$addMember,
				$this->resource->user_id,
				$this->resource->username,
				'resource',
				$this->resource->resource_id,
				'team_member_add'
			);
		}

		if ($this->sendLeaveNotification && $this->resource->User)
		{
			foreach ($this->removeMembers AS $removeMember)
			{
				/** @var \XF\Repository\UserAlert $alertRepo */
				$alertRepo = $this->repository('XF:UserAlert');
				$alertRepo->alert(
					$this->resource->User,
					$removeMember->user_id,
					$removeMember->username,
					'resource',
					$this->resource->resource_id,
					'team_member_leave'
				);
			}
		}
	}
}