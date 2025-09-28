<?php

namespace XFMG\XF\Entity;

use XF\Mvc\Entity\Structure;

class Attachment extends XFCP_Attachment
{
	public function getXfmgMirrorMedia()
	{
		return $this->Data->XfmgMirrorMedia ?? null;
	}

	public function getXfmgMediaType()
	{
		if (!$this->Data)
		{
			return null;
		}

		return $this->repository('XFMG:Media')->getMediaTypeFromAttachment($this) ?: null;
	}

	/**
	 * @return \XFMG\Entity\Category|null
	 */
	public function getXfmgMirrorContainer()
	{
		if ($this->content_type == 'post')
		{
			/** @var \XF\Entity\Post $post */
			$post = $this->Container;
			if (!$post)
			{
				return null;
			}

			if ($post->user_id != $this->Data->user_id)
			{
				// if the attachment wasn't created by the poster, then don't create media from it
				return null;
			}

			$forum = $post->Thread->Forum ?? null;
			if (!$forum)
			{
				return null;
			}

			$category = $forum->XfmgMediaMirrorCategory;
			if (!$category || !$this->validateXfmgMediaMirrorCategory($category))
			{
				return null;
			}

			return $category;
		}

		return null;
	}

	protected function validateXfmgMediaMirrorCategory(\XFMG\Entity\Category $category): bool
	{
		if ($category->category_type != 'media')
		{
			return false;
		}

		if (!in_array($this->xfmg_media_type, $category->allowed_types))
		{
			return false;
		}

		return true;
	}

	public static function getStructure(Structure $structure)
	{
		$structure = parent::getStructure($structure);

		$structure->columns['xfmg_is_mirror_handler'] = ['type' => self::BOOL, 'default' => false, 'changeLog' => false];

		$structure->getters['XfmgMirrorMedia'] = false;
		$structure->getters['xfmg_media_type'] = true;

		$structure->withAliases['embed'][] = 'Data.XfmgMirrorMedia';
		$structure->withAliases['embed'][] = 'Data.XfmgMirrorMedia.Category';
		$structure->withAliases['embed'][] = function()
		{
			return 'Data.XfmgMirrorMedia.Category.Permissions|' . \XF::visitor()->permission_combination_id;
		};

		return $structure;
	}
}