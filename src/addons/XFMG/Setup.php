<?php

namespace XFMG;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\Db\Schema\Alter;
use XF\Db\Schema\Create;
use XF\Util\File;
use XFMG\Install\Data\MySql;

class Setup extends AbstractSetup
{
	use StepRunnerInstallTrait;
	use StepRunnerUpgradeTrait;
	use StepRunnerUninstallTrait;

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
		foreach ($this->getData() AS $dataSql)
		{
			$this->query($dataSql);
		}
	}

	public function installStep3()
	{
		$this->schemaManager()->alterTable('xf_user', function(Alter $table)
		{
			$table->addColumn('xfmg_album_count', 'int')->setDefault(0);
			$table->addColumn('xfmg_media_count', 'int')->setDefault(0);
			$table->addColumn('xfmg_media_quota', 'int')->setDefault(0);
			$table->addKey('xfmg_album_count', 'xengallery_album_count');
			$table->addKey('xfmg_media_count', 'xengallery_media_count');
		});
	}

	public function installStep4()
	{
		$this->alterTable('xf_attachment_data', function(Alter $table)
		{
			$table->addColumn('xfmg_mirror_media_id', 'int')->setDefault(0);
		});
	}

	public function installStep5()
	{
		$this->alterTable('xf_attachment', function(Alter $table)
		{
			$table->addColumn('xfmg_is_mirror_handler', 'tinyint')->setDefault(0);
		});
	}

	public function installStep6()
	{
		$this->alterTable('xf_forum', function(Alter $table)
		{
			$table->addColumn('xfmg_media_mirror_category_id', 'int')->setDefault(0);
		});
	}

	public function installStep7()
	{
		$this->applyDefaultPermissions();
	}

	public function upgrade110Step1()
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xengallery_media', function(Alter $table)
		{
			$table->addColumn('media_caption', 'text')->nullable()->after('media_description');
			$table->addColumn('media_type', 'enum')->values(['image', 'video'])->setDefault('image')->after('media_date');
			$table->addColumn('view_count', 'int')->setDefault(0)->after('rating_count');
			$table->addColumn('media_embed_url', 'text')->nullable()->after('media_tag');
		});
	}

	public function upgrade110Step2()
	{
		$this->schemaManager()->alterTable('xengallery_category', function(Alter $table)
		{
			$table->addColumn('allowed_types', 'blob')->nullable()->after('upload_user_groups');
		});

		$this->db()->update('xengallery_category', [
			'allowed_types' => 'a:1:{i:0;s:3:"all";}'
		], null);
	}

	public function upgrade112Step1()
	{
		$sm = $this->schemaManager();
		$db = $this->db();

		$sm->alterTable('xengallery_media', function(Alter $table)
		{
			$table->changeColumn('media_type')->values(['image', 'video', 'image_upload', 'video_embed'])->setDefault('image');
		});

		$db->update('xengallery_media', [
			'media_type' => 'image_upload'
		], 'media_type = ?', 'image');

		$db->update('xengallery_media', [
			'media_type' => 'video_embed'
		], 'media_type = ?', 'video');

		$sm->alterTable('xengallery_media', function(Alter $table)
		{
			$table->changeColumn('media_type')->values(['image_upload', 'video_embed'])->setDefault('image_upload');
		});
	}

	public function upgrade120Step1()
	{
		$this->schemaManager()->alterTable('xengallery_media', function(Alter $table)
		{
			$table->addColumn('last_comment_date', 'int')->setDefault(0)->after('media_date');
		});
	}

	public function upgrade2000070Step1()
	{
		// Adds all of the tables which were introduced in this version
		foreach ($this->getLegacyTables()[2000070] AS $tableSql)
		{
			$this->query($tableSql);
		}
	}

	public function upgrade2000070Step2()
	{
		$this->schemaManager()->alterTable('xengallery_category', function(Alter $table)
		{
			$table->renameColumn('xengallery_category_id', 'category_id');
		});
	}

	public function upgrade2000070Step3()
	{
		$this->schemaManager()->alterTable('xengallery_media', function(Alter $table)
		{
			$table->addColumn('album_id', 'int')->setDefault(0)->after('media_state');
			$table->addColumn('media_privacy', 'enum')->values(['private', 'public', 'shared', 'members', 'followed', 'category'])->setDefault('public')->after('album_id');
			$table->addColumn('last_edit_date', 'int')->setDefault(0)->after('media_date');
			$table->addColumn('custom_media_fields', 'mediumblob')->nullable()->after('media_state');
			$table->addColumn('watermark_id', 'int')->setDefault(0);
			$table->addColumn('media_exif_data_cache', 'mediumblob');
			$table->addColumn('media_exif_data_cache_full', 'mediumblob')->after('media_exif_data_cache');
			$table->changeColumn('media_title', 'text');
			$table->changeColumn('media_description', 'text');
			$table->changeColumn('media_type')->values(['image_upload', 'video_upload', 'video_embed']);
			$table->renameColumn('xengallery_category_id', 'category_id');
			$table->renameColumn('media_view_count', 'view_count');
		});
	}

	public function upgrade2000070Step4()
	{
		$this->schemaManager()->alterTable('xengallery_rating', function(Alter $table)
		{
			$table->renameColumn('media_rating_id', 'rating_id');
			$table->renameColumn('media_id', 'content_id');
			$table->addColumn('content_type', 'enum')->values(['album', 'media']);
		});
	}

	public function upgrade2000070Step5()
	{
		$this->schemaManager()->alterTable('xengallery_category', function(Alter $table)
		{
			$table->addColumn('category_media_count', 'int')->setDefault(0);
			$table->addColumn('field_cache', 'mediumblob');
		});
	}

	public function upgrade2000070Step6()
	{
		$this->schemaManager()->alterTable('xengallery_comment', function(Alter $table)
		{
			$table->renameColumn('media_id', 'content_id');
			$table->renameColumn('media_comment', 'message');
			$table->addColumn('content_type', 'enum')->values(['album', 'media']);
		});
	}

	public function upgrade2000070Step7()
	{
		$this->schemaManager()->alterTable('xengallery_album', function(Alter $table)
		{
			$table->addColumn('album_comment_count', 'int')->setDefault(0)->after('album_rating_avg');
			$table->addColumn('album_last_comment_date', 'int')->setDefault(0)->after('album_comment_count');
		});
	}

	public function upgrade2000070Step8()
	{
		$this->db()->update('xf_option', [
			'option_value' => 'a:2:{s:5:"width";s:3:"300";s:6:"height";s:3:"300";}'
		], 'option_id = ?', 'xengalleryThumbnailDimension');
	}

	public function upgrade2010070Step1()
	{
		// Adds all of the tables which were introduced in this version
		foreach ($this->getLegacyTables()[2010070] AS $tableSql)
		{
			$this->query($tableSql);
		}
	}

	public function upgrade2010070Step2()
	{
		$this->schemaManager()->alterTable('xengallery_media', function(Alter $table)
		{
			$table->addColumn('warning_id', 'int')->setDefault(0);
			$table->addColumn('warning_message', 'varchar', 255)->setDefault('');
			$table->addColumn('position', 'int')->setDefault(0);
			$table->addColumn('imported', 'int')->setDefault(0);
			$table->addColumn('thumbnail_date', 'int')->setDefault(0);
			$table->changeColumn('media_tag', 'text')->nullable(true);
			$table->addKey('position');
		});
	}

	public function upgrade2010070Step3()
	{
		$this->schemaManager()->alterTable('xengallery_album', function(Alter $table)
		{
			$table->addColumn('warning_id', 'int')->setDefault(0);
			$table->addColumn('warning_message', 'varchar', 255)->setDefault('');
			$table->addColumn('album_default_order', 'enum')->values(['', 'custom'])->setDefault('');
			$table->addColumn('album_thumbnail_date', 'int')->setDefault(0);
			$table->addColumn('manual_media_cache', 'tinyint')->setDefault(0)->after('media_cache');
			$table->renameColumn('user_id', 'album_user_id');
			$table->renameColumn('username', 'album_username');
			$table->renameColumn('random_media_cache', 'media_cache');
		});
	}

	public function upgrade2010070Step4()
	{
		$this->schemaManager()->alterTable('xengallery_comment', function(Alter $table)
		{
			$table->addColumn('warning_id', 'int')->setDefault(0);
			$table->addColumn('warning_message', 'varchar', 255)->setDefault('');
		});
	}

	public function upgrade2010070Step5()
	{
		$this->schemaManager()->alterTable('xengallery_user_tag', function(Alter $table)
		{
			$table->addColumn('tag_by_user_id', 'int')->setDefault(0);
			$table->addColumn('tag_by_username', 'varchar', 50)->setDefault('');
			$table->addColumn('tag_state', 'enum')->values(['approved', 'pending', 'rejected'])->setDefault('approved');
			$table->addColumn('tag_state_date', 'int')->setDefault(0);
		});
	}

	public function upgrade2010670Step1()
	{
		$this->schemaManager()->alterTable('xengallery_rating', function(Alter $table)
		{
			$table->dropIndexes('media_user_id');
			$table->addKey(['content_type', 'content_id', 'user_id'], 'content_type_id_user_id');
		});
	}

	public function upgrade2010670Step2()
	{
		$this->schemaManager()->alterTable('xengallery_album', function(Alter $table)
		{
			$table->changeColumn('album_title', 'text');
			$table->changeColumn('album_description', 'text');
		});
	}

	public function upgrade2010870Step1()
	{
		$this->schemaManager()->alterTable('xengallery_album', function(Alter $table)
		{
			$table->changeColumn('media_cache', 'mediumblob')->nullable();
		});
	}

	public function upgrade2010870Step2()
	{
		$this->schemaManager()->alterTable('xengallery_rating', function(Alter $table)
		{
			$table->dropIndexes('content_type_id_user_id');
		});
	}

	public function upgrade2010870Step3()
	{
		$sm = $this->schemaManager();

		$sm->createTable('xengallery_rating_temp', function(Create $table)
		{
			$table->addColumn('rating_id', 'int')->autoIncrement();
			$table->addColumn('content_id', 'int');
			$table->addColumn('content_type', 'varbinary', 25);
			$table->addColumn('user_id', 'int');
			$table->addColumn('username', 'varchar', 50)->setDefault('');
			$table->addColumn('rating', 'tinyint');
			$table->addColumn('rating_date', 'int');
			$table->addUniqueKey(['content_type', 'content_id', 'user_id'], 'content_type_id_user_id');
		});

		$this->query("
			INSERT IGNORE INTO xengallery_rating_temp
			SELECT *
			FROM xengallery_rating
		");

		$sm->dropTable('xengallery_rating');
		$sm->renameTable('xengallery_rating_temp', 'xengallery_rating');
	}

	public function upgrade901000170Step1()
	{
		$this->schemaManager()->alterTable('xengallery_album', function(Alter $table)
		{
			$table->addColumn('album_rating_weighted', 'float')->setDefault(0)->after('album_rating_avg');
		});
	}

	public function upgrade901000170Step2()
	{
		$this->schemaManager()->alterTable('xengallery_media', function(Alter $table)
		{
			$table->addColumn('rating_weighted', 'float')->setDefault(0)->after('rating_avg');
		});
	}

	public function upgrade901000470Step1(array $stepParams)
	{
		$stepParams = array_replace([
			'queryKeys' => [
				'xengallery_media_drop',
				'xengallery_media_add',
				'xengallery_album',
				'xengallery_comment',
				'xengallery_user_tag',
				'xf_user'
			]
		], $stepParams);

		$queries = [
			'xengallery_media_drop' => 'ALTER TABLE xengallery_media DROP INDEX user_id',
			'xengallery_media_add' => 'ALTER TABLE xengallery_media ADD INDEX user_id_media_date (user_id, media_date), ADD INDEX album_id_media_date (album_id, media_date), ADD INDEX category_id_media_date (category_id, media_date)',
			'xengallery_album' => 'ALTER TABLE xengallery_album ADD INDEX album_create_date (album_create_date), ADD INDEX album_user_id_album_create_date (album_user_id, album_create_date)',
			'xengallery_comment' => 'ALTER TABLE xengallery_comment ADD INDEX comment_date (comment_date), ADD INDEX content_type_content_id_comment_date (content_type, content_id, comment_date)',
			'xengallery_user_tag' => 'ALTER TABLE xengallery_user_tag ADD INDEX media_id (media_id)',
			'xf_user' => 'ALTER TABLE xf_user ADD INDEX xengallery_media_count (xengallery_media_count), ADD INDEX xengallery_album_count (xengallery_album_count)'
		];

		if (!$stepParams['queryKeys'])
		{
			return true;
		}

		$db = $this->db();
		$startTime = microtime(true);
		$maxRunTime = $this->app->config('jobMaxRunTime');

		foreach ($stepParams['queryKeys'] AS $key => $name)
		{
			$query = isset($queries[$name]) ? $queries[$name] : null;
			if (!$query)
			{
				continue;
			}

			try
			{
				$db->query($query);
				unset($stepParams['queryKeys'][$key]);
			}
			catch (\XF\Db\Exception $e)
			{
				if ($name != 'xengallery_media_drop') // skip logging an error about this as an error here may be expected.
				{
					\XF::logException($e, false, "XFMG: Error adding index(es) ($name): ");
				}

				unset($stepParams['queryKeys'][$key]);
				continue;
			}

			if ($maxRunTime && microtime(true) - $startTime > $maxRunTime)
			{
				break;
			}
		}

		return $stepParams;
	}

	public function upgrade901000570Step1()
	{
		$this->db()->update('xengallery_media', [
			'media_privacy' => 'category'
		], 'category_id > 0 AND media_privacy <> \'category\'');
	}

	public function upgrade901000570Step2(array $stepParams)
	{
		$stepParams = array_replace([
			'position' => 0
		], $stepParams);

		$db = $this->db();

		$albumIds = $db->fetchAllColumn($db->limit('
			SELECT album.album_id
			FROM xengallery_album AS album
			INNER JOIN xengallery_album_permission AS perm ON
				(album.album_id = perm.album_id AND perm.permission = \'view\')
			WHERE album.album_id > ?
				AND perm.access_type IN(\'shared\', \'followed\')
			ORDER BY album_id
		', 10), $stepParams['position']);
		if (!$albumIds)
		{
			return true;
		}

		$db->beginTransaction();

		foreach ($albumIds AS $albumId)
		{
			$stepParams['position'] = $albumId;

			$album = $db->fetchOne('
				SELECT *
				FROM xengallery_album
				WHERE album_id = ?
			', $albumId);

			$bind = [
				$album['album_id'],
				$album['album_user_id']
			];

			$ownerShared = $db->fetchOne(
				'SELECT shared_user_id FROM xengallery_shared_map WHERE album_id = ? AND shared_user_id = ?', $bind
			);
			if (!$ownerShared)
			{
				$db->query('
					INSERT IGNORE INTO xengallery_shared_map
						(album_id, shared_user_id)
					VALUES
						(?, ?)
				', $bind);
			}
		}

		$db->commit();

		return $stepParams;
	}

	public function upgrade901010070Step1()
	{
		// Adds all of the tables which were introduced in this version
		foreach ($this->getLegacyTables()[901010070] AS $tableSql)
		{
			$this->query($tableSql);
		}
	}

	public function upgrade901010070Step2()
	{
		$db = $this->db();

		$db->delete('xf_content_type_field', "content_type = 'xengallery_content_tag'");
	}

	public function upgrade901010070Step3()
	{
		$db = $this->db();

		// Categories which are "all" may not necessarily want video upload enabling - should be opt in.
		foreach ($db->fetchAll('SELECT * FROM xengallery_category') AS $category)
		{
			$allowedTypes = @unserialize($category['allowed_types']);
			if (!$allowedTypes || !in_array('all', $allowedTypes))
			{
				continue;
			}

			$allowedTypes = [
				'image_upload', 'video_embed'
			];
			$db->update('xengallery_category', ['allowed_types' => serialize($allowedTypes)], 'category_id = ' . $db->quote($category['category_id']));
		}
	}

	public function upgrade901010070Step4()
	{
		$this->schemaManager()->alterTable('xengallery_media', function(Alter $table)
		{
			$table->addColumn('tags', 'mediumblob');
			$table->dropColumns('media_content_tag_cache');
		});
	}

	public function upgrade901010070Step5()
	{
		$this->schemaManager()->alterTable('xengallery_category', function(Alter $table)
		{
			$table->addColumn('min_tags', 'smallint')->setDefault(0);
		});
	}

	public function upgrade901010070Step6()
	{
		$this->schemaManager()->alterTable('xengallery_field', function(Alter $table)
		{
			$table->addColumn('display_add_media', 'tinyint')->setDefault(0);
			$table->addColumn('required', 'tinyint')->setDefault(0);
		});
	}

	public function upgrade901010170Step1()
	{
		$this->schemaManager()->alterTable('xengallery_album', function(Alter $table)
		{
			$table->renameColumn('warning_id', 'album_warning_id');
			$table->renameColumn('warning_message', 'album_warning_message');
		});
	}

	public function upgrade901010470Step1()
	{
		$this->db()->update('xf_option', [
			'option_value' => 10
		], "option_id = 'xengalleryMaxCommentsPerPage' AND option_value = 0");
	}

	public function upgrade901010570Step1()
	{
		$this->schemaManager()->alterTable('xengallery_comment', function(Alter $table)
		{
			$table->addKey('user_id');
		});
	}

	public function upgrade901011170Step1()
	{
		$this->schemaManager()->alterTable('xengallery_comment', function(Alter $table)
		{
			$table->addKey('rating_id');
		});
	}

	public function upgrade901011270Step1()
	{
		$this->schemaManager()->alterTable('xengallery_media', function(Alter $table)
		{
			$table->addKey('media_date');
		});
	}

	public function upgrade902000010Step1()
	{
		$sm = $this->schemaManager();

		$renameTables = [
			'album',
			'album_permission',
			'album_view',
			'album_watch',
			'category',
			'category_watch',
			'comment',
			'media_user_view',
			'media_view',
			'media_watch',
			'rating',
			'transcode_queue'
		];

		foreach ($renameTables AS $renameTable)
		{
			$sm->renameTable("xengallery_{$renameTable}", "xf_mg_{$renameTable}");
		}

		$sm->renameTable('xengallery_media', 'xf_mg_media_item');

		$sm->renameTable('xengallery_add_map', 'xf_mg_shared_map_add');
		$sm->renameTable('xengallery_shared_map', 'xf_mg_shared_map_view');

		$sm->renameTable('xengallery_field', 'xf_mg_media_field');
		$sm->renameTable('xengallery_field_category', 'xf_mg_category_field');
		$sm->renameTable('xengallery_field_value', 'xf_mg_media_field_value');

		$sm->renameTable('xengallery_user_tag', 'xf_mg_media_note');

		$dropTables = [
			'xengallery_category_map',
			'xengallery_exif',
			'xengallery_exif_cache',
			'xengallery_private_map'
		];
		foreach ($dropTables AS $dropTable)
		{
			$sm->dropTable($dropTable);
		}
	}

	public function upgrade902000010Step2()
	{
		$this->query("
			DELETE FROM xf_route_filter
			WHERE find_route = 'xengallery/'
			AND replace_route = 'media/'
		");

		$this->query("
			DELETE FROM xf_route_filter
			WHERE find_route IN('xengallery-xfruseralbums/', 'xengallery-ewrmedio/')
		");

		// Attempt to retain any existing route filters
		$this->query("
			UPDATE xf_route_filter
			SET
				prefix = 'media',
				find_route = REPLACE(find_route, 'xengallery/', 'media/')
			WHERE prefix = 'xengallery'
		");

		$this->app->repository('XF:RouteFilter')->rebuildRouteFilterCache();
	}

	public function upgrade902000010Step3()
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_mg_category', function(Alter $table)
		{
			$table->addColumn('category_type', 'enum')->values(['media', 'album', 'container'])->setDefault('media')->after('category_breadcrumb');
		});

		if (!$sm->columnExists('view_user_groups', 'xf_mg_category'))
		{
			$sm->alterTable('xf_mg_category', function(Alter $table)
			{
				$table->addColumn('view_user_groups', 'blob')->after('category_description');
			});

			$this->query("
				UPDATE xf_mg_category
				SET view_user_groups = ?
			", 'a:1:{i:0;i:-1;}');
		}

		$db = $this->db();

		$userGroupIds = $db->fetchAllColumn('
			SELECT user_group_id
			FROM xf_user_group
		');

		$categories = $db->fetchAllKeyed('
			SELECT category_id, view_user_groups, upload_user_groups
			FROM xf_mg_category
		', 'category_id');

		foreach ($categories AS $categoryId => $groups)
		{
			$viewGroups = @unserialize($groups['view_user_groups']);
			$addGroups = @unserialize($groups['upload_user_groups']);

			if (!$addGroups)
			{
				// No add groups was implicitly a container category
				$this->query("
					UPDATE xf_mg_category
					SET category_type = 'container'
					WHERE category_id = ?
				", $categoryId);
			}

			$viewInherit = in_array('-1', $viewGroups ?: []);
			$addInherit = in_array('-1', $addGroups ?: []);

			if ($viewInherit && $addInherit)
			{
				// Everything is set to "all" so no changes needed
				continue;
			}

			$baseValues = [
				'content_type' => 'xfmg_category',
				'content_id' => $categoryId,
				'user_id' => 0,
				'permission_group_id' => 'xfmg',
				'permission_value_int' => 0
			];

			if (!$viewInherit)
			{
				foreach ($userGroupIds AS $groupId)
				{
					$viewValues = $baseValues + [
						'user_group_id' => $groupId,
						'permission_id' => 'view',
						'permission_value' => (
							$viewGroups && in_array($groupId, $viewGroups)
						) ? 'content_allow' : 'reset'
					];

					try
					{
						$db->insert('xf_permission_entry_content', $viewValues);
					}
					catch (\XF\Db\Exception $e) {}
				}
			}

			if (!$addInherit)
			{
				foreach ($userGroupIds AS $groupId)
				{
					$addValues = $baseValues + [
						'user_group_id' => $groupId,
						'permission_id' => 'add',
						'permission_value' => (
							$addGroups && in_array($groupId, $addGroups)
						) ? 'content_allow' : 'reset'
					];

					try
					{
						$db->insert('xf_permission_entry_content', $addValues);
					}
					catch (\XF\Db\Exception $e) {}
				}
			}
		}
	}

	public function upgrade902000010Step4()
	{
		$this->schemaManager()->alterTable('xf_mg_category', function(Alter $table)
		{
			$table->renameColumn('category_title', 'title');
			$table->renameColumn('category_description', 'description');
			$table->renameColumn('category_media_count', 'media_count');
			$table->renameColumn('category_breadcrumb', 'breadcrumb_data');
			$table->addColumn('lft', 'int')->setDefault(0);
			$table->addColumn('rgt', 'int')->setDefault(0);
			$table->addColumn('album_count', 'int')->setDefault(0)->after('media_count');
			$table->addColumn('comment_count', 'int')->setDefault(0)->after('album_count');
			$table->dropColumns(['view_user_groups', 'upload_user_groups']);
		});

		$this->query("
			UPDATE xf_mg_category
			SET allowed_types = ?
			WHERE allowed_types = ?
		", [serialize(['image', 'video', 'video']), serialize(['all'])]); // this is fixed in a subsequent step

		$this->query("
			UPDATE xf_mg_category
			SET
				allowed_types = REPLACE(allowed_types, 's:12:\"image_upload\"', 's:5:\"image\"'),
				allowed_types = REPLACE(allowed_types, 's:12:\"video_upload\"', 's:5:\"video\"'),
				allowed_types = REPLACE(allowed_types, 's:11:\"video_embed\"', 's:5:\"embed\"')
		");

		// update field category cache format
		$db = $this->db();
		$fieldCache = [];

		$entries = $db->fetchAll("
			SELECT *
			FROM xf_mg_category_field
		");
		foreach ($entries AS $entry)
		{
			$fieldCache[$entry['category_id']][$entry['field_id']] = $entry['field_id'];
		}

		$db->beginTransaction();

		foreach ($fieldCache AS $categoryId => $cache)
		{
			$db->update(
				'xf_mg_category',
				['field_cache' => serialize($cache)],
				'category_id = ?',
				$categoryId
			);
		}

		$db->commit();
	}

	public function upgrade902000010Step5(array $stepParams)
	{
		$stepParams = array_replace([
			'position' => 0
		], $stepParams);

		$perPage = 250;
		$db = $this->db();

		$categoryIds = $db->fetchAllColumn($db->limit(
			'
				SELECT category_id
				FROM xf_mg_category
				WHERE category_id > ?
				ORDER BY category_id
			', $perPage
		), $stepParams['position']);
		if (!$categoryIds)
		{
			return true;
		}

		$db->beginTransaction();

		foreach ($categoryIds AS $categoryId)
		{
			$count = $db->fetchOne('
				SELECT SUM(comment_count)
				FROM xf_mg_media_item
				WHERE category_id = ?
				AND media_state = \'visible\'
			', $categoryId);

			$db->update('xf_mg_category', ['comment_count' => intval($count)], 'category_id = ?', $categoryId);
		}

		$db->commit();

		$stepParams['position'] = end($categoryIds);

		return $stepParams;
	}

	public function upgrade902000010Step6()
	{
		$this->query("
			UPDATE xf_mg_media_item
			SET watermark_id = 1
			WHERE watermark_id > 0
		");

		$db = $this->db();
		$watermarkId = $db->fetchOne("
			SELECT watermark_id
			FROM xengallery_watermark
			WHERE is_site = 1
			ORDER BY watermark_date DESC
			LIMIT 1
		");

		$watermarkDefaultValue = $watermarkOptionValue = [
			'enabled' => false,
			'watermark_hash' => ''
		];

		if ($watermarkId)
		{
			$existingPath = sprintf('data://xengallery_watermark/%d/%d.jpg',
				floor($watermarkId / 1000),
				$watermarkId
			);
			if ($this->app->fs()->has($existingPath))
			{
				$temp = File::copyAbstractedPathToTempFile($existingPath);
				$watermarkHash = md5_file($temp);
				$newPath = sprintf('data://xfmg/watermark/%s.jpg',
					$watermarkHash
				);
				File::copyFileToAbstractedPath($temp, $newPath);
				File::deleteAbstractedDirectory('data://xengallery_watermark');

				$watermarkOptionValue['enabled'] = true;
				$watermarkOptionValue['watermark_hash'] = $watermarkHash;
			}
		}

		// Placeholder to set values; other values will be imported
		$db->insert('xf_option', [
			'option_id' => 'xfmgWatermarking',
			'option_value' => json_encode($watermarkOptionValue),
			'default_value' => json_encode($watermarkDefaultValue),
			'edit_format' => 'template',
			'edit_format_params' => '',
			'data_type' => 'array',
			'sub_options' => '',
			'validation_class' => '',
			'validation_method' => ''
		]);

		$this->schemaManager()->dropTable('xengallery_watermark');

		$this->schemaManager()->alterTable('xf_mg_media_item', function(Alter $table)
		{
			$table->renameColumn('media_title', 'title');
			$table->renameColumn('media_description', 'description');
			$table->renameColumn('media_exif_data_cache_full', 'exif_data');
			$table->renameColumn('media_view_count', 'view_count');
			$table->changeColumn('watermark_id')->renameTo('watermarked')->type('tinyint');
			$table->changeColumn('media_type')->resetDefinition()->type('varbinary', 25);
			$table->addColumn('media_hash', 'varchar', 32)->nullable()->after('media_id');
			$table->addUniqueKey('media_hash');
			$table->dropColumns(['attachment_id', 'media_embed_cache', 'media_exif_data_cache', 'media_privacy']);
		});

		$this->query("
			UPDATE xf_mg_media_item
			SET media_type = 'image'
			WHERE media_type = 'image_upload'
		");

		$this->query("
			UPDATE xf_mg_media_item
			SET media_type = 'video'
			WHERE media_type = 'video_upload'
		");

		$this->query("
			UPDATE xf_mg_media_item
			SET media_type = 'embed'
			WHERE media_type = 'video_embed'
		");

		$this->query("
			UPDATE xf_mg_media_item
			SET media_hash = MD5(CONCAT(media_id, ?))
		", \XF::generateRandomString(8, true));
	}

	public function upgrade902000010Step7()
	{
		$this->schemaManager()->createTable('xf_mg_media_temp', function(Create $table)
		{
			$table->addColumn('temp_media_id', 'int')->autoIncrement();
			$table->addColumn('media_hash', 'varchar', 32);
			$table->addColumn('temp_media_date', 'int');
			$table->addColumn('media_type', 'varbinary', 25);
			$table->addColumn('user_id', 'int');
			$table->addColumn('title', 'text');
			$table->addColumn('description', 'text');
			$table->addColumn('thumbnail_date', 'int');
			$table->addColumn('attachment_id', 'int')->nullable();
			$table->addColumn('requires_transcoding', 'tinyint')->setDefault(0);
			$table->addColumn('exif_data', 'mediumblob')->nullable();
			$table->addUniqueKey('media_hash');
			$table->addUniqueKey('attachment_id');
		});
	}

	public function upgrade902000010Step8()
	{
		$sm = $this->schemaManager();

		$sm->dropTable('xf_mg_media_view');

		$sm->createTable('xf_mg_media_view', function(Create $table)
		{
			$table->engine('MEMORY');

			$table->addColumn('media_id', 'int');
			$table->addColumn('total', 'int');
			$table->addPrimaryKey('media_id');
		});
	}

	public function upgrade902000010Step9()
	{
		$this->schemaManager()->alterTable('xf_mg_comment', function(Alter $table)
		{
			$table->changeColumn('content_type')->resetDefinition()->type('varbinary', 25);
			$table->addColumn('last_edit_date', 'int')->setDefault(0);
			$table->addColumn('last_edit_user_id', 'int')->setDefault(0);
			$table->addColumn('edit_count', 'int')->setDefault(0);
			$table->addColumn('embed_metadata', 'blob')->nullable();
		});

		$this->query("
			UPDATE xf_mg_comment
			SET content_type = 'xfmg_media'
			WHERE content_type = 'media'
		");

		$this->query("
			UPDATE xf_mg_comment
			SET content_type = 'xfmg_album'
			WHERE content_type = 'album'
		");
	}

	public function upgrade902000010Step10()
	{
		$this->schemaManager()->alterTable('xf_mg_rating', function(Alter $table)
		{
			$table->changeColumn('content_type')->resetDefinition()->type('varbinary', 25);
		});

		$this->query("
			UPDATE xf_mg_rating
			SET content_type = 'xfmg_media'
			WHERE content_type = 'media'
		");

		$this->query("
			UPDATE xf_mg_rating
			SET content_type = 'xfmg_album'
			WHERE content_type = 'album'
		");
	}

	public function upgrade902000010Step11()
	{
		$sm = $this->schemaManager();

		foreach (['xengallery_album_count', 'xengallery_media_count', 'xengallery_media_quota'] AS $column)
		{
			if (!$sm->columnExists('xf_user', $column))
			{
				$sm->alterTable('xf_user', function(Alter $table) use ($column)
				{
					$table->addColumn($column, 'int')->setDefault(0);
				});
			}
		}

		$sm->alterTable('xf_user', function(Alter $table)
		{
			$table->renameColumn('xengallery_album_count', 'xfmg_album_count');
			$table->renameColumn('xengallery_media_count', 'xfmg_media_count');
			$table->renameColumn('xengallery_media_quota', 'xfmg_media_quota');
		});
	}

	public function upgrade902000010Step12()
	{
		$this->query("
			UPDATE xf_option
			SET option_id = 'xfmgFfmpeg'
			WHERE option_id = 'xengalleryVideoTranscoding'
		");

		$defaultValue = [
			'enabled' => false,
			'ffmpegPath' => '',
			'thumbnail' => false,
			'transcode' => false,
			'phpPath' => '',
			'limit' => 0,
			'forceTranscode' => false
		];

		$this->query("
			UPDATE xf_option
			SET default_value = ?
			WHERE option_id = 'xfmgFfmpeg'
		", json_encode($defaultValue));

		$ffmpegOptions = json_decode($this->db()->fetchOne("
			SELECT option_value
			FROM xf_option
			WHERE option_id = 'xfmgFfmpeg'
		"), true);

		$update = false;
		foreach (array_keys($defaultValue) AS $key)
		{
			if (!isset($ffmpegOptions[$key]))
			{
				$update = true;
				$ffmpegOptions[$key] = $defaultValue[$key];
			}
		}

		if ($update)
		{
			$this->query("
				UPDATE xf_option
				SET option_value = ?
				WHERE option_id = 'xfmgFfmpeg'
			", json_encode($ffmpegOptions));
		}
	}

	public function upgrade902000010Step13()
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_mg_album', function(Alter $table)
		{
			$table->addColumn('category_id', 'int')->unsigned()->setDefault(0)->after('album_id');
			$table->addColumn('album_hash', 'varchar', 32)->nullable()->after('category_id');
			$table->addColumn('view_privacy', 'enum', ['public', 'members', 'private', 'shared', 'inherit'])->setDefault('private')->after('last_update_date');
			$table->addColumn('view_users', 'mediumblob')->nullable()->after('view_privacy');
			$table->addColumn('add_privacy', 'enum', ['public', 'members', 'private', 'shared', 'inherit'])->setDefault('private')->after('view_users');
			$table->addColumn('add_users', 'mediumblob')->nullable()->after('add_privacy');
			$table->renameColumn('album_title', 'title');
			$table->renameColumn('album_description', 'description');
			$table->renameColumn('album_create_date', 'create_date');
			$table->renameColumn('album_user_id', 'user_id');
			$table->renameColumn('album_username', 'username');
			$table->renameColumn('album_likes', 'likes');
			$table->renameColumn('album_like_users', 'like_users');
			$table->renameColumn('album_media_count', 'media_count');
			$table->renameColumn('album_view_count', 'view_count');
			$table->renameColumn('album_rating_count', 'rating_count');
			$table->renameColumn('album_rating_sum', 'rating_sum');
			$table->renameColumn('album_rating_avg', 'rating_avg');
			$table->renameColumn('album_rating_weighted', 'rating_weighted');
			$table->renameColumn('album_comment_count', 'comment_count');
			$table->renameColumn('album_last_comment_date', 'last_comment_date');
			$table->renameColumn('album_warning_id', 'warning_id');
			$table->renameColumn('album_warning_message', 'warning_message');
			$table->renameColumn('album_default_order', 'default_order');
			$table->renameColumn('album_thumbnail_date', 'thumbnail_date');

			// new column as old media_cache column won't have the required contents anyway
			$table->addColumn('media_item_cache', 'mediumblob')->nullable();
			$table->dropColumns(['media_cache', 'manual_media_cache']);
		});

		$sm->alterTable('xf_mg_shared_map_add', function(Alter $table)
		{
			$table->renameColumn('add_user_id','user_id');
		});

		$sm->alterTable('xf_mg_shared_map_view', function(Alter $table)
		{
			$table->renameColumn('shared_user_id','user_id');
		});

		$this->query("
			UPDATE xf_mg_album
			SET album_hash = MD5(CONCAT(album_id, ?))
		", \XF::generateRandomString(8, true));
	}

	public function upgrade902000010Step14()
	{
		$sm = $this->schemaManager();

		$sm->dropTable('xf_mg_album_view');

		$sm->createTable('xf_mg_album_view', function(Create $table)
		{
			$table->engine('MEMORY');

			$table->addColumn('album_id', 'int');
			$table->addColumn('total', 'int');
			$table->addPrimaryKey('album_id');
		});
	}

	public function upgrade902000010Step15(array $stepParams)
	{
		$stepParams = array_replace([
			'position' => 0
		], $stepParams);

		$perPage = 250;
		$db = $this->db();

		$albumIds = $db->fetchAllColumn($db->limit(
			'
				SELECT DISTINCT album_id
				FROM xf_mg_album_permission
				WHERE album_id > ?
				ORDER BY album_id
			', $perPage
		), $stepParams['position']);
		if (!$albumIds)
		{
			$db->getSchemaManager()->dropTable('xf_mg_album_permission');

			return true;
		}

		$queryResults = $db->query('
			SELECT *
			FROM xf_mg_album_permission
			WHERE album_id IN (' . $db->quote($albumIds) . ')
			ORDER BY album_id, permission
		');
		$privacyGrouped = [];
		while ($result = $queryResults->fetch())
		{
			if ($result['access_type'] == 'followed')
			{
				$result['access_type'] = 'shared';
			}

			$shareUsers = unserialize($result['share_users']) ?: [];

			$privacyGrouped[$result['album_id']][$result['permission']] = [
				$result['permission'] . '_privacy' => $result['access_type'],
				$result['permission'] . '_users' => json_encode(array_keys($shareUsers)) // new format, json array of user IDs
			];
		}

		$db->beginTransaction();

		foreach ($privacyGrouped AS $albumId => $privacy)
		{
			$add = isset($privacy['add']) ? $privacy['add'] : [];
			$view = isset($privacy['view']) ? $privacy['view'] : [];
			$updates = array_merge($add, $view);
			if ($updates)
			{
				$db->update('xf_mg_album', $updates, 'album_id = ?', $albumId);
			}
		}

		$db->commit();

		$stepParams['position'] = end($albumIds);

		return $stepParams;
	}

	public function upgrade902000010Step16()
	{
		$db = $this->db();

		$optionIds = $db->quote([
			'xengalleryShowRecentComments',
			'xengalleryShowTopContributors',
			'xengalleryShowStatisticsBlock',
			'xengalleryForumListRecent',
			'xengalleryRecentMediaCategories',
			'xengallerRecentMediaAlbums', // the "y" was missing in the original version
			'xengalleryRecentMediaLimit',
			'xengalleryForumListPosition',
			'xengalleryRecentMediaOrder'
		]);
		$options = $db->fetchPairs('
			SELECT option_id, option_value
			FROM xf_option
			WHERE option_id IN(' . $optionIds . ')
		');

		if (!$options)
		{
			return;
		}

		if ($options['xengalleryShowRecentComments'])
		{
			$this->createWidget(
				'xfmg_gallery_wrapper_latest_comments',
				'xfmg_latest_comments',
				[
					'positions' => [
						'xfmg_gallery_wrapper_sidenav' => 100
					]
				],
				'Media comments'
			);
		}

		if ($options['xengalleryShowTopContributors'])
		{
			$this->createWidget(
				'xfmg_gallery_wrapper_most_media_items',
				'member_stat',
				[
					'positions' => [
						'xfmg_gallery_wrapper_sidenav' => 200
					],
					'options' => [
						'member_stat_key' => 'xfmg_most_media_items'
					]
				]
			);
		}

		$showStatistics = json_decode($options['xengalleryShowStatisticsBlock'], true);
		if ($showStatistics && $showStatistics['enabled'])
		{
			$widgetPositions = [];
			if ($showStatistics['forum_side'])
			{
				$widgetPositions['forum_list_sidebar'] = 55;
			}
			if ($showStatistics['gallery_side'])
			{
				$widgetPositions['xfmg_gallery_wrapper_sidenav'] = 300;
			}
			if ($widgetPositions)
			{
				$this->createWidget(
					'xfmg_statistics',
					'xfmg_gallery_statistics',
					[
						'positions' => $widgetPositions
					],
					'Media statistics'
				);
			}
		}

		if ($options['xengalleryForumListRecent'])
		{
			$categoryIds = json_decode($options['xengalleryRecentMediaCategories'], true);
			$includePersonalAlbums = $options['xengallerRecentMediaAlbums']; // "y" was missing before
			$limit = $options['xengalleryRecentMediaLimit'];
			$order = $options['xengalleryRecentMediaOrder'];

			$forumListPositions = json_decode($options['xengalleryForumListPosition'], true);
			$widgetPositions = [];

			if (isset($forumListPositions['top']))
			{
				$widgetPositions['forum_overview_top'] = 100;
			}
			if (isset($forumListPositions['bottom']))
			{
				$widgetPositions['forum_overview_bottom'] = 100;
			}
			if (isset($forumListPositions['sidebar']))
			{
				$widgetPositions['forum_list_sidebar'] = 70;
			}

			if ($widgetPositions)
			{
				$title = $order == 'new' ? 'Latest media' : 'Random media';

				$this->createWidget(
					'xfmg_media_slider',
					'xfmg_media_slider',
					[
						'positions' => $widgetPositions,
						'options' => [
							'category_ids' => $categoryIds ?: [0],
							'include_personal_albums' => (bool)$includePersonalAlbums,
							'limit' => $limit,
							'order' => $order == 'new' ? 'latest' : 'random'
						]
					],
					$title
				);
			}
		}
	}

	public function upgrade902000010Step17()
	{
		$sm = $this->schemaManager();

		$sm->createTable('xf_mg_album_comment_read', function(Create $table)
		{
			$table->addColumn('comment_read_id', 'int')->autoIncrement();
			$table->addColumn('user_id', 'int');
			$table->addColumn('album_id', 'int');
			$table->addColumn('comment_read_date', 'int');
			$table->addUniqueKey(['user_id', 'album_id']);
			$table->addKey('album_id');
			$table->addKey('comment_read_date');
		});

		$sm->createTable('xf_mg_media_comment_read', function(Create $table)
		{
			$table->addColumn('comment_read_id', 'int')->autoIncrement();
			$table->addColumn('user_id', 'int');
			$table->addColumn('media_id', 'int');
			$table->addColumn('comment_read_date', 'int');
			$table->addUniqueKey(['user_id', 'media_id']);
			$table->addKey('media_id');
			$table->addKey('comment_read_date');
		});
	}

	public function upgrade902000010Step18()
	{
		$this->schemaManager()->alterTable('xf_user_option', function(Alter $table)
		{
			$table->dropColumns('xengallery_unviewed_media_count');
		});
	}

	public function upgrade902000010Step19()
	{
		$sm = $this->schemaManager();
		$db = $this->db();

		$sm->alterTable('xf_mg_media_field', function (Alter $table)
		{
			$table->changeColumn('display_group')->setDefault('below_media');
			$table->changeColumn('field_type')->resetDefinition()->type('varbinary', 25)->setDefault('textbox');
			$table->changeColumn('match_type')->resetDefinition()->type('varbinary', 25)->setDefault('none');
			$table->addColumn('match_params', 'blob')->after('match_type');
		});

		// There was a faulty default value here which never existed.
		// The old DW should have prevented it ever being used, but just in case...
		$db->update('xf_mg_media_field', [
			'display_group' => 'below_info'
		], 'display_group = ?', 'below_info_tab');

		// No tabs in the new media view UI so re-purpose them as sidebar blocks
		$db->update('xf_mg_media_field', [
			'display_group' => 'extra_info_sidebar_block'
		], 'display_group = ?', 'extra_tab');
		$db->update('xf_mg_media_field', [
			'display_group' => 'new_sidebar_block'
		], 'display_group = ?', 'new_tab');

		foreach ($db->fetchAllKeyed("SELECT * FROM xf_mg_media_field", 'field_id') AS $fieldId => $field)
		{
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
				$db->update('xf_mg_media_field', $update, 'field_id = ?', $fieldId);
			}
		}

		$sm->alterTable('xf_mg_media_field', function(Alter $table)
		{
			$table->dropColumns(['match_regex', 'match_callback_class', 'match_callback_method']);
		});

		$sm->alterTable('xf_mg_media_item', function(Alter $table)
		{
			$table->renameColumn('custom_media_fields', 'custom_fields');
		});
	}

	public function upgrade902000010Step20()
	{
		$this->schemaManager()->alterTable('xf_mg_media_note', function(Alter $table)
		{
			$table->renameColumn('tag_id', 'note_id');
			$table->renameColumn('user_id', 'tagged_user_id');
			$table->renameColumn('username', 'tagged_username');
			$table->renameColumn('tag_data', 'note_data');
			$table->renameColumn('tag_date', 'note_date');
			$table->renameColumn('tag_by_user_id', 'user_id');
			$table->renameColumn('tag_by_username', 'username');
			$table->addColumn('note_type', 'varbinary', 25)->setDefault('user_tag')->after('note_id');
			$table->addColumn('note_text', 'text')->nullable()->after('note_data');
		});
	}

	public function upgrade902000010Step21(array $stepParams)
	{
		$stepParams = array_replace([
			'content_type_tables' => [
				'xf_approval_queue' => true,
				'xf_attachment' => true,
				'xf_deletion_log' => true,
				'xf_ip' => true,
				'xf_reaction_content' => true,
				'xf_moderator_log' => true,
				'xf_news_feed' => true,
				'xf_report' => true,
				'xf_search_index' => true,
				'xf_tag_content' => true,
				'xf_user_alert' => true,
				'xf_warning' => true
			],
			'content_types' => [
				'album',
				'comment',
				'rating',
				'media',
			]
		], $stepParams);

		$db = $this->db();
		$startTime = microtime(true);
		$maxRunTime = $this->app->config('jobMaxRunTime');

		if (!$stepParams['content_type_tables'])
		{
			$columns = [];
			foreach (['album', 'comment', 'media'] AS $contentType)
			{
				$oldType = 'xengallery_' . $contentType;
				$oldLen = strlen($oldType);

				$newType = 'xfmg_' . $contentType;
				$newLen = strlen($newType);

				$columns[] = 'data = REPLACE(data, \'s:' . $oldLen .  ':"' . $oldType . '"\', \'s:' . $newLen . ':"' . $newType . '"\')';
			}
			$this->query('
				UPDATE xf_spam_cleaner_log
				SET ' . implode(",\n", $columns)
			);
			return true;
		}

		foreach ($stepParams['content_type_tables'] AS $table => $null)
		{
			foreach ($stepParams['content_types'] AS $contentType)
			{
				$db->update($table, [
					'content_type' => 'xfmg_' . $contentType
				], 'content_type = ?', 'xengallery_' . $contentType);
			}

			unset ($stepParams['content_type_tables'][$table]);

			if ($maxRunTime && microtime(true) - $startTime > $maxRunTime)
			{
				break;
			}
		}

		return $stepParams;
	}

	public function upgrade902000010Step22()
	{
		$this->query("
			UPDATE xf_option
			SET option_id = REPLACE(option_id, 'xengallery', 'xfmg')
			WHERE addon_id = 'XenGallery'
		");

		$this->query("
			UPDATE xf_admin_permission
			SET admin_permission_id = 'mediaGallery'
			WHERE admin_permission_id = 'manageXenGallery'
		");

		$this->query("
			UPDATE xf_admin
			SET permission_cache = REPLACE(permission_cache, 's:16:\"manageXenGallery\"', 's:12:\"mediaGallery\"')
		");
	}

	public function upgrade902000010Step23()
	{
		$this->query("
			UPDATE xf_permission
			SET permission_group_id = 'xfmg'
			WHERE permission_group_id = 'xengallery'
			AND addon_id = 'XenGallery'
		");

		$this->query("
			UPDATE xf_permission_entry
			SET permission_group_id = 'xfmg'
			WHERE permission_group_id = 'xengallery'
		");

		$tablesToUpdate = [
			'xf_permission',
			'xf_permission_entry'
		];

		$permissionDeletes = [
			'editUrl',
			'viewRatings',
			'download',
			'rotate',
			'crop',
			'flip',
			'avatar',
			'avatarAny',
			'thumbnail',
			'maxTags',
			'videoFileSize',
			'videoMaxItems',
			'addWatermarkAny',
			'removeWatermarkAny',
			'bypassWatermark',
			'addWatermark',
			'removeWatermark',
			'changeViewPermissionAny',
			'changeAddPermissionAny',
			'customOrderAny',
			'albumThumbnailAny',
			'viewAlbums',
			'changeViewPermission',
			'changeAddPermission',
			'customOrder',
			'albumThumbnail',
			'viewCategories',
			'bypassModQueueComment',
			'imageMaxItems',
			'moveToAnyAlbum',
			'editUrlAny',
			'flipAny',
			'rotateAny',
			'cropAny',
			'thumbnailAny'
		];

		$permissionRenames = [
			'bypassModQueueMedia' => 'addWithoutApproval',
			'delete' => 'deleteOwn',
			'edit' => 'editOwn',
			'move' => 'moveOwn',
			'viewTag' => 'viewNote',
			'tag' => 'addNoteOwn',
			'deleteTag' => 'deleteOwnNote',
			'deleteTagAny' => 'deleteAnyNote',
			'bypassApproval' => 'tagWithoutApproval',
			'deleteAlbumAny' => 'deleteAnyAlbum',
			'editAlbumAny' => 'editAnyAlbum',
			'hardDeleteAlbumAny' => 'hardDeleteAnyAlbum',
			'uploadImage' => 'addImage',
			'uploadVideo' => 'addVideo',
			'embedVideo' => 'addEmbed',
			'deleteAlbum' => 'deleteOwnAlbum',
			'editAlbum' => 'editOwnAlbum',
			'deleteCommentAny' => 'deleteAnyComment',
			'hardDeleteCommentAny' => 'hardDeleteAnyComment',
			'editCommentAny' => 'editAnyComment',
			'generalStorageQuota' => 'maxAllowedStorage',
			'imageWidth' => 'maxImageWidth',
			'imageHeight' => 'maxImageHeight',
			'imageFileSize' => 'maxFileSize',
			'viewOverride' => 'bypassPrivacy',
			'approveUnapproveMedia' => 'approveUnapprove'
		];

		foreach ($tablesToUpdate AS $table)
		{
			$this->query("
				DELETE FROM $table
				WHERE permission_id IN(" . $this->db()->quote($permissionDeletes) . ")
				AND permission_group_id = 'xfmg'
			");

			foreach ($permissionRenames AS $old => $new)
			{
				$this->query("
					UPDATE `$table`
					SET permission_id = ?
					WHERE permission_id = ? AND permission_group_id = ?
				", [$new, $old, 'xfmg']);
			}

			$this->query("
				UPDATE `$table`
				SET permission_group_id = ?
				WHERE permission_group_id = ? AND permission_id = ?
			", ['xfmgStorage', 'xfmg', 'maxAllowedStorage']);
		}
	}

	public function upgrade902000010Step24(array $stepParams)
	{
		$stepParams = array_replace([
			'position' => 0,
			'video_path_reset' => false,
			'thumbnail_date_reset' => false
		], $stepParams);

		$perPage = 250;
		$db = $this->db();
		$startTime = microtime(true);
		$maxRunTime = $this->app->config('jobMaxRunTime');

		if ($stepParams['video_path_reset'] === false)
		{
			$db->update('xf_attachment_data', [
				'file_path' => 'data://xfmg/video/%FLOOR%/%DATA_ID%-%HASH%.mp4'
			], 'file_path = ?', '%DATA%/xengallery_videos/%FLOOR%/%DATA_ID%-%HASH%.mp4');
			$stepParams['video_path_reset'] = true;
		}

		if ($stepParams['thumbnail_date_reset'] === false)
		{
			$db->update('xf_mg_media_item', ['thumbnail_date' => 0], null);
			$stepParams['thumbnail_date_reset'] = true;
		}

		$mediaIds = $db->fetchAllColumn($db->limit(
			'
				SELECT media_id
				FROM xf_mg_media_item
				WHERE media_id > ?
				ORDER BY media_id
			', $perPage
		), $stepParams['position']);
		if (!$mediaIds)
		{
			return true;
		}

		foreach ($mediaIds AS $mediaId)
		{
			$mediaItem = $db->fetchRow('
				SELECT media.*, att.*, attdat.*
				FROM xf_mg_media_item AS media
				LEFT JOIN xf_attachment AS att ON
					(att.content_id = media.media_id AND att.content_type = \'xfmg_media\')
				LEFT JOIN xf_attachment_data AS attdat ON
					(attdat.data_id = att.data_id)
				WHERE media_id = ?
			', $mediaId);

			if ($mediaItem)
			{
				$fs = $this->app->fs();

				$oldThumbAbsPath = sprintf(
					'data://xengallery/%d/%d-%s.jpg',
					floor($mediaItem['data_id'] / 1000), $mediaItem['data_id'], $mediaItem['file_hash']
				);
				$newThumbAbsPath = sprintf(
					'data://xfmg/thumbnail/%d/%d-%s.jpg',
					floor($mediaId / 1000), $mediaId, $mediaItem['media_hash']
				);

				switch ($mediaItem['media_type'])
				{
					case 'image':
						$oldOrigAbsPath = sprintf(
							'internal-data://xengallery/originals/%d/%d-file_hash.data',
							floor($mediaItem['data_id'] / 1000), $mediaItem['data_id']
						);
						$newOrigAbsPAth = sprintf(
							'internal-data://xfmg/original/%d/%d-%s.data',
							floor($mediaId / 1000), $mediaId, $mediaItem['media_hash']
						);
						if ($fs->has($oldOrigAbsPath))
						{
							$fs->move($oldOrigAbsPath, $newOrigAbsPAth);
						}
						break;

					case 'video':
						$oldVideoAbsPath = sprintf(
							'data://xengallery_videos/%d/%d-%s.mp4',
							floor($mediaItem['data_id'] / 1000), $mediaItem['data_id'], $mediaItem['file_hash']
						);
						$newVideoAbsPAth = sprintf(
							'data://xfmg/video/%d/%d-%s.mp4',
							floor($mediaItem['data_id'] / 1000), $mediaItem['data_id'], $mediaItem['file_hash']
						);
						if ($fs->has($oldVideoAbsPath))
						{
							$fs->move($oldVideoAbsPath, $newVideoAbsPAth);
						}
						break;

					case 'embed':
						preg_match('/\[media=(.*?)\](.*?)\[\/media\]/is', $mediaItem['media_tag'], $parts);

						$oldThumbAbsPath = sprintf(
							'data://xengallery/%s/%s_%s_thumb.jpg',
							$parts[1], $parts[1], $parts[2]
						);
						break;
				}

				if ($fs->has($oldThumbAbsPath))
				{
					$moved = $fs->move($oldThumbAbsPath, $newThumbAbsPath);
					if ($moved)
					{
						$db->update('xf_mg_media_item', ['thumbnail_date' => time()], 'media_id = ?', $mediaId);
					}
				}
			}

			$stepParams['position'] = $mediaId;

			if ($maxRunTime && microtime(true) - $startTime > $maxRunTime)
			{
				break;
			}
		}

		return $stepParams;
	}

	public function upgrade902000010Step25(array $stepParams)
	{
		$stepParams = array_replace([
			'position' => 0,
			'thumbnail_date_reset' => false
		], $stepParams);

		$perPage = 250;
		$db = $this->db();
		$startTime = microtime(true);
		$maxRunTime = $this->app->config('jobMaxRunTime');

		if ($stepParams['thumbnail_date_reset'] === false)
		{
			$db->update('xf_mg_album', ['thumbnail_date' => 0], null);
			$stepParams['thumbnail_date_reset'] = true;
		}

		$albumIds = $db->fetchAllColumn($db->limit(
			'
				SELECT album_id
				FROM xf_mg_album
				WHERE album_id > ?
				ORDER BY album_id
			', $perPage
		), $stepParams['position']);
		if (!$albumIds)
		{
			return true;
		}

		foreach ($albumIds AS $albumId)
		{
			$album = $db->fetchRow('
				SELECT *
				FROM xf_mg_album
				WHERE album_id = ?
			', $albumId);

			if ($album)
			{
				$mediaItem = $db->fetchRow('
					SELECT *
					FROM xf_mg_media_item
					WHERE album_id = ?
						AND media_state = \'visible\'
						AND thumbnail_date > 0
					ORDER BY media_date DESC
					LIMIT 1
				', $albumId);

				if ($mediaItem)
				{
					$mediaThumbAbsPath = sprintf('data://xfmg/thumbnail/%d/%d-%s.jpg',
						floor($mediaItem['media_id'] / 1000),
						$mediaItem['media_id'],
						$mediaItem['media_hash']
					);
					$albumThumbAbsPath = sprintf('data://xfmg/album_thumbnail/%d/%d-%s.jpg',
						floor($album['album_id'] / 1000),
						$album['album_id'],
						$album['album_hash']
					);

					if (!$this->app->fs()->has($albumThumbAbsPath))
					{
						$copied = $this->app->fs()->copy($mediaThumbAbsPath, $albumThumbAbsPath);
						if ($copied)
						{
							$db->update('xf_mg_album', ['thumbnail_date' => time()], 'album_id = ?', $albumId);
						}
					}
				}
			}

			$stepParams['position'] = $albumId;

			if ($maxRunTime && microtime(true) - $startTime > $maxRunTime)
			{
				break;
			}
		}

		return $stepParams;
	}

	public function upgrade902000010Step26(array $stepParams)
	{
		$stepParams = array_replace([
			'position' => 0
		], $stepParams);

		$perPage = 250;
		$db = $this->db();
		$startTime = microtime(true);
		$maxRunTime = $this->app->config('jobMaxRunTime');

		$reportIds = $db->fetchAllColumn($db->limit(
			'
				SELECT report_id
				FROM xf_report
				WHERE report_id > ?
					AND content_type IN (\'xfmg_media\', \'xfmg_album\', \'xfmg_comment\')
				ORDER BY report_id
			', $perPage
		), $stepParams['position']);
		if (!$reportIds)
		{
			return true;
		}

		foreach ($reportIds AS $reportId)
		{
			$stepParams['position'] = $reportId;

			if ($maxRunTime && microtime(true) - $startTime > $maxRunTime)
			{
				break;
			}

			$report = $db->fetchRow('
				SELECT *
				FROM xf_report
				WHERE report_id = ?
			', $reportId);

			if (!$report)
			{
				continue;
			}

			$oldContentInfo = @unserialize($report['content_info']);
			if (!$oldContentInfo)
			{
				continue;
			}

			$newContentInfo = null;

			switch ($report['content_type'])
			{
				case 'xfmg_media':

					$newContentInfo = [
						'category_id' => isset($oldContentInfo['media']['category_id']) ? $oldContentInfo['media']['category_id'] : 0,
						'category_title' => isset($oldContentInfo['media']['category_title']) ? $oldContentInfo['media']['category_title'] : '',
						'album_id' => isset($oldContentInfo['media']['album_id']) ? $oldContentInfo['media']['album_id'] : 0,
						'album_title' => isset($oldContentInfo['media']['album_title']) ? $oldContentInfo['media']['album_title'] : '',
						'media_id' => isset($oldContentInfo['media']['media_id']) ? $oldContentInfo['media']['media_id'] : 0,
						'title' => isset($oldContentInfo['media']['media_title']) ? $oldContentInfo['media']['media_title'] : '',
						'description' => isset($oldContentInfo['media']['media_description']) ? $oldContentInfo['media']['media_description'] : '',
						'user_id' => isset($oldContentInfo['media']['user_id']) ? $oldContentInfo['media']['user_id'] : 0,
						'username' => isset($oldContentInfo['media']['username']) ? $oldContentInfo['media']['username'] : 0,
					];
					break;

				case 'xfmg_album':

					$newContentInfo = [
						'album_id' => isset($oldContentInfo['album']['album_id']) ? $oldContentInfo['album']['album_id'] : 0,
						'title' => isset($oldContentInfo['album']['album_title']) ? $oldContentInfo['album']['album_title'] : '',
						'description' => isset($oldContentInfo['album']['album_description']) ? $oldContentInfo['album']['album_description'] : '',
						'category_id' => isset($oldContentInfo['album']['category_id']) ? $oldContentInfo['album']['category_id'] : 0,
						'category_title' => isset($oldContentInfo['album']['category_title']) ? $oldContentInfo['album']['category_title'] : '',
						'user_id' => isset($oldContentInfo['album']['album_user_id']) ? $oldContentInfo['album']['album_user_id'] : 0,
						'username' => isset($oldContentInfo['album']['album_username']) ? $oldContentInfo['album']['album_username'] : 0,
					];
					break;

				case 'xfmg_comment':

					$newContentInfo = [
						'category_id' => 0,
						'category_title' => '',
						'album_id' => 0,
						'album_title' => '',
						'user_id' => isset($oldContentInfo['comment']['user_id']) ? $oldContentInfo['comment']['user_id'] : 0,
						'username' => isset($oldContentInfo['comment']['username']) ? $oldContentInfo['comment']['username'] : '',
						'comment' => [
							'comment_id' => isset($oldContentInfo['comment']['comment_id']) ? $oldContentInfo['comment']['comment_id'] : 0,
							'username' => isset($oldContentInfo['comment']['username']) ? $oldContentInfo['comment']['username'] : '',
							'message' => isset($oldContentInfo['comment']['message']) ? $oldContentInfo['comment']['message'] : ''
						]
					];
					if (!empty($oldContentInfo['album']))
					{
						$newContentInfo['content_type'] = 'xfmg_album';
						$newContentInfo['content_id'] = isset($oldContentInfo['album']['album_id']) ? $oldContentInfo['album']['album_id'] : 0;
						$newContentInfo['content_title'] = isset($oldContentInfo['album']['album_title']) ? $oldContentInfo['album']['album_title'] : 0;
						$newContentInfo['content_description'] = isset($oldContentInfo['album']['album_description']) ? $oldContentInfo['album']['album_description'] : 0;
					}
					else if (!empty($oldContentInfo['media']))
					{
						$newContentInfo['category_id'] = isset($oldContentInfo['media']['category_id']) ? $oldContentInfo['media']['category_id'] : 0;
						$newContentInfo['category_title'] = isset($oldContentInfo['media']['category_title']) ? $oldContentInfo['media']['category_title'] : '';
						$newContentInfo['album_id'] = isset($oldContentInfo['media']['album_id']) ? $oldContentInfo['media']['album_id'] : 0;
						$newContentInfo['album_title'] = isset($oldContentInfo['media']['album_title']) ? $oldContentInfo['media']['album_title'] : '';
						$newContentInfo['content_type'] = 'xfmg_media';
						$newContentInfo['content_id'] = isset($oldContentInfo['media']['media_id']) ? $oldContentInfo['media']['media_id'] : 0;
						$newContentInfo['content_title'] = isset($oldContentInfo['media']['media_title']) ? $oldContentInfo['media']['media_title'] : 0;
						$newContentInfo['content_description'] = isset($oldContentInfo['media']['media_description']) ? $oldContentInfo['media']['media_description'] : 0;
					}
					break;
			}

			if ($newContentInfo)
			{
				$this->query("
					UPDATE xf_report
					SET content_info = ?
					WHERE report_id = ?
				", [serialize($newContentInfo), $reportId]);
			}
		}

		return $stepParams;
	}

	public function upgrade902000010Step27()
	{
		$this->query("
			UPDATE xf_user_alert
			SET `action` = 'transcode_success'
			WHERE `action` = 'video_transcode_success'
				AND content_type = 'xfmg_media'
		");

		$this->query("
			UPDATE xf_user_alert
			SET `action` = 'transcode_failed'
			WHERE `action` = 'video_transcode_failed'
				AND content_type = 'user'
		");

		$this->query("
			UPDATE xf_user_alert
			SET `action` = 'insert'
			WHERE `action` = 'watch_comment'
				AND content_type = 'xfmg_comment'
		");
	}

	public function upgrade902000010Step28()
	{
		$map = [
			'xengallery_field_*_choice_*' => 'xfmg_media_field_choice.$1_$2',
			'xengallery_field_*_desc' => 'xfmg_media_field_desc.*',
			'xengallery_field_*' => 'xfmg_media_field_title.*'
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

	public function upgrade902000032Step1()
	{
		$this->query("
			UPDATE xf_user_alert
			SET `action` = 'insert'
			WHERE `action` = 'watch_insert'
				AND content_type = 'xfmg_media'
		");
	}

	public function upgrade902000032Step2()
	{
		// In Beta 1 the 'all' option was translated to 'image', 'video', 'video'. This attempts to fix that.

		$this->query("
			UPDATE xf_mg_category
			SET allowed_types = ?
			WHERE allowed_types = ?
		", [
			serialize(['image', 'video', 'audio', 'embed']),
			serialize(['image', 'video', 'video'])
		]);
	}

	public function upgrade902000037Step1()
	{
		$db = $this->db();

		$widgets = $db->fetchAllKeyed('
			SELECT *
			FROM xf_widget
			WHERE definition_id = \'xfmg_media_slider\'
				OR definition_id = \'xfmg_album_slider\'
		', 'widget_key');

		if (!$widgets)
		{
			return;
		}

		$changes = false;

		foreach ($widgets AS $widgetKey => $widget)
		{
			$options = json_decode($widget['options'], true);
			if (isset($options['slider']['limit']))
			{
				$options['slider']['item'] = $options['slider']['limit'];
				unset($options['slider']['limit']);

				$db->update('xf_widget', [
					'options' => json_encode($options)
				], 'widget_key = ?', $widgetKey);

				$changes = true;
			}
		}

		if ($changes)
		{
			$db->delete('xf_data_registry', 'data_key = ?', 'widgetCache');
		}
	}

	public function upgrade902000038Step1()
	{
		$sm = $this->schemaManager();

		$toApply = function(Alter $table)
		{
			$table->changeColumn('thumbnail_date')->unsigned();
			$table->addColumn('custom_thumbnail_date', 'int')->setDefault(0)->after('thumbnail_date');
			$table->addColumn('last_comment_id', 'int')->setDefault(0)->after('last_comment_date');
			$table->addColumn('last_comment_user_id', 'int')->setDefault(0)->after('last_comment_id');
			$table->addColumn('last_comment_username', 'varchar', 50)->setDefault('')->after('last_comment_user_id');
		};

		$sm->alterTable('xf_mg_album', $toApply);
		$sm->alterTable('xf_mg_media_item', $toApply);
	}

	public function upgrade902000038Step2(array $stepParams)
	{
		$timer = new \XF\Timer($this->app->config('jobMaxRunTime'));

		$stepParams = array_replace([
			'position' => 0
		], $stepParams);

		$db = $this->db();

		$albumIds = $db->fetchAllColumn($db->limit('
			SELECT album_id
			FROM xf_mg_album
			WHERE album_id > ?
			AND comment_count > 0
			ORDER BY album_id
		', 500), $stepParams['position']);
		if (!$albumIds)
		{
			return true;
		}

		$db->beginTransaction();

		foreach ($albumIds AS $albumId)
		{
			$stepParams['position'] = $albumId;

			$lastComment = $db->fetchRow("
				SELECT 
					comment_id AS last_comment_id, 
					comment_date AS last_comment_date, 
					user_id AS last_comment_user_id, 
					username AS last_comment_username
				FROM xf_mg_comment
				WHERE content_id = ?
					AND content_type = 'xfmg_album'
					AND comment_state = 'visible'
				ORDER BY comment_date DESC
				LIMIT 1
			", $albumId);

			if (!$lastComment)
			{
				$lastComment = [
					'last_comment_id' => 0,
					'last_comment_date' => 0,
					'last_comment_user_id' => 0,
					'last_comment_username' => ''
				];
			}

			$db->update('xf_mg_album', $lastComment, 'album_id = ?', $albumId);

			if ($timer->limitExceeded())
			{
				break;
			}
		}

		$db->commit();

		return $stepParams;
	}

	public function upgrade902000038Step3(array $stepParams)
	{
		$timer = new \XF\Timer($this->app->config('jobMaxRunTime'));

		$stepParams = array_replace([
			'position' => 0
		], $stepParams);

		$db = $this->db();

		$mediaIds = $db->fetchAllColumn($db->limit('
			SELECT media_id
			FROM xf_mg_media_item
			WHERE media_id > ?
			AND comment_count > 0
			ORDER BY media_id
		', 500), $stepParams['position']);
		if (!$mediaIds)
		{
			return true;
		}

		$db->beginTransaction();

		foreach ($mediaIds AS $mediaId)
		{
			$stepParams['position'] = $mediaId;

			$lastComment = $db->fetchRow("
				SELECT 
					comment_id AS last_comment_id, 
					comment_date AS last_comment_date, 
					user_id AS last_comment_user_id, 
					username AS last_comment_username
				FROM xf_mg_comment
				WHERE content_id = ?
					AND content_type = 'xfmg_media'
					AND comment_state = 'visible'
				ORDER BY comment_date DESC
				LIMIT 1
			", $mediaId);

			if (!$lastComment)
			{
				$lastComment = [
					'last_comment_id' => 0,
					'last_comment_date' => 0,
					'last_comment_user_id' => 0,
					'last_comment_username' => ''
				];
			}

			$db->update('xf_mg_media_item', $lastComment, 'media_id = ?', $mediaId);

			if ($timer->limitExceeded())
			{
				break;
			}
		}

		$db->commit();

		return $stepParams;
	}

	public function upgrade902000051Step1()
	{
		// some fields weren't unsigned int 10 whereas they should be (at least for consistency)

		$sm = $this->schemaManager();

		$changes = [
			'xf_mg_album' => [
				'thumbnail_date'
			],
			'xf_mg_category_field' => [
				'category_id'
			],
			'xf_mg_media_item' => [
				'thumbnail_date'
			]
		];

		foreach ($changes AS $table => $columns)
		{
			$sm->alterTable($table, function(Alter $table) use ($columns)
			{
				foreach ($columns AS $column)
				{
					$table->changeColumn($column)->unsigned()->length(10);
				}
			});
		}
	}

	public function upgrade902000051Step2()
	{
		// these were missing from new installs of Beta 8 so re-add them here.

		$sm = $this->schemaManager();

		$toApply = function(Alter $table)
		{
			$table->changeColumn('thumbnail_date')->unsigned();
			$table->addColumn('custom_thumbnail_date', 'int')->setDefault(0)->after('thumbnail_date');
			$table->addColumn('last_comment_id', 'int')->setDefault(0)->after('last_comment_date');
			$table->addColumn('last_comment_user_id', 'int')->setDefault(0)->after('last_comment_id');
			$table->addColumn('last_comment_username', 'varchar', 50)->setDefault('')->after('last_comment_user_id');
		};

		if (!$sm->columnExists('xf_mg_album', 'custom_thumbnail_date'))
		{
			$sm->alterTable('xf_mg_album', $toApply);
		}
		if (!$sm->columnExists('xf_mg_album', 'custom_thumbnail_date'))
		{
			$sm->alterTable('xf_mg_media_item', $toApply);
		}
	}

	public function upgrade902000070Step1()
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_mg_album', function(Alter $table)
		{
			$table->changeColumn('add_privacy')->nullable();
			$table->changeColumn('view_privacy')->nullable();
		});

		$sm->alterTable('xf_mg_category', function(Alter $table)
		{
			$table->changeColumn('category_type')->setDefault('media');
			$table->addKey(['parent_category_id', 'lft']);
			$table->addKey(['lft', 'rgt']);
		});

		$sm->alterTable('xf_mg_media_item', function(Alter $table)
		{
			$table->changeColumn('watermarked')->type('tinyint');
		});

		$sm->alterTable('xf_mg_media_note', function(Alter $table)
		{
			$table->changeColumn('note_type')->setDefault('user_tag');
		});

		$sm->alterTable('xf_user_option', function(Alter $table)
		{
			$table->dropColumns([
				'xengallery_default_media_watch_state',
				'xengallery_default_album_watch_state',
				'xengallery_default_category_watch_state'
			]);
		});
	}

	public function upgrade902000170Step1()
	{
		$sm = $this->schemaManager();

		// Naming of the xengallery_rating table was wrong for upgrades from very old versions to XF2.
		// Likely not going to be hit at this point, but just in case...
		if ($sm->tableExists('xengallery_temp') && !$sm->tableExists('xf_mg_rating'))
		{
			$sm->renameTable('xengallery_temp', 'xf_mg_rating');
		}
	}

	// ################################ UPGRADE TO 2.1.0 A1 ##################

	public function upgrade902010010Step1(array $stepParams)
	{
		$position = empty($stepParams[0]) ? 0 : $stepParams[0];
		$stepData = empty($stepParams[2]) ? [] : $stepParams[2];

		return $this->entityColumnsToJson(
			'XFMG:Album', ['like_users'], $position, $stepData);
	}

	public function upgrade902010010Step2(array $stepParams)
	{
		$position = empty($stepParams[0]) ? 0 : $stepParams[0];
		$stepData = empty($stepParams[2]) ? [] : $stepParams[2];

		return $this->entityColumnsToJson(
			'XFMG:Category', ['field_cache', 'allowed_types', 'breadcrumb_data'], $position, $stepData
		);
	}

	public function upgrade902010010Step3(array $stepParams)
	{
		$position = empty($stepParams[0]) ? 0 : $stepParams[0];
		$stepData = empty($stepParams[2]) ? [] : $stepParams[2];

		return $this->entityColumnsToJson('XFMG:Comment', ['like_users'], $position, $stepData);
	}

	public function upgrade902010010Step4(array $stepParams)
	{
		$position = empty($stepParams[0]) ? 0 : $stepParams[0];
		$stepData = empty($stepParams[2]) ? [] : $stepParams[2];

		return $this->entityColumnsToJson(
			'XFMG:MediaItem', ['like_users', 'custom_fields', 'tags'], $position, $stepData
		);
	}

	public function upgrade902010010Step5(array $stepParams)
	{
		$position = empty($stepParams[0]) ? 0 : $stepParams[0];
		$stepData = empty($stepParams[2]) ? [] : $stepParams[2];

		return $this->entityColumnsToJson('XFMG:MediaNote', ['note_data'], $position, $stepData);
	}

	public function upgrade902010010Step6(array $stepParams)
	{
		$position = empty($stepParams[0]) ? 0 : $stepParams[0];
		$stepData = empty($stepParams[2]) ? [] : $stepParams[2];

		return $this->entityColumnsToJson('XFMG:TranscodeQueue', ['queue_data'], $position, $stepData);
	}

	public function upgrade902010010Step7()
	{
		$this->migrateTableToReactions('xf_mg_media_item');
	}

	public function upgrade902010010Step8()
	{
		$this->migrateTableToReactions('xf_mg_album');
	}

	public function upgrade902010010Step9()
	{
		$this->migrateTableToReactions('xf_mg_comment');
	}

	public function upgrade902010010Step10()
	{
		$this->renameLikeAlertOptionsToReactions(['xfmg_media', 'xfmg_album', 'xfmg_comment']);
	}

	public function upgrade902010010Step11()
	{
		$this->renameLikeAlertsToReactions(['xfmg_media', 'xfmg_album', 'xfmg_comment']);
	}

	public function upgrade902010010Step12()
	{
		$this->renameLikePermissionsToReactions([
			'xfmg' => true // global and content
		], 'like');

		$this->renameLikePermissionsToReactions([
			'xfmg' => true // global and content
		], 'likeAlbum', 'reactAlbum');

		$this->renameLikePermissionsToReactions([
			'xfmg' => true // global and content
		], 'likeComment', 'reactComment');

		$this->renameLikeStatsToReactions(['xfmg_album', 'xfmg_comment', 'xfmg_media']);
	}

	public function upgrade902020010Step1()
	{
		$this->alterTable('xf_mg_media_item', function (Alter $table)
		{
			$table->addKey('last_comment_user_id');
			$table->addKey('last_comment_date');
		});
	}

	public function upgrade902020010Step2()
	{
		$this->alterTable('xf_mg_album', function (Alter $table)
		{
			$table->addKey('last_comment_user_id');
			$table->addKey('last_comment_date');
		});
	}

	public function upgrade902020010Step3()
	{
		$this->alterTable('xf_mg_media_field', function (Alter $table)
		{
			$table->addColumn('wrapper_template', 'text')->after('display_template');
		});
	}

	public function upgrade902020010Step4()
	{
		$this->alterTable('xf_attachment_data', function(Alter $table)
		{
			$table->addColumn('xfmg_mirror_media_id', 'int')->setDefault(0);
		});
	}

	public function upgrade902020010Step5()
	{
		$this->alterTable('xf_attachment', function(Alter $table)
		{
			$table->addColumn('xfmg_is_mirror_handler', 'tinyint')->setDefault(0);
		});
	}

	public function upgrade902020010Step6()
	{
		$this->alterTable('xf_forum', function(Alter $table)
		{
			$table->addColumn('xfmg_media_mirror_category_id', 'int')->setDefault(0);
		});
	}

	public function upgrade902020010Step7()
	{
		$this->alterTable('xf_mg_media_item', function (Alter $table)
		{
			$table->addColumn('poster_date', 'int')->setDefault(0)->after('custom_thumbnail_date');
		});

		$this->alterTable('xf_mg_media_temp', function (Alter $table)
		{
			$table->addColumn('poster_date', 'int')->setDefault(0)->after('thumbnail_date');
		});
	}

	public function upgrade902020010Step8()
	{
		$ffmpegOptions = $this->db()->fetchOne("
			SELECT option_value
			FROM xf_option
			WHERE option_id = 'xfmgFfmpeg'
		");

		if ($ffmpegOptions)
		{
			$array = json_decode($ffmpegOptions, true);
			if ($array)
			{
				$array['poster'] = !empty($array['thumbnail']);

				$this->executeUpgradeQuery("
					UPDATE xf_option
					SET option_value = ?
					WHERE option_id = 'xfmgFfmpeg'
				", json_encode($array));
			}
		}
	}

	public function upgrade902020035Step1()
	{
		$this->alterTable('xf_mg_category', function (Alter $table)
		{
			$table->addColumn('category_index_limit', 'int')->nullable();
		});
	}

	public function upgrade902020051Step1()
	{
		$this->createTable('xf_mg_attachment_exif', function(Create $table)
		{
			$table->addColumn('attachment_id', 'int');
			$table->addColumn('attach_date', 'int');
			$table->addColumn('exif_data', 'mediumblob')->nullable();
		});
	}

	public function upgrade902020170Step1()
	{
		// repair an issue where media within albums may no longer be in the
		// correct category if the album had been moved to a different category

		$this->executeUpgradeQuery("
			UPDATE xf_mg_media_item AS mi
			INNER JOIN xf_mg_album AS a ON 
				(mi.album_id = a.album_id)
			SET mi.category_id = a.category_id
			WHERE mi.album_id > 0
		");
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

		if ($previousVersion && $previousVersion < 901000170)
		{
			$this->app->jobManager()->enqueueUnique(
				'xfmgMediaRatingRebuild',
				'XFMG:MediaRatingRebuild'
			);
			$this->app->jobManager()->enqueueUnique(
				'xfmgAlbumRatingRebuild',
				'XFMG:AlbumRatingRebuild'
			);
		}

		if ($previousVersion && $previousVersion < 901010070)
		{
			$this->app->jobManager()->enqueueUnique(
				'xfmgLegacyContentTags',
				'XFMG:Upgrade\LegacyTags110'
			);
		}

		if ($previousVersion && $previousVersion < 901010370)
		{
			$this->app->jobManager()->enqueueUnique(
				'xfmgQuotaRebuild',
				'XFMG:UserMediaQuota',
				[
					'resetField' => true
				]
			);
		}

		if ($previousVersion && $previousVersion < 902000010)
		{
			/** @var \XF\Service\RebuildNestedSet $service */
			$service = $this->app->service('XF:RebuildNestedSet', 'XFMG:Category', [
				'parentField' => 'parent_category_id'
			]);
			$service->rebuildNestedSetInfo();

			$likeContentTypes = [
				'xfmg_album',
				'xfmg_comment',
				'xfmg_media'
			];
			foreach ($likeContentTypes AS $contentType)
			{
				$this->app->jobManager()->enqueueUnique(
					'xfmgUpgradeLikeIsCountedRebuild_' . $contentType,
					'XF:LikeIsCounted',
					['type' => $contentType],
					false
				);
			}

			$cache = $this->app->simpleCache()->XFMG;

			$mediaRepo = $this->app->repository('XFMG:Media');
			$cache->randomMediaCache = $mediaRepo->generateRandomMediaCache();

			$albumRepo = $this->app->repository('XFMG:Album');
			$cache->randomAlbumCache = $albumRepo->generateRandomAlbumCache();
		}

		if ($previousVersion && $previousVersion < 902000037)
		{
			// Albums contain a media ID cache which is NULL for legacy
			// albums. This is rebuilt in the Albums rebuild.
			$this->app->jobManager()->enqueueUnique(
				'xfmgAlbumRebuild',
				'XFMG:Album'
			);
		}

		if ($previousVersion && $previousVersion < 902000051)
		{
			// Media quotas were not implemented between 2.0 Alpha and Beta 6.
			// Media quotas were not calculated correctly prior to RC 1.
			// Trigger a rebuild to ensure user quotas are up to date.
			$this->app->jobManager()->enqueueUnique(
				'xfmgQuotaRebuild',
				'XFMG:UserMediaQuota'
			);
		}

		if ($previousVersion && $previousVersion < 902000053)
		{
			// Album counts in categories were not updated correctly
			// prior to RC3 so trigger a rebuild.
			$this->app->jobManager()->enqueueUnique(
				'xfmgCategoryRebuild',
				'XFMG:Category'
			);
		}
	}

	public function uninstallStep1()
	{
		$sm = $this->schemaManager();

		foreach (array_keys($this->getTables()) AS $tableName)
		{
			$sm->dropTable($tableName);
		}
	}

	public function uninstallStep2()
	{
		$db = $this->db();

		$contentTypes = ['xfmg_rating', 'xfmg_media_note', 'xfmg_media', 'xfmg_album', 'xfmg_category', 'xfmg_comment'];

		$this->uninstallContentTypeData($contentTypes);

		$db->beginTransaction();

		$db->delete('xf_admin_permission_entry', "admin_permission_id = 'xfmg'");
		$db->delete('xf_permission_cache_content', "content_type = 'xfmg_category'");
		$db->delete('xf_permission_entry', "permission_group_id = 'xfmg'");
		$db->delete('xf_permission_entry_content', "permission_group_id = 'xfmg'");

		$db->commit();
	}

	public function uninstallStep3()
	{
		$tables = [
			'xf_user' => [
				'xfmg_album_count',
				'xfmg_media_count',
				'xfmg_media_quota'
			],
			'xf_attachment_data' => [
				'xfmg_mirror_media_id'
			],
			'xf_attachment' => [
				'xfmg_is_mirror_handler'
			],
			'xf_forum' => [
				'xfmg_media_mirror_category_id'
			]
		];

		foreach ($tables AS $table => $columns)
		{
			$this->alterTable($table, function (Alter $table) use ($columns)
			{
				$table->dropColumns($columns);
			});
		}
	}

	protected function getTables()
	{
		$data = new MySql();
		return $data->getTables();
	}

	/**
	 * This function contains legacy table definitions that need to be added between XMG 1.x (and 2.x) and XFMG 1.x (and 2.0)
	 *
	 * @return array
	 */
	protected function getLegacyTables()
	{
		$tables = [];

		$tableConfig = $this->db()->getDefaultTableConfig();

		$tables[2000070]['xengallery_album'] = "
			CREATE TABLE IF NOT EXISTS `xengallery_album` (
			  album_id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
			  album_title VARCHAR(100) NOT NULL,
			  album_description VARCHAR(200) NOT NULL,
			  album_create_date INT(10) UNSIGNED NOT NULL DEFAULT 0,
			  last_update_date INT(10) UNSIGNED NOT NULL DEFAULT 0,
			  random_media_cache BLOB DEFAULT NULL,
			  album_share_users BLOB DEFAULT NULL,
			  album_state ENUM('visible','moderated','deleted') NOT NULL DEFAULT 'visible',
			  album_privacy ENUM('private','public','shared','members','followed') NOT NULL DEFAULT 'private',
			  user_id INT(10) UNSIGNED NOT NULL DEFAULT 0,
			  username VARCHAR(50) NOT NULL,
			  ip_id INT(10) UNSIGNED NOT NULL DEFAULT 0,
			  album_likes INT(10) UNSIGNED NOT NULL DEFAULT 0,
			  album_like_users BLOB DEFAULT NULL,
			  album_media_count INT(10) UNSIGNED NOT NULL DEFAULT 0,
			  album_view_count INT(10) UNSIGNED NOT NULL DEFAULT 0,
			  album_rating_count INT(10) UNSIGNED NOT NULL DEFAULT 0,
			  album_rating_sum INT(10) UNSIGNED NOT NULL DEFAULT 0,
			  album_rating_avg FLOAT UNSIGNED NOT NULL DEFAULT 0,
			  PRIMARY KEY (`album_id`)
			) ENGINE = {$tableConfig['engine']} CHARACTER SET {$tableConfig['charset']} COLLATE {$tableConfig['collation']}
		";

		$tables[2000070]['xengallery_album_view'] = "
			CREATE TABLE IF NOT EXISTS `xengallery_album_view` (
			  `album_id` int(10) unsigned NOT NULL,
			  KEY `album_id` (`album_id`)
			) ENGINE = MEMORY CHARACTER SET {$tableConfig['charset']} COLLATE {$tableConfig['collation']}
		";

		$tables[2000070]['xengallery_content_tag'] = "
			CREATE TABLE IF NOT EXISTS `xengallery_content_tag` (
			  `tag_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
			  `tag_name` varchar(50) NOT NULL DEFAULT '',
			  `tag_clean` varchar(50) NOT NULL DEFAULT '',
			  `use_count` int(10) unsigned NOT NULL DEFAULT '0',
			  PRIMARY KEY (`tag_id`),
			  KEY `tag_id_tag_name` (`tag_id`,`tag_name`),
			  KEY `tag_name` (`tag_name`)
			) ENGINE = {$tableConfig['engine']} CHARACTER SET {$tableConfig['charset']} COLLATE {$tableConfig['collation']}
		";

		$tables[2000070]['xengallery_content_tag_map'] = "
			CREATE TABLE IF NOT EXISTS `xengallery_content_tag_map` (
			  `tag_id` int(10) unsigned NOT NULL,
			  `media_id` int(10) unsigned NOT NULL DEFAULT '0',
			  PRIMARY KEY (`tag_id`,`media_id`)
			) ENGINE = {$tableConfig['engine']} CHARACTER SET {$tableConfig['charset']} COLLATE {$tableConfig['collation']}
		";

		$tables[2000070]['xengallery_exif'] = "
			CREATE TABLE IF NOT EXISTS `xengallery_exif` (
			  `media_id` int(10) unsigned NOT NULL DEFAULT '0',
			  `exif_name` varchar(200) NOT NULL DEFAULT '',
			  `exif_value` varchar(200) NOT NULL DEFAULT '',
			  `exif_format` varchar(50) NOT NULL DEFAULT '{value}',
			  PRIMARY KEY (`media_id`,`exif_name`)
			) ENGINE = {$tableConfig['engine']} CHARACTER SET {$tableConfig['charset']} COLLATE {$tableConfig['collation']}
		";

		$tables[2000070]['xengallery_field'] = "
			CREATE TABLE IF NOT EXISTS `xengallery_field` (
			  `field_id` varbinary(25) NOT NULL,
			  `display_group` varchar(25) NOT NULL DEFAULT 'below_info_tab',
			  `display_order` int(10) unsigned NOT NULL DEFAULT '1',
			  `field_type` varchar(25) NOT NULL DEFAULT 'textbox',
			  `field_choices` blob NOT NULL,
			  `match_type` varchar(25) NOT NULL DEFAULT 'none',
			  `match_regex` varchar(250) NOT NULL DEFAULT '',
			  `match_callback_class` varchar(75) NOT NULL DEFAULT '',
			  `match_callback_method` varchar(75) NOT NULL DEFAULT '',
			  `max_length` int(10) unsigned NOT NULL DEFAULT '0',
			  `album_use` tinyint UNSIGNED NOT NULL DEFAULT '1',
			  `display_template` text NOT NULL,
			  PRIMARY KEY (`field_id`),
			  KEY `display_group_order` (`display_group`,`display_order`)
			) ENGINE = {$tableConfig['engine']} CHARACTER SET {$tableConfig['charset']} COLLATE {$tableConfig['collation']}
		";

		$tables[2000070]['xengallery_field_category'] = "
			CREATE TABLE IF NOT EXISTS `xengallery_field_category` (
			  `field_id` varbinary(25) NOT NULL,
			  `category_id` int(11) NOT NULL,
			  PRIMARY KEY (`field_id`,`category_id`),
			  KEY `category_id` (`category_id`)
			) ENGINE = {$tableConfig['engine']} CHARACTER SET {$tableConfig['charset']} COLLATE {$tableConfig['collation']}
		";

		$tables[2000070]['xengallery_field_value'] = "
			CREATE TABLE IF NOT EXISTS `xengallery_field_value` (
			  `media_id` int(10) unsigned NOT NULL,
			  `field_id` varbinary(25) NOT NULL,
			  `field_value` mediumtext NOT NULL,
			  PRIMARY KEY (`media_id`,`field_id`),
			  KEY `field_id` (`field_id`)
			) ENGINE = {$tableConfig['engine']} CHARACTER SET {$tableConfig['charset']} COLLATE {$tableConfig['collation']}
		";

		$tables[2000070]['xengallery_private_map'] = "
			CREATE  TABLE IF NOT EXISTS `xengallery_private_map` (
			  `album_id` int(10) unsigned NOT NULL,
			  `private_user_id` int(10) unsigned NOT NULL,
			  PRIMARY KEY (`album_id`, `private_user_id`)
			) ENGINE = {$tableConfig['engine']} CHARACTER SET {$tableConfig['charset']} COLLATE {$tableConfig['collation']}
		";

		$tables[2000070]['xengallery_shared_map'] = "
			CREATE  TABLE IF NOT EXISTS `xengallery_shared_map` (
			  `album_id` int(10) unsigned NOT NULL,
			  `shared_user_id` int(10) unsigned NOT NULL,
			  PRIMARY KEY (`album_id`, `shared_user_id`)
			) ENGINE = {$tableConfig['engine']} CHARACTER SET {$tableConfig['charset']} COLLATE {$tableConfig['collation']}
		";

		$tables[2000070]['xengallery_user_tag'] = "
			CREATE TABLE IF NOT EXISTS `xengallery_user_tag` (
			  `tag_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
			  `media_id` int(10) unsigned NOT NULL DEFAULT '0',
			  `user_id` int(10) unsigned NOT NULL DEFAULT '0',
			  `username` varchar(50) NOT NULL DEFAULT '',
			  `tag_data` blob,
			  `tag_date` int(10) unsigned NOT NULL DEFAULT '0',
			  PRIMARY KEY (`tag_id`)
			) ENGINE = {$tableConfig['engine']} CHARACTER SET {$tableConfig['charset']} COLLATE {$tableConfig['collation']}
		";

		$tables[2000070]['xengallery_watermark'] = "
			CREATE TABLE IF NOT EXISTS `xengallery_watermark` (
			  `watermark_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
			  `watermark_user_id` int(10) unsigned NOT NULL DEFAULT '0',
			  `watermark_date` int(10) unsigned NOT NULL DEFAULT '0',
			  `is_site` tinyint(3) unsigned NOT NULL DEFAULT '0',
			  PRIMARY KEY (`watermark_id`)
			) ENGINE = {$tableConfig['engine']} CHARACTER SET {$tableConfig['charset']} COLLATE {$tableConfig['collation']}
		";

		$tables[2010070]['xengallery_add_map'] = "
			CREATE TABLE IF NOT EXISTS xengallery_add_map (
				album_id INT(10) UNSIGNED NOT NULL,
				add_user_id INT(10) UNSIGNED NOT NULL,
				PRIMARY KEY (album_id,add_user_id)
			) ENGINE = {$tableConfig['engine']} CHARACTER SET {$tableConfig['charset']} COLLATE {$tableConfig['collation']};
		";

		$tables[2010070]['xengallery_album_permission'] = "
			CREATE TABLE IF NOT EXISTS xengallery_album_permission (
				album_id INT(10) UNSIGNED NOT NULL,
				permission ENUM('view','add') NOT NULL,
				access_type ENUM('public','followed','members','private','shared') DEFAULT 'public',
				share_users MEDIUMBLOB NOT NULL,
				PRIMARY KEY (album_id,permission)
			) ENGINE = {$tableConfig['engine']} CHARACTER SET {$tableConfig['charset']} COLLATE {$tableConfig['collation']};
		";

		$tables[2010070]['xengallery_album_watch'] = "
			CREATE TABLE IF NOT EXISTS xengallery_album_watch (
				user_id INT(10) UNSIGNED NOT NULL,
				album_id INT(10) UNSIGNED NOT NULL,
				notify_on ENUM('','media','comment','media_comment') NOT NULL,
				send_alert TINYINT(3) UNSIGNED NOT NULL,
				send_email TINYINT(3) UNSIGNED NOT NULL,
				PRIMARY KEY (user_id,album_id),
				KEY album_id_notify_on (album_id,notify_on)
			) ENGINE = {$tableConfig['engine']} CHARACTER SET {$tableConfig['charset']} COLLATE {$tableConfig['collation']};
		";

		$tables[2010070]['xengallery_category_map'] = "
			CREATE TABLE IF NOT EXISTS xengallery_category_map (
				category_id INT(10) UNSIGNED NOT NULL DEFAULT '0',
				view_user_group_id INT(10) UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY (category_id,view_user_group_id)
			) ENGINE = {$tableConfig['engine']} CHARACTER SET {$tableConfig['charset']} COLLATE {$tableConfig['collation']};
		";

		$tables[2010070]['xengallery_category_watch'] = "
			CREATE TABLE IF NOT EXISTS xengallery_category_watch (
				user_id INT(10) UNSIGNED NOT NULL,
				category_id INT(10) UNSIGNED NOT NULL,
				notify_on ENUM('','media') NOT NULL,
				send_alert TINYINT(3) UNSIGNED NOT NULL,
				send_email TINYINT(3) UNSIGNED NOT NULL,
				include_children TINYINT(3) UNSIGNED NOT NULL,
				PRIMARY KEY (user_id,category_id),
				KEY category_id_notify_on (category_id,notify_on)
			) ENGINE = {$tableConfig['engine']} CHARACTER SET {$tableConfig['charset']} COLLATE {$tableConfig['collation']};
		";

		$tables[2010070]['xengallery_exif_cache'] = "
			CREATE TABLE IF NOT EXISTS xengallery_exif_cache (
				data_id INT(10) NOT NULL DEFAULT '0',
				media_exif_data_cache_full MEDIUMBLOB NOT NULL,
				cache_date INT(10) UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY (data_id)
			) ENGINE = {$tableConfig['engine']} CHARACTER SET {$tableConfig['charset']} COLLATE {$tableConfig['collation']};
		";

		$tables[2010070]['xengallery_media_watch'] = "
			CREATE TABLE IF NOT EXISTS xengallery_media_watch (
				user_id INT(10) UNSIGNED NOT NULL,
				media_id INT(10) UNSIGNED NOT NULL,
				notify_on ENUM('','comment') NOT NULL DEFAULT '',
				send_alert TINYINT(3) UNSIGNED NOT NULL,
				send_email TINYINT(3) UNSIGNED NOT NULL,
				PRIMARY KEY (user_id,media_id),
				KEY media_id_notify_on (media_id,notify_on)
			) ENGINE = {$tableConfig['engine']} CHARACTER SET {$tableConfig['charset']} COLLATE {$tableConfig['collation']}
		";

		$tables[901010070]['xengallery_transcode_queue'] = "
			CREATE TABLE IF NOT EXISTS xengallery_transcode_queue (
				transcode_queue_id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
				queue_data MEDIUMBLOB NOT NULL,
				queue_state ENUM('pending', 'processing') DEFAULT 'pending',
				queue_date INT(10) UNSIGNED NOT NULL,
				PRIMARY KEY (transcode_queue_id),
				KEY queue_date (queue_date)
			) ENGINE = {$tableConfig['engine']} CHARACTER SET {$tableConfig['charset']} COLLATE {$tableConfig['collation']}
		";

		return $tables;
	}

	protected function getData()
	{
		$data = new MySql();
		return $data->getData();
	}

	protected function applyDefaultPermissions($previousVersion = null)
	{
		$applied = false;

		if (!$previousVersion)
		{
			// XFMG: Media permissions
			$this->applyGlobalPermission('xfmg', 'view', 'general', 'viewNode');
			$this->applyGlobalPermission('xfmg', 'add', 'forum', 'postThread');
			$this->applyGlobalPermission('xfmg', 'deleteOwn', 'forum', 'deleteOwnPost');
			$this->applyGlobalPermission('xfmg', 'editOwn', 'forum', 'editOwnPost');
			$this->applyGlobalPermissionInt('xfmg', 'editOwnMediaTimeLimit', -1);
			$this->applyGlobalPermission('xfmg', 'moveOwn', 'forum', 'editOwnPost');
			$this->applyGlobalPermission('xfmg', 'react', 'forum', 'react');
			$this->applyGlobalPermission('xfmg', 'rate', 'forum', 'react');

			// XFMG: Media moderator permissions
			$this->applyGlobalPermission('xfmg', 'viewDeleted', 'forum', 'viewDeleted');
			$this->applyGlobalPermission('xfmg', 'viewModerated', 'forum', 'viewDeleted');
			$this->applyGlobalPermission('xfmg', 'undelete', 'forum', 'undelete');
			$this->applyGlobalPermission('xfmg', 'approveUnapprove', 'forum', 'approveUnapprove');
			$this->applyGlobalPermission('xfmg', 'deleteAny', 'forum', 'deleteAnyPost');
			$this->applyGlobalPermission('xfmg', 'hardDeleteAny', 'forum', 'hardDeleteAnyPost');
			$this->applyGlobalPermission('xfmg', 'editAny', 'forum', 'editAnyPost');
			$this->applyGlobalPermission('xfmg', 'moveAny', 'forum', 'editAnyPost');
			$this->applyGlobalPermission('xfmg', 'warn', 'forum', 'warn');

			// XFMG: Album permissions
			$this->applyGlobalPermission('xfmg', 'createAlbum', 'forum', 'postThread');
			$this->applyGlobalPermission('xfmg', 'addImage', 'forum', 'postThread');
			$this->applyGlobalPermission('xfmg', 'addVideo', 'forum', 'postThread');
			$this->applyGlobalPermission('xfmg', 'addAudio', 'forum', 'postThread');
			$this->applyGlobalPermission('xfmg', 'addEmbed', 'forum', 'postThread');
			$this->applyGlobalPermission('xfmg', 'deleteOwnAlbum', 'forum', 'deleteOwnPost');
			$this->applyGlobalPermission('xfmg', 'editOwnAlbum', 'forum', 'editOwnPost');
			$this->applyGlobalPermissionInt('xfmg', 'editOwnAlbumTimeLimit', -1);
			$this->applyGlobalPermission('xfmg', 'moveOwnAlbum', 'forum', 'editOwnPost');
			$this->applyGlobalPermission('xfmg', 'changePrivacyOwnAlbum', 'forum', 'editOwnPost');
			$this->applyGlobalPermission('xfmg', 'reactAlbum', 'forum', 'react');
			$this->applyGlobalPermission('xfmg', 'rateAlbum', 'forum', 'react');

			// XFMG: Album moderator permissions
			$this->applyGlobalPermission('xfmg', 'bypassPrivacy', 'general', 'bypassUserPrivacy');
			$this->applyGlobalPermission('xfmg', 'viewDeletedAlbums', 'forum', 'viewDeleted');
			$this->applyGlobalPermission('xfmg', 'undeleteAlbum', 'forum', 'undelete');
			$this->applyGlobalPermission('xfmg', 'deleteAnyAlbum', 'forum', 'deleteAnyPost');
			$this->applyGlobalPermission('xfmg', 'hardDeleteAnyAlbum', 'forum', 'hardDeleteAnyPost');
			$this->applyGlobalPermission('xfmg', 'editAnyAlbum', 'forum', 'editAnyPost');
			$this->applyGlobalPermission('xfmg', 'moveAnyAlbum', 'forum', 'editAnyPost');
			$this->applyGlobalPermission('xfmg', 'changePrivacyAnyAlbum', 'forum', 'editAnyPost');
			$this->applyGlobalPermission('xfmg', 'warnAlbum', 'forum', 'warn');

			// XFMG: Comment permissions
			$this->applyGlobalPermission('xfmg', 'viewComments', 'general', 'viewNode');
			$this->applyGlobalPermission('xfmg', 'addComment', 'forum', 'postThread');
			$this->applyGlobalPermission('xfmg', 'deleteComment', 'forum', 'deleteOwnPost');
			$this->applyGlobalPermission('xfmg', 'editComment', 'forum', 'editOwnPost');
			$this->applyGlobalPermissionInt('xfmg', 'editOwnCommentTimeLimit', -1);
			$this->applyGlobalPermission('xfmg', 'reactComment', 'forum', 'react');

			// XFMG: Comment moderator permissions
			$this->applyGlobalPermission('xfmg', 'viewDeletedComments', 'forum', 'viewDeleted');
			$this->applyGlobalPermission('xfmg', 'viewModeratedComments', 'forum', 'viewModerated');
			$this->applyGlobalPermission('xfmg', 'undeleteComment', 'forum', 'undelete');
			$this->applyGlobalPermission('xfmg', 'approveUnapproveComment', 'forum', 'approveUnapprove');
			$this->applyGlobalPermission('xfmg', 'deleteAnyComment', 'forum', 'deleteAnyPost');
			$this->applyGlobalPermission('xfmg', 'editAnyComment', 'forum', 'editAnyPost');
			$this->applyGlobalPermission('xfmg', 'warnComment', 'forum', 'warn');

			// XFMG: Note permissions
			$this->applyGlobalPermission('xfmg', 'viewNote', 'general', 'viewNode');
			$this->applyGlobalPermission('xfmg', 'tagSelf', 'forum', 'postThread');
			$this->applyGlobalPermission('xfmg', 'addNoteOwn', 'forum', 'postThread');
			$this->applyGlobalPermission('xfmg', 'addNoteAny', 'forum', 'tagAnyThread');
			$this->applyGlobalPermission('xfmg', 'deleteTagSelf', 'forum', 'deleteOwnPost');
			$this->applyGlobalPermission('xfmg', 'deleteOwnNote', 'forum', 'deleteOwnPost');

			// XFMG: Note permissions
			$this->applyGlobalPermission('xfmg', 'viewPendingNote', 'forum', 'viewModerated');
			$this->applyGlobalPermission('xfmg', 'deleteAnyNote', 'forum', 'deleteAnyPost');
			$this->applyGlobalPermission('xfmg', 'tagWithoutApproval', 'general', 'bypassUserPrivacy');

			// XFMG: Upload quotas
			$this->applyGlobalPermission('xfmgStorage', 'maxAllowedStorage', 'use_int', 50);
			$this->applyGlobalPermission('xfmg', 'maxFileSize', 'use_int', 10);
			$this->applyGlobalPermission('xfmg', 'maxImageWidth', 'use_int', -1);
			$this->applyGlobalPermission('xfmg', 'maxImageHeight', 'use_int', -1);

			$applied = true;
		}

		if (!$previousVersion || $previousVersion < 901000170)
		{
			// XFMG: Comment moderator permissions
			$this->applyGlobalPermission('xfmg', 'hardDeleteAnyComment', 'forum', 'hardDeleteAnyPost');

			$applied = true;
		}

		if (!$previousVersion || $previousVersion < 901010070)
		{
			// XFMG: Media permissions
			$this->applyGlobalPermission('xfmg', 'tagOwnMedia', 'forum', 'tagOwnThread');
			$this->applyGlobalPermission('xfmg', 'tagAnyMedia', 'forum', 'tagAnyThread');
			$this->applyGlobalPermission('xfmg', 'manageOthersTagsOwnMedia', 'forum', 'manageOthersTagsOwnThread');

			// XFMG: Media moderator permissions
			$this->applyGlobalPermission('xfmg', 'manageAnyTag', 'forum', 'manageAnyTag');

			$applied = true;
		}

		if (!$previousVersion || $previousVersion < 902000010)
		{
			// XFMG: Media moderator permissions
			$this->applyGlobalPermission('xfmg', 'inlineMod', 'forum', 'inlineMod');

			// XFMG: Album moderator permissions
			$this->applyGlobalPermission('xfmg', 'inlineModAlbum', 'forum', 'inlineMod');

			// XFMG: Comment moderator permissions
			$this->applyGlobalPermission('xfmg', 'inlineModComment', 'forum', 'inlineMod');

			$applied = true;
		}

		if (!$previousVersion || $previousVersion < 902000034)
		{
			// XFMG: Note moderator permissions
			$this->applyGlobalPermission('xfmg', 'editNoteAny', 'forum', 'editAnyPost');

			$applied = true;
		}

		if (!$previousVersion || $previousVersion < 902000035)
		{
			// XFMG: Album moderator permissions
			$this->applyGlobalPermission('xfmg', 'approveUnapproveAlbum', 'xfmg', 'approveUnapprove');

			$applied = true;
		}

		if ($previousVersion < 902000035)
		{
			// Note: these were missed from the 2.0.0 alpha upgrade but set for new installs.

			// XFMG: Media moderator permissions
			$this->applyGlobalPermission('xfmg', 'undelete', 'forum', 'undelete');

			// XFMG: Album moderator permissions
			$this->applyGlobalPermission('xfmg', 'undeleteAlbum', 'forum', 'undelete');

			// XFMG: Comment moderator permissions
			$this->applyGlobalPermission('xfmg', 'undeleteComment', 'forum', 'undelete');

			$applied = true;
		}

		if (!$previousVersion || $previousVersion < 902000051)
		{
			// Note: Deliberately not adding bypass watermark or watermark own permission, this likely won't be needed in most cases

			// XFMG: Media moderator permissions
			$this->applyGlobalPermission('xfmg', 'watermarkAny', 'xfmg', 'editAny');

			$applied = true;
		}

		if (!$previousVersion || ($previousVersion && $previousVersion > 902000010 && $previousVersion < 902000470))
		{
			// Note: Only apply default perm value if not already running XF2 (or new install)

			// XFMG: Media permissions
			$this->applyGlobalPermission('xfmg', 'addWithoutApproval', 'general', 'submitWithoutApproval');
		}

		return $applied;
	}
}