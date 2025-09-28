<?php

namespace XFRM\Service\ResourceItem;

use XFRM\Entity\ResourceItem;

class Move extends \XF\Service\AbstractService
{
	/**
	 * @var \XFRM\Entity\ResourceItem
	 */
	protected $resource;

	protected $alert = false;
	protected $alertReason = '';

	protected $notifyWatchers = false;

	protected $prefixId = null;

	protected $extraSetup = [];

	public function __construct(\XF\App $app, ResourceItem $resource)
	{
		parent::__construct($app);
		$this->resource = $resource;
	}

	public function getResource()
	{
		return $this->resource;
	}

	public function setSendAlert($alert, $reason = null)
	{
		$this->alert = (bool)$alert;
		if ($reason !== null)
		{
			$this->alertReason = $reason;
		}
	}

	public function setPrefix($prefixId)
	{
		$this->prefixId = ($prefixId === null ? $prefixId : intval($prefixId));
	}

	public function setNotifyWatchers($value = true)
	{
		$this->notifyWatchers = (bool)$value;
	}

	public function addExtraSetup(callable $extra)
	{
		$this->extraSetup[] = $extra;
	}

	public function move(\XFRM\Entity\Category $category)
	{
		$user = \XF::visitor();

		$resource = $this->resource;
		$oldCategory = $resource->Category;

		$moved = ($resource->resource_category_id != $category->resource_category_id);

		foreach ($this->extraSetup AS $extra)
		{
			call_user_func($extra, $resource, $category);
		}

		$resource->resource_category_id = $category->resource_category_id;
		if ($this->prefixId !== null)
		{
			$resource->prefix_id = $this->prefixId;
		}

		if (!$resource->preSave())
		{
			throw new \XF\PrintableException($resource->getErrors());
		}

		$db = $this->db();
		$db->beginTransaction();

		$resource->save(true, false);

		$db->commit();

		if ($moved && $resource->isVisible() && $this->alert && $resource->user_id != $user->user_id)
		{
			/** @var \XFRM\Repository\ResourceItem $resourceRepo */
			$resourceRepo = $this->repository('XFRM:ResourceItem');
			$resourceRepo->sendModeratorActionAlert($this->resource, 'move', $this->alertReason);
		}

		if ($moved && $this->notifyWatchers)
		{
			/** @var \XFRM\Service\ResourceUpdate\Notify $notifier */
			$notifier = $this->service('XFRM:ResourceUpdate\Notify', $resource->Description, 'resource');
			if ($oldCategory)
			{
				$notifier->skipUsersWatchingCategory($oldCategory);
			}
			$notifier->notifyAndEnqueue(3);
		}

		return $moved;
	}
}