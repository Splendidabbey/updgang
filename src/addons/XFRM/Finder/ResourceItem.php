<?php

namespace XFRM\Finder;

use XF\Mvc\Entity\Finder;

class ResourceItem extends Finder
{
	public function applyGlobalVisibilityChecks($allowOwnPending = false)
	{
		$visitor = \XF::visitor();
		$conditions = [];
		$viewableStates = ['visible'];

		if ($visitor->hasPermission('resource', 'viewDeleted'))
		{
			$viewableStates[] = 'deleted';

			$this->with('DeletionLog');
		}

		if ($visitor->hasPermission('resource', 'viewModerated'))
		{
			$viewableStates[] = 'moderated';
		}
		else if ($visitor->user_id && $allowOwnPending)
		{
			$conditions[] = [
				'resource_state' => 'moderated',
				'user_id' => $visitor->user_id
			];
		}

		$conditions[] = ['resource_state', $viewableStates];

		$this->whereOr($conditions);

		return $this;
	}

	public function applyVisibilityChecksInCategory(\XFRM\Entity\Category $category, $allowOwnPending = false)
	{
		$conditions = [];
		$viewableStates = ['visible'];

		if ($category->canViewDeletedResources())
		{
			$viewableStates[] = 'deleted';

			$this->with('DeletionLog');
		}
		
		$visitor = \XF::visitor();
		if ($category->canViewModeratedResources())
		{
			$viewableStates[] = 'moderated';
		}
		else if ($visitor->user_id && $allowOwnPending)
		{
			$conditions[] = [
				'resource_state' => 'moderated',
				'user_id' => $visitor->user_id
			];
		}

		$conditions[] = ['resource_state', $viewableStates];

		$this->whereOr($conditions);

		return $this;
	}

	public function watchedOnly($userId = null)
	{
		if ($userId === null)
		{
			$userId = \XF::visitor()->user_id;
		}
		if (!$userId)
		{
			// no user, just ignore
			return $this;
		}

		$this->whereOr(
			['Watch|' . $userId . '.user_id', '!=', null],
			['Category.Watch|' . $userId . '.user_id', '!=', null]
		);

		return $this;
	}

	/**
	 * @deprecated Use with('full') or with('fullCategory') instead
	 *
	 * @param bool $includeCategory
	 * @return $this
	 */
	public function forFullView($includeCategory = true)
	{
		$this->with($includeCategory ? 'fullCategory' : 'full');

		return $this;
	}

	public function useDefaultOrder()
	{
		$defaultOrder = $this->app()->options()->xfrmListDefaultOrder ?: 'last_update';
		$defaultDir = $defaultOrder == 'title' ? 'asc' : 'desc';

		$this->setDefaultOrder($defaultOrder, $defaultDir);

		return $this;
	}
}