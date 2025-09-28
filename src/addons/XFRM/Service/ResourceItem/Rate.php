<?php

namespace XFRM\Service\ResourceItem;

use XFRM\Entity\ResourceItem;

class Rate extends \XF\Service\AbstractService
{
	use \XF\Service\ValidateAndSavableTrait;

	/**
	 * @var \XFRM\Entity\ResourceItem
	 */
	protected $resource;

	/**
	 * @var \XFRM\Entity\ResourceRating
	 */
	protected $rating;

	/**
	 * @var \XF\Entity\User
	 */
	protected $user;

	protected $reviewRequired = false;
	protected $reviewMinLength = 0;

	protected $sendAlert = true;

	protected $isPreRegAction = false;

	public function __construct(\XF\App $app, ResourceItem $resource)
	{
		parent::__construct($app);

		$this->user = \XF::visitor();
		$this->resource = $resource;
		$this->rating = $this->setupRating();

		$this->reviewRequired = $this->app->options()->xfrmReviewRequired;
		$this->reviewMinLength = $this->app->options()->xfrmMinimumReviewLength;
	}

	protected function setupRating()
	{
		$resource = $this->resource;

		$rating = $this->em()->create('XFRM:ResourceRating');
		$rating->resource_id = $resource->resource_id;
		$rating->resource_version_id = $resource->current_version_id;
		$rating->user_id = $this->user->user_id;
		$rating->version_string = $resource->CurrentVersion->version_string;

		return $rating;
	}

	public function getResource()
	{
		return $this->resource;
	}

	public function getRating()
	{
		return $this->rating;
	}

	public function setRating($rating, $message = '')
	{
		$this->rating->rating = $rating;
		$this->rating->message = $message;
	}

	public function setIsAnonymous($value = true)
	{
		$this->rating->is_anonymous = (bool)$value;
	}

	public function setIsPreRegAction(bool $isPreRegAction)
	{
		$this->isPreRegAction = $isPreRegAction;
	}

	public function setReviewRequirements($reviewRequired = null, $minLength = null)
	{
		if ($reviewRequired !== null)
		{
			$this->reviewRequired = (bool)$reviewRequired;
		}
		if ($minLength !== null)
		{
			$minLength = max(0, intval($minLength));
			$this->reviewMinLength = $minLength;
		}
	}

	public function setCustomFields(array $customFields)
	{
		$resource = $this->resource;
		$rating = $this->rating;

		/** @var \XF\CustomField\Set $fieldSet */
		$fieldSet = $rating->custom_fields;
		$fieldDefinition = $fieldSet->getDefinitionSet()
			->filterEditable($fieldSet, 'user')
			->filterOnly($resource->Category->review_field_cache);

		$customFieldsShown = array_keys($fieldDefinition->getFieldDefinitions());

		if ($customFieldsShown)
		{
			$fieldSet->bulkSet($customFields, $customFieldsShown, 'user');
		}
	}

	public function checkForSpam()
	{
		$rating = $this->rating;

		if (
			!$this->user->isSpamCheckRequired()
			|| !strlen($this->rating->message)
			|| $this->rating->getErrors()
		)
		{
			return;
		}

		$message = $rating->message;

		$checker = $this->app->spam()->contentChecker();
		$checker->check($this->user, $message, [
			'permalink' => $this->app->router('public')->buildLink('canonical:resources', $rating->Resource),
			'content_type' => 'resource_rating'
		]);

		$decision = $checker->getFinalDecision();
		switch ($decision)
		{
			case 'moderated':
			case 'denied':
				$checker->logSpamTrigger('resource_rating', null);
				$rating->error(\XF::phrase('your_content_cannot_be_submitted_try_later'));
				break;
		}
	}

	protected function _validate()
	{
		$rating = $this->rating;

		$rating->preSave();
		$errors = $rating->getErrors();

		if ($this->isPreRegAction)
		{
			// ignore this as we'll resolve it later
			unset($errors['user_id']);
		}

		if ($this->reviewRequired && !$rating->is_review)
		{
			$errors['message'] = \XF::phrase('xfrm_please_provide_review_with_your_rating');
		}

		if ($rating->is_review && utf8_strlen($rating->message) < $this->reviewMinLength)
		{
			$errors['message'] = \XF::phrase(
				'xfrm_your_review_must_be_at_least_x_characters',
				['min' => $this->reviewMinLength]
			);
		}

		if (!$rating->rating)
		{
			$errors['rating'] = \XF::phrase('xfrm_please_select_star_rating');
		}

		return $errors;
	}

	protected function _save()
	{
		if ($this->isPreRegAction)
		{
			throw new \LogicException("Pre-reg action ratings cannot be saved");
		}

		$rating = $this->rating;

		$this->db()->beginTransaction();

		$existing = $this->resource->CurrentVersion->Ratings[$rating->user_id];
		if ($existing)
		{
			$existing->delete();
		}

		$rating->save(true, false);

		if ($this->sendAlert)
		{
			$this->repository('XFRM:ResourceRating')->sendReviewAlertToResourceAuthor($rating);
		}

		$this->db()->commit();

		return $rating;
	}
}