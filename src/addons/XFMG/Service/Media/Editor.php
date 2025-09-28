<?php

namespace XFMG\Service\Media;

use XFMG\Entity\MediaItem;

class Editor extends \XF\Service\AbstractService
{
	use \XF\Service\ValidateAndSavableTrait;

	/**
	 * @var MediaItem
	 */
	protected $mediaItem;

	/**
	 * @var \XFMG\Service\Media\Preparer
	 */
	protected $mediaItemPreparer;

	protected $embedUrl;

	protected $alert = false;
	protected $alertReason = '';

	public function __construct(\XF\App $app, MediaItem $mediaItem)
	{
		parent::__construct($app);
		$this->setMediaItem($mediaItem);
	}

	public function setMediaItem(MediaItem $mediaItem)
	{
		$this->mediaItem = $mediaItem;
		$this->mediaItemPreparer = $this->service('XFMG:Media\Preparer', $this->mediaItem);
	}

	public function getMediaItem()
	{
		return $this->mediaItem;
	}

	public function getMediaItemPreparer()
	{
		return $this->mediaItemPreparer;
	}

	public function setTitle($title, $description = null)
	{
		$this->mediaItem->title = $title;
		if ($description !== null)
		{
			$this->setDescription($description);
		}
	}

	public function setDescription($description)
	{
		$this->mediaItem->description = $description;
	}

	public function setEmbedUrl($url)
	{
		/** @var \XF\Validator\Url $validator */
		$validator = $this->app->validator('Url');
		$url = $validator->coerceValue($url);

		$this->embedUrl = $url;
	}

	public function setCustomFields(array $customFields, $subsetUpdate = false)
	{
		$mediaItem = $this->mediaItem;

		$editMode = $mediaItem->getFieldEditMode();

		/** @var \XF\CustomField\Set $fieldSet */
		$fieldSet = $mediaItem->custom_fields;
		$fieldDefinition = $fieldSet->getDefinitionSet()
			->filterEditable($fieldSet, $editMode)
			->filterOnly($mediaItem->category_id ? $mediaItem->Category->field_cache : $mediaItem->Album->field_cache);

		$customFieldsShown = array_keys($fieldDefinition->getFieldDefinitions());

		if ($subsetUpdate)
		{
			// only updating the values passed through, so remove anything not present
			foreach ($customFieldsShown AS $k => $fieldName)
			{
				if (!isset($customFields[$fieldName]))
				{
					unset($customFieldsShown[$k]);
				}
			}
		}

		if ($customFieldsShown)
		{
			$fieldSet->bulkSet($customFields, $customFieldsShown, $editMode);
		}
	}

	public function setSendAlert($alert, $reason = null)
	{
		$this->alert = (bool)$alert;
		if ($reason !== null)
		{
			$this->alertReason = $reason;
		}
	}

	public function checkForSpam()
	{
		if ($this->mediaItem->media_state == 'visible' && \XF::visitor()->isSpamCheckRequired())
		{
			$this->mediaItemPreparer->checkForSpam();
		}
	}

	protected function finalSetup() {}

	protected function _validate()
	{
		$mediaItem = $this->mediaItem;

		$this->finalSetup();

		$errors = [];

		if ($this->embedUrl && $mediaItem->media_embed_url != $this->embedUrl && $mediaItem->media_type == 'embed')
		{
			/** @var \XF\Validator\Url $validator */
			$validator = $this->app->validator('Url');
			$url = $validator->coerceValue($this->embedUrl);

			if (!$validator->isValid($url) || !$this->app->http()->reader()->isRequestableUntrustedUrl($url))
			{
				$errors[] = \XF::phrase('xfmg_pasted_text_does_not_appear_to_be_valid_url');
				return $errors;
			}

			/** @var \XF\Repository\BbCodeMediaSite $bbCodeMediaSiteRepo */
			$bbCodeMediaSiteRepo = $this->repository('XF:BbCodeMediaSite');

			$sites = $bbCodeMediaSiteRepo->findActiveMediaSites()->fetch();
			$match = $bbCodeMediaSiteRepo->urlMatchesMediaSiteList($url, $sites);

			if (!$match)
			{
				$errors[] = \XF::phrase('specified_url_cannot_be_embedded_as_media');
				return $errors;
			}

			$mediaItem->media_embed_url = $url;
			$mediaItem->media_tag = '[MEDIA=' . $match['media_site_id'] . ']' . $match['media_id'] . '[/MEDIA]';
		}

		$mediaItem->preSave();
		$errors += $mediaItem->getErrors();

		return $errors;
	}

	protected function _save()
	{
		$mediaItem = $this->mediaItem;
		$visitor = \XF::visitor();

		$db = $this->db();
		$db->beginTransaction();

		$mediaItem->save(true, false);

		$this->mediaItemPreparer->afterUpdate();

		if ($mediaItem->media_state == 'visible' && $this->alert && $mediaItem->user_id != $visitor->user_id)
		{
			/** @var \XFMG\Repository\Media $mediaRepo */
			$mediaRepo = $this->repository('XFMG:Media');
			$mediaRepo->sendModeratorActionAlert($mediaItem, 'edit', $this->alertReason);
		}

		$db->commit();

		return $mediaItem;
	}
}