<?php

namespace XFRM\Service\ResourceRating;

use XFRM\Entity\ResourceRating;

class AuthorReplyDelete extends \XF\Service\AbstractService
{
	/**
	 * @var ResourceRating
	 */
	protected $rating;

	public function __construct(\XF\App $app, ResourceRating $rating)
	{
		parent::__construct($app);
		$this->rating = $rating;
	}

	public function getRating()
	{
		return $this->rating;
	}

	public function delete()
	{
		if ($this->rating->author_response === '')
		{
			return false;
		}

		$this->rating->author_response = '';
		$this->rating->save();

		return true;
	}
}