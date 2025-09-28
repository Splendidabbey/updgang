<?php

namespace XFRM\Service\ResourceItem;

use XFRM\Entity\ResourceItem;

class Feature extends \XF\Service\AbstractService
{
	/**
	 * @var \XFRM\Entity\ResourceItem
	 */
	protected $resource;

	public function __construct(\XF\App $app, ResourceItem $resource)
	{
		parent::__construct($app);
		$this->resource = $resource;
	}

	public function getResource()
	{
		return $this->resource;
	}

	public function feature()
	{
		$db = $this->db();
		$db->beginTransaction();

		$affected = $db->insert('xf_rm_resource_feature', [
			'resource_id' => $this->resource->resource_id,
			'feature_date' => \XF::$time
		], false, 'feature_date = VALUES(feature_date)');

		if ($affected == 1)
		{
			// insert
			$this->onNewFeature();
		}

		$db->commit();
	}

	protected function onNewFeature()
	{
		if ($this->resource->isVisible())
		{
			$category = $this->resource->Category;
			if ($category)
			{
				$category->featured_count++;
				$category->save();
			}
		}

		$this->app->logger()->logModeratorAction('resource', $this->resource, 'feature');
	}

	public function unfeature()
	{
		$db = $this->db();
		$db->beginTransaction();

		$affected = $db->delete('xf_rm_resource_feature', 'resource_id = ?', $this->resource->resource_id);
		if ($affected)
		{
			$this->onUnfeature();
		}

		$db->commit();
	}

	protected function onUnfeature()
	{
		if ($this->resource->isVisible())
		{
			$category = $this->resource->Category;
			if ($category)
			{
				$category->featured_count--;
				$category->save();
			}
		}

		$this->app->logger()->logModeratorAction('resource', $this->resource, 'unfeature');
	}
}