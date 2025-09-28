<?php

namespace XFMG\Admin\Controller;

use XF\Mvc\FormAction;
use XF\Mvc\ParameterBag;
use XF\Admin\Controller\AbstractController;

class Category extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertAdminPermission('mediaGallery');
	}

	/**
	 * @return \XFMG\ControllerPlugin\CategoryTree
	 */
	protected function getCategoryTreePlugin()
	{
		return $this->plugin('XFMG:CategoryTree');
	}

	public function actionIndex()
	{
		return $this->getCategoryTreePlugin()->actionList([
			'permissionContentType' => 'xfmg_category'
		]);
	}

	protected function categoryAddEdit(\XFMG\Entity\Category $category)
	{
		$categoryRepo = $this->getCategoryRepo();
		$mediaRepo = $this->getMediaRepo();

		$categoryTree = $categoryRepo->createCategoryTree();

		/** @var \XF\Repository\Node $nodeRepo */
		$nodeRepo = $this->repository('XF:Node');
		$nodeList = $nodeRepo->getFullNodeList();
		$nodeRepo->loadNodeTypeDataForNodes($nodeList);

		$mirrorNodeIds = [];
		if ($category->category_id)
		{
			foreach ($nodeList AS $node)
			{
				/** @var \XF\Entity\Node $node */
				if ($node->node_type_id == 'Forum'
					&& isset($node->Data->xfmg_media_mirror_category_id)
					&& $node->Data->xfmg_media_mirror_category_id == $category->category_id
				)
				{
					$mirrorNodeIds[] = $node->node_id;
				}
			}
		}

		$nodeTree = $nodeRepo->createNodeTree($nodeList);
		$nodeTree = $nodeTree->filter(null, function($id, $node, $depth, $children, $tree)
		{
			return ($children || $node->node_type_id == 'Forum');
		});

		$viewParams = [
			'category' => $category,
			'categoryTree' => $categoryTree,
			'categoryTypes' => $categoryRepo->getCategoryTypes(),
			'mediaTypes' => $mediaRepo->getMediaTypes(),
			'nodeTree' => $nodeTree,
			'mirrorNodeIds' => $mirrorNodeIds
		];
		return $this->view('XFMG:Category\Edit', 'xfmg_category_edit', $viewParams);
	}

	public function actionEdit(ParameterBag $params)
	{
		$category = $this->assertCategoryExists($params->category_id);
		return $this->categoryAddEdit($category);
	}

	public function actionAdd()
	{
		$parentCategoryId = $this->filter('parent_category_id', 'uint');

		$category = $this->em()->create('XFMG:Category');
		$category->parent_category_id = $parentCategoryId;

		return $this->categoryAddEdit($category);
	}

	protected function categorySaveProcess(\XFMG\Entity\Category $category)
	{
		$form = $this->formAction();

		$input = $this->filter([
			'title' => 'str',
			'description' => 'str',
			'parent_category_id' => 'uint',
			'display_order' => 'uint',
			'min_tags' => 'uint',
			'category_type' => 'str',
			'allowed_types' => 'array-str',
			'category_index_limit' => '?uint'
		]);

		$form->validate(function(FormAction $form) use($input)
		{
			if (($input['category_type'] == 'album' || $input['category_type'] == 'media') && !$input['allowed_types'])
			{
				$form->logError(\XF::phrase('xfmg_category_of_type_album_or_media_must_support_at_least_one_media_type'));
			}
		});

		$form->basicEntitySave($category, $input);

		$mirrorNodeIds = $this->filter('mirror_node_ids', 'array-uint');
		$form->apply(function() use ($category, $mirrorNodeIds)
		{
			$this->getCategoryRepo()->updateMirrorNodesForCategory($category, $mirrorNodeIds);
		});

		return $form;
	}

	public function actionSave(ParameterBag $params)
	{
		if ($params->category_id)
		{
			$category = $this->assertCategoryExists($params->category_id);
		}
		else
		{
			$category = $this->em()->create('XFMG:Category');
		}

		$this->categorySaveProcess($category)->run();

		return $this->redirect($this->buildLink('media-gallery/categories') . $this->buildLinkHash($category->category_id));
	}

	public function actionDelete(ParameterBag $params)
	{
		return $this->getCategoryTreePlugin()->actionDelete($params);
	}

	public function actionSort()
	{
		return $this->getCategoryTreePlugin()->actionSort();
	}

	/**
	 * @return \XFMG\ControllerPlugin\CategoryPermission
	 */
	protected function getCategoryPermissionPlugin()
	{
		/** @var \XFMG\ControllerPlugin\CategoryPermission $plugin */
		$plugin = $this->plugin('XFMG:CategoryPermission');
		$plugin->setFormatters('XFMG:Category\Permission%s', 'xfmg_category_permission_%s');
		$plugin->setRoutePrefix('media-gallery/categories/permissions');

		return $plugin;
	}

	public function actionPermissions(ParameterBag $params)
	{
		return $this->getCategoryPermissionPlugin()->actionList($params);
	}

	public function actionPermissionsEdit(ParameterBag $params)
	{
		return $this->getCategoryPermissionPlugin()->actionEdit($params);
	}

	public function actionPermissionsSave(ParameterBag $params)
	{
		return $this->getCategoryPermissionPlugin()->actionSave($params);
	}

	/**
	 * @param string $id
	 * @param array|string|null $with
	 * @param null|string $phraseKey
	 *
	 * @return \XFMG\Entity\Category
	 */
	protected function assertCategoryExists($id, $with = null, $phraseKey = null)
	{
		return $this->assertRecordExists('XFMG:Category', $id, $with, $phraseKey);
	}

	/**
	 * @return \XFMG\Repository\Category
	 */
	protected function getCategoryRepo()
	{
		return $this->repository('XFMG:Category');
	}

	/**
	 * @return \XFMG\Repository\Media
	 */
	protected function getMediaRepo()
	{
		return $this->repository('XFMG:Media');
	}
}