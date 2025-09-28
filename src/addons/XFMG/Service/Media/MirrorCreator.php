<?php

namespace XFMG\Service\Media;

use XF\Service\AbstractService;

class MirrorCreator extends AbstractService
{
	use \XF\Service\ValidateAndSavableTrait;

	/**
	 * @var \XFMG\Entity\MediaItem
	 */
	protected $mediaItem;

	/**
	 * @var \XFMG\Entity\Category
	 */
	protected $container;

	/**
	 * @var \XF\Entity\Attachment
	 */
	protected $baseAttachment;

	/**
	 * @var Preparer
	 */
	protected $mediaItemPreparer;

	/**
	 * @var null|bool
	 */
	protected $isTrancodingRequired = null;

	public function __construct(\XF\App $app, \XF\Entity\Attachment $baseAttachment)
	{
		parent::__construct($app);

		if (!$baseAttachment->Data)
		{
			throw new \InvalidArgumentException("Base attachment does not have a data record");
		}

		$this->baseAttachment = $baseAttachment;
		$this->mediaItem = $this->setupMediaItem($baseAttachment);

		$this->mediaItemPreparer = $this->service('XFMG:Media\Preparer', $this->mediaItem);
		$this->mediaItemPreparer->logIp(false);
		// default to not logging IPs as this is generally a duplication of another action
	}

	protected function setupMediaItem(\XF\Entity\Attachment $baseAttachment): \XFMG\Entity\MediaItem
	{
		$attachmentData = $baseAttachment->Data;

		/** @var \XFMG\Repository\Media $mediaRepo */
		$mediaRepo = $this->repository('XFMG:Media');

		/** @var \XFMG\Entity\MediaItem $mediaItem */
		$mediaItem = $this->em()->create('XFMG:MediaItem');
		$mediaItem->media_hash = $mediaRepo->generateTempMediaHash();

		$user = $attachmentData->User;
		$mediaItem->user_id = $user ? $user->user_id : 0;
		$mediaItem->username = $user ? $user->username : '';
		$mediaItem->set('title', $attachmentData->filename, ['forceConstraint' => true]);
		$mediaItem->media_date = $baseAttachment->attach_date;

		$mediaType = $mediaRepo->getMediaTypeFromAttachment($baseAttachment);
		if ($mediaType)
		{
			$mediaItem->media_type = $mediaType;
		}

		return $mediaItem;
	}

	public function setCategory(\XFMG\Entity\Category $category)
	{
		$this->container = $category;

		$mediaItem = $this->mediaItem;
		$mediaItem->category_id = $category->category_id;

		$user = $mediaItem->User ?: $this->repository('XF:User')->getGuestUser();
		\XF::asVisitor($user, function() use ($mediaItem, $category)
		{
			$mediaItem->media_state = $category->getNewContentState();
		});
	}

	public function setExif(array $exif)
	{
		$this->mediaItem->exif_data = $exif;
	}

	public function setMediaState(string $state)
	{
		$this->mediaItem->media_state = $state;
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

	public function setTitle($title, $description = '')
	{
		$this->mediaItem->title = $title;

		if ($description)
		{
			$this->mediaItemPreparer->setDescription($description);
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

	public function isMirrorable(): bool
	{
		$mediaType = $this->mediaItem->media_type;
		if (!$mediaType)
		{
			return false;
		}

		if ($this->container->category_type != 'media')
		{
			return false;
		}

		if (!in_array($mediaType, $this->container->allowed_types))
		{
			return false;
		}

		if ($this->isTrancodingRequired())
		{
			return false;
		}

		return true;
	}

	public function isTrancodingRequired(): bool
	{
		if ($this->isTrancodingRequired === null)
		{
			$this->isTrancodingRequired = $this->checkTranscodingRequired();
		}

		return $this->isTrancodingRequired;
	}

	protected function checkTranscodingRequired(): bool
	{
		$attachmentData = $this->baseAttachment->Data;
		$mediaItem = $this->mediaItem;

		if ($mediaItem->media_type == 'video')
		{
			$tempPath = \XF\Util\File::copyAbstractedPathToTempFile($attachmentData->getAbstractedDataPath());

			$videoInfo = new \XFMG\VideoInfo\Preparer($tempPath);
			$result = $videoInfo->getInfo();

			return (!$result->isValid() || $result->requiresTranscoding());
		}
		else if ($mediaItem->media_type == 'audio')
		{
			$tempPath = \XF\Util\File::copyAbstractedPathToTempFile($attachmentData->getAbstractedDataPath());

			/** @var \XFMG\Service\Media\MP3Detector $MP3Detector */
			$MP3Detector = $this->service('XFMG:Media\MP3Detector', $tempPath);

			return ($MP3Detector->isValidMP3() ? false : true);
		}

		return false;
	}

	protected function finalSetup()
	{
	}

	protected function _validate()
	{
		$this->finalSetup();

		if (!$this->isMirrorable())
		{
			throw new \LogicException("Not a mirrorable attachment, check before saving");
		}

		$mediaItem = $this->mediaItem;
		$mediaItem->preSave();
		$errors = $mediaItem->getErrors();

		return $errors;
	}

	protected function _save()
	{
		$mediaItem = $this->mediaItem;

		$this->db()->beginTransaction();

		$mediaItem->save(true, false);

		$user = $mediaItem->User ?: $this->repository('XF:User')->getGuestUser();
		\XF::asVisitor($user, function() use ($mediaItem)
		{
			$visitor = \XF::visitor();

			/** @var \XFMG\Repository\Media $mediaRepo */
			$mediaRepo = $this->repository('XFMG:Media');
			$mediaRepo->markMediaItemViewedByVisitor($mediaItem);

			/** @var \XFMG\Repository\MediaWatch $watchRepo */
			$watchRepo = $this->repository('XFMG:MediaWatch');
			$watchRepo->autoWatchMediaItem($mediaItem, $visitor, true);
		});

		/** @var \XF\Entity\Attachment $newAttachment */
		$newAttachment = $this->em()->create('XF:Attachment');
		$newAttachment->content_type = 'xfmg_media';
		$newAttachment->content_id = $mediaItem->media_id;
		$newAttachment->data_id = $this->baseAttachment->data_id;
		$newAttachment->attach_date = $mediaItem->media_date;
		$newAttachment->save();

		$mediaItem->hydrateRelation('Attachment', $newAttachment);

		$mediaItem->rebuildThumbnail();
		$mediaItem->rebuildPoster();

		$this->mediaItemPreparer->afterInsert();

		$this->baseAttachment->Data->fastUpdate('xfmg_mirror_media_id', $mediaItem->media_id);
		$this->baseAttachment->fastUpdate('xfmg_is_mirror_handler', 1);

		$this->db()->commit();

		return $mediaItem;
	}

	public function sendNotifications()
	{
		/** @var \XFMG\Service\Media\Notifier $notifier */
		$notifier = $this->service('XFMG:Media\Notifier', $this->mediaItem);
		$notifier->setMentionedUserIds($this->mediaItemPreparer->getMentionedUserIds());
		$notifier->notifyAndEnqueue(1);
	}
}