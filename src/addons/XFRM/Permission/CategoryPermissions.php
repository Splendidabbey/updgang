<?php

namespace XFRM\Permission;

use XF\Mvc\Entity\Entity;
use XF\Permission\TreeContentPermissions;

class CategoryPermissions extends TreeContentPermissions
{
	public function getContentType()
	{
		return 'resource_category';
	}

	public function getAnalysisTypeTitle()
	{
		return \XF::phrase('xfrm_resource_category_permissions');
	}

	public function getContentTitle(Entity $entity)
	{
		return $entity->title;
	}

	public function isValidPermission(\XF\Entity\Permission $permission)
	{
		return ($permission->permission_group_id == 'resource');
	}

	public function getContentTree()
	{
		/** @var \XFRM\Repository\Category $categoryRepo */
		$categoryRepo = $this->builder->em()->getRepository('XFRM:Category');
		return $categoryRepo->createCategoryTree($categoryRepo->findCategoryList()->fetch());
	}

	protected function getFinalPerms($contentId, array $calculated, array &$childPerms)
	{
		if (!isset($calculated['resource']))
		{
			$calculated['resource'] = [];
		}

		$final = $this->builder->finalizePermissionValues($calculated['resource']);

		if (empty($final['view']))
		{
			$childPerms['resource']['view'] = 'deny';
		}

		return $final;
	}

	protected function getFinalAnalysisPerms($contentId, array $calculated, array &$childPerms)
	{
		$final = $this->builder->finalizePermissionValues($calculated);

		if (empty($final['resource']['view']))
		{
			$childPerms['resource']['view'] = 'deny';
		}

		return $final;
	}
}