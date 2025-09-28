<?php

namespace XFMG\XF\Entity;

use XF\Mvc\Entity\Structure;

class Post extends XFCP_Post
{
	public function getBbCodeRenderOptions($context, $type)
	{
		$options = parent::getBbCodeRenderOptions($context, $type);
		$options['galleryMedia'] =  $this->GalleryMedia;
		$options['galleryAlbums'] =  $this->GalleryAlbums;

		return $options;
	}

	public function getGalleryMedia()
	{
		return isset($this->_getterCache['GalleryMedia']) ? $this->_getterCache['GalleryMedia'] : null;
	}

	public function getGalleryAlbums()
	{
		return isset($this->_getterCache['GalleryAlbums']) ? $this->_getterCache['GalleryAlbums'] : null;
	}

	public function setGalleryMedia(array $galleryMedia)
	{
		$this->_getterCache['GalleryMedia'] = $galleryMedia;
	}

	public function setGalleryAlbums(array $galleryAlbums)
	{
		$this->_getterCache['GalleryAlbums'] = $galleryAlbums;
	}

	protected function postHidden($hardDelete = false)
	{
		parent::postHidden($hardDelete);

		if (!$hardDelete && $this->attach_count)
		{
			$this->app()->service('XFMG:Media\MirrorManager')->attachmentContainerHidden('post', [$this->post_id]);
		}
	}

	public static function getStructure(Structure $structure)
	{
		$structure = parent::getStructure($structure);

		$structure->getters['GalleryMedia'] = true;
		$structure->getters['GalleryAlbums'] = true;

		return $structure;
	}
}