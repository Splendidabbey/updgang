<?php

namespace XFRM\Widget;

use XF\Widget\AbstractWidget;
use XFRM\Entity\Category;
use XFRM\Entity\ResourceItem;

class FeaturedResources extends AbstractWidget
{
	/**
	 * @var array
	 */
	protected $defaultOptions = [
		'limit' => 5,
		'style' => 'simple',
		'resource_category_ids' => []
	];

	/**
	 * @param string $context
	 *
	 * @return array
	 */
	protected function getDefaultTemplateParams($context)
	{
		$params = parent::getDefaultTemplateParams($context);

		if ($context == 'options')
		{
			$categoryRepo = $this->app->repository('XFRM:Category');
			$params['categoryTree'] = $categoryRepo->createCategoryTree(
				$categoryRepo->findCategoryList()->fetch()
			);
		}

		return $params;
	}

	/**
	 * @return \XF\Widget\WidgetRenderer|string
	 */
	public function render()
	{
		/** @var \XFRM\XF\Entity\User $visitor */
		$visitor = \XF::visitor();
		if (
			!method_exists($visitor, 'canViewResources') ||
			!$visitor->canViewResources()
		)
		{
			return '';
		}

		$options = $this->options;
		$limit = $options['limit'];
		$style = $options['style'];
		$categoryIds = $options['resource_category_ids'];

		$hasCategoryIds = ($categoryIds && !in_array(0, $categoryIds));
		$hasCategoryContext = (
			isset($this->contextParams['category'])
			&& $this->contextParams['category'] instanceof Category
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

		$resourceRepo = $this->repository('XFRM:ResourceItem');
		$finder = $resourceRepo->findFeaturedResources($sourceCategoryIds);

		if ($style == 'full')
		{
			$finder->with('fullCategory');
		}

		if (!$useContext)
		{
			// with the context, we already fetched the category and permissions
			$finder->with('Category.Permissions|' . $visitor->permission_combination_id);
		}

		$resources = $finder->fetch(max($limit * 2, 10));
		$resources = $resources->filter(
			function (ResourceItem $resource) use ($visitor)
			{
				return (
					$resource->canView() &&
					!$visitor->isIgnoring($resource->user_id)
				);
			}
		);

		$total = $resources->count();
		$resources = $resources->slice(0, $limit, true);
		$hasMore = $total > $resources->count();

		$title = $this->getTitle();
		$router = $this->app->router('public');
		if ($category)
		{
			$link = $router->buildLink(
				'resources/categories/featured',
				$category
			);
		}
		else
		{
			$link = $router->buildLink('resources/featured');
		}

		$viewParams = [
			'title' => $title,
			'link' => $link,
			'resources' => $resources,

			'style' => $style,
			'hasMore' => $hasMore
		];
		return $this->renderer('xfrm_widget_featured_resources', $viewParams);
	}

	/**
	 * @param \XF\Phrase|null $error
	 *
	 * @return bool
	 */
	public function verifyOptions(
		\XF\Http\Request $request,
		array &$options,
		&$error = null
	)
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
