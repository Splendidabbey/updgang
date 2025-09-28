<?php

namespace XFRM\Api\Controller;

use XF\Api\Controller\AbstractController;
use XF\Mvc\Entity\Entity;
use XF\Mvc\ParameterBag;

class ResourceReview extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertApiScopeByRequestMethod('resource_rating');
	}

	public function actionGet(ParameterBag $params)
	{
		$review = $this->assertViewableReview($params->resource_rating_id);

		$result = [
			'review' => $review->toApiResult(Entity::VERBOSITY_VERBOSE, ['with_resource' => true])
		];
		return $this->apiResult($result);
	}

	public function actionDelete(ParameterBag $params)
	{
		$review = $this->assertViewableReview($params->resource_rating_id);

		if (\XF::isApiCheckingPermissions() && !$review->canDelete('soft', $error))
		{
			return $this->noPermission($error);
		}

		$type = 'soft';
		$reason = $this->filter('reason', 'str');

		if ($this->filter('hard_delete', 'bool'))
		{
			$this->assertApiScope('resource_rating:delete_hard');

			if (\XF::isApiCheckingPermissions() && !$review->canDelete('hard', $error))
			{
				return $this->noPermission($error);
			}

			$type = 'hard';
		}

		/** @var \XFRM\Service\ResourceRating\Delete $deleter */
		$deleter = $this->service('XFRM:ResourceRating\Delete', $review);

		if ($this->filter('author_alert', 'bool'))
		{
			$deleter->setSendAlert(true, $this->filter('author_alert_reason', 'str'));
		}

		$deleter->delete($type, $reason);

		return $this->apiSuccess();
	}

	public function actionPostAuthorReply(ParameterBag $params)
	{
		$review = $this->assertViewableReview($params->resource_rating_id);

		$this->assertRequiredApiInput('message');

		if (\XF::isApiCheckingPermissions() && !$review->canReply($error))
		{
			return $this->noPermission($error);
		}

		/** @var \XFRM\Service\ResourceRating\AuthorReply $authorReplier */
		$authorReplier = $this->service('XFRM:ResourceRating\AuthorReply', $review);

		$message = $this->filter('message', 'str');
		if (!$authorReplier->reply($message, $error))
		{
			return $this->error($error);
		}

		return $this->apiSuccess([
			'review' => $review->toApiResult(Entity::VERBOSITY_VERBOSE)
		]);
	}

	public function actionDeleteAuthorReply(ParameterBag $params)
	{
		$review = $this->assertViewableReview($params->resource_rating_id);

		if (\XF::isApiCheckingPermissions() && !$review->canDeleteAuthorResponse($error))
		{
			return $this->noPermission($error);
		}

		/** @var \XFRM\Service\ResourceRating\AuthorReplyDelete $deleter */
		$deleter = $this->service('XFRM:ResourceRating\AuthorReplyDelete', $review);
		$deleter->delete();

		return $this->apiSuccess([
			'review' => $review->toApiResult(Entity::VERBOSITY_VERBOSE)
		]);
	}

	/**
	 * @param int $id
	 * @param string|array $with
	 *
	 * @return \XFRM\Entity\ResourceRating
	 *
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function assertViewableReview($id, $with = 'api')
	{
		/** @var \XFRM\Entity\ResourceRating $review */
		$review = $this->assertViewableApiRecord('XFRM:ResourceRating', $id, $with);

		if (!$review->is_review)
		{
			throw $this->exception($this->notFound());
		}

		return $review;
	}
}