<?php

namespace XFMG\XF\Entity;

use XF\Mvc\Entity\Structure;

class AttachmentData extends XFCP_AttachmentData
{
	public static function getStructure(Structure $structure)
	{
		$structure = parent::getStructure($structure);

		$structure->columns['xfmg_mirror_media_id'] = ['type' => self::UINT, 'default' => 0, 'changeLog' => false];

		$structure->relations['XfmgMirrorMedia'] = [
			'entity' => 'XFMG:MediaItem',
			'type' => self::TO_ONE,
			'conditions' => [['media_id', '=', '$xfmg_mirror_media_id']],
			'primary' => true
		];

		return $structure;
	}
}