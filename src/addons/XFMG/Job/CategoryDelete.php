<?php

namespace XFMG\Job;

use XF\Job\AbstractJob;

class CategoryDelete extends AbstractJob
{
	protected $defaultData = [
		'category_id' => null,
		'albumsComplete' => false,
		'mediaComplete' => false,
		'count' => 0,
		'total' => null
	];

	public function run($maxRunTime)
	{
		$s = microtime(true);

		if (!$this->data['category_id'])
		{
			throw new \InvalidArgumentException(\XF::phrase('xfmg_cannot_delete_contents_without_category_id'));
		}

		if ($this->data['albumsComplete'] && $this->data['mediaComplete'])
		{
			return $this->complete();
		}

		$albumFinder = $this->app->finder('XFMG:Album')
			->where('category_id', $this->data['category_id']);
		$albumTotal = $albumFinder->total();

		$mediaItemFinder = $this->app->finder('XFMG:MediaItem')
			->where([
				'category_id' => $this->data['category_id'],
				'album_id' => 0 // album delete will trigger a delete of items in albums
			]);
		$mediaTotal = $mediaItemFinder->total();

		if ($this->data['total'] === null)
		{
			$this->data['total'] = $albumTotal + $mediaTotal;
			if (!$this->data['total'])
			{
				return $this->complete();
			}
		}

		$continue = true; // always continue until media is complete

		if (!$this->data['albumsComplete'])
		{
			if (!$albumTotal)
			{
				$this->data['albumsComplete'] = true;
				if ($this->data['mediaComplete'])
				{
					return $this->complete();
				}
			}

			$albumIds = $albumFinder->pluckFrom('album_id')->fetch(1000)->toArray();
			foreach ($albumIds AS $albumId)
			{
				$this->data['count']++;

				$album = $this->app->find('XFMG:Album', $albumId);
				if (!$album)
				{
					continue;
				}
				$album->delete(false);

				if ($maxRunTime && microtime(true) - $s > $maxRunTime)
				{
					break;
				}
			}
		}

		if ($this->data['albumsComplete'] && !$this->data['mediaComplete'])
		{
			if (!$mediaTotal)
			{
				$this->data['mediaComplete'] = true;
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
		$typePhrase = \XF::phrase('xfmg_category_contents');
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