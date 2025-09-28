<?php

namespace XFRM\Search\Data;

use XF\Mvc\Entity\Entity;
use XF\Search\Data\AbstractData;
use XF\Search\IndexRecord;
use XF\Search\MetadataStructure;
use XF\Search\Query\MetadataConstraint;

class ResourceUpdate extends AbstractData
{
	public function getEntityWith($forView = false)
	{
		$get = ['Resource', 'Resource.Category'];
		if ($forView)
		{
			$get[] = 'Resource.User';

			$visitor = \XF::visitor();
			$get[] = 'Resource.Category.Permissions|' . $visitor->permission_combination_id;
		}

		return $get;
	}

	public function getIndexData(Entity $entity)
	{
		/** @var \XFRM\Entity\ResourceUpdate $entity */

		if (!$entity->Resource || !$entity->Resource->Category)
		{
			return null;
		}

		/** @var \XFRM\Entity\ResourceItem $resource */
		$resource = $entity->Resource;

		if ($entity->isDescription())
		{
			return $this->searcher->handler('resource')->getIndexData($resource);
		}

		$index = IndexRecord::create('resource_update', $entity->resource_update_id, [
			'title' => $entity->title_,
			'message' => $entity->message_,
			'date' => $entity->post_date,
			'user_id' => $resource->user_id,
			'discussion_id' => $entity->resource_id,
			'metadata' => $this->getMetaData($entity)
		]);

		if (!$entity->isVisible())
		{
			$index->setHidden();
		}

		return $index;
	}

	protected function getMetaData(\XFRM\Entity\ResourceUpdate $entity)
	{
		/** @var \XFRM\Entity\ResourceItem $resource */
		$resource = $entity->Resource;

		$metadata = [
			'rescat' => $resource->resource_category_id,
			'resource' => $resource->resource_id
		];
		if ($resource->prefix_id)
		{
			$metadata['resprefix'] = $resource->prefix_id;
		}

		return $metadata;
	}

	public function setupMetadataStructure(MetadataStructure $structure)
	{
		$structure->addField('rescat', MetadataStructure::INT);
		$structure->addField('resource', MetadataStructure::INT);
		$structure->addField('resprefix', MetadataStructure::INT);
	}

	public function canIncludeInResults(Entity $entity, array $resultIds)
	{
		/** @var \XFRM\Entity\ResourceUpdate $entity */
		if (isset($resultIds['resource-' . $entity->resource_id]) && $entity->isDescription())
		{
			return false;
		}

		return true;
	}

	public function getResultDate(Entity $entity)
	{
		return $entity->post_date;
	}

	public function getTemplateData(Entity $entity, array $options = [])
	{
		return [
			'update' => $entity,
			'resource' => $entity->Resource,
			'options' => $options
		];
	}
}