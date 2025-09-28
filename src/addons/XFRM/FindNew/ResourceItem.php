<?php

namespace XFRM\FindNew;

use XF\Entity\FindNew;
use XF\FindNew\AbstractHandler;

class ResourceItem extends AbstractHandler
{
	public function getRoute()
	{
		return 'whats-new/resources';
	}

	public function getPageReply(\XF\Mvc\Controller $controller, FindNew $findNew, array $results, $page, $perPage)
	{
		$canInlineMod = false;

		/** @var \XFRM\Entity\ResourceItem $resource */
		foreach ($results AS $resource)
		{
			if ($resource->canUseInlineModeration())
			{
				$canInlineMod = true;
				break;
			}
		}

		$viewParams = [
			'findNew' => $findNew,

			'page' => $page,
			'perPage' => $perPage,

			'resources' => $results,
			'canInlineMod' => $canInlineMod
		];
		return $controller->view('XFRM:WhatsNew\Resources', 'xfrm_whats_new_resources', $viewParams);
	}

	public function getFiltersFromInput(\XF\Http\Request $request)
	{
		$filters = [];

		$visitor = \XF::visitor();

		$watched = $request->filter('watched', 'bool');
		if ($watched && $visitor->user_id)
		{
			$filters['watched'] = true;
		}

		return $filters;
	}

	public function getDefaultFilters()
	{
		return [];
	}

	public function getResultIds(array $filters, $maxResults)
	{
		$visitor = \XF::visitor();

		/** @var \XFRM\Finder\ResourceItem $finder */
		$finder = \XF::finder('XFRM:ResourceItem')
			->with('Category', true)
			->with('Category.Permissions|' . $visitor->permission_combination_id)
			->where('resource_state', '<>', 'deleted')
			->where('last_update', '>', \XF::$time - (86400 * \XF::options()->readMarkingDataLifetime))
			->order('last_update', 'DESC');

		$this->applyFilters($finder, $filters);

		$resources = $finder->fetch($maxResults);
		$resources = $this->filterResults($resources);

		// TODO: consider overfetching or some other permission limits within the query

		return $resources->keys();
	}

	public function getPageResultsEntities(array $ids)
	{
		$visitor = \XF::visitor();

		$ids = array_map('intval', $ids);

		/** @var \XFRM\Finder\ResourceItem $finder */
		$finder = \XF::finder('XFRM:ResourceItem')
			->where('resource_id', $ids)
			->with('fullCategory')
			->with('Category.Permissions|' . $visitor->permission_combination_id);

		return $finder->fetch();
	}

	protected function filterResults(\XF\Mvc\Entity\AbstractCollection $results)
	{
		$visitor = \XF::visitor();

		return $results->filter(function(\XFRM\Entity\ResourceItem $resource) use($visitor)
		{
			return ($resource->canView() && !$visitor->isIgnoring($resource->user_id));
		});
	}

	protected function applyFilters(\XFRM\Finder\ResourceItem $finder, array $filters)
	{
		$visitor = \XF::visitor();
		if (!empty($filters['watched']))
		{
			$finder->watchedOnly($visitor->user_id);
		}
	}

	public function getResultsPerPage()
	{
		return \XF::options()->xfrmResourcesPerPage;
	}

	public function isAvailable()
	{
		/** @var \XFRM\XF\Entity\User $visitor */
		$visitor = \XF::visitor();
		return $visitor->canViewResources();
	}
}