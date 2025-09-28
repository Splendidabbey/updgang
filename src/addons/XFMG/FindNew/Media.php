<?php

namespace XFMG\FindNew;

use XF\Entity\FindNew;
use XF\FindNew\AbstractHandler;

class Media extends AbstractHandler
{
	public function getRoute()
	{
		return 'whats-new/media';
	}

	public function getPageReply(
		\XF\Mvc\Controller $controller, FindNew $findNew, array $results, $page, $perPage
	)
	{
		$canInlineMod = false;

		/** @var \XFMG\Entity\MediaItem $mediaItem */
		foreach ($results AS $mediaItem)
		{
			if ($mediaItem->canUseInlineModeration())
			{
				$canInlineMod = true;
				break;
			}
		}

		$viewParams = [
			'findNew' => $findNew,

			'page' => $page,
			'perPage' => $perPage,

			'prevPage' => ($page > 1) ? \XF::app()->router()->buildLink('whats-new/media', $findNew, ['page' => $page - 1] + $findNew->filters) : null,
			'nextPage' => ($page < ceil($findNew->result_count / $perPage)) ? \XF::app()->router()->buildLink('whats-new/media', $findNew, ['page' => $page + 1] + $findNew->filters) : null,

			'media' => $results,
			'canInlineMod' => $canInlineMod
		];
		return $controller->view('XFMG:WhatsNew\Media', 'xfmg_whats_new_media', $viewParams);
	}

	public function getFiltersFromInput(\XF\Http\Request $request)
	{
		$filters = [];

		$visitor = \XF::visitor();

		$unviewed = $request->filter('unviewed', 'bool');
		if ($unviewed && $visitor->user_id)
		{
			$filters['unviewed'] = true;
		}

		$watched = $request->filter('watched', 'bool');
		if ($watched && $visitor->user_id)
		{
			$filters['watched'] = true;
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
			return ['unviewed' => true];
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
			->visibleAlbumsOnly()
			->where('media_state', '<>', 'deleted')
			->orderByDate();

		$this->applyFilters($mediaFinder, $filters);

		$media = $mediaFinder->fetch($maxResults);
		$media = $this->filterResults($media);

		return $media->keys();
	}

	public function getPageResultsEntities(array $ids)
	{
		$visitor = \XF::visitor();

		$ids = array_map('intval', $ids);

		$mediaFinder = \XF::finder('XFMG:MediaItem')
			->with('Category.Permissions|' . $visitor->permission_combination_id)
			->where('media_id', $ids)
			->orderByDate();

		return $mediaFinder->fetch();
	}

	protected function filterResults(\XF\Mvc\Entity\AbstractCollection $results)
	{
		$visitor = \XF::visitor();

		return $results->filter(function(\XFMG\Entity\MediaItem $mediaItem) use($visitor)
		{
			return ($mediaItem->canView() && !$visitor->isIgnoring($mediaItem->user_id));
		});
	}

	protected function applyFilters(\XFMG\Finder\MediaItem $mediaFinder, array $filters)
	{
		$visitor = \XF::visitor();

		if (!empty($filters['unviewed']))
		{
			$mediaFinder->unviewedOnly($visitor->user_id);
		}
		else if (sizeof($filters) != 1)
		{
			$mediaFinder->where('media_date', '>', \XF::$time - (86400 * \XF::options()->readMarkingDataLifetime));
		}

		if (!empty($filters['watched']))
		{
			$mediaFinder->watchedOnly($visitor->user_id);
		}

		if (!empty($filters['own']))
		{
			$mediaFinder->where('user_id', $visitor->user_id);
		}
	}

	public function getResultsPerPage()
	{
		return \XF::options()->xfmgMediaPerPage;
	}

	public function isAvailable()
	{
		/** @var \XFMG\XF\Entity\User $visitor */
		$visitor = \XF::visitor();
		return $visitor->canViewMedia();
	}
}