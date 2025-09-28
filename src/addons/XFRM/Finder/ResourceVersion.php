<?php

namespace XFRM\Finder;

use XF\Mvc\Entity\Finder;

class ResourceVersion extends Finder
{
	public function inResource(\XFRM\Entity\ResourceItem $resource, array $limits = [])
	{
		$limits = array_replace([
			'visibility' => true,
		], $limits);

		$this->where('resource_id', $resource->resource_id);

		if ($limits['visibility'])
		{
			$this->applyVisibilityChecksInResource($resource);
		}

		return $this;
	}

	public function applyVisibilityChecksInResource(\XFRM\Entity\ResourceItem $resource)
	{
		$conditions = [];
		$viewableStates = ['visible'];

		if ($resource->canViewDeletedContent())
		{
			$viewableStates[] = 'deleted';

			$this->with('DeletionLog');
		}

		if ($resource->canViewModeratedContent())
		{
			$viewableStates[] = 'moderated';
		}

		$conditions[] = ['version_state', $viewableStates];

		$this->whereOr($conditions);

		return $this;
	}
}