<?php

namespace XFMG\XF\Entity;

use XF\Mvc\Entity\Structure;

class User extends XFCP_User
{
	public function canViewMedia()
	{
		return $this->hasPermission('xfmg', 'view');
	}

	public function canViewAlbums()
	{
		return ($this->app()->options()->xfmgAllowPersonalAlbums);
	}

	public function canAddMedia()
	{
		return ($this->user_id && $this->hasPermission('xfmg', 'add'));
	}

	public function canCreateAlbum()
	{
		return ($this->user_id && $this->hasPermission('xfmg', 'createAlbum'));
	}

	public function hasGalleryCategoryPermission($contentId, $permission)
	{
		return $this->PermissionSet->hasContentPermission('xfmg_category', $contentId, $permission);
	}

	public function cacheGalleryCategoryPermissions(array $categoryIds = null)
	{
		if (is_array($categoryIds))
		{
			\XF::permissionCache()->cacheContentPermsByIds($this->permission_combination_id, 'xfmg_category', $categoryIds);
		}
		else
		{
			\XF::permissionCache()->cacheAllContentPerms($this->permission_combination_id, 'xfmg_category');
		}
	}

	public function getXfmgMediaQuota()
	{
		// Total quota stored in KB but generally evaluated against bytes
		// This getter will return the bytes value rather than KB
		return ($this->xfmg_media_quota_ * 1024);
	}
	
	public function rebuildMediaQuota()
	{
		$diskUsage = $this->db()->fetchOne('
			SELECT SUM(ad.file_size)
			FROM xf_attachment AS a
			INNER JOIN xf_attachment_data AS ad ON
				(a.data_id = ad.data_id)
			LEFT JOIN xf_mg_media_item AS media ON
				(a.content_id = media.media_id AND a.content_type = \'xfmg_media\')
			LEFT JOIN xf_mg_album AS album ON
				(media.album_id = album.album_id)
			WHERE media.media_state = \'visible\'
				AND IF(media.album_id > 0, album.album_state = \'visible\', 1=1)
				AND media.user_id = ?
		', $this->user_id);

		$this->xfmg_media_quota = ($diskUsage ? round($diskUsage / 1024) : 0);
	}

	public static function getStructure(Structure $structure)
	{
		$structure = parent::getStructure($structure);

		$structure->columns['xfmg_album_count'] = ['type' => self::UINT, 'default' => 0, 'forced' => true, 'changeLog' => false];
		$structure->columns['xfmg_media_count'] = ['type' => self::UINT, 'default' => 0, 'forced' => true, 'changeLog' => false];
		$structure->columns['xfmg_media_quota'] = ['type' => self::UINT, 'default' => 0, 'forced' => true, 'changeLog' => false];

		$structure->getters['xfmg_media_quota'] = false;

		return $structure;
	}
}