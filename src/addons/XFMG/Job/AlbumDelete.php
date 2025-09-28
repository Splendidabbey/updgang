<?php

namespace XFMG\Job;

use XF\Job\AbstractJob;

class AlbumDelete extends AbstractJob
{
	protected $defaultData = [
		'album_id' => null,
		'count' => 0,
		'total' => null
	];

	public function run($maxRunTime)
	{
		$s = microtime(true);

		if (!$this->data['album_id'])
		{
			throw new \InvalidArgumentException(\XF::phrase('xfmg_cannot_delete_contents_without_album_id'));
		}

		$mediaItemFinder = $this->app->finder('XFMG:MediaItem')
			->where([
				'album_id' => $this->data['album_id']
			]);
		$mediaTotal = $mediaItemFinder->total();

		if ($this->data['total'] === null)
		{
			$this->data['total'] = $mediaTotal;
			if (!$this->data['total'])
			{
				return $this->complete();
			}
		}

		if (!$mediaTotal)
		{
			return $this->complete();
		}

		$mediaIds = $mediaItemFinder->pluckFrom('media_id')->fetch(1000)->toArray();
		if (!$mediaIds)
		{
			return $this->complete();
		}

		$continue = count($mediaIds) < 1000 ? false : true;

		foreach ($mediaIds AS $mediaId)
		{
			$this->data['count']++;

			$mediaItem = $this->app->find('XFMG:MediaItem', $mediaId);
			if (!$mediaItem)
			{
				continue;
			}
			$mediaItem->delete(false);

			if ($maxRunTime && microtime(true) - $s > $maxRunTime)
			{
				$continue = true;
				break;
			}
		}

		if ($continue)
		{
			return $this->resume();
		}
		else
		{
			return $this->complete();
		}
	}

	public function getStatusMessage()
	{
		$actionPhrase = \XF::phrase('deleting');
		$typePhrase = \XF::phrase('xfmg_album_contents');
		return sprintf('%s... %s (%s/%s)', $actionPhrase, $typePhrase,
			\XF::language()->numberFormat($this->data['count']), \XF::language()->numberFormat($this->data['total'])
		);
	}

	public function canCancel()
	{
		return true;
	}

	public function canTriggerByChoice()
	{
		return true;
	}
}