<?php

namespace XFMG\Repository;

use XF\Mvc\Entity\Repository;

class MediaWatch extends Repository
{
	public function autoWatchMediaItem(\XFMG\Entity\MediaItem $mediaItem, \XF\Entity\User $user, $onCreation = false)
	{
		$userField = $onCreation ? 'creation_watch_state' : 'interaction_watch_state';

		if (!$mediaItem->media_id || !$user->user_id || !$user->Option->getValue($userField))
		{
			return null;
		}

		$watch = $this->em->find('XFMG:MediaWatch', [
			'media_id' => $mediaItem->media_id,
			'user_id' => $user->user_id
		]);
		if ($watch)
		{
			return null;
		}

		/** @var \XFMG\Entity\MediaWatch $watch */
		$watch = $this->em->create('XFMG:MediaWatch');
		$watch->media_id = $mediaItem->media_id;
		$watch->user_id = $user->user_id;
		$watch->notify_on = 'comment';
		$watch->send_alert = true;
		$watch->send_email = ($user->Option->getValue($userField) == 'watch_email');
		$watch->save();

		return $watch;
	}

	public function setWatchState(\XFMG\Entity\MediaItem $mediaItem, \XF\Entity\User $user, $action, array $config = [])
	{
		if (!$mediaItem->media_id || !$user->user_id)
		{
			throw new \InvalidArgumentException("Invalid media item or user");
		}

		$watch = $this->em->find('XFMG:MediaWatch', [
			'media_id' => $mediaItem->media_id,
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
					$watch = $this->em->create('XFMG:MediaWatch');
					$watch->media_id = $mediaItem->media_id;
					$watch->user_id = $user->user_id;
				}
				unset($config['media_id'], $config['user_id']);

				$watch->bulkSet($config);
				$watch->save();
				break;

			case 'update':
				if ($watch)
				{
					unset($config['media_id'], $config['user_id']);

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
				unset($updates['media_id'], $updates['user_id']);
				return $db->update('xf_mg_media_watch', $updates, 'user_id = ?', $user->user_id);

			case 'delete':
				return $db->delete('xf_mg_media_watch', 'user_id = ?', $user->user_id);

			default:
				throw new \InvalidArgumentException("Unknown action '$action'");
		}
	}
}