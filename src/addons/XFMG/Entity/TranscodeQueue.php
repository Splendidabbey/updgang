<?php

namespace XFMG\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int|null transcode_queue_id
 * @property array queue_data
 * @property string queue_state
 * @property int queue_date
 */
class TranscodeQueue extends Entity
{
	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_mg_transcode_queue';
		$structure->shortName = 'XFMG:TranscodeQueue';
		$structure->primaryKey = 'transcode_queue_id';
		$structure->columns = [
			'transcode_queue_id' => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
			'queue_data' => ['type' => self::JSON_ARRAY, 'required' => true],
			'queue_state' => ['type' => self::STR, 'default' => 'pending'],
			'queue_date' => ['type' => self::UINT, 'default' => \XF::$time]
		];
		$structure->getters = [];
		$structure->relations = [];
		$structure->options = [];

		return $structure;
	}
}