<?php

namespace XFMG\Repository;

use XF\Mvc\Entity\Repository;

class CategoryWatch extends Repository
{
	public function setWatchState(\XFMG\Entity\Category $category, \XF\Entity\User $user, $action, array $config = [])
	{
		if (!$category->category_id || !$user->user_id)
		{
			throw new \InvalidArgumentException("Invalid category or user");
		}

		$watch = $this->em->find('XFMG:CategoryWatch', [
			'category_id' => $category->category_id,
			'user_id' => $user->user_id
		]);

		switch ($action)
		{
			case 'delete':
				if ($watch)
				{
					$watch->delete();
				}
				break;

			case 'watch':
				if (!$watch)
				{
					$watch = $this->em->create('XFMG:CategoryWatch');
					$watch->category_id = $category->category_id;
					$watch->user_id = $user->user_id;
				}
				unset($config['category_id'], $config['user_id']);

				$watch->bulkSet($config);
				$watch->save();
				break;

			case 'update':
				if ($watch)
				{
					unset($config['category_id'], $config['user_id']);

					$watch->bulkSet($config);
					$watch->save();
				}
				break;

			default:
				throw new \InvalidArgumentException("Unknown action '$action' (expected: delete/watch/update)");
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
				unset($updates['category_id'], $updates['user_id']);
				return $db->update('xf_mg_category_watch', $updates, 'user_id = ?', $user->user_id);

			case 'delete':
				return $db->delete('xf_mg_category_watch', 'user_id = ?', $user->user_id);

			default:
				throw new \InvalidArgumentException("Unknown action '$action'");
		}
	}
}