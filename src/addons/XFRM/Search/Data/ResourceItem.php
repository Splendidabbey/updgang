<?php

namespace XFRM\Search\Data;

use XF\Mvc\Entity\Entity;
use XF\Search\Data\AbstractData;
use XF\Search\IndexRecord;
use XF\Search\MetadataStructure;
use XF\Search\Query\MetadataConstraint;

class ResourceItem extends AbstractData
{
	public function getEntityWith($forView = false)
	{
		$get = ['Category', 'Description'];
		if ($forView)
		{
			$get[] = 'User';
			$get[] = 'CurrentVersion';

			$visitor = \XF::visitor();
			$get[] = 'Category.Permissions|' . $visitor->permission_combination_id;
		}

		return $get;
	}

	public function getIndexData(Entity $entity)
	{
		/** @var \XFRM\Entity\ResourceItem $entity */

		if (!$entity->Category)
		{
			return null;
		}

		/** @var \XFRM\Entity\ResourceUpdate|null $description */
		$description = $entity->Description;

		$message = $description ? $description->message_  : '';

		$index = IndexRecord::create('resource', $entity->resource_id, [
			'title' => $entity->title_,
			'message' => $entity->tag_line_ . ' ' . $message,
			'date' => $entity->resource_date,
			'user_id' => $entity->user_id,
			'discussion_id' => $entity->resource_id,
			'metadata' => $this->getMetaData($entity)
		]);

		if (!$entity->isVisible())
		{
			$index->setHidden();
		}

		if ($entity->tags)
		{
			$index->indexTags($entity->tags);
		}

		return $index;
	}

	protected function getMetaData(\XFRM\Entity\ResourceItem $entity)
	{
		$metadata = [
			'rescat' => $entity->resource_category_id,
			'resource' => $entity->resource_id
		];
		if ($entity->prefix_id)
		{
			$metadata['resprefix'] = $entity->prefix_id;
		}

		return $metadata;
	}

	public function setupMetadataStructure(MetadataStructure $structure)
	{
		$structure->addField('rescat', MetadataStructure::INT);
		$structure->addField('resource', MetadataStructure::INT);
		$structure->addField('resprefix', MetadataStructure::INT);
	}

	public function getResultDate(Entity $entity)
	{
		return $entity->resource_date;
	}

	public function getTemplateData(Entity $entity, array $options = [])
	{
		return [
			'resource' => $entity,
			'options' => $options
		];
	}

	public function canUseInlineModeration(Entity $entity, &$error = null)
	{
		/** @var \XFRM\Entity\ResourceItem $entity */
		return $entity->canUseInlineModeration($error);
	}

	public function getSearchableContentTypes()
	{
		return ['resource', 'resource_update'];
	}

	public function getSearchFormTab()
	{
		/** @var \XFRM\XF\Entity\User $visitor */
		$visitor = \XF::visitor();
		if (!method_exists($visitor, 'canViewResources') || !$visitor->canViewResources())
		{
			return null;
		}

		return [
			'title' => \XF::phrase('xfrm_search_resources'),
			'order' => 300
		];
	}

	public function getSectionContext()
	{
		return 'xfrm';
	}

	public function getSearchFormData()
	{
		$prefixListData = $this->getPrefixListData();

		return [
			'prefixGroups' => $prefixListData['prefixGroups'],
			'prefixesGrouped' => $prefixListData['prefixesGrouped'],

			'categoryTree' => $this->getSearchableCategoryTree()
		];
	}

	/**
	 * @return \XF\Tree
	 */
	protected function getSearchableCategoryTree()
	{
		/** @var \XFRM\Repository\Category $categoryRepo */
		$categoryRepo = \XF::repository('XFRM:Category');
		return $categoryRepo->createCategoryTree($categoryRepo->getViewableCategories());
	}

	protected function getPrefixListData()
	{
		/** @var \XFRM\Repository\ResourcePrefix $prefixRepo */
		$prefixRepo = \XF::repository('XFRM:ResourcePrefix');
		return $prefixRepo->getVisiblePrefixListData();
	}

	public function applyTypeConstraintsFromInput(\XF\Search\Query\Query $query, \XF\Http\Request $request, array &$urlConstraints)
	{
		$prefixes = $request->filter('c.prefixes', 'array-uint');
		$prefixes = array_unique($prefixes);
		if ($prefixes && reset($prefixes))
		{
			$query->withMetadata('resprefix', $prefixes);
		}
		else
		{
			unset($urlConstraints['prefixes']);
		}

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

			$query->withMetadata('rescat', $categoryIds);
		}
		else
		{
			unset($urlConstraints['categories']);
			unset($urlConstraints['child_categories']);
		}

		$includeUpdates = $request->filter('c.include_updates', 'bool');
		if (!$includeUpdates)
		{
			$query->inType('resource');
			unset($urlConstraints['include_updates']);
		}
	}

	public function getTypePermissionConstraints(\XF\Search\Query\Query $query, $isOnlyType)
	{
		/** @var \XFRM\Repository\Category $categoryRepo */
		$categoryRepo = \XF::repository('XFRM:Category');

		$with = ['Permissions|' . \XF::visitor()->permission_combination_id];
		$categories = $categoryRepo->findCategoryList(null, $with)->fetch();

		$skip = [];
		foreach ($categories AS $category)
		{
			/** @var \XFRM\Entity\Category $category */
			if (!$category->canView())
			{
				$skip[] = $category->resource_category_id;
			}
		}

		if ($skip)
		{
			return [
				new MetadataConstraint('rescat', $skip, MetadataConstraint::MATCH_NONE)
			];
		}
		else
		{
			return [];
		}
	}

	public function getGroupByType()
	{
		return 'resource';
	}
}