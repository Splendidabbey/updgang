<?php

namespace XFMG\Search\Data;

use XF\Mvc\Entity\Entity;
use XF\Search\Data\AbstractData;
use XF\Search\IndexRecord;
use XF\Search\MetadataStructure;

class Album extends AbstractData
{
	public function getEntityWith($forView = false)
	{
		$get = ['User', 'Category'];
		if ($forView)
		{
			$visitor = \XF::visitor();
			$get[] = 'Category.Permissions|' . $visitor->permission_combination_id;
		}

		return $get;
	}

	public function getIndexData(Entity $entity)
	{
		/** @var \XFMG\Entity\Album $entity */

		$index = IndexRecord::create('xfmg_album', $entity->album_id, [
			'title' => $entity->title_,
			'message' => $entity->description_,
			'date' => $entity->create_date,
			'user_id' => $entity->user_id,
			'discussion_id' => $entity->album_id,
			'metadata' => $this->getMetaData($entity)
		]);

		if (!$entity->isVisible())
		{
			$index->setHidden();
		}

		return $index;
	}

	protected function getMetaData(\XFMG\Entity\Album $entity)
	{
		$metadata = [];

		$metadata['albumcat'] = $entity->category_id;

		return $metadata;
	}

	public function setupMetadataStructure(MetadataStructure $structure)
	{
		$structure->addField('albumcat', MetadataStructure::INT);
	}

	public function getResultDate(Entity $entity)
	{
		return $entity->create_date;
	}

	public function getSearchableContentTypes()
	{
		return ['xfmg_album'];
	}

	public function getTemplateData(Entity $entity, array $options = [])
	{
		return [
			'album' => $entity,
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
			'title' => \XF::phrase('xfmg_search_albums'),
			'order' => 205
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

			$query->withMetadata('albumcat', $categoryIds);
		}
		else
		{
			unset($urlConstraints['categories']);
			unset($urlConstraints['child_categories']);
		}
	}

	public function canUseInlineModeration(Entity $entity, &$error = null)
	{
		/** @var \XFMG\Entity\Album $entity */
		return $entity->canUseInlineModeration($error);
	}
}