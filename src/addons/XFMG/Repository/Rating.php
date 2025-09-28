<?php

namespace XFMG\Repository;

use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

class Rating extends Repository
{
	/**
	 * @param string $contentType
	 * @param int $contentId
	 *
	 * @return Finder
	 */
	public function findContentRatings($contentType, $contentId)
	{
		return $this->finder('XFMG:Rating')
			->where([
				'content_type' => $contentType,
				'content_id' => $contentId
			])->order('rating_date');
	}
}