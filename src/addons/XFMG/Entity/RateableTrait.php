<?php

namespace XFMG\Entity;

use XF\Mvc\Entity\Structure;

trait RateableTrait
{
	protected $weightedThreshold = 10;
	protected $weightedAverage = 3;

	public function canRate(&$error = null)
	{
		if (!$this->isVisible())
		{
			return false;
		}

		$visitor = \XF::visitor();
		if (!$visitor->user_id)
		{
			return false;
		}

		$contentType = $this->structure()->contentType;
		if ($contentType == 'xfmg_media')
		{
			$stateField = 'media_state';
			$perm = 'rate';
		}
		else
		{
			$stateField = 'album_state';
			$perm = 'rateAlbum';
		}

		if ($this->$stateField != 'visible')
		{
			return false;
		}

		if ($this->user_id == $visitor->user_id)
		{
			$error = \XF::phrase('xfmg_rating_your_own_content_is_considered_cheating');
			return false;
		}

		return $this->hasPermission($perm);
	}

	public function hasRated()
	{
		$visitor = \XF::visitor();

		if (!$visitor->user_id)
		{
			return false;
		}

		return !empty($this->Ratings[$visitor->user_id]);
	}

	/**
	 * @return \XFMG\Entity\Rating
	 */
	public function getNewRating()
	{
		$comment = $this->em()->create('XFMG:Rating');
		$comment->content_type = $this->structure()->contentType;
		$comment->content_id = $this->getEntityId();

		return $comment;
	}

	/**
	 * @return array
	 */
	public function getRatingIds()
	{
		$structure = $this->structure();

		return $this->db()->fetchAllColumn("
			SELECT rating_id
			FROM xf_mg_rating
			WHERE content_type = ?
				AND content_id = ?
			ORDER BY rating_date
		", [$structure->contentType, $this->getEntityId()]);
	}

	public function rebuildRating()
	{
		$structure = $this->structure();

		$rating = $this->db()->fetchRow("
			SELECT COUNT(*) AS total,
				SUM(rating) AS sum
			FROM xf_mg_rating
			WHERE content_type = ?
			AND content_id = ?
		", [$structure->contentType, $this->getEntityId()]);

		$this->rating_sum = $rating['sum'] ?: 0;
		$this->rating_count = $rating['total'] ?: 0;
	}

	public function updateRatingAverage()
	{
		$threshold = $this->weightedThreshold;
		$average = $this->weightedAverage;

		$this->rating_weighted = ($threshold * $average + $this->rating_sum) / ($threshold + $this->rating_count);

		if ($this->rating_count)
		{
			$this->rating_avg = $this->rating_sum / $this->rating_count;
		}
		else
		{
			$this->rating_avg = 0;
		}
	}

	protected function _postDeleteRatings()
	{
		$db = $this->db();

		$ratingIds = $this->rating_ids;

		if ($ratingIds)
		{
			$db->delete('xf_mg_rating', 'rating_id IN (' . $db->quote($ratingIds) . ')');
		}
	}

	protected static function addRateableStructureElements(Structure $structure)
	{
		$structure->columns['rating_count'] = ['type' => self::UINT, 'forced' => true, 'default' => 0, 'api' => true];
		$structure->columns['rating_sum'] = ['type' => self::UINT, 'forced' => true, 'default' => 0];
		$structure->columns['rating_avg'] = ['type' => self::FLOAT, 'default' => 0, 'api' => true];
		$structure->columns['rating_weighted'] = ['type' => self::FLOAT, 'default' => 0, 'api' => true];

		$structure->getters['rating_ids'] = true;

		$structure->relations['Ratings'] = [
			'entity' => 'XFMG:Rating',
			'type' => self::TO_MANY,
			'conditions' => [
				['content_type', '=', $structure->contentType],
				['content_id', '=', '$' . $structure->primaryKey]
			],
			'key' => 'user_id',
			'order' => 'rating_date'
		];

	}
}