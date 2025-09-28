<?php

namespace XFMG\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int|null comment_read_id
 * @property int user_id
 * @property int media_id
 * @property int comment_read_date
 */
class MediaCommentRead extends Entity
{
	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_mg_media_comment_read';
		$structure->shortName = 'XFMG:MediaCommentRead';
		$structure->primaryKey = 'comment_read_id';
		$structure->columns = [
			'comment_read_id' => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
			'user_id' => ['type' => self::UINT, 'required' => true],
			'media_id' => ['type' => self::UINT, 'required' => true],
			'comment_read_date' => ['type' => self::UINT, 'required' => true]
		];
		$structure->getters = [];
		$structure->relations = [];
		$structure->options = [];

		return $structure;
	}
}