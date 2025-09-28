<?php

namespace XFRM;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\Db\Schema\Alter;
use XF\Db\Schema\Create;

class Setup extends AbstractSetup
{
	use StepRunnerInstallTrait;
	use StepRunnerUpgradeTrait;
	use StepRunnerUninstallTrait;

	// ################################ INSTALLATION ####################

	public function installStep1()
	{
		$sm = $this->schemaManager();

		foreach ($this->getTables() AS $tableName => $closure)
		{
			$sm->createTable($tableName, $closure);
		}
	}

	public function installStep2()
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_user', function(Alter $table)
		{
			$table->addColumn('xfrm_resource_count', 'int')->setDefault(0);
			$table->addKey('xfrm_resource_count', 'resource_count');
		});
	}

	public function installStep3()
	{
		$this->db()->query("
			REPLACE INTO `xf_rm_category`
				(`resource_category_id`, 
				`title`, 
				`description`, 
				`parent_category_id`, `depth`, `lft`, `rgt`, `display_order`, 
				`resource_count`, `last_update`, `last_resource_title`, `last_resource_id`, `breadcrumb_data`, 
				`allow_local`, `allow_external`, `allow_commercial_external`, `allow_fileless`, 
				`thread_node_id`, `thread_prefix_id`, 
				`always_moderate_create`, `always_moderate_update`, 
				field_cache, prefix_cache, review_field_cache)
			VALUES
				(1, 
				'Example category', 
				'This is an example resource manager category. You can manage the resource manager categories via the <a href=\"admin.php?resource-manager/categories/\" target=\"_blank\">Admin control panel</a>. From there, you can setup more categories or change the resource manager options.', 
				0, 0, 1, 2, 1, 
				0, 0, '', 0, '[]', 
				1, 1, 1, 1, 
				0, 0, 
				0, 0, 
				'', '', '');
		");

		$this->db()->query("
			REPLACE INTO xf_admin_permission_entry
				(user_id, admin_permission_id)
			SELECT user_id, 'resourceManager'
			FROM xf_admin_permission_entry
			WHERE admin_permission_id = 'node'
		");

		foreach ($this->getDefaultWidgetSetup() AS $widgetKey => $widgetFn)
		{
			$widgetFn($widgetKey);
		}

		$this->insertThreadType('resource', 'XFRM:ResourceItem', 'XFRM');
	}

	public function postInstall(array &$stateChanges)
	{
		if ($this->applyDefaultPermissions())
		{
			// since we're running this after data imports, we need to trigger a permission rebuild
			// if we changed anything
			$this->app->jobManager()->enqueueUnique(
				'permissionRebuild',
				'XF:PermissionRebuild',
				[],
				false
			);
		}

		/** @var \XF\Service\RebuildNestedSet $service */
		$service = \XF::service('XF:RebuildNestedSet', 'XFRM:Category', [
			'parentField' => 'parent_category_id'
		]);
		$service->rebuildNestedSetInfo();

		\XF::repository('XFRM:ResourcePrefix')->rebuildPrefixCache();
		\XF::repository('XFRM:ResourceField')->rebuildFieldCache();
	}

	// ################################ UPGRADE TO 1.1.0 B1 ##################
	// note: this is just translated from the XF1 version roughly as is

	public function upgrade1010031Step1()
	{
		$this->query("
			CREATE TABLE IF NOT EXISTS `xf_resource_feature` (
				`resource_id` int(10) unsigned NOT NULL,
				`feature_date` int(10) unsigned NOT NULL,
				PRIMARY KEY  (`resource_id`),
				KEY `feature_date` (`feature_date`)
			) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
		");

		$this->query("
			CREATE TABLE IF NOT EXISTS `xf_resource_field` (
			  	field_id VARBINARY(25) NOT NULL,
				display_group VARCHAR(25) NOT NULL DEFAULT 'above_info',
				display_order INT UNSIGNED NOT NULL DEFAULT 1,
				field_type VARCHAR(25) NOT NULL DEFAULT 'textbox',
				field_choices BLOB NOT NULL,
				match_type VARCHAR(25) NOT NULL DEFAULT 'none',
				match_regex VARCHAR(250) NOT NULL DEFAULT '',
				match_callback_class VARCHAR(75) NOT NULL DEFAULT '',
				match_callback_method VARCHAR(75) NOT NULL DEFAULT '',
				max_length INT UNSIGNED NOT NULL DEFAULT 0,
				required TINYINT UNSIGNED NOT NULL DEFAULT 0,
				display_template TEXT NOT NULL,
				PRIMARY KEY (field_id),
				KEY display_group_order (display_group, display_order)
			) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
		");

		$this->query("
			CREATE TABLE IF NOT EXISTS xf_resource_field_category (
				field_id VARBINARY(25) NOT NULL,
				resource_category_id INT NOT NULL,
				PRIMARY KEY (field_id, resource_category_id),
				KEY resource_category_id (resource_category_id)
			) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
		");

		$this->query("
			CREATE TABLE IF NOT EXISTS xf_resource_field_value (
				resource_id INT UNSIGNED NOT NULL,
				field_id VARBINARY(25) NOT NULL,
				field_value MEDIUMTEXT NOT NULL,
				PRIMARY KEY (resource_id, field_id),
				KEY field_id (field_id)
			) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
		");

		$this->query("
			CREATE TABLE IF NOT EXISTS xf_resource_category_prefix (
				resource_category_id INT UNSIGNED NOT NULL,
				prefix_id INT UNSIGNED NOT NULL,
				PRIMARY KEY (resource_category_id, prefix_id),
				KEY prefix_id (prefix_id)
			) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
		");

		$this->query("
			CREATE TABLE IF NOT EXISTS xf_resource_category_watch (
				`user_id` int(10) unsigned NOT NULL,
				`resource_category_id` int(10) unsigned NOT NULL,
				`notify_on` enum('','resource','update') NOT NULL,
				`send_alert` tinyint(3) unsigned NOT NULL,
				`send_email` tinyint(3) unsigned NOT NULL,
				`include_children` tinyint(3) unsigned NOT NULL,
				PRIMARY KEY (`user_id`,`resource_category_id`),
				KEY `node_id_notify_on` (`resource_category_id`,`notify_on`)
			) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
		");

		$this->query("
			CREATE TABLE IF NOT EXISTS xf_resource_prefix (
				prefix_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
				prefix_group_id INT UNSIGNED NOT NULL,
				display_order INT UNSIGNED NOT NULL,
				materialized_order INT UNSIGNED NOT NULL COMMENT 'Internally-set order, based on prefix_group.display_order, prefix.display_order',
				css_class VARCHAR(50) NOT NULL DEFAULT '',
				allowed_user_group_ids blob NOT NULL,
				PRIMARY KEY (prefix_id),
				KEY materialized_order (materialized_order)
			) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
		");

		$this->query("
			CREATE TABLE IF NOT EXISTS xf_resource_prefix_group (
				prefix_group_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
				display_order INT UNSIGNED NOT NULL,
				PRIMARY KEY (prefix_group_id)
			) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
		");
	}

	public function upgrade1010031Step2()
	{
		$db = $this->db();

		try
		{
			$userGroupIds = $db->fetchAllColumn("
				SELECT user_group_id
				FROM xf_user_group
			");
			$categoryUpdates = $db->fetchPairs("
				SELECT resource_category_id, allow_submit_user_group_ids
				FROM xf_resource_category
				WHERE allow_submit_user_group_ids <> '-1'
			");
			foreach ($categoryUpdates AS $categoryId => $groups)
			{
				$allowGroupIds = explode(',', $groups);
				foreach ($userGroupIds AS $userGroupId)
				{
					$db->query("
						REPLACE INTO xf_permission_entry_content
							(content_type, content_id, user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int)
						VALUES
							('resource_category', ?, ?, 0, 'resource', 'add', ?, 0)
					", [
						$categoryId, $userGroupId, in_array($userGroupId, $allowGroupIds) ? 'content_allow' : 'reset'
					]);
				}
			}
		}
		catch (\XF\Db\Exception $e) {}
	}

	public function upgrade1010031Step3()
	{
		$this->query("
			ALTER TABLE xf_resource
				ADD custom_resource_fields MEDIUMBLOB NOT NULL,
				ADD prefix_id INT UNSIGNED NOT NULL DEFAULT 0,
				ADD icon_date INT UNSIGNED NOT NULL DEFAULT 0,
				ADD KEY prefix_id (prefix_id)
		");

		$this->query("
			ALTER TABLE xf_resource_update
				ADD warning_id INT UNSIGNED NOT NULL DEFAULT 0,
				ADD warning_message VARCHAR(255) NOT NULL DEFAULT ''
		");

		$this->query("
			ALTER TABLE xf_resource_rating
				ADD warning_id INT UNSIGNED NOT NULL DEFAULT 0,
				ADD is_anonymous TINYINT UNSIGNED NOT NULL DEFAULT 0
		");

		$this->query("
			ALTER TABLE xf_resource_category
				ADD field_cache MEDIUMBLOB NOT NULL,
				DROP allow_submit_user_group_ids,
				ADD prefix_cache MEDIUMBLOB NOT NULL,
				ADD require_prefix TINYINT UNSIGNED NOT NULL DEFAULT '0',
				ADD featured_count SMALLINT UNSIGNED NOT NULL DEFAULT '0'
		");
	}

	// ################################ UPGRADE TO 1.2.0 B1 ##################
	// note: this is just translated from the XF1 version roughly as is

	public function upgrade1020010Step1()
	{
		$this->query("
			ALTER TABLE xf_resource
				ADD tags MEDIUMBLOB NOT NULL
		");

		$this->query("
			ALTER TABLE xf_resource_category
				ADD min_tags SMALLINT UNSIGNED NOT NULL DEFAULT '0'
		");

		$this->query("
			ALTER TABLE xf_resource_rating
				ADD INDEX rating_date (rating_date)
		");
	}

	// ################################ UPGRADE TO 2.0.0 B1 ##################

	public function upgrade2000010Step1()
	{
		$sm = $this->schemaManager();

		$renameTables = [
			'xf_resource' => 'xf_rm_resource',
			'xf_resource_category' => 'xf_rm_category',
			'xf_resource_category_prefix' => 'xf_rm_category_prefix',
			'xf_resource_category_watch' => 'xf_rm_category_watch',
			'xf_resource_download' => 'xf_rm_resource_download',
			'xf_resource_feature' => 'xf_rm_resource_feature',
			'xf_resource_field' => 'xf_rm_resource_field',
			'xf_resource_field_category' => 'xf_rm_resource_field_category',
			'xf_resource_field_value' => 'xf_rm_resource_field_value',
			'xf_resource_prefix' => 'xf_rm_resource_prefix',
			'xf_resource_prefix_group' => 'xf_rm_resource_prefix_group',
			'xf_resource_rating' => 'xf_rm_resource_rating',
			'xf_resource_update' => 'xf_rm_resource_update',
			'xf_resource_version' => 'xf_rm_resource_version',
			'xf_resource_watch' => 'xf_rm_resource_watch',
		];
		foreach ($renameTables AS $from => $to)
		{
			$sm->renameTable($from, $to);
		}

		$sm->alterTable('xf_user', function(Alter $table)
		{
			$table->renameColumn('resource_count', 'xfrm_resource_count');
			// note that the index name is still resource_count
		});
	}

	public function upgrade2000010Step2()
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_rm_category', function(Alter $table)
		{
			$table->renameColumn('category_title', 'title');
			$table->renameColumn('category_description', 'description');
			$table->renameColumn('category_breadcrumb', 'breadcrumb_data');
			$table->addColumn('enable_versioning', 'tinyint')->setDefault(1);
		});

		$sm->alterTable('xf_rm_resource', function(Alter $table)
		{
			$table->changeColumn('resource_category_id')->length(10)->unsigned();
			$table->renameColumn('custom_resource_fields', 'custom_fields');
			$table->addColumn('resource_type', 'varchar', 25);
			$table->dropColumns('had_first_visible');
		});

		$sm->alterTable('xf_rm_resource_update', function (Alter $table)
		{
			$table->changeColumn('resource_id')->length(10)->unsigned();
			$table->addColumn('embed_metadata', 'blob')->nullable();
			$table->dropColumns('had_first_visible');
		});

		$sm->alterTable('xf_rm_resource_version', function (Alter $table)
		{
			$table->addColumn('file_count', 'smallint')->setDefault(0);
			$table->dropColumns(['resource_update_id', 'had_first_visible']);
		});

		$sm->alterTable('xf_rm_resource_watch', function(Alter $table)
		{
			$table->dropColumns('watch_key');
		});
	}

	public function upgrade2000010Step3()
	{
		$this->query("
			UPDATE xf_rm_resource
			SET resource_type = IF(external_purchase_url <> '', 'external_purchase', IF(is_fileless, 'fileless', 'download'))
		");
	}

	public function upgrade2000010Step4()
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_rm_resource', function(Alter $table)
		{
			$table->dropColumns('is_fileless');
		});
	}

	public function upgrade2000010Step5()
	{
		$this->query("
			UPDATE xf_rm_resource_version AS v, xf_rm_resource AS r
			SET v.file_count = 1
			WHERE v.resource_id = r.resource_id AND v.download_url = '' AND r.resource_type = 'download'
		");
	}

	public function upgrade2000010Step6()
	{
		$db = $this->db();

		$tablesToUpdate = [
			'xf_permission',
			'xf_permission_entry',
			'xf_permission_entry_content'
		];
		$renames = [
			'updateSelf' => 'updateOwn',
			'deleteSelf' => 'deleteOwn',
			'deleteReviewAny' => 'deleteAnyReview'
		];

		foreach ($tablesToUpdate AS $table)
		{
			foreach ($renames AS $old => $new)
			{
				$db->update($table, [
					'permission_id' => $new
				], 'permission_id = ? AND permission_group_id = ?', [$old, 'resource']);
			}
		}

		$options = $this->db()->fetchPairs("
			SELECT option_id, option_value
			FROM xf_option
			WHERE option_id IN ('topResourcesCount', 'latestResourceReviewsCount', 'forumListNewResources')
		");

		if (!isset($options['topResourcesCount']))
		{
			$this->insertNamedWidget('xfrm_list_top_resources');
		}
		else
		{
			$topResourcesCount = intval($options['topResourcesCount']);
			if ($topResourcesCount > 0)
			{
				$this->insertNamedWidget('xfrm_list_top_resources', ['limit' => $topResourcesCount]);
			}
		}

		if (!isset($options['latestResourceReviewsCount']))
		{
			$this->insertNamedWidget('xfrm_overview_latest_reviews');
		}
		else
		{
			$reviewCount = intval($options['latestResourceReviewsCount']);
			if ($reviewCount > 0)
			{
				$this->insertNamedWidget('xfrm_overview_latest_reviews', ['limit' => $reviewCount]);
			}
		}

		if (!isset($options['forumListNewResources']))
		{
			$this->insertNamedWidget('xfrm_forum_overview_new_resources');
		}
		else
		{
			$forumResourceCount = intval($options['forumListNewResources']);
			if ($forumResourceCount > 0)
			{
				$this->insertNamedWidget('xfrm_forum_overview_new_resources', ['limit' => $forumResourceCount]);
			}
		}

		$this->insertNamedWidget('xfrm_overview_top_authors');
		$this->insertNamedWidget('xfrm_whats_new_overview_new_resources');
	}

	public function upgrade2000010Step7()
	{
		$sm = $this->schemaManager();
		$db = $this->db();

		$sm->alterTable('xf_rm_resource_field', function (Alter $table)
		{
			$table->changeColumn('field_type')->resetDefinition()->type('varbinary', 25)->setDefault('textbox');
			$table->changeColumn('match_type')->resetDefinition()->type('varbinary', 25)->setDefault('none');
			$table->addColumn('match_params', 'blob')->after('match_type');
		});

		foreach ($db->fetchAllKeyed("SELECT * FROM xf_rm_resource_field", 'field_id') AS $fieldId => $field)
		{
			if (!isset($field['match_regex']))
			{
				// column removed already, this has been run
				continue;
			}

			$update = [];
			$matchParams = [];

			switch ($field['match_type'])
			{
				case 'regex':
					if ($field['match_regex'])
					{
						$matchParams['regex'] = $field['match_regex'];
					}
					break;

				case 'callback':
					if ($field['match_callback_class'] && $field['match_callback_method'])
					{
						$matchParams['callback_class'] = $field['match_callback_class'];
						$matchParams['callback_method'] = $field['match_callback_method'];
					}
					break;
			}

			if (!empty($matchParams))
			{
				$update['match_params'] = json_encode($matchParams);
			}

			if ($field['field_choices'] && $fieldChoices = @unserialize($field['field_choices']))
			{
				$update['field_choices'] = json_encode($fieldChoices);
			}

			if (!empty($update))
			{
				$db->update('xf_rm_resource_field', $update, 'field_id = ?', $fieldId);
			}
		}

		$sm->alterTable('xf_rm_resource_field', function(Alter $table)
		{
			$table->dropColumns(['match_regex', 'match_callback_class', 'match_callback_method']);
		});

		$sm->renameTable('xf_rm_resource_field_category', 'xf_rm_category_field');
		$sm->alterTable('xf_rm_category_field', function (Alter $table)
		{
			$table->changeColumn('resource_category_id')->length(10)->unsigned();
		});
	}

	public function upgrade2000010Step8()
	{
		$optionMap = [
			'paidResourceThreadTemplate' => 'xfrmPaidResourceThreadTitleTemplate',
			'requireDownloadToRate' => 'xfrmRequireDownloadToRate',
			'resourceReviewRequired' => 'xfrmReviewRequired',
			'resourceMinimumReviewLength' => 'xfrmMinimumReviewLength',
			'resourceAllowAnonReview' => 'xfrmAllowAnonReview',
			'resourceFilelessViewFull' => 'xfrmFilelessViewFull',
			'allowAltResourceSupportUrl' => 'xfrmAllowSupportUrl',
			'resourceAllowIcons' => 'xfrmAllowIcons',
			'resourceDeleteThreadAction' => 'xfrmResourceDeleteThreadAction',
			'resourceDefaultSort' => 'xfrmListDefaultOrder',
			'resourceMaxFileSize' => 'xfrmResourceMaxFileSize',
			'resourceExtensions' => 'xfrmResourceExtensions',
			'resourcesPerPage' => 'xfrmResourcesPerPage',
			'resourceReviewsPerPage' => 'xfrmReviewsPerPage',
			'resourceUpdatesPerPage' => 'xfrmUpdatesPerPage',
			'authorOtherResourcesCount' => 'xfrmAuthorOtherResourcesCount',
			'resourceRecentUpdatesCount' => 'xfrmRecentUpdatesCount',
			'resourceRecentReviewsCount' => 'xfrmRecentReviewsCount',
			'resourceCurrencies' => 'xfrmResourceCurrencies'
		];

		$db = $this->db();
		$db->beginTransaction();

		foreach ($optionMap AS $from => $to)
		{
			$db->update('xf_option', ['option_id' => $to], 'option_id = ?', $from);
		}

		$db->commit();
	}

	public function upgrade2000010Step9()
	{
		$map = [
			'resource_prefix_group_*' => 'resource_prefix_group.*',
			'resource_prefix_*' => 'resource_prefix.*',
			'resource_field_*_choice_*' => 'xfrm_resource_field_choice.$1_$2',
			'resource_field_*_desc' => 'xfrm_resource_field_desc.*',
			'resource_field_*' => 'xfrm_resource_field_title.*',
		];

		$db = $this->db();

		foreach ($map AS $from => $to)
		{
			$mySqlRegex = '^' . str_replace('*', '[a-zA-Z0-9_]+', $from) . '$';
			$phpRegex = '/^' . str_replace('*', '([a-zA-Z0-9_]+)', $from) . '$/';
			$replace = str_replace('*', '$1', $to);

			$results = $db->fetchPairs("
				SELECT phrase_id, title
				FROM xf_phrase
				WHERE title RLIKE BINARY ?
					AND addon_id = ''
			", $mySqlRegex);
			if ($results)
			{
				/** @var \XF\Entity\Phrase[] $phrases */
				$phrases = \XF::em()->findByIds('XF:Phrase', array_keys($results));
				foreach ($results AS $phraseId => $oldTitle)
				{
					if (isset($phrases[$phraseId]))
					{
						$newTitle = preg_replace($phpRegex, $replace, $oldTitle);

						$phrase = $phrases[$phraseId];
						$phrase->title = $newTitle;
						$phrase->global_cache = false;
						$phrase->save(false);
					}
				}
			}
		}
	}

	public function upgrade2000010Step10()
	{
		$db = $this->db();

		// update prefix CSS classes to the new name
		$prefixes = $db->fetchPairs("
			SELECT prefix_id, css_class
			FROM xf_rm_resource_prefix
			WHERE css_class <> ''
		");

		$db->beginTransaction();

		foreach ($prefixes AS $id => $class)
		{
			$newClass = preg_replace_callback('#prefix\s+prefix([A-Z][a-zA-Z0-9_-]*)#', function ($match)
			{
				$variant = strtolower($match[1][0]) . substr($match[1], 1);
				if ($variant == 'secondary')
				{
					$variant = 'accent';
				}
				return 'label label--' . $variant;
			}, $class);
			if ($newClass != $class)
			{
				$db->update('xf_rm_resource_prefix',
					['css_class' => $newClass],
					'prefix_id = ?', $id
				);
			}
		}

		$db->commit();

		// update field category cache format
		$fieldCache = [];

		$entries = $db->fetchAll("
			SELECT *
			FROM xf_rm_category_field
		");
		foreach ($entries AS $entry)
		{
			$fieldCache[$entry['resource_category_id']][$entry['field_id']] = $entry['field_id'];
		}

		$db->beginTransaction();

		foreach ($fieldCache AS $categoryId => $cache)
		{
			$db->update(
				'xf_rm_category',
				['field_cache' => serialize($cache)],
				'resource_category_id = ?',
				$categoryId
			);
		}

		$db->commit();
	}

	public function upgrade2000010Step11()
	{
		$db = $this->db();

		$associations = $db->fetchAll("
			SELECT cp.*
			FROM xf_rm_category_prefix AS cp
			INNER JOIN xf_rm_resource_prefix as p ON
				(cp.prefix_id = p.prefix_id)
			ORDER BY p.materialized_order
		");

		$cache = [];
		foreach ($associations AS $association)
		{
			$cache[$association['resource_category_id']][$association['prefix_id']] = $association['prefix_id'];
		}

		$db->beginTransaction();

		foreach ($cache AS $categoryId => $prefixes)
		{
			$db->update(
				'xf_rm_category',
				['prefix_cache' => serialize($prefixes)],
				'resource_category_id = ?',
				$categoryId
			);
		}

		$db->commit();
	}

	// ################################ UPGRADE TO 2.1.0 A1 ##################

	public function upgrade2010010Step1(array $stepParams)
	{
		$position = empty($stepParams[0]) ? 0 : $stepParams[0];
		$stepData = empty($stepParams[2]) ? [] : $stepParams[2];

		return $this->entityColumnsToJson(
			'XFRM:Category', ['field_cache', 'prefix_cache', 'breadcrumb_data'], $position, $stepData
		);
	}

	public function upgrade2010010Step2(array $stepParams)
	{
		$position = empty($stepParams[0]) ? 0 : $stepParams[0];
		$stepData = empty($stepParams[2]) ? [] : $stepParams[2];

		return $this->entityColumnsToJson('XFRM:ResourceItem', ['custom_fields', 'tags'], $position, $stepData);
	}

	public function upgrade2010010Step3(array $stepParams)
	{
		$position = empty($stepParams[0]) ? 0 : $stepParams[0];
		$stepData = empty($stepParams[2]) ? [] : $stepParams[2];

		return $this->entityColumnsToJson('XFRM:ResourceUpdate', ['like_users'], $position, $stepData);
	}

	public function upgrade2010010Step4()
	{
		$this->migrateTableToReactions('xf_rm_resource_update');
	}

	public function upgrade2010010Step5()
	{
		$this->renameLikeAlertOptionsToReactions('resource_update');
	}

	public function upgrade2010010Step6()
	{
		$this->renameLikeAlertsToReactions('resource_update');
	}

	public function upgrade2010010Step7()
	{
		$this->renameLikePermissionsToReactions([
			'resource' => true // global and content
		]);

		$this->renameLikeStatsToReactions('resource');
	}

	public function upgrade2010010Step8()
	{
		// misc steps

		$this->applyContentPermission('resource', 'uploadUpdateVideo', 'resource', 'uploadUpdateAttach');
	}

	public function upgrade2010013Step1()
	{
		$this->alterTable('xf_rm_resource', function(Alter $table)
		{
			$table->addColumn('view_count', 'int')->setDefault(0);
		});
	}

	public function upgrade2010013Step2()
	{
		$this->createTable('xf_rm_resource_view', function(Create $table)
		{
			$table->engine('MEMORY');

			$table->addColumn('resource_id', 'int');
			$table->addColumn('total', 'int');
			$table->addPrimaryKey('resource_id');
		});
	}

	public function upgrade2010013Step3()
	{
		$this->alterTable('xf_rm_category', function(Alter $table)
		{
			$table->addColumn('enable_support_url', 'tinyint')->setDefault(1);
		});

		$allowSupportUrl = $this->db()->fetchOne("
			SELECT option_value
			FROM xf_option
			WHERE option_id = 'xfrmAllowSupportUrl'
		");
		if (!$allowSupportUrl)
		{
			$this->executeUpgradeQuery("
				UPDATE xf_rm_category
				SET enable_support_url = 0
			");
		}
	}

	// ################################ UPGRADE TO 2.2.0 A1 ##################

	public function upgrade2020010Step1()
	{
		$this->addPrefixDescHelpPhrases([
			'resource' => 'xf_rm_resource_prefix'
		]);

		$this->insertThreadType('resource', 'XFRM:ResourceItem', 'XFRM');

		$this->insertNamedWidget('xfrm_list_featured_resources');
	}

	public function upgrade2020010Step2()
	{
		$this->createTable('xf_rm_category_review_field', function(Create $table)
		{
			$table->addColumn('field_id', 'varbinary', 25);
			$table->addColumn('resource_category_id', 'int');
			$table->addPrimaryKey(['field_id', 'resource_category_id']);
			$table->addKey('resource_category_id');
		});

		$this->createTable('xf_rm_resource_review_field', function(Create $table)
		{
			$table->addColumn('field_id', 'varbinary', 25);
			$table->addColumn('display_group', 'varchar', 25)->setDefault('above_info');
			$table->addColumn('display_order', 'int')->setDefault(1);
			$table->addColumn('field_type', 'varbinary', 25)->setDefault('textbox');
			$table->addColumn('field_choices', 'blob');
			$table->addColumn('match_type', 'varbinary', 25)->setDefault('none');
			$table->addColumn('match_params', 'blob');
			$table->addColumn('max_length', 'int')->setDefault(0);
			$table->addColumn('required', 'tinyint')->setDefault(0);
			$table->addColumn('display_template', 'text');
			$table->addColumn('wrapper_template', 'text');
			$table->addPrimaryKey('field_id');
			$table->addKey(['display_group', 'display_order'], 'display_group_order');
		});

		$this->createTable('xf_rm_resource_review_field_value', function(Create $table)
		{
			$table->addColumn('resource_rating_id', 'int');
			$table->addColumn('field_id', 'varbinary', 25);
			$table->addColumn('field_value', 'mediumtext');
			$table->addPrimaryKey(['resource_rating_id', 'field_id']);
			$table->addKey('field_id');
		});

		$this->createTable('xf_rm_resource_team_member', function(Create $table)
		{
			$table->addColumn('resource_id', 'int');
			$table->addColumn('user_id', 'int');
			$table->addPrimaryKey(['resource_id', 'user_id']);
			$table->addKey('user_id');
		});
	}

	public function upgrade2020010Step3()
	{
		$this->alterTable('xf_rm_resource_field', function(Alter $table)
		{
			$table->addColumn('wrapper_template', 'text')->after('display_template');
		});

		$this->alterTable('xf_rm_category', function(Alter $table)
		{
			$table->addColumn('review_field_cache', 'mediumblob')->after('field_cache');
		});
	}

	public function upgrade2020010Step4()
	{
		$this->alterTable('xf_rm_resource_rating', function(Alter $table)
		{
			$table->addColumn('custom_fields', 'mediumblob');
			$table->addColumn('vote_score', 'int')->unsigned(false);
			$table->addColumn('vote_count', 'int')->setDefault(0);
			$table->addColumn('author_response_team_user_id', 'int')->setDefault(0)->after('version_string');
			$table->addColumn('author_response_team_username', 'varchar', 50)->setDefault('')->after('author_response_team_user_id');
			$table->addKey('author_response_team_user_id');
		});
	}

	public function upgrade2020010Step5()
	{
		$this->alterTable('xf_rm_resource', function(Alter $table)
		{
			$table->addColumn('team_member_user_ids', 'blob')->after('username');
		});
	}

	public function upgrade2020010Step6()
	{
		$this->alterTable('xf_rm_resource_update', function(Alter $table)
		{
			$table->addColumn('team_user_id', 'int')->after('resource_id');
			$table->addColumn('team_username', 'varchar', 50)->after('team_user_id');
			$table->addKey('team_user_id');
		});
	}

	public function upgrade2020010Step7()
	{
		$this->alterTable('xf_rm_resource_version', function(Alter $table)
		{
			$table->addColumn('team_user_id', 'int')->after('resource_id');
			$table->addColumn('team_username', 'varchar', 50)->after('team_user_id');
			$table->addKey('team_user_id');
		});
	}

	public function upgrade2020010Step8()
	{
		$this->alterTable('xf_rm_resource_update', function(Alter $table)
		{
			$table->addColumn('last_edit_date', 'int')->setDefault(0)->after('warning_message');
			$table->addColumn('last_edit_user_id', 'int')->setDefault(0)->after('last_edit_date');
			$table->addColumn('edit_count', 'int')->setDefault(0)->after('last_edit_user_id');
		});
	}

	// ############################################ FINAL UPGRADE ACTIONS ##########################

	public function postUpgrade($previousVersion, array &$stateChanges)
	{
		if ($this->applyDefaultPermissions($previousVersion))
		{
			// since we're running this after data imports, we need to trigger a permission rebuild
			// if we changed anything
			$this->app->jobManager()->enqueueUnique(
				'permissionRebuild',
				'XF:PermissionRebuild',
				[],
				false
			);
		}

		if ($previousVersion && $previousVersion < 2000010)
		{
			$this->app->jobManager()->enqueueUnique(
				'xfrmUpgradeUpdateEmbedMetadataRebuild',
				'XFRM:ResourceUpdateEmbedMetadata',
				['types' => 'attachments'],
				false
			);

			$this->app->jobManager()->enqueueUnique(
				'xfrmUpgradeLikeIsCountedRebuild',
				'XF:LikeIsCounted',
				['type' => 'resource_update'],
				false
			);

			/** @var \XF\Service\RebuildNestedSet $service */
			$service = \XF::service('XF:RebuildNestedSet', 'XFRM:Category', [
				'parentField' => 'parent_category_id'
			]);
			$service->rebuildNestedSetInfo();
		}

		\XF::repository('XFRM:ResourcePrefix')->rebuildPrefixCache();
		\XF::repository('XFRM:ResourceField')->rebuildFieldCache();
	}

	// ############################################ UNINSTALL #########################

	public function uninstallStep1()
	{
		$sm = $this->schemaManager();

		foreach (array_keys($this->getTables()) AS $tableName)
		{
			$sm->dropTable($tableName);
		}

		foreach ($this->getDefaultWidgetSetup() AS $widgetKey => $widgetFn)
		{
			$this->deleteWidget($widgetKey);
		}
	}

	public function uninstallStep2()
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_user', function(Alter $table)
		{
			$table->dropColumns('xfrm_resource_count');
		});
	}

	public function uninstallStep3()
	{
		$db = $this->db();

		$contentTypes = ['resource', 'resource_category', 'resource_update', 'resource_version', 'resource_rating'];

		$this->uninstallContentTypeData($contentTypes);

		$db->beginTransaction();

		$db->delete('xf_admin_permission_entry', "admin_permission_id = 'resourceManager'");
		$db->delete('xf_permission_cache_content', "content_type = 'resource_category'");
		$db->delete('xf_permission_entry', "permission_group_id = 'resource'");
		$db->delete('xf_permission_entry_content', "permission_group_id = 'resource'");

		$db->commit();
	}

	// ############################# TABLE / DATA DEFINITIONS ##############################

	protected function getTables()
	{
		$tables = [];

		$tables['xf_rm_category'] = function(Create $table)
		{
			$table->addColumn('resource_category_id', 'int')->autoIncrement();
			$table->addColumn('title', 'varchar', 100);
			$table->addColumn('description', 'text');
			$table->addColumn('parent_category_id', 'int')->setDefault(0);
			$table->addColumn('display_order', 'int')->setDefault(0);
			$table->addColumn('lft', 'int')->setDefault(0);
			$table->addColumn('rgt', 'int')->setDefault(0);
			$table->addColumn('depth', 'smallint')->setDefault(0);
			$table->addColumn('breadcrumb_data', 'blob');
			$table->addColumn('resource_count', 'int')->setDefault(0);
			$table->addColumn('featured_count', 'smallint')->setDefault(0);
			$table->addColumn('last_update', 'int')->setDefault(0);
			$table->addColumn('last_resource_title', 'varchar', 100)->setDefault('');
			$table->addColumn('last_resource_id', 'int')->setDefault(0);
			$table->addColumn('field_cache', 'mediumblob');
			$table->addColumn('review_field_cache', 'mediumblob');
			$table->addColumn('prefix_cache', 'mediumblob');
			$table->addColumn('require_prefix', 'tinyint')->setDefault(0);
			$table->addColumn('thread_node_id', 'int')->setDefault(0);
			$table->addColumn('thread_prefix_id', 'int')->setDefault(0);
			$table->addColumn('allow_local', 'tinyint')->setDefault(0);
			$table->addColumn('allow_external', 'tinyint')->setDefault(0);
			$table->addColumn('allow_commercial_external', 'tinyint')->setDefault(0);
			$table->addColumn('allow_fileless', 'tinyint')->setDefault(0);
			$table->addColumn('always_moderate_create', 'tinyint')->setDefault(0);
			$table->addColumn('always_moderate_update', 'tinyint')->setDefault(0);
			$table->addColumn('min_tags', 'smallint')->setDefault(0);
			$table->addColumn('enable_versioning', 'tinyint')->setDefault(1);
			$table->addColumn('enable_support_url', 'tinyint')->setDefault(1);
			$table->addKey(['parent_category_id', 'lft']);
			$table->addKey(['lft', 'rgt']);
		};

		$tables['xf_rm_category_field'] = function(Create $table)
		{
			$table->addColumn('field_id', 'varbinary', 25);
			$table->addColumn('resource_category_id', 'int');
			$table->addPrimaryKey(['field_id', 'resource_category_id']);
			$table->addKey('resource_category_id');
		};

		$tables['xf_rm_category_prefix'] = function(Create $table)
		{
			$table->addColumn('resource_category_id', 'int');
			$table->addColumn('prefix_id', 'int');
			$table->addPrimaryKey(['resource_category_id', 'prefix_id']);
			$table->addKey('prefix_id');
		};

		$tables['xf_rm_category_review_field'] = function(Create $table)
		{
			$table->addColumn('field_id', 'varbinary', 25);
			$table->addColumn('resource_category_id', 'int');
			$table->addPrimaryKey(['field_id', 'resource_category_id']);
			$table->addKey('resource_category_id');
		};

		$tables['xf_rm_category_watch'] = function(Create $table)
		{
			$table->addColumn('user_id', 'int');
			$table->addColumn('resource_category_id', 'int');
			$table->addColumn('notify_on', 'enum')->values(['','resource','update']);
			$table->addColumn('send_alert', 'tinyint');
			$table->addColumn('send_email', 'tinyint');
			$table->addColumn('include_children', 'tinyint');
			$table->addPrimaryKey(['user_id', 'resource_category_id']);
			$table->addKey(['resource_category_id', 'notify_on'], 'node_id_notify_on');
		};

		$tables['xf_rm_resource'] = function(Create $table)
		{
			$table->addColumn('resource_id', 'int')->autoIncrement();
			$table->addColumn('title', 'varchar', 100)->setDefault('');
			$table->addColumn('tag_line', 'varchar', 100)->setDefault('');
			$table->addColumn('user_id', 'int');
			$table->addColumn('username', 'varchar', 100)->setDefault('');
			$table->addColumn('team_member_user_ids', 'blob');
			$table->addColumn('resource_state', 'enum')->values(['visible','moderated','deleted'])->setDefault('visible');
			$table->addColumn('resource_type', 'varchar', 25);
			$table->addColumn('resource_date', 'int');
			$table->addColumn('resource_category_id', 'int');
			$table->addColumn('current_version_id', 'int');
			$table->addColumn('description_update_id', 'int')->comment('Points to the resource update that acts as the description for this resource');
			$table->addColumn('discussion_thread_id', 'int')->comment('Points to an automatically-created thread for this resource');
			$table->addColumn('external_url', 'varchar', 500)->setDefault('');
			$table->addColumn('external_purchase_url', 'varchar', 500)->setDefault('');
			$table->addColumn('price', 'decimal', '10,2')->setDefault(0.00);
			$table->addColumn('currency', 'varchar', 3)->setDefault('');
			$table->addColumn('view_count', 'int')->setDefault(0);
			$table->addColumn('download_count', 'int')->setDefault(0);
			$table->addColumn('rating_count', 'int')->setDefault(0);
			$table->addColumn('rating_sum', 'int')->setDefault(0);
			$table->addColumn('rating_avg', 'float', '')->setDefault(0);
			$table->addColumn('rating_weighted', 'float', '')->setDefault(0);
			$table->addColumn('update_count', 'int')->setDefault(0);
			$table->addColumn('review_count', 'int')->setDefault(0);
			$table->addColumn('last_update', 'int');
			$table->addColumn('alt_support_url', 'varchar', 500)->setDefault('');
			$table->addColumn('custom_fields', 'mediumblob');
			$table->addColumn('prefix_id', 'int')->setDefault(0);
			$table->addColumn('icon_date', 'int')->setDefault(0);
			$table->addColumn('tags', 'mediumblob');
			$table->addKey(['resource_category_id', 'last_update'], 'category_last_update');
			$table->addKey(['resource_category_id', 'rating_weighted'], 'category_rating_weighted');
			$table->addKey('last_update');
			$table->addKey('rating_weighted');
			$table->addKey(['user_id', 'last_update']);
			$table->addKey('discussion_thread_id');
			$table->addKey('prefix_id');
		};

		$tables['xf_rm_resource_update'] = function(Create $table)
		{
			$table->addColumn('resource_update_id', 'int', 11)->autoIncrement();
			$table->addColumn('resource_id', 'int');
			$table->addColumn('team_user_id', 'int');
			$table->addColumn('team_username', 'varchar', 50);
			$table->addColumn('title', 'varchar', 100)->setDefault('')->comment('Title field is optional, and is not used in the first post.');
			$table->addColumn('message', 'mediumtext')->comment('Supports BB code');
			$table->addColumn('message_state', 'enum')->values(['visible','moderated','deleted'])->setDefault('visible');
			$table->addColumn('post_date', 'int');
			$table->addColumn('attach_count', 'int')->setDefault(0);
			$table->addColumn('reaction_score', 'int')->unsigned(false)->setDefault(0);
			$table->addColumn('reactions', 'blob')->nullable();
			$table->addColumn('reaction_users', 'blob');
			$table->addColumn('ip_id', 'int')->setDefault(0);
			$table->addColumn('warning_id', 'int')->setDefault(0);
			$table->addColumn('warning_message', 'varchar', 255)->setDefault('');
			$table->addColumn('last_edit_date', 'int')->setDefault(0);
			$table->addColumn('last_edit_user_id', 'int')->setDefault(0);
			$table->addColumn('edit_count', 'int')->setDefault(0);
			$table->addColumn('embed_metadata', 'blob')->nullable();
			$table->addKey(['resource_id', 'post_date']);
			$table->addKey('team_user_id');
		};

		$tables['xf_rm_resource_download'] = function(Create $table)
		{
			$table->addColumn('resource_download_id', 'int')->autoIncrement();
			$table->addColumn('resource_version_id', 'int');
			$table->addColumn('user_id', 'int');
			$table->addColumn('resource_id', 'int');
			$table->addColumn('last_download_date', 'int');
			$table->addUniqueKey(['resource_version_id', 'user_id'], 'version_user');
			$table->addKey(['user_id', 'resource_id'], 'user_resource');
		};

		$tables['xf_rm_resource_feature'] = function(Create $table)
		{
			$table->addColumn('resource_id', 'int');
			$table->addColumn('feature_date', 'int');
			$table->addPrimaryKey('resource_id');
			$table->addKey('feature_date');
		};

		$tables['xf_rm_resource_field'] = function(Create $table)
		{
			$table->addColumn('field_id', 'varbinary', 25);
			$table->addColumn('display_group', 'varchar', 25)->setDefault('above_info');
			$table->addColumn('display_order', 'int')->setDefault(1);
			$table->addColumn('field_type', 'varbinary', 25)->setDefault('textbox');
			$table->addColumn('field_choices', 'blob');
			$table->addColumn('match_type', 'varbinary', 25)->setDefault('none');
			$table->addColumn('match_params', 'blob');
			$table->addColumn('max_length', 'int')->setDefault(0);
			$table->addColumn('required', 'tinyint')->setDefault(0);
			$table->addColumn('display_template', 'text');
			$table->addColumn('wrapper_template', 'text');
			$table->addPrimaryKey('field_id');
			$table->addKey(['display_group', 'display_order'], 'display_group_order');
		};

		$tables['xf_rm_resource_field_value'] = function(Create $table)
		{
			$table->addColumn('resource_id', 'int');
			$table->addColumn('field_id', 'varbinary', 25);
			$table->addColumn('field_value', 'mediumtext');
			$table->addPrimaryKey(['resource_id', 'field_id']);
			$table->addKey('field_id');
		};

		$tables['xf_rm_resource_review_field'] = function(Create $table)
		{
			$table->addColumn('field_id', 'varbinary', 25);
			$table->addColumn('display_group', 'varchar', 25)->setDefault('above_info');
			$table->addColumn('display_order', 'int')->setDefault(1);
			$table->addColumn('field_type', 'varbinary', 25)->setDefault('textbox');
			$table->addColumn('field_choices', 'blob');
			$table->addColumn('match_type', 'varbinary', 25)->setDefault('none');
			$table->addColumn('match_params', 'blob');
			$table->addColumn('max_length', 'int')->setDefault(0);
			$table->addColumn('required', 'tinyint')->setDefault(0);
			$table->addColumn('display_template', 'text');
			$table->addColumn('wrapper_template', 'text');
			$table->addPrimaryKey('field_id');
			$table->addKey(['display_group', 'display_order'], 'display_group_order');
		};

		$tables['xf_rm_resource_review_field_value'] = function(Create $table)
		{
			$table->addColumn('resource_rating_id', 'int');
			$table->addColumn('field_id', 'varbinary', 25);
			$table->addColumn('field_value', 'mediumtext');
			$table->addPrimaryKey(['resource_rating_id', 'field_id']);
			$table->addKey('field_id');
		};

		$tables['xf_rm_resource_prefix'] = function(Create $table)
		{
			$table->addColumn('prefix_id', 'int')->autoIncrement();
			$table->addColumn('prefix_group_id', 'int');
			$table->addColumn('display_order', 'int');
			$table->addColumn('materialized_order', 'int')->comment('Internally-set order, based on prefix_group.display_order, prefix.display_order');
			$table->addColumn('css_class', 'varchar', 50)->setDefault('');
			$table->addColumn('allowed_user_group_ids', 'blob');
			$table->addKey('materialized_order');
		};

		$tables['xf_rm_resource_prefix_group'] = function(Create $table)
		{
			$table->addColumn('prefix_group_id', 'int')->autoIncrement();
			$table->addColumn('display_order', 'int');
		};

		$tables['xf_rm_resource_rating'] = function(Create $table)
		{
			$table->addColumn('resource_rating_id', 'int')->autoIncrement();
			$table->addColumn('resource_version_id', 'int');
			$table->addColumn('user_id', 'int');
			$table->addColumn('rating', 'tinyint');
			$table->addColumn('rating_date', 'int');
			$table->addColumn('message', 'mediumtext');
			$table->addColumn('resource_id', 'int');
			$table->addColumn('version_string', 'varchar', 50);
			$table->addColumn('author_response_team_user_id', 'int')->setDefault(0);
			$table->addColumn('author_response_team_username', 'varchar', 50)->setDefault('');
			$table->addColumn('author_response', 'mediumtext');
			$table->addColumn('is_review', 'tinyint')->setDefault(0);
			$table->addColumn('count_rating', 'tinyint')->setDefault(1)->comment('Whether this counts towards the global resource rating.');
			$table->addColumn('rating_state', 'enum')->values(['visible','deleted'])->setDefault('visible');
			$table->addColumn('warning_id', 'int')->setDefault(0);
			$table->addColumn('is_anonymous', 'tinyint')->setDefault(0);
			$table->addColumn('custom_fields', 'mediumblob');
			$table->addColumn('vote_score', 'int')->unsigned(false);
			$table->addColumn('vote_count', 'int')->setDefault(0);
			$table->addUniqueKey(['resource_version_id', 'user_id'], 'version_user_id');
			$table->addKey('user_id');
			$table->addKey(['count_rating', 'resource_id']);
			$table->addKey(['resource_id', 'rating_date']);
			$table->addKey('rating_date');
			$table->addKey('author_response_team_user_id');
		};

		$tables['xf_rm_resource_team_member'] = function(Create $table)
		{
			$table->addColumn('resource_id', 'int');
			$table->addColumn('user_id', 'int');
			$table->addPrimaryKey(['resource_id', 'user_id']);
			$table->addKey('user_id');
		};

		$tables['xf_rm_resource_version'] = function(Create $table)
		{
			$table->addColumn('resource_version_id', 'int')->autoIncrement();
			$table->addColumn('resource_id', 'int');
			$table->addColumn('team_user_id', 'int');
			$table->addColumn('team_username', 'varchar', 50);
			$table->addColumn('version_string', 'varchar', 50);
			$table->addColumn('release_date', 'int');
			$table->addColumn('download_url', 'varchar', 250)->setDefault('');
			$table->addColumn('download_count', 'int')->setDefault(0);
			$table->addColumn('rating_count', 'int')->setDefault(0);
			$table->addColumn('rating_sum', 'int')->setDefault(0);
			$table->addColumn('version_state', 'enum')->values(['visible','moderated','deleted'])->setDefault('visible');
			$table->addColumn('file_count', 'smallint')->setDefault(0);
			$table->addKey(['resource_id', 'release_date']);
			$table->addKey('team_user_id');
		};

		$tables['xf_rm_resource_view'] = function(Create $table)
		{
			$table->engine('MEMORY');

			$table->addColumn('resource_id', 'int');
			$table->addColumn('total', 'int');
			$table->addPrimaryKey('resource_id');
		};

		$tables['xf_rm_resource_watch'] = function(Create $table)
		{
			$table->addColumn('user_id', 'int');
			$table->addColumn('resource_id', 'int');
			$table->addColumn('email_subscribe', 'tinyint')->setDefault(0);
			$table->addPrimaryKey(['user_id', 'resource_id']);
			$table->addKey(['resource_id', 'email_subscribe']);
		};

		return $tables;
	}

	protected function getDefaultWidgetSetup()
	{
		return [
			'xfrm_list_featured_resources' => function($key, array $options = [])
			{
				$options = array_replace([
					'style' => 'carousel'
				], $options);

				$this->createWidget(
					$key,
					'xfrm_featured_resources',
					[
						'positions' => [
							'xfrm_overview_above_resources' => 100,
							'xfrm_category_above_resources' => 100
						],
						'options' => $options
					]
				);
			},
			'xfrm_list_top_resources' => function($key, array $options = [])
			{
				$options = array_replace([], $options);

				$this->createWidget(
					$key,
					'xfrm_top_resources',
					[
						'positions' => [
							'xfrm_overview_sidenav' => 100,
							'xfrm_category_sidenav' => 100,
						],
						'options' => $options
					]
				);
			},
			'xfrm_overview_latest_reviews' => function($key, array $options = [])
			{
				$options = array_replace([], $options);

				$this->createWidget(
					$key,
					'xfrm_latest_reviews',
					[
						'positions' => ['xfrm_overview_sidenav' => 200],
						'options' => $options
					]
				);
			},
			'xfrm_overview_top_authors' => function($key, array $options = [])
			{
				$options = array_replace([
					'member_stat_key' => 'xfrm_most_resources'
				], $options);

				$this->createWidget(
					$key,
					'member_stat',
					[
						'positions' => ['xfrm_overview_sidenav' => 300],
						'options' => $options
					]
				);
			},
			'xfrm_whats_new_overview_new_resources' => function($key, array $options = [])
			{
				$options = array_replace([
					'limit' => 10,
					'style' => 'full'
				], $options);

				$this->createWidget(
					$key,
					'xfrm_new_resources',
					[
						'positions' => ['whats_new_overview' => 200],
						'options' => $options
					]
				);
			},
			'xfrm_forum_overview_new_resources' => function($key, array $options = [])
			{
				$options = array_replace([], $options);

				$this->createWidget(
					$key,
					'xfrm_new_resources',
					[
						'positions' => [
							'forum_list_sidebar' => 38,
							'forum_new_posts_sidebar' => 28
						],
						'options' => $options
					]
				);
			},
		];
	}

	protected function insertNamedWidget($key, array $options = [])
	{
		$widgets = $this->getDefaultWidgetSetup();
		if (!isset($widgets[$key]))
		{
			throw new \InvalidArgumentException("Unknown widget '$key'");
		}

		$widgetFn = $widgets[$key];
		$widgetFn($key, $options);
	}

	protected function applyDefaultPermissions($previousVersion = null)
	{
		$applied = false;

		if (!$previousVersion)
		{
			$this->applyGlobalPermission('resource', 'view', 'general', 'viewNode');
			$this->applyGlobalPermission('resource', 'viewUpdateAttach', 'general', 'viewNode');
			$this->applyGlobalPermission('resource', 'download', 'forum', 'viewAttachment');
			$this->applyGlobalPermission('resource', 'react', 'forum', 'react');
			$this->applyGlobalPermission('resource', 'rate', 'forum', 'react');
			$this->applyGlobalPermission('resource', 'add', 'forum', 'postThread');
			$this->applyGlobalPermission('resource', 'uploadUpdateAttach', 'forum', 'postThread');
			$this->applyGlobalPermission('resource', 'updateOwn', 'forum', 'editOwnPost');
			$this->applyGlobalPermission('resource', 'reviewReply', 'forum', 'editOwnPost');
			$this->applyGlobalPermission('resource', 'deleteOwn', 'forum', 'deleteOwnPost');
			$this->applyGlobalPermission('resource', 'viewDeleted', 'forum', 'viewDeleted');
			$this->applyGlobalPermission('resource', 'deleteAny', 'forum', 'deleteAnyPost');
			$this->applyGlobalPermission('resource', 'undelete', 'forum', 'undelete');
			$this->applyGlobalPermission('resource', 'hardDeleteAny', 'forum', 'hardDeleteAnyPost');
			$this->applyGlobalPermission('resource', 'deleteAnyReview', 'forum', 'deleteAnyPost');
			$this->applyGlobalPermission('resource', 'editAny', 'forum', 'editAnyPost');
			$this->applyGlobalPermission('resource', 'reassign', 'forum', 'editAnyPost');
			$this->applyGlobalPermission('resource', 'viewModerated', 'forum', 'viewModerated');
			$this->applyGlobalPermission('resource', 'approveUnapprove', 'forum', 'approveUnapprove');

			$applied = true;
		}

		if (!$previousVersion || $previousVersion < 1010031)
		{
			$this->applyGlobalPermission('resource', 'featureUnfeature', 'forum', 'stickUnstickThread');
			$this->applyGlobalPermission('resource', 'warn', 'forum', 'warn');

			$applied = true;
		}

		if (!$previousVersion || $previousVersion < 1020000)
		{
			$this->applyGlobalPermission('resource', 'tagOwnResource', 'resource', 'add');
			$this->applyGlobalPermission('resource', 'manageOthersTagsOwnRes', 'resource', 'deleteSelf');
			$this->applyGlobalPermission('resource', 'manageAnyTag', 'resource', 'editAny');

			$applied = true;
		}

		if (!$previousVersion || $previousVersion < 2000010)
		{
			$this->query("
				REPLACE INTO xf_permission_entry
					(user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int)
				SELECT DISTINCT user_group_id, user_id, 'resource', 'inlineMod', 'allow', 0
				FROM xf_permission_entry
				WHERE permission_group_id = 'resource'
					AND permission_id IN ('deleteAny', 'undelete', 'approveUnapprove', 'reassign', 'editAny', 'featureUnfeature')
			");
			$this->query("
				REPLACE INTO xf_permission_entry_content
					(content_type, content_id, user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int)
				SELECT DISTINCT content_type, content_id, user_group_id, user_id, 'resource', 'inlineMod', 'content_allow', 0
				FROM xf_permission_entry_content
				WHERE permission_group_id = 'resource'
					AND permission_id IN ('deleteAny', 'undelete', 'approveUnapprove', 'reassign', 'editAny', 'featureUnfeature')
			");

			$applied = true;
		}

		if (!$previousVersion || $previousVersion < 2020010)
		{
			$this->applyGlobalPermission('resource', 'contentVote', 'resource', 'rate');
			$this->applyContentPermission('resource', 'contentVote', 'resource', 'rate');
			$this->applyGlobalPermission('resource', 'manageOwnTeam', 'resource', 'updateOwn');
			$this->applyContentPermission('resource', 'manageOwnTeam', 'resource', 'updateOwn');

			$applied = true;
		}

		return $applied;
	}
}