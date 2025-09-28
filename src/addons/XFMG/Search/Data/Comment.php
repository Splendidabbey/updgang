<?php

namespace XFMG\Search\Data;

use XF\Mvc\Entity\Entity;
use XF\Search\Data\AbstractData;
use XF\Search\IndexRecord;
use XF\Search\MetadataStructure;

class Comment extends AbstractData
{
	public function getEntityWith($forView = false)
	{
		return ['User', 'Media', 'Album'];
	}

	public function getIndexData(Entity $entity)
	{
		/** @var \XFMG\Entity\Comment $entity */

		$content = $entity->Content;
		if (!$content)
		{
			return null;
		}

		$index = IndexRecord::create('xfmg_comment', $entity->comment_id, [
			'title' => '',
			'message' => $entity->message_,
			'date' => $entity->comment_date,
			'user_id' => $entity->user_id,
			'discussion_id' => $entity->content_id,
			'metadata' => $this->getMetaData($entity)
		]);

		if (!$entity->isVisible())
		{
			$index->setHidden();
		}

		return $index;
	}

	protected function getMetaData(\XFMG\Entity\Comment $entity)
	{
		$metadata = [];

		$metadata['content_type'] = $entity->content_type;
		$metadata['content_id'] = $entity->content_id;

		return $metadata;
	}

	public function setupMetadataStructure(MetadataStructure $structure)
	{
		$structure->addField('content_type', MetadataStructure::STR);
		$structure->addField('content_id', MetadataStructure::INT);
	}

	public function getResultDate(Entity $entity)
	{
		return $entity->comment_date;
	}

	public function getSearchableContentTypes()
	{
		return ['xfmg_comment'];
	}

	public function getTemplateData(Entity $entity, array $options = [])
	{
		return [
			'comment' => $entity,
			'options' => $options
		];
	}

	public function getSearchFormTab()
	{
		/** @var \XFMG\XF\Entity\User $visitor */
		$visitor = \XF::visitor();
		if (!method_exists($visitor, 'canViewMedia') || !$visitor->canViewMedia())
		{
			return null;
		}

		return [
			'title' => \XF::phrase('xfmg_search_media_comments'),
			'order' => 210
		];
	}

	public function getSectionContext()
	{
		return 'xfmg';
	}

	public function getSearchFormData()
	{
		return [
			'types' => [
				'xfmg_media' => \XF::phrase('xfmg_media_items'),
				'xfmg_album' => \XF::phrase('xfmg_albums')
			]
		];
	}

	public function applyTypeConstraintsFromInput(\XF\Search\Query\Query $query, \XF\Http\Request $request, array &$urlConstraints)
	{
		$contentTypes = $request->filter('c.types', 'array-str');
		$contentTypes = array_unique($contentTypes);
		if ($contentTypes)
		{
			$query->withMetadata('content_type', $contentTypes);

			$contentIds = $request->filter('c.ids', 'array-int');
			$contentIds = array_unique($contentIds);
			if ($contentIds && reset($contentIds))
			{
				$query->withMetadata('content_id', $contentIds);
			}
			else
			{
				unset($urlConstraints['ids']);
			}
		}
	}

	public function canUseInlineModeration(Entity $entity, &$error = null)
	{
		/** @var \XFMG\Entity\Comment $entity */
		return $entity->canUseInlineModeration($error);
	}
}