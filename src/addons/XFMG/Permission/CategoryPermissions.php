<?php

namespace XFMG\Permission;

use XF\Mvc\Entity\Entity;
use XF\Permission\TreeContentPermissions;

class CategoryPermissions extends TreeContentPermissions
{
	protected function getContentType()
	{
		return 'xfmg_category';
	}

	public function getAnalysisTypeTitle()
	{
		return \XF::phrase('xfmg_media_category_permissions');
	}

	public function getContentTitle(Entity $entity)
	{
		return $entity->title;
	}

	public function isValidPermission(\XF\Entity\Permission $permission)
	{
		return ($permission->permission_group_id == 'xfmg');
	}

	public function getContentTree()
	{
		/** @var \XFMG\Repository\Category $categoryRepo */
		$categoryRepo = $this->builder->em()->getRepository('XFMG:Category');
		return $categoryRepo->createCategoryTree($categoryRepo->findCategoryList()->fetch());
	}

	protected function getFinalPerms($contentId, array $calculated, array &$childPerms)
	{
		if (!isset($calculated['xfmg']))
		{
			$calculated['xfmg'] = [];
		}

		$final = $this->builder->finalizePermissionValues($calculated['xfmg']);

		if (empty($final['view']))
		{
			$childPerms['xfmg']['view'] = 'deny';
		}

		return $final;
	}

	protected function getFinalAnalysisPerms($contentId, array $calculated, array &$childPerms)
	{
		$final = $this->builder->finalizePermissionValues($calculated);

		if (empty($final['xfmg']['view']))
		{
			$childPerms['xfmg']['view'] = 'deny';
		}

		return $final;
	}
}