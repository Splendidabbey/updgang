<?php

namespace XFMG\Import\Importer;

class XFMG22 extends XFMG21
{
	public static function getListInfo()
	{
		return [
			'target' => 'XenForo Media Gallery',
			'source' => 'XenForo Media Gallery 2.2',
		];
	}

	protected function validateVersion(\XF\Db\AbstractAdapter $db, &$error)
	{
		$versionId = $db->fetchOne("SELECT version_id FROM xf_addon WHERE addon_id = 'XFMG'");
		if (!$versionId || intval($versionId) < 902020031)
		{
			$error = \XF::phrase('xfmg_you_may_only_import_from_xenforo_media_gallery_x', ['version' => '2.2']);
			return false;
		}

		return true;
	}
}