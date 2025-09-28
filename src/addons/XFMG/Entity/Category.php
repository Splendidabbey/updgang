<?php

namespace XFMG\Entity;

use XF\Entity\AbstractCategoryTree;
use XF\Mvc\Entity\Structure;
use XF\Util\Arr;

/**
 * COLUMNS
 * @property int|null category_id
 * @property string title
 * @property string description
 * @property string category_type
 * @property int media_count
 * @property int album_count
 * @property int comment_count
 * @property array field_cache
 * @property array allowed_types
 * @property int min_tags
 * @property int|null category_index_limit
 * @property int parent_category_id
 * @property int display_order
 * @property int lft
 * @property int rgt
 * @property int depth
 * @property array breadcrumb_data
 *
 * RELATIONS
 * @property \XF\Mvc\Entity\AbstractCollection|\XFMG\Entity\CategoryWatch[] Watch
 * @property \XF\Mvc\Entity\AbstractCollection|\XF\Entity\PermissionCacheContent[] Permissions
 */
class Category extends AbstractCategoryTree implements \XF\Entity\LinkableInterface
{
	public function canView(&$error = null)
	{
		return $this->hasPermission('view');
	}

	public function canAddMedia(&$error = null)
	{
		if ($this->category_type == 'album')
		{
			if (!$this->canCreateAlbum())
			{
				return false;
			}
		}
		return (\XF::visitor()->user_id && $this->hasPermission('add') && ($this->canUploadMedia($error) || $this->canEmbedMedia($error)));
	}

	public function canCreateAlbum()
	{
		return (\XF::visitor()->user_id && $this->hasPermission('createAlbum'));
	}

	public function canUploadMedia(&$error = null)
	{
		if ($this->category_type == 'container')
		{
			return false;
		}
		else if ($this->category_type == 'album')
		{
			$album = $this->_em->create('XFMG:Album');
			return $album->canUploadMedia($error);
		}
		else
		{
			foreach ($this->allowed_types AS $type)
			{
				if ($type == 'image' || $type == 'video' || $type == 'audio')
				{
					return true;
				}
			}

			return false;
		}
	}

	public function canEmbedMedia(&$error = null)
	{
		if ($this->category_type == 'container')
		{
			return false;
		}
		else if ($this->category_type == 'album')
		{
			$album = $this->_em->create('XFMG:Album');
			return $album->canEmbedMedia($error);
		}
		else
		{
			return in_array('embed', $this->allowed_types);
		}
	}

	public function canEditTags(MediaItem $mediaItem = null, &$error = null)
	{
		if (!$this->app()->options()->enableTagging)
		{
			return false;
		}

		$visitor = \XF::visitor();

		// if no media item, assume the media will be owned by this person
		if (!$mediaItem || $mediaItem->user_id == $visitor->user_id)
		{
			if ($this->hasPermission('tagOwnMedia'))
			{
				return true;
			}
		}

		if (
			$this->hasPermission('tagAnyMedia')
			|| $this->hasPermission('manageAnyTag')
		)
		{
			return true;
		}

		return false;
	}

	public function canUseInlineModeration(&$error = null)
	{
		return $this->hasPermission('inlineMod');
	}

	public function canWatch(&$error = null)
	{
		return (\XF::visitor()->user_id ? true : false);
	}

	public function getNewContentState()
	{
		$visitor = \XF::visitor();

		if ($visitor->user_id && $this->hasPermission('approveUnapprove'))
		{
			return 'visible';
		}

		if (!$this->hasPermission('addWithoutApproval'))
		{
			return 'moderated';
		}

		return 'visible';
	}

	public function isEmpty()
	{
		$db = $this->db();
		$albumCount = $db->fetchOne('SELECT COUNT(*) FROM xf_mg_album WHERE category_id = ?', $this->category_id);
		$mediaCount = $db->fetchOne('SELECT COUNT(*) FROM xf_mg_media_item WHERE category_id = ?', $this->category_id);

		return ($this->isInsert() || ($albumCount === 0 && $mediaCount === 0));
	}

	public function hasPermission($permission)
	{
		/** @var \XFMG\XF\Entity\User $visitor */
		$visitor = \XF::visitor();

		if ($permission == 'maxAllowedStorage') // special case -- global and not relevant on a per content basis
		{
			return $visitor->hasPermission('xfmgStorage', $permission);
		}
		return $visitor->hasGalleryCategoryPermission($this->category_id, $permission);
	}

	public function getCategoryListExtras()
	{
		return [
			'media_count' => $this->media_count,
			'album_count' => $this->album_count,
			'comment_count' => $this->comment_count
		];
	}

	public function getAttachmentConstraints()
	{
		$options = $this->app()->options();

		$extensions = [];
		if (in_array('image', $this->allowed_types))
		{
			$extensions = array_merge($extensions, Arr::stringToArray($options->xfmgImageExtensions));
		}
		if (in_array('video', $this->allowed_types))
		{
			$extensions = array_merge($extensions, Arr::stringToArray($options->xfmgVideoExtensions));
		}
		if (in_array('audio', $this->allowed_types))
		{
			$extensions = array_merge($extensions, Arr::stringToArray($options->xfmgAudioExtensions));
		}

		$total = $this->hasPermission('maxAllowedStorage');
		$size = $this->hasPermission('maxFileSize');
		$width = $this->hasPermission('maxImageWidth');
		$height = $this->hasPermission('maxImageHeight');

		// Treat both 0 and -1 as unlimited
		return [
			'extensions' => $extensions,
			'total' => ($total <= 0) ? PHP_INT_MAX : $total * 1024 * 1024,
			'size' => ($size <= 0) ? PHP_INT_MAX : $size * 1024 * 1024,
			'width' => ($width <= 0) ? PHP_INT_MAX : $width,
			'height' => ($height <= 0) ? PHP_INT_MAX : $height,
			'count' => 100
		];
	}

	public function getBreadcrumbs($includeSelf = true, $linkType = 'public')
	{
		if ($linkType == 'public')
		{
			$link = 'media/categories';
		}
		else
		{
			$link = 'media-gallery/categories';
		}
		return $this->_getBreadcrumbs($includeSelf, $linkType, $link);
	}

	public function rebuildCounters()
	{
		$this->rebuildMediaCount();
		$this->rebuildAlbumCount();
		$this->rebuildCommentCount();

		return true;
	}

	public function rebuildMediaCount()
	{
		$this->media_count = $this->db()->fetchOne("
			SELECT COUNT(*)
			FROM xf_mg_media_item AS mi
			LEFT JOIN xf_mg_album AS a ON
				(mi.album_id = a.album_id)
			WHERE mi.category_id = ?
				AND mi.media_state = 'visible'
				AND IF(mi.album_id > 0, a.album_state = 'visible', 1=1)
		", $this->category_id);

		return $this->media_count;
	}

	public function rebuildAlbumCount()
	{
		$this->album_count = $this->db()->fetchOne("
			SELECT COUNT(*)
			FROM xf_mg_album
			WHERE category_id = ?
				AND album_state = 'visible'
		", $this->category_id);

		return $this->album_count;
	}

	public function rebuildCommentCount()
	{
		$mediaCommentCount = $this->db()->fetchOne("
			SELECT SUM(comment_count)
			FROM xf_mg_media_item
			WHERE category_id = ?
				AND media_state = 'visible'
		", $this->category_id);

		$albumCommentCount = $this->db()->fetchOne("
			SELECT SUM(comment_count)
			FROM xf_mg_album
			WHERE category_id = ?
				AND album_state = 'visible'
		", $this->category_id);

		$this->comment_count = intval($mediaCommentCount) + intval($albumCommentCount);

		return $this->comment_count;
	}

	public function mediaItemAdded(MediaItem $mediaItem)
	{
		$this->media_count++;
		$this->comment_count += $mediaItem->comment_count;
	}

	public function mediaItemRemoved(MediaItem $mediaItem)
	{
		$this->media_count--;
		$this->comment_count -= $mediaItem->comment_count;
	}

	public function albumAdded(Album $album)
	{
		$this->album_count++;
		$this->media_count += $album->media_count;

		$db = $this->db();
		$count = $db->fetchOne('
			SELECT SUM(comment_count)
			FROM xf_mg_media_item
			WHERE album_id = ?
			AND media_state = \'visible\'
		', $album->album_id);
		$this->comment_count += ($count + $album->comment_count);
	}

	public function albumRemoved(Album $album)
	{
		$this->album_count--;
		$this->media_count -= $album->media_count;

		$db = $this->db();
		$commentCount = $db->fetchOne('
			SELECT SUM(comment_count)
			FROM xf_mg_media_item
			WHERE album_id = ?
			AND media_state = \'visible\'
		', $album->album_id);
		$this->comment_count -= ($commentCount + $album->comment_count);
	}

	public function commentAdded(Comment $comment)
	{
		$this->comment_count++;
	}

	public function commentRemoved(Comment $comment)
	{
		$this->comment_count--;
	}

	protected function _preSave()
	{
		if ($this->isUpdate() && !$this->isEmpty() && $this->isChanged('category_type'))
		{
			$this->error(\XF::phrase('xfmg_category_type_change_explain'));
		}

		if ($this->category_type == 'container')
		{
			$this->allowed_types = [];
		}
	}

	protected function _postDelete()
	{
		$db = $this->db();

		$db->delete('xf_mg_category_field', 'category_id = ?', $this->category_id);
		$db->delete('xf_mg_category_watch', 'category_id = ?', $this->category_id);

		if ($this->getOption('delete_contents'))
		{
			$this->app()->jobManager()->enqueueUnique('xfmgCategoryDelete' . $this->category_id, 'XFMG:CategoryDelete', [
				'category_id' => $this->category_id
			]);
		}
	}

	public function getContentUrl(bool $canonical = false, array $extraParams = [], $hash = null)
	{
		$route = ($canonical ? 'canonical:' : '') . 'media/categories';
		return $this->app()->router('public')->buildLink($route, $this, $extraParams, $hash);
	}

	public function getContentPublicRoute()
	{
		return 'media/categories';
	}

	public function getContentTitle(string $context = '')
	{
		return \XF::phrase('xfmg_media_category_x', [
			'title' => $this->title
		]);
	}

	/**
	 * @param \XF\Api\Result\EntityResult $result
	 * @param int $verbosity
	 * @param array $options
	 */
	protected function setupApiResultData(
		\XF\Api\Result\EntityResult $result, $verbosity = self::VERBOSITY_NORMAL, array $options = []
	)
	{
		if ($verbosity > self::VERBOSITY_NORMAL)
		{
			$fields = [];
			if ($this->field_cache)
			{
				$fieldEntities = $this->repository('XFMG:MediaField')->findFieldsForList()
					->whereIds($this->field_cache)
					->fetch();
				$fields = $fieldEntities->toApiResults();
			}

			$result->custom_fields = $fields;
		}

		$result->can_add = $this->canAddMedia();

		$result->view_url = $this->getContentUrl(true);
	}

	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_mg_category';
		$structure->shortName = 'XFMG:Category';
		$structure->primaryKey = 'category_id';
		$structure->contentType = 'xfmg_category';
		$structure->columns = [
			'category_id' => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
			'title' => ['type' => self::STR, 'maxLength' => 100,
				'required' => 'please_enter_valid_title', 'api' => true
			],
			'description' => ['type' => self::STR, 'default' => '', 'api' => true],
			'category_type' => ['type' => self::STR, 'default' => 'media',
				'allowedValues' => array_keys(\XF::repository('XFMG:Category')->getCategoryTypes()),
				'api' => true
			],
			'media_count' => ['type' => self::UINT, 'forced' => true, 'default' => 0, 'api' => true],
			'album_count' => ['type' => self::UINT, 'forced' => true, 'default' => 0, 'api' => true],
			'comment_count' => ['type' => self::UINT, 'forced' => true, 'default' => 0, 'api' => true],
			'field_cache' => ['type' => self::JSON_ARRAY, 'default' => []],
			'allowed_types' => ['type' => self::JSON_ARRAY,
				'default' => array_keys(\XF::repository('XFMG:Media')->getMediaTypes()),
				'api' => true
			],
			'min_tags' => ['type' => self::UINT, 'forced' => true, 'default' => 0, 'max' => 100, 'api' => true],
			'category_index_limit' => ['type' => self::UINT, 'api' => true, 'nullable' => true]
		];
		$structure->getters = [];
		$structure->relations = [
			'Watch' => [
				'entity' => 'XFMG:CategoryWatch',
				'type' => self::TO_MANY,
				'conditions' => 'category_id',
				'key' => 'user_id'
			]
		];
		$structure->options = [
			'delete_contents' => true
		];
		$structure->withAliases = [
			'api' => []
		];

		static::addCategoryTreeStructureElements($structure, [
			'breadcrumb_json' => true
		]);

		return $structure;
	}
}