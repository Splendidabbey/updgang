<?php

namespace XFRM\Repository;

use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;
use XF\PrintableException;

class ResourceUpdate extends Repository
{
	public function findUpdatesInResource(\XFRM\Entity\ResourceItem $resource, array $limits = [])
	{
		/** @var \XFRM\Finder\ResourceUpdate $finder */
		$finder = $this->finder('XFRM:ResourceUpdate');
		$finder->inResource($resource, $limits)
			->setDefaultOrder('post_date', 'desc');

		if ($resource->canViewTeamMembers())
		{
			$finder->with('TeamUser');
		}

		return $finder;
	}

	public function sendModeratorActionAlert(\XFRM\Entity\ResourceUpdate $update, $action, $reason = '', array $extra = [])
	{
		$resource = $update->Resource;

		if (!$resource || !$resource->user_id || !$resource->User)
		{
			return false;
		}

		$extra = array_merge([
			'title' => $resource->title,
			'prefix_id' => $resource->prefix_id,
			'update' => $update->title,
			'link' => $this->app()->router('public')->buildLink('nopath:resources/update', $update),
			'resourceLink' => $this->app()->router('public')->buildLink('nopath:resources', $resource),
			'reason' => $reason
		], $extra);

		/** @var \XF\Repository\UserAlert $alertRepo */
		$alertRepo = $this->repository('XF:UserAlert');
		$alertRepo->alert(
			$resource->User,
			0, '',
			'user', $resource->user_id,
			"resource_update_{$action}", $extra,
			['dependsOnAddOnId' => 'XFRM']
		);

		return true;
	}
}