<?php

namespace XFRM\PreRegAction\ResourceItem;

use XF\Entity\PreRegAction;
use XF\Entity\User;
use XF\Mvc\Entity\Entity;
use XF\PreRegAction\AbstractHandler;

class Rate extends AbstractHandler
{
	public function getContainerContentType(): string
	{
		return 'resource';
	}

	public function getDefaultActionData(): array
	{
		return [
			'rating' => 0,
			'message' => '',
			'custom_fields' => [],
			'is_anonymous' => false
		];
	}

	protected function canCompleteAction(PreRegAction $action, Entity $containerContent, User $newUser): bool
	{
		/** @var \XFRM\Entity\ResourceItem $containerContent */
		if (!$containerContent->canRate())
		{
			return false;
		}

		/** @var \XFRM\Entity\ResourceRating|null $existingRating */
		$existingRating = $containerContent->CurrentVersion->Ratings[$newUser->user_id];
		if ($existingRating && !$existingRating->canUpdate())
		{
			return false;
		}

		return true;
	}

	protected function executeAction(PreRegAction $action, Entity $containerContent, User $newUser)
	{
		/** @var \XFRM\Entity\ResourceItem $containerContent */

		$rater = $this->setupResourceRate($action, $containerContent);
		$rater->checkForSpam();

		if (!$rater->validate())
		{
			return null;
		}

		return $rater->save();
	}

	protected function setupResourceRate(
		PreRegAction $action,
		\XFRM\Entity\ResourceItem $resource
	): \XFRM\Service\ResourceItem\Rate
	{
		/** @var \XFRM\Service\ResourceItem\Rate $rater */
		$rater = \XF::app()->service('XFRM:ResourceItem\Rate', $resource);

		$rater->setRating($action->action_data['rating'], $action->action_data['message']);
		$rater->setCustomFields($action->action_data['custom_fields']);

		if (\XF::options()->xfrmAllowAnonReview && $action->action_data['is_anonymous'])
		{
			$rater->setIsAnonymous();
		}

		return $rater;
	}

	protected function sendSuccessAlert(
		PreRegAction $action,
		Entity $containerContent,
		User $newUser,
		Entity $executeContent
	)
	{
		if (!($executeContent instanceof \XFRM\Entity\ResourceRating))
		{
			return;
		}

		/** @var \XFRM\Entity\ResourceRating $rating */
		$rating = $executeContent;

		/** @var \XF\Repository\UserAlert $alertRepo */
		$alertRepo = \XF::repository('XF:UserAlert');

		$alertRepo->alertFromUser(
			$newUser, null,
			'resource_rating', $rating->resource_rating_id,
			'pre_reg',
			['welcome' => $action->isForNewUser()]
		);
	}

	protected function getStructuredContentData(PreRegAction $preRegAction, Entity $containerContent): array
	{
		/** @var \XFRM\Entity\ResourceItem $containerContent */

		return [
			'title' => \XF::phrase('xfrm_review_for_x', ['title' => $containerContent->title]),
			'title_link' => $containerContent->getContentUrl(),
			'rating' => $preRegAction->action_data['rating'],
			'text' => $preRegAction->action_data['message']
		];
	}

	protected function getApprovalQueueTemplate(): string
	{
		return 'public:pre_reg_action_approval_queue_resource_rate';
	}
}