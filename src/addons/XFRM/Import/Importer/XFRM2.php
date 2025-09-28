<?php

namespace XFRM\Import\Importer;

use XF\Import\Importer\XenForoSourceTrait;
use XF\Import\StepState;

class XFRM2 extends AbstractRMImporter
{
	use XenForoSourceTrait;

	public static function getListInfo()
	{
		return [
			'target' => 'XenForo Resource Manager',
			'source' => 'XenForo Resource Manager 2.0',
		];
	}

	protected function requiresForumImportLog()
	{
		return true;
	}

	protected function validateVersion(\XF\Db\AbstractAdapter $db, &$error)
	{
		$versionId = $db->fetchOne("SELECT version_id FROM xf_addon WHERE addon_id = 'XFRM'");
		if (!$versionId || intval($versionId) < 2000031 || intval($versionId) >= 2010031)
		{
			$error = \XF::phrase('xfrm_you_may_only_import_from_xenforo_resource_manager_x', ['version' => '2.0']);
			return false;
		}

		return true;
	}

	protected function getStepConfigDefault()
	{
		return [];
	}

	public function renderStepConfigOptions(array $vars)
	{
		return '';
	}

	public function validateStepConfig(array $steps, array &$stepConfig, array &$errors)
	{
		return true;
	}

	protected function doInitializeSource()
	{
		$this->sourceDb = new \XF\Db\Mysqli\Adapter(
			$this->baseConfig['db'],
			$this->app->config('fullUnicode')
		);

		$this->forumLog = new \XF\Import\Log(
			$this->app->db(), $this->baseConfig['forum_import_log']
		);
	}

	public function getSteps()
	{
		return [
			'categories' => [
				'title' => \XF::phrase('categories')
			],
			'categoryPermissions' => [
				'title' => \XF::phrase('xfrm_category_permissions'),
				'depends' => ['categories']
			],
			'resourcePrefixes' => [
				'title' => \XF::phrase('xfrm_resource_prefixes'),
				'depends' => ['categories']
			],
			'resourceFields' => [
				'title' => \XF::phrase('xfrm_resource_fields'),
				'depends' => ['categories']
			],
			'resources' => [
				'title' => \XF::phrase('xfrm_resources'),
				'depends' => ['categories'],
				'force' => ['resourceVersions', 'resourceUpdates']
			],
			'ratings' => [
				'title' => \XF::phrase('xfrm_ratings'),
				'depends' => ['resources']
			],
			'likes' => [
				'title' => \XF::phrase('likes'),
				'depends' => ['resources']
			],
			'tags' => [
				'title' => \XF::phrase('tags'),
				'depends' => ['resources']
			],
		];
	}

	// ############################## STEP: CATEGORIES #########################

	public function stepCategories(StepState $state)
	{
		$categories = $this->sourceDb->fetchAllKeyed("
			SELECT *
			FROM xf_rm_category
			ORDER BY resource_category_id
		", 'resource_category_id');

		$categoryTreeMap = [];
		foreach ($categories AS $categoryId => $category)
		{
			$categoryTreeMap[$category['parent_category_id']][] = $categoryId;
		}

		$state->imported = $this->importCategoryTree($categories, $categoryTreeMap);

		return $state->complete();
	}

	protected function importCategoryTree(array $categories, array $tree, $oldParentId = 0, $newParentId = 0)
	{
		if (!isset($tree[$oldParentId]))
		{
			return 0;
		}

		$total = 0;

		foreach ($tree[$oldParentId] AS $oldCategoryId)
		{
			$category = $categories[$oldCategoryId];

			/** @var \XFRM\Import\Data\Category $importCategory */
			$importCategory = $this->newHandler('XFRM:Category');

			$watchers = $this->sourceDb->fetchAllKeyed("
				SELECT *
				FROM xf_rm_category_watch
				WHERE resource_category_id = ?
			", 'user_id', $oldCategoryId);
			if ($watchers)
			{
				$watcherUserIdMap = $this->lookup('user', array_keys($watchers));
				foreach ($watchers AS $watcherUserId => $params)
				{
					if (!empty($watcherUserIdMap[$watcherUserId]))
					{
						$importCategory->addWatcher($watcherUserIdMap[$watcherUserId], $params);
					}
				}
			}

			$importCategory->bulkSet($this->mapKeys($category, [
				'title',
				'description',
				'display_order',
				'require_prefix',
				'allow_local',
				'allow_external',
				'allow_commercial_external',
				'allow_fileless',
				'always_moderate_create',
				'always_moderate_update',
				'min_tags',
				'enable_versioning'
			]));
			if (isset($category['enable_support_url'])) // present in RM 2.1
			{
				$importCategory->enable_support_url = $category['enable_support_url'];
			}
			$importCategory->parent_category_id = $newParentId;
			$importCategory->thread_node_id = $this->lookupId('node', $category['thread_node_id']);
			$importCategory->thread_prefix_id = $this->lookupId('thread_prefix', $category['thread_prefix_id']);

			$newCategoryId = $importCategory->save($oldCategoryId);
			if ($newCategoryId)
			{
				$total++;
				$total += $this->importCategoryTree($categories, $tree, $oldCategoryId, $newCategoryId);
			}
		}

		return $total;
	}

	// ############################## STEP: CATEGORY PERMISSIONS #########################

	public function stepCategoryPermissions(StepState $state)
	{
		$this->typeMap('user_group');
		$this->typeMap('resource_category');

		$entries = $this->sourceDb->fetchAll("
			SELECT *
			FROM xf_permission_entry_content
			WHERE content_type = 'resource_category'
		");

		$mapUserIds = [];
		foreach ($entries AS $entry)
		{
			if ($entry['user_id'])
			{
				$mapUserIds[] = $entry['user_id'];
			}
		}

		$mappedUserIds = $this->lookup('user', $mapUserIds);

		$groupedEntries = [];
		foreach ($entries AS $entry)
		{
			$newCategoryId = $this->lookupId('resource_category', $entry['content_id']);
			if (!$newCategoryId)
			{
				continue;
			}

			if ($entry['user_id'])
			{
				$type = 'user';
				$newInsertId = $mappedUserIds[$entry['user_id']];
				if (!$newInsertId)
				{
					continue;
				}
			}
			else if ($entry['user_group_id'])
			{
				$type = 'group';
				$newInsertId = $this->lookupId('user_group', $entry['user_group_id']);
				if (!$newInsertId)
				{
					continue;
				}
			}
			else
			{
				$type = 'global';
				$newInsertId = 0;
			}

			if ($entry['permission_value'] == 'use_int')
			{
				$permValue = $entry['permission_value_int'];
			}
			else
			{
				$permValue = $entry['permission_value'];
			}

			$groupedEntries[$newCategoryId][$type][$newInsertId][$entry['permission_group_id']][$entry['permission_id']] = $permValue;
		}

		/** @var \XF\Import\DataHelper\Permission $permHelper */
		$permHelper = $this->dataManager->helper('XF:Permission');
		foreach ($groupedEntries AS $categoryId => $groupedTypeEntries)
		{
			foreach ($groupedTypeEntries AS $type => $typeEntries)
			{
				foreach ($typeEntries AS $typeId => $permsGrouped)
				{
					if ($type == 'user')
					{
						$permHelper->insertContentUserPermissions('resource_category', $categoryId, $typeId, $permsGrouped);
					}
					else if ($type == 'group')
					{
						$permHelper->insertContentUserGroupPermissions('resource_category', $categoryId, $typeId, $permsGrouped);
					}
					else
					{
						$permHelper->insertContentGlobalPermissions('resource_category', $categoryId, $permsGrouped);
					}

					$state->imported++;
				}
			}
		}

		return $state->complete();
	}

	// ########################### STEP: RESOURCE PREFIXES ###############################

	public function stepResourcePrefixes(StepState $state)
	{
		$this->typeMap('resource_category');
		$this->typeMap('user_group');

		$prefixGroups = $this->sourceDb->fetchAllKeyed("
			SELECT rpg.*,
				p.phrase_text
			FROM xf_rm_resource_prefix_group AS rpg
			INNER JOIN xf_phrase AS p ON (p.language_id = 0 AND p.title = CONCAT('resource_prefix_group.', rpg.prefix_group_id))
		", 'prefix_group_id');
		$mappedGroupIds = [];

		foreach ($prefixGroups AS $oldGroupId => $group)
		{
			/** @var \XFRM\Import\Data\ResourcePrefixGroup $importGroup */
			$importGroup = $this->newHandler('XFRM:ResourcePrefixGroup');
			$importGroup->display_order = $group['display_order'];
			$importGroup->setTitle($group['phrase_text']);

			$newGroupId = $importGroup->save($oldGroupId);
			if ($newGroupId)
			{
				$mappedGroupIds[$oldGroupId] = $newGroupId;
			}
		}

		$prefixes = $this->sourceDb->fetchAllKeyed("
			SELECT rp.*,
				ptitle.phrase_text AS title,
				pdesc.phrase_text AS desc_text,
				phelp.phrase_text AS help_text
			FROM xf_rm_resource_prefix AS rp
			INNER JOIN xf_phrase AS ptitle ON (ptitle.language_id = 0 AND ptitle.title = CONCAT('resource_prefix.', rp.prefix_id))
			LEFT JOIN xf_phrase AS pdesc ON (pdesc.language_id = 0 AND pdesc.title = CONCAT('resource_prefix_desc.', rp.prefix_id))
			LEFT JOIN xf_phrase AS phelp ON (phelp.language_id = 0 AND phelp.title = CONCAT('resource_prefix_help.', rp.prefix_id))
		", 'prefix_id');

		$prefixCategoryMap = [];
		$categoryPrefixes = $this->sourceDb->fetchAll("
			SELECT *
			FROM xf_rm_category_prefix
		");
		foreach ($categoryPrefixes AS $categoryPrefix)
		{
			$newCategoryId = $this->lookupId('resource_category', $categoryPrefix['resource_category_id']);
			if ($newCategoryId)
			{
				$prefixCategoryMap[$categoryPrefix['prefix_id']][] = $newCategoryId;
			}
		}

		foreach ($prefixes AS $oldPrefixId => $prefix)
		{
			/** @var \XFRM\Import\Data\ResourcePrefix $importPrefix */
			$importPrefix = $this->newHandler('XFRM:ResourcePrefix');
			$importPrefix->bulkSet($this->mapKeys($prefix, [
				'display_order',
				'css_class'
			]));
			$importPrefix->prefix_group_id = isset($mappedGroupIds[$prefix['prefix_group_id']])
				? $mappedGroupIds[$prefix['prefix_group_id']]
				: 0;
			if ($prefix['allowed_user_group_ids'] == '-1')
			{
				$importPrefix->allowed_user_group_ids = [-1];
			}
			else
			{
				$importPrefix->allowed_user_group_ids = $this->mapUserGroupList($prefix['allowed_user_group_ids']);
			}

			$importPrefix->setTitle($prefix['title']);
			$importPrefix->setDescription($prefix['desc_text'] ?? '');
			$importPrefix->setHelp($prefix['help_text'] ?? '');

			if (!empty($prefixCategoryMap[$oldPrefixId]))
			{
				$importPrefix->setCategories($prefixCategoryMap[$oldPrefixId]);
			}

			if ($importPrefix->save($oldPrefixId))
			{
				$state->imported++;
			}
		}

		return $state->complete();
	}

	// ############################## STEP: RESOURCE FIELDS #########################

	public function stepResourceFields(StepState $state)
	{
		$this->typeMap('resource_category');
		$this->typeMap('user_group');

		$fields = $this->sourceDb->fetchAllKeyed("
			SELECT field.*,
				ptitle.phrase_text AS title,
				pdesc.phrase_text AS description
			FROM xf_rm_resource_field AS field
			INNER JOIN xf_phrase AS ptitle ON
				(ptitle.language_id = 0 AND ptitle.title = CONCAT('xfrm_resource_field_title.', field.field_id))
			INNER JOIN xf_phrase AS pdesc ON
				(pdesc.language_id = 0 AND pdesc.title = CONCAT('xfrm_resource_field_desc.', field.field_id))
		", 'field_id');

		$existingFields = $this->db()->fetchPairs("SELECT field_id, field_id FROM xf_rm_resource_field");

		$fieldCategoryMap = [];
		$categoryFields = $this->sourceDb->fetchAll("
			SELECT *
			FROM xf_rm_category_field
		");
		foreach ($categoryFields AS $categoryField)
		{
			$newCategoryId = $this->lookupId('resource_category', $categoryField['resource_category_id']);
			if ($newCategoryId)
			{
				$fieldCategoryMap[$categoryField['field_id']][] = $newCategoryId;
			}
		}

		foreach ($fields AS $oldId => $field)
		{
			if (!empty($existingFields[$oldId]))
			{
				// don't import a field if we already have one called that - this assumes the same structure
				$this->logHandler('XFRM:ResourceField', $oldId, $oldId);
			}
			else
			{
				/** @var \XFRM\Import\Data\ResourceField $import */
				$import = $this->setupCustomFieldImport('XFRM:ResourceField', $field);

				if (!empty($fieldCategoryMap[$oldId]))
				{
					$import->setCategories($fieldCategoryMap[$oldId]);
				}

				$import->save($oldId);
			}

			$state->imported++;
		}

		return $state->complete();
	}

	// ############################## STEP: RESOURCES #########################

	public function getStepEndResources()
	{
		return $this->sourceDb->fetchOne("SELECT MAX(resource_id) FROM xf_rm_resource") ?: 0;
	}

	public function stepResources(StepState $state, array $stepConfig, $maxTime)
	{
		$limit = 250;
		$timer = new \XF\Timer($maxTime);

		$resources = $this->sourceDb->fetchAll("
			SELECT
				r.*,
				user.*,
				IF(user.username IS NULL, r.username, user.username) AS username,
				d.delete_date,
				d.delete_user_id,
				d.delete_username,
				d.delete_reason
				FROM
					xf_rm_resource AS r
				LEFT JOIN xf_user AS user ON (r.user_id	= user.user_id)
				LEFT JOIN xf_deletion_log AS d ON (d.content_type = 'resource' AND d.content_id = r.resource_id)
			WHERE r.resource_id > ? AND r.resource_id <= ?
			ORDER BY r.resource_id
			LIMIT {$limit}
		", [$state->startAfter, $state->end]);
		if (!$resources)
		{
			return $state->complete();
		}

		$mapUserIds = [];
		$mapThreadIds = [];
		$mapCategoryIds = [];
		$mapPrefixIds = [];

		foreach ($resources AS $resource)
		{
			$mapUserIds[] = $resource['user_id'];
			$mapThreadIds[] = $resource['discussion_thread_id'];
			$mapCategoryIds[] = $resource['resource_category_id'];
			$mapPrefixIds[] = $resource['prefix_id'];
		}

		$this->lookup('user', $mapUserIds);
		$this->lookup('thread', $mapThreadIds);
		$this->lookup('resource_category', $mapCategoryIds);
		$this->lookup('resource_prefix', $mapPrefixIds);

		foreach ($resources AS $resource)
		{
			$oldId = $resource['resource_id'];
			$state->startAfter = $oldId;

			$userId = $this->lookupId('user', $resource['user_id']);
			$threadId = $this->lookupId('thread', $resource['discussion_thread_id']);
			$categoryId = $this->lookupId('resource_category', $resource['resource_category_id']);
			$prefixId = $this->lookupId('resource_prefix', $resource['prefix_id']);

			if (!$categoryId)
			{
				continue;
			}

			/** @var \XFRM\Import\Data\ResourceItem $resourceImport */
			$resourceImport = $this->newHandler('XFRM:ResourceItem');

			$resourceImport->bulkSet($this->mapKeys($resource, [
				'title',
				'tag_line',
				'username',
				'resource_date',
				'current_version_id',
				'description_update_id',
				'external_url',
				'download_count',
				'last_update',
				'update_count',
				'review_count',
				'price',
				'currency',
				'external_purchase_url',
				'resource_state',
				'alt_support_url',
				'resource_type'
			]));
			if (isset($resource['view_count']))  // present in RM 2.1
			{
				$resourceImport->view_count = $resource['view_count'];
			}
			$resourceImport->bulkSet([
				'resource_category_id' => $categoryId,
				'user_id' => $userId,
				'discussion_thread_id' => $threadId,
				'prefix_id' => $prefixId
			]);

			if (!empty($resource['team_member_user_ids']))
			{
				$teamMemberUserIds = $this->decodeValue($resource['team_member_user_ids'], 'list-comma');
				$resourceImport->setTeamMemberUserIds($this->lookup('user', $teamMemberUserIds));
			}

			$resourceImport->setDeletionLogData($this->extractDeletionLogData($resource));

			$customFields = $this->decodeValue($resource['custom_fields'], 'serialized-json-array');
			if ($customFields)
			{
				$resourceImport->setCustomFields($this->mapCustomFields('resource_field', $customFields));
			}

			$watchers = $this->sourceDb->fetchPairs("
				SELECT user_id, email_subscribe
				FROM xf_rm_resource_watch
				WHERE resource_id = ?
			", $resource['resource_id']);
			if ($watchers)
			{
				$watcherUserIdMap = $this->lookup('user', array_keys($watchers));
				foreach ($watchers AS $watcherUserId => $emailSubscribe)
				{
					if (!empty($watcherUserIdMap[$watcherUserId]))
					{
						$resourceImport->addWatcher($watcherUserIdMap[$watcherUserId], $emailSubscribe ? true : false);
					}
				}
			}

			$versions = $this->sourceDb->fetchAllKeyed("
				SELECT
					rv.*,
					d.delete_date,
					d.delete_user_id,
					d.delete_username,
					d.delete_reason
				FROM
					xf_rm_resource_version AS rv
					LEFT JOIN xf_deletion_log AS d ON (d.content_type = 'resource_version' AND d.content_id = rv.resource_version_id)
				WHERE
					rv.resource_id = ?
				ORDER BY
					rv.resource_version_id
			", 'resource_version_id', $oldId);

			if (!$versions)
			{
				continue;
			}

			foreach ($versions AS $versionId => $version)
			{
				/** @var \XFRM\Import\Data\ResourceVersion $versionImport */
				$versionImport = $this->newHandler('XFRM:ResourceVersion');

				$versionImport->bulkSet($this->mapKeys($version, [
					'version_string',
					'release_date',
					'download_count',
					'rating_count',
					'rating_sum',
					'download_url',
					'version_state',
					'file_count'
				]));

				if (!empty($version['team_user_id']))
				{
					// importing from 2.2, interject this without major changes
					$versionImport->team_user_id = $this->lookupId('user', $version['team_user_id'], 0);
					$versionImport->team_username = $version['team_username'];
				}

				$versionImport->setDeletionLogData($this->extractDeletionLogData($version));

				$attachments = $this->sourceDb->fetchAllKeyed("
					SELECT
						a.*,
						ad.*
					FROM
						xf_attachment AS a
						INNER JOIN xf_attachment_data AS ad ON (a.data_id = ad.data_id)
						WHERE
							a.content_type = 'resource_version'
							AND a.content_id = ?
						ORDER BY
							a.attachment_id
				", 'attachment_id', $versionId);

				if ($attachments)
				{
					foreach ($attachments AS $attachmentId => $attachment)
					{
						$sourceFile = $this->getSourceAttachmentDataPath(
							$attachment['data_id'], $attachment['file_path'], $attachment['file_hash']
						);
						if (!file_exists($sourceFile) || !is_readable($sourceFile))
						{
							continue;
						}

						/** @var \XF\Import\Data\Attachment $attachmentImport */
						$attachmentImport = $this->newHandler('XF:Attachment');
						$attachmentImport->bulkSet($this->mapKeys($attachment, [
							'content_type',
							'attach_date',
							'temp_hash',
							'unassociated',
							'view_count'
						]));
						$attachmentImport->setDataExtra('upload_date', $version['release_date']);
						$attachmentImport->setDataExtra('file_path', $attachment['file_path']);
						$attachmentImport->setDataUserId($this->lookupId('user', $attachment['user_id'], 0));
						$attachmentImport->setSourceFile($sourceFile, $attachment['filename']);

						$versionImport->addAttachment($attachmentId, $attachmentImport);
					}
				}

				$resourceImport->addVersion($versionId, $versionImport);
			}

			$updates = $this->sourceDb->fetchAllKeyed("
				SELECT
					ru.*,
				    ip.ip,
					d.delete_date,
					d.delete_user_id,
					d.delete_username,
					d.delete_reason
				FROM
					xf_rm_resource_update AS ru
					LEFT JOIN xf_ip AS ip ON (ru.ip_id = ip.ip_id)
					LEFT JOIN xf_deletion_log AS d ON (d.content_type = 'resource_update' AND d.content_id = ru.resource_update_id)
				WHERE
					ru.resource_id = ?
				ORDER BY
					resource_update_id
			", 'resource_update_id', $oldId);

			if (!$updates)
			{
				continue;
			}

			foreach ($updates AS $updateId => $update)
			{
				/** @var \XFRM\Import\Data\ResourceUpdate $updateImport */
				$updateImport = $this->newHandler('XFRM:ResourceUpdate');

				$updateImport->bulkSet($this->mapKeys($update, [
					'title',
					'message',
					'message_state',
					'post_date',
					'attach_count',
					'warning_message'
				]));
				$updateImport->warning_id = $this->lookupId('warning', $update['warning_id'], 0);

				if (!empty($update['team_user_id']))
				{
					// importing from 2.2, interject this without major changes
					$updateImport->team_user_id = $this->lookupId('user', $update['team_user_id'], 0);
					$updateImport->team_username = $update['team_username'];
				}

				$updateImport->setDeletionLogData($this->extractDeletionLogData($update));
				$updateImport->setLoggedIp($update['ip'], $userId);

				$attachments = $this->sourceDb->fetchAllKeyed("
					SELECT
						a.*,
						ad.*
					FROM
						xf_attachment AS a
						INNER JOIN xf_attachment_data AS ad ON (a.data_id = ad.data_id)
						WHERE
							a.content_type = 'resource_update'
							AND a.content_id = ?
						ORDER BY
							a.attachment_id
				", 'attachment_id', $updateId);

				if ($attachments)
				{
					foreach ($attachments AS $attachmentId => $attachment)
					{
						$sourceFile = $this->getSourceAttachmentDataPath(
							$attachment['data_id'], $attachment['file_path'], $attachment['file_hash']
						);
						if (!file_exists($sourceFile) || !is_readable($sourceFile))
						{
							continue;
						}

						/** @var \XF\Import\Data\Attachment $attachmentImport */
						$attachmentImport = $this->newHandler('XF:Attachment');
						$attachmentImport->bulkSet($this->mapKeys($attachment, [
							'content_type',
							'attach_date',
							'temp_hash',
							'unassociated',
							'view_count'
						]));
						$attachmentImport->setDataExtra('upload_date', $version['release_date']);
						$attachmentImport->setDataExtra('file_path', $attachment['file_path']);
						$attachmentImport->setDataUserId($this->lookupId('user', $attachment['user_id'], 0));
						$attachmentImport->setSourceFile($sourceFile, $attachment['filename']);
						$attachmentImport->setContainerCallback([$this, 'rewriteEmbeddedAttachments']);

						$updateImport->addAttachment($attachmentId, $attachmentImport);
					}
				}

				$resourceImport->addUpdate($updateId, $updateImport);
			}

			if ($resource['icon_date'])
			{
				$dataDir = $this->baseConfig['data_dir'];
				$oldGroupId = floor($oldId / 1000);

				$oldIconPath = "$dataDir/resource_icons/$oldGroupId/$oldId.jpg";
				$resourceImport->setIconPath($oldIconPath);
			}

			$newId = $resourceImport->save($oldId);
			if ($newId)
			{
				$state->imported++;

				$resourceItem = $this->em()->find('XFRM:ResourceItem', $newId);

				$newVersionId = $this->lookupId('resource_version', $resource['current_version_id']);
				$newUpdateId = $this->lookupId('resource_update', $resource['description_update_id']);

				$resourceItem->fastUpdate([
					'current_version_id' => $newVersionId ?: 0,
					'description_update_id' => $newUpdateId ?: 0
				]);

				$this->em()->detachEntity($resourceItem);
			}

			if ($timer->limitExceeded())
			{
				break;
			}
		}

		\XF\Util\File::cleanUpTempFiles();

		return $state->resumeIfNeeded();
	}

	public function rewriteEmbeddedAttachments(\XF\Mvc\Entity\Entity $container, \XF\Entity\Attachment $attachment, $oldId, array $extras, $messageCol = 'message')
	{
		// rewrites the message content for resource updates
		parent::rewriteEmbeddedAttachments($container, $attachment, $oldId, $extras, $messageCol);

		/** @var \XFRM\Entity\ResourceItem $resource */
		$resource = $container->Resource;

		$thread = $resource->Discussion;
		if (!$thread)
		{
			return;
		}

		$firstPost = $thread->FirstPost;
		if (!$firstPost)
		{
			return;
		}

		// rewrites the message content for automated first post
		parent::rewriteEmbeddedAttachments($firstPost, $attachment, $oldId, $extras, $messageCol);

		$firstPost->saveIfChanged($null, false, false);
	}

	// ############################## STEP: RESOURCE RATINGS #########################

	public function getStepEndRatings()
	{
		return $this->sourceDb->fetchOne("SELECT MAX(resource_rating_id) FROM xf_rm_resource_rating") ?: 0;
	}

	public function stepRatings(StepState $state, array $stepConfig, $maxTime)
	{
		$limit = 250;
		$timer = new \XF\Timer($maxTime);

		$ratings = $this->sourceDb->fetchAll("
			SELECT
				r.*,
				user.*,
				d.delete_date,
				d.delete_user_id,
				d.delete_username,
				d.delete_reason
				FROM
					xf_rm_resource_rating AS r
				LEFT JOIN xf_user AS user ON (r.user_id	= user.user_id)
				LEFT JOIN xf_deletion_log AS d ON (d.content_type = 'resource_rating' AND d.content_id = r.resource_rating_id)
			WHERE r.resource_rating_id > ? AND r.resource_rating_id <= ?
			ORDER BY r.resource_rating_id
			LIMIT {$limit}
		", [$state->startAfter, $state->end]);
		if (!$ratings)
		{
			return $state->complete();
		}

		$mapUserIds = [];
		$mapVersionIds = [];
		$mapResourceIds = [];
		$mapWarningIds = [];

		foreach ($ratings AS $rating)
		{
			$mapUserIds[] = $rating['user_id'];
			$mapWarningIds[] = $rating['warning_id'];
			$mapVersionIds[] = $rating['resource_version_id'];
			$mapResourceIds[] = $rating['resource_id'];
		}

		$this->lookup('user', $mapUserIds);
		$this->lookup('warning', $mapWarningIds);
		$this->lookup('resource_version', $mapVersionIds);
		$this->lookup('resource', $mapResourceIds);

		foreach ($ratings AS $rating)
		{
			$oldId = $rating['resource_rating_id'];
			$state->startAfter = $oldId;

			$ratingImport = $this->setupRatingImport($rating);
			if (!$ratingImport)
			{
				continue;
			}

			$newId = $ratingImport->save($oldId);
			if ($newId)
			{
				$state->imported++;
			}

			if ($timer->limitExceeded())
			{
				break;
			}
		}

		return $state->resumeIfNeeded();
	}

	protected function setupRatingImport(array $rating)
	{
		$versionId = $this->lookupId('resource_version', $rating['resource_version_id']);
		$resourceId = $this->lookupId('resource', $rating['resource_id']);

		if (!$versionId || !$resourceId)
		{
			return null;
		}

		/** @var \XFRM\Import\Data\ResourceRating $ratingImport */
		$ratingImport = $this->newHandler('XFRM:ResourceRating');

		$ratingImport->bulkSet($this->mapKeys($rating, [
			'rating',
			'rating_date',
			'message',
			'version_string',
			'author_response',
			'is_review',
			'count_rating',
			'rating_state',
			'is_anonymous'
		]));

		$ratingImport->bulkSet([
			'resource_version_id' => $versionId,
			'user_id' => $this->lookupId('user', $rating['user_id']),
			'resource_id' => $resourceId,
			'warning_id' => $this->lookupId('warning', $rating['warning_id'], 0)
		]);

		return $ratingImport;
	}

	// ########################### STEP: LIKES ###############################

	public function getStepEndLikes()
	{
		return $this->getMaxLikeIdForContentTypes('resource_update');
	}

	public function stepLikes(StepState $state, array $stepConfig, $maxTime)
	{
		return $this->getLikesStepStateForContentTypes(
			'resource_update', $state, $stepConfig, $maxTime
		);
	}

	// ########################### STEP: TAGS ###############################

	public function getStepEndTags()
	{
		return $this->getMaxTagContentIdForContentTypes('resource');
	}

	public function stepTags(StepState $state, array $stepConfig, $maxTime)
	{
		return $this->getTagsStepStateForContentTypes('resource', $state, $stepConfig, $maxTime);
	}
}