<?php

namespace XFRM\Repository;

use XF\Mvc\Entity\Repository;

class ResourceWatch extends Repository
{
	public function autoWatchResource(\XFRM\Entity\ResourceItem $resource, \XF\Entity\User $user, $onCreation = false)
	{
		$userField = $onCreation ? 'creation_watch_state' : 'interaction_watch_state';

		if (!$resource->resource_id || !$user->user_id || !$user->Option->getValue($userField))
		{
			return null;
		}

		$watch = $this->em->find('XFRM:ResourceWatch', [
			'resource_id' => $resource->resource_id,
			'user_id' => $user->user_id
		]);
		if ($watch)
		{
			return null;
		}

		$watch = $this->em->create('XFRM:ResourceWatch');
		$watch->resource_id = $resource->resource_id;
		$watch->user_id = $user->user_id;
		$watch->email_subscribe = ($user->Option->getValue($userField) == 'watch_email');

		try
		{
			$watch->save();
		}
		catch (\XF\Db\DuplicateKeyException $e)
		{
			return null;
		}

		return $watch;
	}

	public function setWatchState(\XFRM\Entity\ResourceItem $resource, \XF\Entity\User $user, $action, array $config = [])
	{
		if (!$resource->resource_id || !$user->user_id)
		{
			throw new \InvalidArgumentException("Invalid resource or user");
		}

		$watch = $this->em->find('XFRM:ResourceWatch', [
			'resource_id' => $resource->resource_id,
			'user_id' => $user->user_id
		]);

		switch ($action)
		{
			case 'watch':
				if (!$watch)
				{
					$watch = $this->em->create('XFRM:ResourceWatch');
					$watch->resource_id = $resource->resource_id;
					$watch->user_id = $user->user_id;
				}
				unset($config['resource_id'], $config['user_id']);

				$watch->bulkSet($config);
				$watch->save();
				break;

			case 'update':
				if ($watch)
				{
					unset($config['resource_id'], $config['user_id']);

					$watch->bulkSet($config);
					$watch->save();
				}
				break;

			case 'delete':
				if ($watch)
				{
					$watch->delete();
				}
				break;

			default:
				throw new \InvalidArgumentException("Unknown action '$action' (expected: delete/watch)");
		}
	}

	public function setWatchStateForAll(\XF\Entity\User $user, $action, array $updates = [])
	{
		if (!$user->user_id)
		{
			throw new \InvalidArgumentException("Invalid user");
		}

		$db = $this->db();

		switch ($action)
		{
			case 'update':
				unset($updates['resource_id'], $updates['user_id']);
				return $db->update('xf_rm_resource_watch', $updates, 'user_id = ?', $user->user_id);

			case 'delete':
				return $db->delete('xf_rm_resource_watch', 'user_id = ?', $user->user_id);

			default:
				throw new \InvalidArgumentException("Unknown action '$action'");
		}
	}

	public function isValidWatchState($state)
	{
		switch ($state)
		{
			case 'watch':
			case 'update':
			case 'delete':

			default:
				return false;
		}
	}
}