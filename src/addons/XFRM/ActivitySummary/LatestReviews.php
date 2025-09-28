<?php

namespace XFRM\ActivitySummary;

use XF\ActivitySummary\AbstractSection;
use XF\ActivitySummary\Instance;
use XF\Mvc\Entity\Finder;

class LatestReviews extends AbstractSection
{
	protected $defaultOptions = [
		'limit' => 5,
		'resource_category_ids' => [0],
	];

	public function getDefaultTitle(\XF\Entity\ActivitySummaryDefinition $definition)
	{
		return \XF::phrase('xfrm_latest_reviews');
	}

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

	protected function getBaseFinderForFetch(): Finder
	{
		return $this->finder('XFRM:ResourceRating')
			->with('Resource', true)
			->with(['Resource.Category', 'User'])
			->setDefaultOrder('rating_date', 'desc');
	}

	protected function findDataForFetch(Finder $finder): Finder
	{
		$options = $this->options;

		$limit = $options['limit'];
		$categoryIds = $options['resource_category_ids'];

		$finder->where([
			'Resource.resource_state' => 'visible',
			'rating_state' => 'visible',
			'is_review' => 1
		])->limit(max($limit * 2, 10));

		if ($categoryIds && !in_array(0, $categoryIds))
		{
			$finder->where('Resource.resource_category_id', $categoryIds);
		}

		$finder->where('rating_date', '>', $this->getActivityCutOff());

		return $finder;
	}

	protected function renderInternal(Instance $instance): string
	{
		$user = $instance->getUser();
		if (!method_exists($user, 'cacheResourceCategoryPermissions'))
		{
			return '';
		}

		/** @var \XF\Mvc\Entity\ArrayCollection|\XFRM\Entity\ResourceRating[] $reviews */
		$reviews = $this->fetchData();

		$categoryIds = $reviews->pluck(
			function (\XFRM\Entity\ResourceRating $review)
			{
				return $review->Resource ? [$review->resource_rating_id, $review->Resource->resource_category_id] : null;
			},
			false
		);
		$user->cacheResourceCategoryPermissions(array_unique($categoryIds));

		foreach ($reviews AS $reviewId => $review)
		{
			if (!$review->canView() || $review->isIgnored())
			{
				unset($reviews[$reviewId]);
				continue;
			}

			if ($instance->hasSeen('resource_rating', $reviewId))
			{
				unset($reviews[$reviewId]);
				continue;
			}
		}

		if (!$reviews->count())
		{
			return '';
		}

		$reviews = $reviews->slice(0, $this->options['limit']);

		foreach ($reviews AS $review)
		{
			$instance->addSeen('resource_rating', $review->resource_rating_id);
		}

		$viewParams = [
			'reviews' => $reviews
		];
		return $this->renderSectionTemplate($instance, 'xfrm_activity_summary_latest_reviews', $viewParams);
	}

	public function verifyOptions(\XF\Http\Request $request, array &$options, &$error = null)
	{
		$options = $request->filter([
			'limit' => 'uint',
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