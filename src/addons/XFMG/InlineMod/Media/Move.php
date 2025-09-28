<?php

namespace XFMG\InlineMod\Media;

use XF\Http\Request;
use XF\InlineMod\AbstractAction;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Entity;
use XFMG\InlineMod\AlertSendableTrait;

class Move extends AbstractAction
{
	use AlertSendableTrait;

	/**
	 * @var \XFMG\Entity\Category
	 */
	protected $targetCategory;
	protected $targetCategoryId;

	/**
	 * @var \XFMG\Entity\Album
	 */
	protected $targetAlbum;

	public function getTitle()
	{
		return \XF::phrase('xfmg_move_media_items...');
	}

	protected function canApplyInternal(AbstractCollection $entities, array $options, &$error)
	{
		$result = parent::canApplyInternal($entities, $options, $error);

		if ($result && $options['form_submitted'])
		{
			if ($options['target_type'] == 'category')
			{
				if ($options['target_category_id'])
				{
					$category = $this->getTargetCategory($options['target_category_id']);
					if (!$category)
					{
						return false;
					}

					if (!$category->canView($error))
					{
						return false;
					}

					if ($options['check_all_same_target'])
					{
						$allSame = true;
						foreach ($entities AS $entity)
						{
							/** @var \XFMG\Entity\MediaItem $entity */
							if ($entity->category_id != $options['target_category_id'])
							{
								$allSame = false;
								break;
							}
						}

						if ($allSame)
						{
							$error = \XF::phraseDeferred('xfmg_all_selected_media_items_already_in_destination_category');
							return false;
						}
					}
				}
				else
				{
					return false;
				}
			}
			else
			{
				$album = $this->getTargetAlbum($options);
				if (!$album)
				{
					$error = $this->targetThreadError;
					return false;
				}

				if ($album->exists() && !$album->canView($error))
				{
					return false;
				}

				if ($album->exists() && $options['check_all_same_target'])
				{
					$allSame = true;
					foreach ($entities AS $entity)
					{
						/** @var \XFMG\Entity\MediaItem $entity */
						if ($entity->album_id != $album->album_id)
						{
							$allSame = false;
							break;
						}
					}

					if ($allSame)
					{
						$error = \XF::phraseDeferred('xfmg_all_selected_media_items_already_in_destination_album');
						return false;
					}
				}

				if (!$album->exists() && !$options['album_title'])
				{
					$error = \XF::phraseDeferred('xfmg_you_must_specify_title_to_create_new_album_for_these_media_items');
					return false;
				}
			}
		}

		return $result;
	}

	protected function canApplyToEntity(Entity $entity, array $options, &$error = null)
	{
		/** @var \XFMG\Entity\MediaItem $entity */
		return $entity->canMove($error);
	}

	protected function applyInternal(AbstractCollection $entities, array $options)
	{
		$targetCategory = null;
		$targetAlbum = null;

		if ($options['target_type'] == 'category')
		{
			$targetCategory = $this->getTargetCategory($options['target_category_id']);
		}
		else
		{
			$targetAlbum = $this->getTargetAlbum($options);
			if (!$targetAlbum->exists())
			{
				/** @var \XFMG\Service\Album\Creator $creator */
				$creator = $this->app()->service('XFMG:Album\Creator');
				$creator->setTitle($options['album_title'], $options['album_description']);
				$creator->setViewPrivacy('private');

				$targetAlbum = $creator->save();
				$this->targetAlbum = $targetAlbum;
			}

			if ($targetAlbum->category_id)
			{
				$targetCategory = $targetAlbum->Category;
			}
		}

		/** @var \XFMG\Service\Media\Mover $mover */
		$mover = $this->app()->service('XFMG:Media\Mover', $targetCategory, $targetAlbum);
		$mover->move($entities);

		if ($options['alert'])
		{
			$mover->setSendAlert($options['alert'], $options['alert_reason']);
		}

		if ($targetAlbum)
		{
			$this->returnUrl = $this->app()->router('public')->buildLink('media/albums', $targetAlbum);
		}
		else
		{
			$this->returnUrl = $this->app()->router('public')->buildLink('media/categories', $targetCategory);
		}
	}

	protected function applyToEntity(Entity $entity, array $options)
	{
		throw new \LogicException("applyToEntity should not be called on media move.");
	}

	public function getBaseOptions()
	{
		return [
			'form_submitted' => false,
			'target_type' => 'category',
			'target_category_id' => 0,
			'album_type' => 'create',
			'album_title' => '',
			'album_description' => '',
			'album_url' => '',
			'check_all_same_target' => true,
			'alert' => false,
			'alert_reason' => ''
		];
	}

	public function renderForm(AbstractCollection $entities, \XF\Mvc\Controller $controller)
	{
		/** @var \XFMG\Repository\Category $categoryRepo */
		$categoryRepo = $this->app()->repository('XFMG:Category');
		$categoryList = $categoryRepo->getViewableCategories();

		$categoryTree = $categoryRepo->createCategoryTree($categoryList);
		$categoryTree = $categoryTree->filter(null, function($id, \XFMG\Entity\Category $category, $depth, $children, \XF\Tree $tree)
		{
			// note: this doesn't check, by design, if the moderator has any permissions to add media to said container
			// including whether the container supports the media types.
			return ($children || $category->category_type == 'media');
		});

		$viewParams = [
			'mediaItems' => $entities,
			'total' => count($entities),
			'categoryTree' => $categoryTree,
			'first' => $entities->first(),
			'canSendAlert' => $this->canSendAlert($entities)
		];
		return $controller->view('XFMG:Public:InlineMod\Media\Move', 'xfmg_inline_mod_media_move', $viewParams);
	}

	public function getFormOptions(AbstractCollection $entities, Request $request)
	{
		return [
			'form_submitted' => true,
			'target_type' => $request->filter('target_type', 'str'),
			'target_category_id' => $request->filter('target_category_id', 'uint'),
			'album_type' => $request->filter('album_type', 'str'),
			'album_title' => $request->filter('album.title', 'str'),
			'album_description' => $request->filter('album.description','str'),
			'album_url' => $request->filter('album_url', 'str'),
			'notify_category_watchers' => $request->filter('notify_category_watchers', 'bool'),
			'notify_album_watchers' => $request->filter('notify_album_watchers', 'bool'),
			'alert' => $request->filter('author_alert', 'bool'),
			'alert_reason' => $request->filter('author_alert_reason', 'str')
		];
	}

	/**
	 * @param integer $categoryId
	 * 
	 * @return null|\XFMG\Entity\Category
	 */
	protected function getTargetCategory($categoryId)
	{
		$categoryId = intval($categoryId);

		if ($this->targetCategoryId && $this->targetCategoryId == $categoryId)
		{
			return $this->targetCategory;
		}
		if (!$categoryId)
		{
			return null;
		}

		/** @var \XFMG\Entity\Category $category */
		$category = $this->app()->em()->find('XFMG:Category', $categoryId);
		if (!$category || $category->category_type != 'media')
		{
			throw new \InvalidArgumentException("Invalid target category ($categoryId)");
		}

		$this->targetCategoryId = $categoryId;
		$this->targetCategory = $category;

		return $this->targetCategory;
	}

	protected $targetThreadError;

	/**
	 * @param array $options
	 *
	 * @return null|\XFMG\Entity\Album
	 */
	protected function getTargetAlbum(array $options)
	{
		if ($this->targetAlbum)
		{
			return $this->targetAlbum;
		}

		if ($options['album_type'] == 'existing')
		{
			/** @var \XFMG\Repository\Album $albumRepo */
			$albumRepo = $this->app()->repository('XFMG:Album');
			$album = $albumRepo->getAlbumFromUrl($options['album_url'], $error);
			if (!$album)
			{
				$this->targetThreadError = $error;
				return null;
			}
		}
		else
		{
			$album = $this->app()->em()->create('XFMG:Album');
		}

		$this->targetAlbum = $album;

		return $this->targetAlbum;
	}
}