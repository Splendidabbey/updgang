<?php

namespace XFMG\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int|null media_view_id
 * @property int user_id
 * @property int media_id
 * @property int media_view_date
 */
class MediaUserView extends Entity
{
	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_mg_media_user_view';
		$structure->shortName = 'XFMG:MediaUserView';
		$structure->primaryKey = 'media_view_id';
		$structure->columns = [
			'media_view_id' => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
			'user_id' => ['type' => self::UINT, 'required' => true],
			'media_id' => ['type' => self::UINT, 'required' => true],
			'media_view_date' => ['type' => self::UINT, 'required' => true]
		];
		$structure->getters = [];
		$structure->relations = [];
		$structure->options = [];

		return $structure;
	}
}