<?php

namespace XFMG\Service\Media;

use XF\Service\AbstractService;

class TranscodeEnqueuer extends AbstractService
{
	use \XF\Service\ValidateAndSavableTrait;

	/**
	 * @var \XFMG\Entity\MediaTemp
	 */
	protected $mediaTemp;

	/**
	 * @var \XFMG\Entity\Album|\XFMG\Entity\Category
	 */
	protected $container;

	/**
	 * @var \XFMG\Entity\TranscodeQueue
	 */
	protected $queueItem;
	protected $queueData = [];

	/**
	 * @var \XF\Entity\Attachment
	 */
	protected $attachment;

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
		$this->queueData['type'] = $mediaTemp->media_type;
		$this->queueItem = $this->em()->create('XFMG:TranscodeQueue');

		$this->setUser(\XF::visitor());
		$this->queueData['ip'] = $this->app->request()->getIp();
	}

	public function setUser(\XF\Entity\User $user)
	{
		$this->queueData['user_id'] = $user->user_id;
		$this->queueData['username'] = $user->username;
	}

	public function setContainer(\XF\Mvc\Entity\Entity $container)
	{
		if ($container instanceof \XFMG\Entity\Album)
		{
			$this->setAlbum($container);
		}
		else if ($container instanceof \XFMG\Entity\Category)
		{
			$this->setCategory($container);
		}
		else
		{
			throw new \InvalidArgumentException("Container entity must be an album or category.");
		}

		$this->container = $container;
		$this->tagChanger = $this->service('XF:Tag\Changer', 'xfmg_media', $container);
	}

	public function setAlbum(\XFMG\Entity\Album $album)
	{
		$this->queueData['album_id'] = $album->album_id;
	}

	public function setCategory(\XFMG\Entity\Category $category)
	{
		$this->queueData['category_id'] = $category->category_id;
	}

	public function setTitle($title, $description = '')
	{
		$this->queueData['title'] = $title;
		$this->queueData['description'] = $description;
	}

	public function setTags($tags)
	{
		$this->queueData['tags'] = $tags;

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
		$fieldSet = $this->em()->create('XFMG:MediaItem')->custom_fields;
		$fieldDefinition = $fieldSet->getDefinitionSet()
			->filterEditable($fieldSet, 'user')
			->filterOnly($this->container->field_cache)
			->filter('display_add_media');

		$customFieldsShown = array_keys($fieldDefinition->getFieldDefinitions());

		if ($customFieldsShown)
		{
			$this->queueData['custom_fields'] = $customFields;
		}
		else
		{
			$this->queueData['custom_fields'] = [];
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
			/** @var \XF\Entity\Attachment $attachment */
			$attachment = $this->em()->find('XF:Attachment', $attachmentId, 'Data');

			if (!$attachment || $attachment['temp_hash'] != $attachmentHash)
			{
				throw new \XF\PrintableException('There was a problem associating the current attachment.');
			}
		}

		$this->attachment = $attachment;
		$this->queueData['attachment_id'] = $attachment->attachment_id;
		$this->queueData['fileName'] = $attachment->Data->getAbstractedDataPath();
	}

	protected function finalSetup()
	{
		$this->queueItem->queue_data = $this->queueData;
		$this->queueItem->queue_state = 'pending';
		$this->queueItem->queue_date = time();
	}

	protected function _validate()
	{
		$this->finalSetup();

		if (!$this->container->canAddMedia($error))
		{
			return [$error];
		}

		$queueItem = $this->queueItem;
		$queueItem->preSave();
		$errors = $queueItem->getErrors();

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
		$queueItem = $this->queueItem;
		$queueItem->save();
		return $queueItem;
	}

	public function afterInsert()
	{
		$queueClass = $this->app->extendClass('XFMG\Ffmpeg\Queue');

		/** @var \XFMG\Ffmpeg\Queue $queue */
		$queue = new $queueClass($this->app);
		$queue->queue();
	}
}