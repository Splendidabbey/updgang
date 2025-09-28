<?php

namespace XFRM\Widget;

use XF\Widget\AbstractWidget;

class TopResources extends AbstractWidget
{
	protected $defaultOptions = [
		'limit' => 5,
		'style' => 'simple',
		'resource_category_ids' => []
	];

	protected function getDefaultTemplateParams($context)
	{
		$params = parent::getDefaultTemplateParams($context);
		if ($context == 'options')
		{
			$categoryRepo = $this->app->repository('XFRM:Category');
			$params['categoryTree'] = $categoryRepo->createCategoryTree($categoryRepo->findCategoryList()->fetch());
		}
		return $params;
	}

	public function render()
	{
		/** @var \XFRM\XF\Entity\User $visitor */
		$visitor = \XF::visitor();
		if (!method_exists($visitor, 'canViewResources') || !$visitor->canViewResources())
		{
			return '';
		}

		$options = $this->options;
		$limit = $options['limit'];
		$categoryIds = $options['resource_category_ids'];

		$hasCategoryIds = ($categoryIds && !in_array(0, $categoryIds));
		$hasCategoryContext = (
			isset($this->contextParams['category'])
			&& $this->contextParams['category'] instanceof \XFRM\Entity\Category
		);
		$useContext = false;
		$category = null;

		if (!$hasCategoryIds && $hasCategoryContext)
		{
			/** @var \XFRM\Entity\Category $category */
			$category = $this->contextParams['category'];
			$viewableDescendents = $category->getViewableDescendants();
			$sourceCategoryIds = array_keys($viewableDescendents);
			$sourceCategoryIds[] = $category->resource_category_id;

			$useContext = true;
		}
		else if ($hasCategoryIds)
		{
			$sourceCategoryIds = $categoryIds;
		}
		else
		{
			$sourceCategoryIds = null;
		}

		/** @var \XFRM\Finder\ResourceItem $finder */
		$finder = $this->repository('XFRM:ResourceItem')->findTopResources($sourceCategoryIds);

		if (!$useContext)
		{
			// with the context, we already fetched the category and permissions
			$finder->with('Category.Permissions|' . $visitor->permission_combination_id);
		}

		if ($options['style'] == 'full')
		{
			$finder->with('fullCategory');
		}

		$resources = $finder->fetch(max($limit * 2, 10));

		/** @var \XFRM\Entity\ResourceItem $resource */
		foreach ($resources AS $resourceId => $resource)
		{
			if (!$resource->canView() || $visitor->isIgnoring($resource->user_id))
			{
				unset($resources[$resourceId]);
			}
		}

		$total = $resources->count();
		$resources = $resources->slice(0, $limit, true);

		$viewParams = [
			'title' => $this->getTitle(),
			'category' => $category,
			'resources' => $resources,
			'style' => $options['style'],
			'hasMore' => $total > $resources->count()
		];
		return $this->renderer('xfrm_widget_top_resources', $viewParams);
	}

	public function verifyOptions(\XF\Http\Request $request, array &$options, &$error = null)
	{
		$options = $request->filter([
			'limit' => 'uint',
			'style' => 'str',
			'resource_category_ids' => 'array-uint'
		]);
		if (in_array(0, $options['resource_category_ids']))
		{
			$options['resource_category_ids'] = [0];
		}
		if ($options['limit'] < 1)
		{
			$options['limit'] = 1;
		}

		return true;
	}
}