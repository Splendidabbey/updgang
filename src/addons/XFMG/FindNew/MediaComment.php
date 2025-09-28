<?php

namespace XFMG\FindNew;

use XF\Entity\FindNew;
use XF\FindNew\AbstractHandler;
use XF\Mvc\Entity\ArrayCollection;

class MediaComment extends AbstractHandler
{
	public function getRoute()
	{
		return 'whats-new/media-comments';
	}

	public function getPageReply(
		\XF\Mvc\Controller $controller, FindNew $findNew, array $results, $page, $perPage
	)
	{
		$viewParams = [
			'findNew' => $findNew,

			'page' => $page,
			'perPage' => $perPage,

			'items' => $results
		];
		return $controller->view('XFMG:WhatsNew\MediaComments', 'xfmg_whats_new_media_comments', $viewParams);
	}

	public function getFiltersFromInput(\XF\Http\Request $request)
	{
		$filters = [];

		$visitor = \XF::visitor();

		$unread = $request->filter('unread', 'bool');
		if ($unread && $visitor->user_id)
		{
			$filters['unread'] = true;
		}

		$own = $request->filter('own', 'bool');
		if ($own && $visitor->user_id)
		{
			$filters['own'] = true;
		}

		return $filters;
	}

	public function getDefaultFilters()
	{
		$visitor = \XF::visitor();

		if ($visitor->user_id)
		{
			return ['unread' => true];
		}
		else
		{
			return [];
		}
	}

	public function getResultIds(array $filters, $maxResults)
	{
		$visitor = \XF::visitor();

		/** @var \XFMG\Finder\MediaItem $mediaFinder */
		$mediaFinder = \XF::finder('XFMG:MediaItem')
			->with(['Category', 'Album'])
			->with('Category.Permissions|' . $visitor->permission_combination_id)
			->where('last_comment_date', '>', 0)
			->where('media_state', '<>', 'deleted')
			->orderByDate('last_comment_date');

		$this->applyFilters($mediaFinder, $filters);
		$mediaItems = $mediaFinder->fetch($maxResults);
		$mediaItems = $this->filterResults($mediaItems)->toArray();

		/** @var \XFMG\Finder\Album $albumsFinder */
		$albumsFinder = \XF::finder('XFMG:Album')
			->with('Category')
			->with('Category.Permissions|' . $visitor->permission_combination_id)
			->where('last_comment_date', '>', 0)
			->where('album_state', '<>', 'deleted')
			->orderByDate('last_comment_date');

		$this->applyFilters($albumsFinder, $filters);
		$albums = $albumsFinder->fetch($maxResults);
		$albums = $this->filterResults($albums)->toArray();

		$merged = $this->mergeAndSortMediaAndAlbums($mediaItems, $albums);

		return array_keys($merged);
	}

	protected function mergeAndSortMediaAndAlbums(array $mediaItems, array $albums)
	{
		$merged = [];
		foreach ($mediaItems AS $mediaId => $mediaItem)
		{
			$merged['media-' . $mediaId] = $mediaItem;
		}
		foreach ($albums AS $albumId => $album)
		{
			$merged['album-' . $albumId] = $album;
		}
		uasort($merged, function($itemA, $itemB)
		{
			return ($itemB['last_comment_date'] - $itemA['last_comment_date']);
		});
		return $merged;
	}

	public function getPageResultsEntities(array $ids)
	{
		$mediaIds = [];
		$albumIds = [];

		foreach ($ids AS $id)
		{
			if (strpos($id, 'media-') !== false)
			{
				$mediaIds[] = substr($id, 6);
			}
			else
			{
				$albumIds[] = substr($id, 6);
			}
		}

		$mediaIds = array_map('intval', $mediaIds);
		$albumIds = array_map('intval', $albumIds);

		$visitor = \XF::visitor();

		$mediaFinder = \XF::finder('XFMG:MediaItem')
			->with('Category.Permissions|' . $visitor->permission_combination_id)
			->with('CommentRead|' . $visitor->user_id)
			->with('LastComment', true)
			->with('LastCommenter')
			->where('media_id', $mediaIds)
			->orderByDate('last_comment_date');

		$albumFinder = \XF::finder('XFMG:Album')
			->with('Category.Permissions|' . $visitor->permission_combination_id)
			->with('CommentRead|' . $visitor->user_id)
			->with('LastComment', true)
			->with('LastCommenter')
			->where('album_id', $albumIds)
			->orderByDate('last_comment_date');

		$mediaItems = $mediaFinder->fetch()->toArray();
		$albums = $albumFinder->fetch()->toArray();

		$merged = $this->mergeAndSortMediaAndAlbums($mediaItems, $albums);
		$merged = new ArrayCollection($merged);

		return $merged;
	}

	protected function filterResults(\XF\Mvc\Entity\AbstractCollection $results)
	{
		$visitor = \XF::visitor();

		return $results->filter(function(\XF\Mvc\Entity\Entity $entity) use($visitor)
		{
			/** @var \XFMG\Entity\Album|\XFMG\Entity\MediaItem $entity */
			return ($entity->canView() && !$visitor->isIgnoring($entity->user_id));
		});
	}

	/**
	 * @param \XFMG\Finder\MediaItem|\XFMG\Finder\Album $finder
	 *
	 * @param array $filters
	 */
	protected function applyFilters(\XF\Mvc\Entity\Finder $finder, array $filters)
	{
		$visitor = \XF::visitor();

		if (!empty($filters['unread']))
		{
			$finder->withUnreadCommentsOnly($visitor->user_id);
		}
		else if (sizeof($filters) != 1)
		{
			$finder->where('last_comment_date', '>', \XF::$time - (86400 * \XF::options()->readMarkingDataLifetime));
		}
	}

	public function getResultsPerPage()
	{
		// We display as either media or albums rather than comments so pick one.
		return max(\XF::options()->xfmgMediaPerPage, \XF::options()->xfmgAlbumsPerPage);
	}

	public function isAvailable()
	{
		/** @var \XFMG\XF\Entity\User $visitor */
		$visitor = \XF::visitor();
		return $visitor->canViewMedia();
	}
}