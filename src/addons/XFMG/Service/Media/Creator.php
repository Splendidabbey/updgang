<?php

namespace XFMG\Service\Media;

use XF\Service\AbstractService;

class Creator extends AbstractService
{
	use \XF\Service\ValidateAndSavableTrait;

	/**
	 * @var \XFMG\Entity\MediaTemp
	 */
	protected $mediaTemp;

	/**
	 * @var \XFMG\Entity\MediaItem
	 */
	protected $mediaItem;

	/**
	 * @var \XFMG\Entity\Album|\XFMG\Entity\Category
	 */
	protected $container;

	/**
	 * @var \XF\Entity\Attachment
	 */
	protected $attachment;

	/**
	 * @var Preparer
	 */
	protected $mediaItemPreparer;

	/**
	 * @var \XF\Service\Tag\Changer
	 */
	protected $tagChanger;

	public function __construct(\XF\App $app, \XFMG\Entity\MediaTemp $mediaTemp)
	{
		parent::__construct($app);
		$this->setMediaTemp($mediaTemp);
	}

	protected function setMediaTemp(\XFMG\Entity\MediaTemp $mediaTemp)
	{
		$this->mediaTemp = $mediaTemp;
		$this->mediaItem = $mediaTemp->getNewMediaItem();
		$this->mediaItem->media_type = $mediaTemp->media_type;
		$this->mediaItemPreparer = $this->service('XFMG:Media\Preparer', $this->mediaItem);

		$this->setUser(\XF::visitor());
	}

	public function setUser(\XF\Entity\User $user)
	{
		$this->mediaItem->user_id = $user->user_id;
		$this->mediaItem->username = $user->username;
	}

	public function getMediaItem()
	{
		return $this->mediaItem;
	}

	public function getMediaItemPreparer()
	{
		return $this->mediaItemPreparer;
	}

	public function logIp($logIp)
	{
		$this->mediaItemPreparer->logIp($logIp);
	}

	public function setContainer(\XF\Mvc\Entity\Entity $container)
	{
		if ($container instanceof \XFMG\Entity\Album)
		{
			$this->setAlbum($container);
			if ($container->Category)
			{
				$this->setCategory($container->Category);
			}
		}
		else if ($container instanceof \XFMG\Entity\Category)
		{
			$this->setCategory($container);
		}
		else
		{
			throw new \InvalidArgumentException("Container entity must be an album or category.");
		}

		$this->mediaItem->media_state = $container->getNewContentState();

		$this->container = $container;
		$this->tagChanger = $this->service('XF:Tag\Changer', 'xfmg_media', $container);
	}

	public function setAlbum(\XFMG\Entity\Album $album)
	{
		$mediaItem = $this->mediaItem;
		$mediaItem->album_id = $album->album_id;
	}

	public function setCategory(\XFMG\Entity\Category $category)
	{
		$mediaItem = $this->mediaItem;
		$mediaItem->category_id = $category->category_id;
	}

	public function setTitle($title, $description = '')
	{
		$this->mediaItem->title = $title;

		if ($description)
		{
			$this->mediaItemPreparer->setDescription($description);
		}
	}

	public function setTags($tags)
	{
		if (!$tags)
		{
			return;
		}

		if ($this->tagChanger->canEdit())
		{
			$this->tagChanger->setEditableTags($tags);
		}
	}

	public function setCustomFields(array $customFields)
	{
		/** @var \XF\CustomField\Set $fieldSet */
		$fieldSet = $this->mediaItem->custom_fields;
		$fieldDefinition = $fieldSet->getDefinitionSet()
			->filterEditable($fieldSet, 'user')
			->filterOnly($this->container->field_cache)
			->filter('display_add_media');

		$customFieldsShown = array_keys($fieldDefinition->getFieldDefinitions());

		if ($customFieldsShown)
		{
			$fieldSet->bulkSet($customFields, $customFieldsShown);
		}
	}

	public function setAttachment($attachmentId, $attachmentHash = null)
	{
		if ($attachmentId instanceof \XF\Entity\Attachment)
		{
			$attachment = $attachmentId;
		}
		else if (!$attachmentId || !$attachmentHash)
		{
			return;
		}
		else
		{
			$attachment = $this->em()->find('XF:Attachment', $attachmentId, 'Data');

			if (!$attachment || $attachment['temp_hash'] != $attachmentHash)
			{
				throw new \XF\PrintableException('There was a problem associating the current attachment.');
			}
		}

		$this->attachment = $attachment;
	}

	public function setMediaSite($mediaEmbedUrl, $mediaTag)
	{
		if (!$mediaEmbedUrl || !$mediaTag)
		{
			return;
		}

		$this->mediaItem->media_embed_url = $mediaEmbedUrl;
		$this->mediaItem->media_tag = $mediaTag;
	}

	public function checkForSpam()
	{
		if ($this->mediaItem->media_state == 'visible' && \XF::visitor()->isSpamCheckRequired())
		{
			$this->mediaItemPreparer->checkForSpam();
		}
	}

	protected function finalSetup()
	{
		$mediaItem = $this->mediaItem;
		$mediaItem->media_date = time();
		$mediaItem->exif_data = $this->mediaTemp->exif_data;
	}

	protected function _validate()
	{
		$this->finalSetup();

		if (!$this->container->canAddMedia($error))
		{
			return [$error];
		}

		$mediaItem = $this->mediaItem;
		$mediaItem->preSave();
		$errors = $mediaItem->getErrors();

		if ($this->tagChanger->canEdit())
		{
			$tagErrors = $this->tagChanger->getErrors();
			if ($tagErrors)
			{
				$errors = array_merge($errors, $tagErrors);
			}
		}

		return $errors;
	}

	protected function _save()
	{
		$mediaItem = $this->mediaItem;

		$mediaItem->save();

		$this->mediaItemPreparer->afterInsert();

		if ($this->tagChanger->canEdit())
		{
			$this->tagChanger->setContentId($mediaItem->media_id, true)->save();
		}

		if ($this->attachment)
		{
			$attachment = $this->attachment;

			// If the Attachment relation was accessed earlier NULL will be cached now so 
			// hydrate the Attachment relation on the MediaItem entity to ensure it is available.
			$mediaItem->hydrateRelation('Attachment', $attachment);

			$this->db()->update('xf_attachment', [
				'content_id' => $mediaItem->media_id,
				'temp_hash' => '',
				'unassociated' => 0
			], 'attachment_id = ?', $attachment->attachment_id);

			$attachment->getHandler()->onAssociation($attachment, $mediaItem);
		}
		else if ($mediaItem->media_type == 'embed')
		{
			$tempMedia = $this->mediaTemp;

			if ($tempMedia->thumbnail_date)
			{
				$tempThumbPath = $tempMedia->getAbstractedTempThumbnailPath();
				$mediaThumbPath = $mediaItem->getAbstractedThumbnailPath();

				$tempFile = \XF\Util\File::copyAbstractedPathToTempFile($tempThumbPath);
				\XF\Util\File::copyFileToAbstractedPath($tempFile, $mediaThumbPath);

				$mediaItem->fastUpdate('thumbnail_date', $tempMedia->thumbnail_date);

				\XF\Util\File::deleteFromAbstractedPath($tempThumbPath);
			}

			$tempMedia->delete();
		}

		return $mediaItem;
	}

	public function sendNotifications()
	{
		/** @var \XFMG\Service\Media\Notifier $notifier */
		$notifier = $this->service('XFMG:Media\Notifier', $this->mediaItem);
		$notifier->setMentionedUserIds($this->mediaItemPreparer->getMentionedUserIds());
		$notifier->notifyAndEnqueue(3);
	}
}