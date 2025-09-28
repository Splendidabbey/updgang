<?php

namespace XFMG\Import\Data;

use XF\Import\Data\AbstractEmulatedData;
use XF\Import\Data\Attachment;
use XF\Import\Data\HasDeletionLogTrait;

class MediaItem extends AbstractEmulatedData
{
	use HasDeletionLogTrait, HasWatchTrait;

	protected $loggedIp;

	protected $generateThumbnail = true;

	protected $thumbnailPath;
	protected $customThumbnailPath;
	protected $originalPath;

	/**
	 * @var MediaNote[]
	 */
	protected $mediaNotes = [];

	/**
	 * @var Attachment
	 */
	protected $attachment;

	public function getImportType()
	{
		return 'xfmg_media';
	}

	protected function getEntityShortName()
	{
		return 'XFMG:MediaItem';
	}

	public function setCustomFields(array $customFields)
	{
		foreach ($customFields AS &$fieldValue)
		{
			if (is_string($fieldValue))
			{
				$fieldValue = $this->convertToUtf8($fieldValue);
			}
		}

		$this->custom_fields = $customFields;
	}

	public function setLoggedIp($loggedIp)
	{
		$this->loggedIp = $loggedIp;
	}

	public function setGenerateThumbnail($generateThumb)
	{
		$this->generateThumbnail = $generateThumb;
	}

	public function setThumbnailPath($path)
	{
		$this->generateThumbnail = false;
		$this->thumbnailPath = $path;
	}

	public function setCustomThumbnailPath($path)
	{
		$this->customThumbnailPath = $path;
	}

	public function setOriginalPath($path)
	{
		$this->originalPath = $path;
	}

	public function addNote($oldId, MediaNote $note)
	{
		$this->mediaNotes[$oldId] = $note;
	}

	public function addAttachment(Attachment $attachment)
	{
		$this->attachment = $attachment;
	}

	protected function preSave($oldId)
	{
		$this->forceNotEmpty('username', $oldId);
		$this->forceNotEmpty('title', $oldId);

		if (!$this->media_hash)
		{
			$this->media_hash = md5(microtime(true) . \XF::generateRandomString(8, true));
		}
	}

	protected function postSave($oldId, $newId)
	{
		$this->logIp($this->loggedIp, $this->media_date);
		$this->insertStateRecord($this->media_state, $this->media_date);

		if ($this->custom_fields)
		{
			$this->insertCustomFieldValues('xf_mg_media_field_value', 'media_id', $newId, $this->custom_fields);
		}

		if ($this->mediaNotes)
		{
			foreach ($this->mediaNotes AS $oldNoteId => $note)
			{
				$note->media_id = $newId;
				$note->log(false);
				$note->checkExisting(false);
				$note->useTransaction(false);

				$note->save($oldNoteId);
			}
		}

		if ($this->attachment)
		{
			$attachment = $this->attachment;

			$attachment->content_id = $newId;
			$attachment->log(false);
			$attachment->checkExisting(false);
			$attachment->useTransaction(false);

			$attachment->save(false);
		}

		$this->insertWatchers($newId);

		/** @var \XFMG\Entity\MediaItem $mediaItem */
		$mediaItem = $this->em()->find('XFMG:MediaItem', $newId, [
			'Attachment', 'Attachment.Data'
		]);

		if (!$mediaItem)
		{
			return;
		}

		if ($this->generateThumbnail)
		{
			/** @var \XFMG\Repository\Media $mediaRepo */
			$mediaRepo = $this->repository('XFMG:Media');

			$success = false;

			switch ($mediaItem->media_type)
			{
				case 'image':
					if ($mediaItem->Attachment && $mediaItem->Attachment->Data)
					{
						$success = $mediaRepo->rebuildImageThumb($mediaItem);
					}
					break;

				case 'audio':
				case 'video':
					if ($mediaItem->Attachment && $mediaItem->Attachment->Data)
					{
						$success = $mediaRepo->rebuildFFmpegThumb($mediaItem);
					}
					break;

				case 'embed':
					$success = $mediaRepo->rebuildEmbedThumb($mediaItem);
					break;
			}

			if ($success)
			{
				$mediaItem->thumbnail_date = time();
			}
			else
			{
				$mediaItem->thumbnail_date = 0;
			}
		}
		else if ($this->thumbnailPath)
		{
			if (file_exists($this->thumbnailPath) && is_readable($this->thumbnailPath))
			{
				\XF\Util\File::copyFileToAbstractedPath(
					$this->thumbnailPath, $mediaItem->getAbstractedThumbnailPath()
				);
				$mediaItem->thumbnail_date = time();
			}
		}

		if ($this->customThumbnailPath)
		{
			if (file_exists($this->customThumbnailPath) && is_readable($this->customThumbnailPath))
			{
				\XF\Util\File::copyFileToAbstractedPath(
					$this->customThumbnailPath, $mediaItem->getAbstractedCustomThumbnailPath()
				);
				$mediaItem->custom_thumbnail_date = time();
			}
		}

		if ($this->originalPath)
		{
			if (file_exists($this->originalPath) && is_readable($this->originalPath))
			{
				\XF\Util\File::copyFileToAbstractedPath(
					$this->originalPath, $mediaItem->getOriginalAbstractedDataPath()
				);
			}
		}

		$mediaItem->saveIfChanged($null, false, false);
		$this->em()->detachEntity($mediaItem);
	}
}