<?php

namespace XFRM\Service\ResourceItem;

use XFRM\Entity\Category;

class Create extends \XF\Service\AbstractService
{
	use \XF\Service\ValidateAndSavableTrait;

	/**
	 * @var \XFRM\Entity\Category
	 */
	protected $category;

	/**
	 * @var \XFRM\Entity\ResourceItem
	 */
	protected $resource;

	/**
	 * @var \XFRM\Entity\ResourceUpdate
	 */
	protected $description;

	/**
	 * @var \XFRM\Entity\ResourceVersion
	 */
	protected $version;

	/**
	 * @var \XFRM\Service\ResourceUpdate\Preparer
	 */
	protected $descriptionPreparer;
	
	/**
	 * @var \XFRM\Service\ResourceVersion\Preparer
	 */
	protected $versionPreparer;

	/**
	 * @var \XF\Service\Tag\Changer
	 */
	protected $tagChanger;

	/**
	 * @var \XF\Service\Thread\Creator|null
	 */
	protected $threadCreator;

	protected $performValidations = true;

	public function __construct(\XF\App $app, Category $category)
	{
		parent::__construct($app);
		$this->category = $category;
		$this->setupDefaults();
	}

	protected function setupDefaults()
	{
		$resource = $this->category->getNewResource();

		$this->resource = $resource;
		$this->description = $resource->getNewUpdate();
		$this->version = $resource->getNewVersion();

		$this->descriptionPreparer = $this->service('XFRM:ResourceUpdate\Preparer', $this->description);
		$this->versionPreparer = $this->service('XFRM:ResourceVersion\Preparer', $this->version);

		$this->resource->addCascadedSave($this->description);
		$this->description->hydrateRelation('Resource', $this->resource);

		$this->resource->addCascadedSave($this->version);
		$this->version->hydrateRelation('Resource', $this->resource);

		$this->tagChanger = $this->service('XF:Tag\Changer', 'resource', $this->category);

		$visitor = \XF::visitor();
		$this->resource->user_id = $visitor->user_id;
		$this->resource->username = $visitor->username;

		$this->resource->resource_state = $this->category->getNewContentState();
		$this->description->message_state = 'visible';
	}

	public function getCategory()
	{
		return $this->category;
	}

	public function getResource()
	{
		return $this->resource;
	}

	public function getDescription()
	{
		return $this->description;
	}

	public function getVersion()
	{
		return $this->version;
	}

	public function setPerformValidations($perform)
	{
		$this->performValidations = (bool)$perform;
	}

	public function getPerformValidations()
	{
		return $this->performValidations;
	}

	public function setIsAutomated()
	{
		$this->logIp(false);
		$this->setPerformValidations(false);
	}

	public function setTitle($title)
	{
		$this->resource->title = $title;
		$this->description->title = $title;
	}

	public function setContent($title, $description, $format = true)
	{
		$this->setTitle($title);

		return $this->descriptionPreparer->setMessage($description, $format, $this->performValidations);
	}

	public function setPrefix($prefixId)
	{
		$this->resource->prefix_id = $prefixId;
	}

	public function setVersionString($version, $useDefault = false)
	{
		$this->versionPreparer->setVersionString($version, $useDefault);
	}

	public function setTags($tags)
	{
		if ($this->tagChanger->canEdit())
		{
			$this->tagChanger->setEditableTags($tags);
		}
	}

	public function setDescriptionAttachmentHash($hash)
	{
		$this->descriptionPreparer->setAttachmentHash($hash);
	}

	public function setCustomFields(array $customFields)
	{
		$resource = $this->resource;

		/** @var \XF\CustomField\Set $fieldSet */
		$fieldSet = $resource->custom_fields;
		$fieldDefinition = $fieldSet->getDefinitionSet()
			->filterEditable($fieldSet, 'user')
			->filterOnly($this->category->field_cache);

		$customFieldsShown = array_keys($fieldDefinition->getFieldDefinitions());

		if ($customFieldsShown)
		{
			$fieldSet->bulkSet($customFields, $customFieldsShown);
		}
	}

	public function logIp($logIp)
	{
		$this->descriptionPreparer->logIp($logIp);
	}

	public function setLocalDownload($versionAttachmentHash)
	{
		$this->resource->resource_type = 'download';

		$this->versionPreparer->setAttachmentHash($versionAttachmentHash);
	}

	public function setExternalDownload($downloadUrl)
	{
		$this->resource->resource_type = 'download';

		$this->versionPreparer->setDownloadUrl($downloadUrl);
	}

	public function setExternalPurchasable($price, $currency, $url)
	{
		$this->resource->bulkSet([
			'resource_type' => 'external_purchase',
			'price' => $price,
			'currency' => $currency,
			'external_purchase_url' => $url
		]);
	}

	public function setFileless()
	{
		$this->resource->resource_type = 'fileless';
	}

	public function checkForSpam()
	{
		if ($this->resource->resource_state == 'visible' && \XF::visitor()->isSpamCheckRequired())
		{
			$this->descriptionPreparer->checkForSpam();
		}
	}

	protected function finalSetup()
	{
	}

	protected function _validate()
	{
		$this->finalSetup();

		$resource = $this->resource;

		if (!$resource->user_id)
		{
			/** @var \XF\Validator\Username $validator */
			$validator = $this->app->validator('Username');
			$resource->username = $validator->coerceValue($resource->username);

			if ($this->performValidations && !$validator->isValid($resource->username, $error))
			{
				return [
					$validator->getPrintableErrorValue($error)
				];
			}
		}

		$resource->preSave();
		$errors = $resource->getErrors();

		if (!$resource->resource_type)
		{
			$errors['resource_type'] = \XF::phrase('xfrm_please_select_valid_resource_type');
		}
		else if (!$this->versionPreparer->validateFiles($versionError) && empty($errors['download_url']))
		{
			$errors[] = $versionError;
		}

		if ($this->performValidations)
		{
			if (!$resource->prefix_id
				&& $this->category->require_prefix
				&& $this->category->getUsablePrefixes()
			)
			{
				$errors[] = \XF::phraseDeferred('please_select_a_prefix');
			}

			if ($this->tagChanger->canEdit())
			{
				$tagErrors = $this->tagChanger->getErrors();
				if ($tagErrors)
				{
					$errors = array_merge($errors, $tagErrors);
				}
			}
		}

		return $errors;
	}

	protected function _save()
	{
		$category = $this->category;
		$resource = $this->resource;
		$update = $this->description;
		$version = $this->version;

		$db = $this->db();
		$db->beginTransaction();

		$resource->save(true, false);
		// update and version will also be saved now

		$resource->fastUpdate([
			'description_update_id' => $update->resource_update_id,
			'current_version_id' => $version->resource_version_id
		]);

		$this->descriptionPreparer->afterInsert();
		$this->versionPreparer->afterInsert();

		if ($this->tagChanger->canEdit())
		{
			$this->tagChanger
				->setContentId($resource->resource_id, true)
				->save($this->performValidations);
		}

		if ($category->thread_node_id && $category->ThreadForum)
		{
			$creator = $this->setupResourceThreadCreation($category->ThreadForum);
			if ($creator && $creator->validate())
			{
				$thread = $creator->save();
				$resource->fastUpdate('discussion_thread_id', $thread->thread_id);
				$this->threadCreator = $creator;

				$this->afterResourceThreadCreated($thread);
			}
		}

		$db->commit();

		return $resource;
	}

	protected function setupResourceThreadCreation(\XF\Entity\Forum $forum)
	{
		/** @var \XF\Service\Thread\Creator $creator */
		$creator = $this->service('XF:Thread\Creator', $forum);
		$creator->setIsAutomated();

		$creator->setContent($this->resource->getExpectedThreadTitle(), $this->getThreadMessage(), false);
		$creator->setPrefix($this->category->thread_prefix_id);

		$creator->setDiscussionTypeAndDataRaw('resource');

		$thread = $creator->getThread();
		$thread->discussion_state = $this->resource->resource_state;

		return $creator;
	}

	protected function getThreadMessage()
	{
		$resource = $this->resource;

		$snippet = $this->app->bbCode()->render(
			$this->app->stringFormatter()->wholeWordTrim($this->description->message, 500),
			'bbCodeClean',
			'post',
			null
		);

		$phrase = \XF::phrase('xfrm_resource_thread_create', [
			'title' => $resource->title_,
			'tag_line' => $resource->tag_line_,
			'username' => $resource->User ? $resource->User->username : $resource->username,
			'snippet' => $snippet,
			'resource_link' => $this->app->router('public')->buildLink('canonical:resources', $this->resource)
		]);

		return $phrase->render('raw');
	}

	protected function afterResourceThreadCreated(\XF\Entity\Thread $thread)
	{
		$this->repository('XF:Thread')->markThreadReadByVisitor($thread);
		$this->repository('XF:ThreadWatch')->autoWatchThread($thread, \XF::visitor(), true);
	}

	public function sendNotifications()
	{
		if ($this->resource->isVisible())
		{
			/** @var \XFRM\Service\ResourceUpdate\Notify $notifier */
			$notifier = $this->service('XFRM:ResourceUpdate\Notify', $this->description, 'resource');
			$notifier->setMentionedUserIds($this->descriptionPreparer->getMentionedUserIds());
			$notifier->notifyAndEnqueue(3);
		}

		if ($this->threadCreator)
		{
			$this->threadCreator->sendNotifications();
		}
	}
}