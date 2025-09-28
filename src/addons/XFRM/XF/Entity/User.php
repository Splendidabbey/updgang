<?php

namespace XFRM\XF\Entity;

use XF\Mvc\Entity\Structure;

class User extends XFCP_User
{
	public function canViewResources(&$error = null)
	{
		return $this->hasPermission('resource', 'view');
	}

	public function canAddResource(&$error = null)
	{
		return ($this->user_id && $this->hasPermission('resource', 'add'));
	}

	public function hasResourceCategoryPermission($contentId, $permission)
	{
		return $this->PermissionSet->hasContentPermission('resource_category', $contentId, $permission);
	}

	public function cacheResourceCategoryPermissions(array $categoryIds = null)
	{
		if (is_array($categoryIds))
		{
			\XF::permissionCache()->cacheContentPermsByIds($this->permission_combination_id, 'resource_category', $categoryIds);
		}
		else
		{
			\XF::permissionCache()->cacheAllContentPerms($this->permission_combination_id, 'resource_category');
		}
	}

	public static function getStructure(Structure $structure)
	{
		$structure = parent::getStructure($structure);

		$structure->columns['xfrm_resource_count'] = ['type' => self::UINT, 'default' => 0, 'forced' => true, 'changeLog' => false];

		return $structure;
	}
}