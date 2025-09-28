<?php

namespace XFMG\Pub\Controller;

use XF\Mvc\ParameterBag;

abstract class AbstractRating extends AbstractController
{
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XFMG\Entity\MediaItem | \XFMG\Entity\Album
	 */
	abstract protected function assertViewableAndRateableContent(ParameterBag $params, array $extraWith = []);

	/**
	 * @param ParameterBag $params
	 *
	 * @return \XFMG\Entity\MediaItem | \XFMG\Entity\Album
	 */
	abstract protected function assertViewableContent(ParameterBag $params);

	abstract protected function getLinkPrefix();

	/**
	 * @param \XF\Mvc\Entity\Entity $content
	 *
	 * @return \XFMG\Service\Rating\Rater
	 */
	protected function setupRatingCreate(\XF\Mvc\Entity\Entity $content)
	{
		/** @var \XFMG\Entity\MediaItem | \XFMG\Entity\Album $content */
		/** @var \XFMG\Service\Rating\Rater $rater */
		$rater = $this->service('XFMG:Rating\Rater', $content);

		$rater->setRating($this->filter('rating', 'uint'));

		if ($content->canAddComment())
		{
			$message = $this->plugin('XF:Editor')->fromInput('message');
			if (!$message && $this->options()->xfmgRequireComment)
			{
				throw $this->exception($this->error(\XF::phrase('xfmg_you_required_to_leave_comment_when_rating')));
			}
			if ($message)
			{
				$rater->setComment($message);
			}
		}

		return $rater;
	}

	protected function finalizeRatingCreate(\XFMG\Service\Rating\Rater $rater)
	{
		$rater->sendNotifications();
	}

	public function actionRate(ParameterBag $params)
	{
		$content = $this->assertViewableAndRateableContent($params, [
			'Ratings|' . \XF::visitor()->user_id . '.Comment',
			'Ratings|' . \XF::visitor()->user_id . '.Album',
			'Ratings|' . \XF::visitor()->user_id . '.Media'
		]);

		if ($this->isPost())
		{
			$rater = $this->setupRatingCreate($content);
			if (!$rater->validate($errors))
			{
				return $this->error($errors);
			}
			$this->assertNotFlooding('post');
			$rating = $rater->save();
			$this->finalizeRatingCreate($rater);

			return $this->redirect(
				$this->buildLink('media' . ($rating->content_type == 'xfmg_album' ? '/albums' : ''), $content),
				\XF::phrase('xfmg_your_rating_has_been_added')
			);
		}
		else
		{
			$rating = $this->filter('rating', 'uint');

			$viewParams = [
				'content' => $content,
				'rating' => $rating,
				'linkPrefix' => $this->getLinkPrefix()
			];
			return $this->view('XFMG:Rating\Rate', 'xfmg_rate', $viewParams);
		}
	}

	public function actionRatings(ParameterBag $params)
	{
		$content = $this->assertViewableContent($params);

		$page = $this->filterPage();
		$perPage = 50;

		/** @var \XFMG\Repository\Rating $ratingRepo */
		$ratingRepo = $this->repository('XFMG:Rating');

		$ratings = $ratingRepo->findContentRatings($content->structure()->contentType, $content->getEntityId())
			->with('User')
			->limitByPage($page, $perPage, 1)
			->fetch();

		$count = $ratings->count();

		if (!$count)
		{
			return $this->message(\XF::phrase('xfmg_no_one_has_rated_this_content_yet'));
		}

		$hasNext = $count > $perPage;
		$ratings = $ratings->slice(0, $perPage);

		$viewParams = [
			'content' => $content,

			'ratings' => $ratings,
			'hasNext' => $hasNext,
			'page' => $page,

			'linkPrefix' => $this->getLinkPrefix()
		];
		return $this->view('XF:Rating\Ratings', 'xfmg_rating_list', $viewParams);
	}
}