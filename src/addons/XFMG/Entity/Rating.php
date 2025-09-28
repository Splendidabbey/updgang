<?php

namespace XFMG\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int|null rating_id
 * @property int content_id
 * @property string content_type
 * @property int user_id
 * @property string username
 * @property int rating
 * @property int rating_date
 *
 * GETTERS
 * @property Album|MediaItem Content
 *
 * RELATIONS
 * @property \XFMG\Entity\Album Album
 * @property \XFMG\Entity\MediaItem Media
 * @property \XFMG\Entity\Comment Comment
 * @property \XF\Entity\User User
 */
class Rating extends Entity
{
	/**
	 * @return Album|MediaItem
	 */
	public function getContent()
	{
		return ($this->content_type == 'xfmg_media' ? $this->Media : $this->Album);
	}

	public function canView(&$error = null)
	{
		$content = $this->Content;

		return $content->canView($error);
	}

	public function isVisible()
	{
		return ($this->Content && $this->Content->isVisible());
	}

	protected function _postSave()
	{
		$this->updateRating();
	}

	protected function _postDelete()
	{
		$this->updateRating(true);
	}

	protected function updateRating($isDelete = false)
	{
		$content = $this->Content;

		if ($this->isInsert())
		{
			$content->rating_sum += $this->rating;
			$content->rating_count += 1;
		}
		else if ($this->isUpdate() && $this->isChanged('rating'))
		{
			$content->rating_sum += ($this->rating - $this->getExistingValue('rating'));
		}
		else if ($isDelete)
		{
			$content->rating_sum = ($content->rating_sum - $this->rating);
			$content->rating_count -= 1;
		}
		else
		{
			return;
		}

		$content->updateRatingAverage();
		$content->save();
	}

	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_mg_rating';
		$structure->shortName = 'XFMG:Rating';
		$structure->contentType = 'xfmg_rating';
		$structure->primaryKey = 'rating_id';
		$structure->columns = [
			'rating_id' => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
			'content_id' => ['type' => self::UINT, 'required' => true],
			'content_type' => ['type' => self::STR, 'required' => true,
				'allowedValues' => ['xfmg_media', 'xfmg_album']
			],
			'user_id' => ['type' => self::UINT, 'default' => 0],
			'username' => ['type' => self::STR, 'maxLength' => 50],
			'rating' => ['type' => self::UINT, 'default' => 0],
			'rating_date' => ['type' => self::UINT, 'default' => \XF::$time]
		];
		$structure->behaviors = [
			'XF:NewsFeedPublishable' => [
				'usernameField' => 'username',
				'dateField' => 'rating_date'
			]
		];
		$structure->getters = [
			'Content' => true
		];
		$structure->relations = [
			'Album' => [
				'entity' => 'XFMG:Album',
				'type' => self::TO_ONE,
				'conditions' => [
					['$content_type', '=', 'xfmg_album'],
					['album_id', '=', '$content_id']
				]
			],
			'Media' => [
				'entity' => 'XFMG:MediaItem',
				'type' => self::TO_ONE,
				'conditions' => [
					['$content_type', '=', 'xfmg_media'],
					['media_id', '=', '$content_id']
				]
			],
			'Comment' => [
				'entity' => 'XFMG:Comment',
				'type' => self::TO_ONE,
				'conditions' => 'rating_id',
				'order' => ['comment_date', 'DESC']
			],
			'User' => [
				'entity' => 'XF:User',
				'type' => self::TO_ONE,
				'conditions' => 'user_id',
				'primary' => true
			]
		];
		$structure->options = [];

		return $structure;
	}
}