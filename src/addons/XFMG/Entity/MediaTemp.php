<?php

namespace XFMG\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int|null temp_media_id
 * @property string media_hash
 * @property int temp_media_date
 * @property string media_type
 * @property int user_id
 * @property string title
 * @property string description
 * @property int thumbnail_date
 * @property int poster_date
 * @property int|null attachment_id
 * @property bool requires_transcoding
 * @property array exif_data
 *
 * GETTERS
 * @property string|null temp_thumbnail_url
 */
class MediaTemp extends Entity
{
	/**
	 * @return string|null
	 */
	public function getTempThumbnailUrl()
	{
		if (!$this->thumbnail_date)
		{
			return null;
		}

		$tempId = $this->temp_media_id;

		$path = sprintf("xfmg/temp/%d-%s.jpg?{$this->thumbnail_date}",
			$tempId,
			$this->media_hash
		);
		return $this->app()->applyExternalDataUrl($path);
	}

	public function getAbstractedTempThumbnailPath()
	{
		$tempId = $this->temp_media_id;

		return sprintf('data://xfmg/temp/%d-%s.jpg',
			$tempId,
			$this->media_hash
		);
	}

	public function getAbstractedTempPosterPath()
	{
		$tempId = $this->temp_media_id;

		return sprintf('data://xfmg/temp/%d-%s-poster.jpg',
			$tempId,
			$this->media_hash
		);
	}

	/**
	 * @return \XFMG\Entity\MediaItem
	 */
	public function getNewMediaItem()
	{
		$mediaItem = $this->_em->create('XFMG:MediaItem');
		$mediaItem->media_hash = $this->media_hash;
		return $mediaItem;
	}

	protected function _preSave()
	{
		if ($this->isInsert() && !$this->media_hash)
		{
			$this->media_hash = $this->repository('XFMG:Media')->generateTempMediaHash();
		}
	}

	protected function _postDelete()
	{
		\XF\Util\File::deleteFromAbstractedPath(
			$this->getAbstractedTempThumbnailPath()
		);

		\XF\Util\File::deleteFromAbstractedPath(
			$this->getAbstractedTempPosterPath()
		);
	}

	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_mg_media_temp';
		$structure->shortName = 'XFMG:MediaTemp';
		$structure->primaryKey = 'temp_media_id';
		$structure->columns = [
			'temp_media_id' => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
			'media_hash' => ['type' => self::STR, 'maxLength' => 32, 'required' => true, 'unique' => true],
			'temp_media_date' => ['type' => self::UINT, 'default' => \XF::$time],
			'media_type' => ['type' => self::STR, 'required' => true,
				'allowedValues' => ['image', 'video', 'audio', 'embed']
			],
			'user_id' => ['type' => self::UINT, 'required' => true],
			'title' => ['type' => self::STR, 'default' => ''],
			'description' => ['type' => self::STR, 'default' => ''],
			'thumbnail_date' => ['type' => self::UINT, 'default' => 0],
			'poster_date' => ['type' => self::UINT, 'default' => 0],
			'attachment_id' => ['type' => self::UINT, 'nullable' => true, 'unique' => true],
			'requires_transcoding' => ['type' => self::BOOL, 'default' => false],
			'exif_data' => ['type' => self::JSON_ARRAY, 'default' => [], 'forced' => true]
		];
		$structure->getters = [
			'temp_thumbnail_url' => true
		];
		$structure->relations = [];
		$structure->options = [];

		return $structure;
	}
}