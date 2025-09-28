<?php

namespace XFMG\Search\Data;

use XF\Mvc\Entity\Entity;
use XF\Search\Data\AbstractData;
use XF\Search\IndexRecord;
use XF\Search\MetadataStructure;

class Media extends AbstractData
{
	public function getEntityWith($forView = false)
	{
		$get = ['User', 'Category', 'Album'];
		if ($forView)
		{
			$visitor = \XF::visitor();
			$get[] = 'Category.Permissions|' . $visitor->permission_combination_id;
		}

		return $get;
	}

	public function getIndexData(Entity $entity)
	{
		/** @var \XFMG\Entity\MediaItem $entity */

		$index = IndexRecord::create('xfmg_media', $entity->media_id, [
			'title' => $entity->title_,
			'message' => $entity->description_,
			'date' => $entity->media_date,
			'user_id' => $entity->user_id,
			'discussion_id' => $entity->media_id,
			'metadata' => $this->getMetaData($entity)
		]);

		if ($entity->tags)
		{
			$index->indexTags($entity->tags);
		}

		if (!$entity->isVisible())
		{
			$index->setHidden();
		}

		return $index;
	}

	protected function getMetaData(\XFMG\Entity\MediaItem $entity)
	{
		$metadata = [];

		$metadata['mediacat'] = $entity->category_id;
		$metadata['mediaalbum'] = $entity->album_id;

		return $metadata;
	}

	public function setupMetadataStructure(MetadataStructure $structure)
	{
		$structure->addField('mediacat', MetadataStructure::INT);
		$structure->addField('mediaalbum', MetadataStructure::INT);
	}

	public function getResultDate(Entity $entity)
	{
		return $entity->media_date;
	}

	public function getSearchableContentTypes()
	{
		return ['xfmg_media'];
	}

	public function getTemplateData(Entity $entity, array $options = [])
	{
		return [
			'mediaItem' => $entity,
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
			'title' => \XF::phrase('xfmg_search_media'),
			'order' => 200
		];
	}

	public function getSectionContext()
	{
		return 'xfmg';
	}

	public function getSearchFormData()
	{
		return [
			'categoryTree' => $this->getSearchableCategoryTree()
		];
	}

	/**
	 * @return \XF\Tree
	 */
	protected function getSearchableCategoryTree()
	{
		/** @var \XFMG\Repository\Category $categoryRepo */
		$categoryRepo = \XF::repository('XFMG:Category');
		$categoryTree = $categoryRepo->createCategoryTree($categoryRepo->getViewableCategories());

		return $categoryTree;
	}

	public function applyTypeConstraintsFromInput(\XF\Search\Query\Query $query, \XF\Http\Request $request, array &$urlConstraints)
	{
		$categoryIds = $request->filter('c.categories', 'array-uint');
		$categoryIds = array_unique($categoryIds);
		if ($categoryIds && reset($categoryIds))
		{
			if ($request->filter('c.child_categories', 'bool'))
			{
				$categoryTree = $this->getSearchableCategoryTree();

				$searchCategoryIds = array_fill_keys($categoryIds, true);
				$categoryTree->traverse(function($id, $category) use (&$searchCategoryIds)
				{
					if (isset($searchCategoryIds[$id]) || isset($searchCategoryIds[$category->parent_category_id]))
					{
						$searchCategoryIds[$id] = true;
					}
				});

				$categoryIds = array_unique(array_keys($searchCategoryIds));
			}
			else
			{
				unset($urlConstraints['child_categories']);
			}

			$query->withMetadata('mediacat', $categoryIds);
		}
		else
		{
			unset($urlConstraints['categories']);
			unset($urlConstraints['child_categories']);
		}

		$albumIds = $request->filter('c.albums', 'array-uint');
		$albumIds = array_unique($albumIds);
		if ($albumIds && reset($albumIds))
		{
			$query->withMetadata('mediaalbum', $albumIds);
		}
		else
		{
			unset($urlConstraints['albums']);
		}
	}

	public function canUseInlineModeration(Entity $entity, &$error = null)
	{
		/** @var \XFMG\Entity\MediaItem $entity */
		return $entity->canUseInlineModeration($error);
	}
}