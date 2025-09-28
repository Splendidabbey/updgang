<?php

namespace XFRM\Service\ResourceRating;

use XFRM\Entity\ResourceRating;

class AuthorReply extends \XF\Service\AbstractService
{
	/**
	 * @var ResourceRating
	 */
	protected $rating;

	protected $sendAlert = true;

	public function __construct(\XF\App $app, ResourceRating $rating)
	{
		parent::__construct($app);
		$this->rating = $rating;
	}

	public function getRating()
	{
		return $this->rating;
	}

	public function reply($message, &$error = null)
	{
		if (!$message)
		{
			$error = \XF::phrase('please_enter_valid_message');
			return false;
		}

		$hasExistingResponse = ($this->rating->author_response ? true : false);

		$visitor = \XF::visitor();
		$this->rating->author_response_team_user_id = $visitor->user_id;
		$this->rating->author_response_team_username = $visitor->username;

		$this->rating->author_response = $message;
		$this->rating->save();

		if (!$hasExistingResponse && $this->sendAlert)
		{
			$this->repository('XFRM:ResourceRating')->sendAuthorReplyAlert($this->rating);
		}

		return true;
	}
}