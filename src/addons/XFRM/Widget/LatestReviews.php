<?php

namespace XFRM\Widget;

use XF\Widget\AbstractWidget;

class LatestReviews extends AbstractWidget
{
	protected $defaultOptions = [
		'limit' => 5
	];

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

		/** @var \XFRM\Finder\ResourceRating $finder */
		$finder = $this->repository('XFRM:ResourceRating')->findLatestReviews();
		$reviews = $finder->fetch(max($limit * 2, 10));

		/** @var \XFRM\Entity\ResourceRating $review */
		foreach ($reviews AS $id => $review)
		{
			if (!$review->canView() || $review->isIgnored() || $review->Resource->isIgnored())
			{
				unset($reviews[$id]);
			}
		}

		$total = $reviews->count();
		$reviews = $reviews->slice(0, $limit, true);

		$link = $this->app->router('public')->buildLink('resources/latest-reviews');

		$viewParams = [
			'title' => $this->getTitle(),
			'link' => $link,
			'reviews' => $reviews,
			'hasMore' => $total > $reviews->count()
		];
		return $this->renderer('xfrm_widget_latest_reviews', $viewParams);
	}

	public function verifyOptions(\XF\Http\Request $request, array &$options, &$error = null)
	{
		$options = $request->filter([
			'limit' => 'uint'
		]);
		if ($options['limit'] < 1)
		{
			$options['limit'] = 1;
		}

		return true;
	}
}