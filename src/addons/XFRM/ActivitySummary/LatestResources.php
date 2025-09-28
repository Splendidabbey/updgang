<?php

namespace XFRM\ActivitySummary;

use XF\ActivitySummary\AbstractSection;
use XF\ActivitySummary\Instance;
use XF\Mvc\Entity\Finder;

class LatestResources extends AbstractSection
{
	protected $defaultOptions = [
		'limit' => 5,
		'resource_category_ids' => [0],
		'condition' => 'last_update',
		'min_reaction_score' => null,
		'order' => 'last_update',
		'direction' => 'DESC'
	];

	public function getDefaultTitle(\XF\Entity\ActivitySummaryDefinition $definition)
	{
		return \XF::phrase('xfrm_latest_resources');
	}

	protected function getDefaultTemplateParams($context)
	{
		$params = parent::getDefaultTemplateParams($context);
		if ($context == 'options')
		{
			$categoryRepo = $this->app->repository('XFRM:Category');
			$params['categoryTree'] = $categoryRepo->createCategoryTree($categoryRepo->findCategoryList()->fetch());

			$params['sortOrders'] = $this->getDefaultOrderOptions();
		}
		return $params;
	}

	protected function getBaseFinderForFetch(): Finder
	{
		return $this->finder('XFRM:ResourceItem')
			->with(['Category', 'Description', 'User', 'User.PermissionCombination'])
			->setDefaultOrder($this->options['order'], $this->options['direction']);
	}

	protected function findDataForFetch(Finder $finder): Finder
	{
		$options = $this->options;

		$limit = $options['limit'];
		$categoryIds = $options['resource_category_ids'];
		$condition = $options['condition'];
		$minReactionScore = $options['min_reaction_score'];

		$finder
			->where('resource_state', 'visible')
			->limit(max($limit * 5, 25));

		if ($categoryIds && !in_array(0, $categoryIds))
		{
			$finder->where('resource_category_id', $categoryIds);
		}

		$finder->where($condition, '>', $this->getActivityCutOff());

		if ($minReactionScore !== null)
		{
			$finder->where('Description.reaction_score', '>=', $minReactionScore);
		}

		return $finder;
	}

	protected function renderInternal(Instance $instance): string
	{
		$user = $instance->getUser();
		if (!method_exists($user, 'cacheResourceCategoryPermissions'))
		{
			return '';
		}

		/** @var \XF\Mvc\Entity\ArrayCollection|\XFRM\Entity\ResourceItem[] $resources */
		$resources = $this->fetchData();

		$categoryIds = $resources->pluckNamed('resource_category_id');

		$user->cacheResourceCategoryPermissions(array_unique($categoryIds));

		foreach ($resources AS $resourceId => $resource)
		{
			if (!$resource->canView() || $resource->isIgnored())
			{
				unset($resources[$resourceId]);
				continue;
			}

			if ($instance->hasSeen('resource', $resourceId))
			{
				unset($resources[$resourceId]);
				continue;
			}
		}

		if (!$resources->count())
		{
			return '';
		}

		$resources = $resources->slice(0, $this->options['limit']);

		foreach ($resources AS $resource)
		{
			$instance->addSeen('resource', $resource->resource_id);
		}

		$viewParams = [
			'resources' => $resources
		];
		return $this->renderSectionTemplate($instance, 'xfrm_activity_summary_latest_resources', $viewParams);
	}

	protected function getDefaultOrderOptions()
	{
		return [
			'last_update' => \XF::phrase('xfrm_last_update'),
			'resource_date' => \XF::phrase('xfrm_first_release'),
			'rating_weighted' => \XF::phrase('rating'),
			'download_count' => \XF::phrase('xfrm_downloads')
		];
	}

	public function verifyOptions(\XF\Http\Request $request, array &$options, &$error = null)
	{
		$options = $request->filter([
			'limit' => 'uint',
			'resource_category_ids' => 'array-uint',
			'condition' => 'str',
			'min_reaction_score' => '?int',
			'order' => 'str',
			'direction' => 'str'
		]);

		if (in_array(0, $options['resource_category_ids']))
		{
			$options['resource_category_ids'] = [0];
		}

		if ($options['limit'] < 1)
		{
			$options['limit'] = 1;
		}

		if (!in_array($options['condition'], ['resource_date', 'last_update']))
		{
			$options['condition'] = 'last_update';
		}

		$orders = $this->getDefaultOrderOptions();
		if (!isset($orders[$options['order']]))
		{
			$options['order'] = 'last_update';
		}

		$options['direction'] = strtoupper($options['direction']);
		if (!in_array($options['direction'], ['ASC', 'DESC']))
		{
			$options['direction'] = 'DESC';
		}

		return true;
	}
}
