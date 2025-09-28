<?php

namespace XFMG\XF\Entity;

use XF\Mvc\Entity\Structure;

class Forum extends XFCP_Forum
{
	public static function getStructure(Structure $structure)
	{
		$structure = parent::getStructure($structure);

		$structure->columns['xfmg_media_mirror_category_id'] = ['type' => self::UINT, 'default' => 0, 'changeLog' => false];

		$structure->relations['XfmgMediaMirrorCategory'] = [
			'entity' => 'XFMG:Category',
			'type' => self::TO_ONE,
			'conditions' => [['category_id', '=', '$xfmg_media_mirror_category_id']],
			'primary' => true
		];

		return $structure;
	}
}