<?php

namespace XFMG\Service\Rating;

use XF\Service\AbstractService;

class Rater extends AbstractService
{
	use \XF\Service\ValidateAndSavableTrait;

	/**
	 * @var \XFMG\Entity\MediaItem | \XFMG\Entity\Album
	 */
	protected $content;

	/**
	 * @var \XFMG\Entity\Rating
	 */
	protected $rating;

	/**
	 * @var \XFMG\Entity\Rating
	 */
	protected $existingRating;

	/**
	 * @var \XFMG\Service\Comment\Creator
	 */
	protected $commentCreator;

	/**
	 * @var \XFMG\Entity\Comment
	 */
	protected $existingComment;

	protected $performValidations = true;

	public function __construct(\XF\App $app, \XF\Mvc\Entity\Entity $content)
	{
		parent::__construct($app);
		$this->setContent($content);
	}

	protected function setContent(\XF\Mvc\Entity\Entity $content)
	{
		$visitor = \XF::visitor();

		$this->content = $content;
		$this->rating = $this->content->getNewRating();

		$this->setUser($visitor);
		$this->handleExisting($content);
	}

	protected function handleExisting(\XF\Mvc\Entity\Entity $content)
	{
		$rating = $this->rating;

		if (isset($content->Ratings[$rating->user_id]))
		{
			/** @var \XFMG\Entity\Rating $existingRating */
			$existingRating = $content->Ratings[$rating->user_id];
			$existingComment = $existingRating->Comment;
			if ($existingComment)
			{
				$existingComment->rating_id = 0;
				$this->existingComment = $existingComment;
				$rating->addCascadedSave($existingComment);
			}
			$this->existingRating = $existingRating;
		}
	}

	public function getContent()
	{
		return $this->content;
	}

	public function getRating()
	{
		return $this->rating;
	}

	public function setUser(\XF\Entity\User $user)
	{
		$this->rating->user_id = $user->user_id;
		$this->rating->username = $user->username;
	}

	public function setRating($rating)
	{
		$this->rating->rating = $rating;
	}

	public function setPerformValidations($perform)
	{
		$this->performValidations = (bool)$perform;
	}

	public function getPerformValidations()
	{
		return $this->performValidations;
	}

	public function setComment($message, $format = true)
	{
		/** @var \XFMG\Service\Comment\Creator $commentCreator */
		$commentCreator = $this->service('XFMG:Comment\Creator', $this->content);
		$commentCreator->setPerformValidations($this->performValidations);
		$commentCreator->setUser($this->rating->User);
		$commentCreator->setMessage($message, $format);
		$commentCreator->checkForSpam();

		$this->commentCreator = $commentCreator;
	}

	protected function finalSetup()
	{
		$this->rating->rating_date = time();
	}

	protected function _validate()
	{
		$this->finalSetup();

		$rating = $this->rating;
		$rating->preSave();
		$errors = $rating->getErrors();
		if (!$errors && $this->commentCreator)
		{
			$this->commentCreator->validate($errors);
		}

		if (!$rating->rating)
		{
			$errors['rating'] = \XF::phrase('xfmg_please_select_star_rating');
		}

		return $errors;
	}

	protected function _save()
	{
		$rating = $this->rating;

		if ($this->existingRating)
		{
			$this->existingRating->delete();
		}

		$rating->save();

		if ($this->commentCreator)
		{
			$comment = $this->commentCreator->getComment();
			$comment->set('rating_id', $rating->rating_id, ['forceSet' => true]);

			// The rating entity will log this itself.
			$comment->getBehavior('XF:NewsFeedPublishable')->setOption('enabled', false);

			$this->commentCreator->save();
		}

		return $rating;
	}

	public function sendNotifications()
	{
		$contentOwner = $this->content->User;
		if (!$contentOwner)
		{
			return;
		}

		$ratingAuthor = $this->rating->User;
		if (!$ratingAuthor)
		{
			return;
		}

		$alertRepo = $this->repository('XF:UserAlert');
		$alertRepo->alert(
			$contentOwner,
			$ratingAuthor->user_id,
			$ratingAuthor->username,
			'xfmg_rating',
			$this->rating->rating_id,
			'insert',
			[]
		);
	}
}