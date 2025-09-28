<?php

namespace XFRM\ThreadType;

use XF\Entity\Thread;
use XF\Http\Request;
use XF\ThreadType\AbstractHandler;

class ResourceItem extends AbstractHandler
{
	public function getTypeIconClass(): string
	{
		return '';
	}

	public function getThreadViewAndTemplate(Thread $thread): array
	{
		return ['XFRM:Thread\ViewTypeResource', 'xfrm_thread_view_type_resource'];
	}

	public function adjustThreadViewParams(Thread $thread, array $viewParams, Request $request): array
	{
		$thread = $viewParams['thread'] ?? null;
		if ($thread)
		{
			/** @var \XFRM\Entity\ResourceItem $resource */
			$resource = \XF::repository('XFRM:ResourceItem')->findResourceForThread($thread)->fetchOne();
			if ($resource && $resource->canView())
			{
				$viewParams['resource'] = $resource;
			}
		}

		return $viewParams;
	}

	public function allowExternalCreation(): bool
	{
		return false;
	}

	public function canThreadTypeBeChanged(Thread $thread): bool
	{
		return false;
	}

	public function canConvertThreadToType(bool $isBulk): bool
	{
		return false;
	}
}