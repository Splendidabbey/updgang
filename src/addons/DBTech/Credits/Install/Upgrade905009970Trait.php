<?php

namespace DBTech\Credits\Install;

use XF\Db\Schema\Alter;

/**
 * @property \XF\AddOn\AddOn addOn
 * @property \XF\App app
 *
 * @method \XF\Db\AbstractAdapter db()
 * @method \XF\Db\SchemaManager schemaManager()
 * @method \XF\Db\Schema\Column addOrChangeColumn($table, $name, $type = null, $length = null)
 */
trait Upgrade905009970Trait
{
	/**
	 *
	 */
	public function upgrade905000031Step1()
	{
		$this->insertNamedWidget('dbtech_credits_wallet');
		$this->insertNamedWidget('dbtech_credits_richest');

		// Purge the cache of both possible copies of this
		\XF::registry()->delete([
			'dbt_credits_currency',
			'dbt_credits_event',
			'dbt_credits_eventtrigger',
			'dbt_credits_field',

			'dbtech_credits_currency',
			'dbtech_credits_event',
			'dbtech_credits_eventtrigger',
			'dbtech_credits_field',
		]);
	}

	/**
	 *
	 */
	public function upgrade905000032Step1()
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_dbtech_credits_eventtrigger', function (Alter $table)
		{
			$table->changeColumn('eventtriggerid', 'varbinary');
		});

		$sm->alterTable('xf_dbtech_credits_currency', function (Alter $table)
		{
			$table->addColumn('postbit', 'tinyint')->setDefault(1);
		});

		$sm->alterTable('xf_dbtech_credits_event', function (Alter $table)
		{
			$table->addColumn('title', 'varchar', 255)->setDefault('')->after('eventid');
			$table->addColumn('display', 'tinyint', 3)->setDefault(1)->after('alert');
		});

		// Purge the cache of both possible copies of this
		\XF::registry()->delete([
			'dbt_credits_event',
			'dbt_credits_eventtrigger',
		]);
	}

	/**
	 *
	 * @throws \XF\Db\Exception
	 */
	public function upgrade905000033Step1()
	{
		$this->query("
			REPLACE INTO `xf_purchasable`
				(`purchasable_type_id`, `purchasable_class`, `addon_id`)
			VALUES
				('dbtech_credits_currency', 'DBTech\\\\Credits\\\\XF:Currency', X'4442546563682F43726564697473')
		");
	}

	/**
	 *
	 * @throws \XF\Db\Exception
	 * @throws \XF\Db\Exception
	 * @throws \XF\Db\Exception
	 * @throws \XF\Db\Exception
	 * @throws \XF\Db\Exception
	 * @throws \XF\Db\Exception
	 * @throws \XF\Db\Exception
	 * @throws \XF\Db\Exception
	 * @throws \XF\Db\Exception
	 * @throws \XF\Db\Exception
	 * @throws \XF\Db\Exception
	 * @throws \XF\Db\Exception
	 * @throws \XF\Db\Exception
	 * @throws \XF\Db\Exception
	 * @throws \XF\Db\Exception
	 * @throws \XF\Db\Exception
	 * @throws \XF\Db\Exception
	 */
	public function upgrade905000039Step1()
	{
		$this->query("
			UPDATE `xf_dbtech_credits_eventtrigger`
				SET `callback_class` = REPLACE(`callback_class`, 'Event_XenGallery', 'Event_SonnbGallery_')
				WHERE `eventtriggerid` LIKE 'xengallery%'
		");

		$this->query("
			UPDATE `xf_dbtech_credits_eventtrigger`
				SET `callback_class` = REPLACE(`callback_class`, 'Event_XenMedio', 'Event_JaxelMedio')
				WHERE `eventtriggerid` LIKE 'xenmedio%'
		");

		$this->query("
			UPDATE `xf_dbtech_credits_eventtrigger`
				SET `callback_class` = REPLACE(`callback_class`, 'Event_PostRate', 'Event_PostRating_Rate')
				WHERE `eventtriggerid` LIKE 'postrate%'
		");

		$this->query("
			UPDATE `xf_dbtech_credits_eventtrigger`
				SET `callback_class` = 'DBTech_Credits_Model_Event_Resource_Download'
				WHERE `eventtriggerid` = 'resourcedownload'
		");

		$this->query("
			UPDATE `xf_dbtech_credits_eventtrigger`
				SET `callback_class` = 'DBTech_Credits_Model_Event_Resource_Downloaded'
				WHERE `eventtriggerid` = 'resourcedownloaded'
		");

		$this->query("
			UPDATE `xf_dbtech_credits_eventtrigger`
				SET `callback_class` = 'DBTech_Credits_Model_Event_Resource_Rate'
				WHERE `eventtriggerid` = 'resourcerate'
		");

		$this->query("
			UPDATE `xf_dbtech_credits_eventtrigger`
				SET `callback_class` = 'DBTech_Credits_Model_Event_Resource_Rated'
				WHERE `eventtriggerid` = 'resourcerated'
		");

		$this->query("
			UPDATE `xf_dbtech_credits_eventtrigger`
				SET `callback_class` = 'DBTech_Credits_Model_Event_Resource_Update'
				WHERE `eventtriggerid` = 'resourceupdate'
		");

		$this->query("
			UPDATE `xf_dbtech_credits_eventtrigger`
				SET `callback_class` = 'DBTech_Credits_Model_Event_Resource_Upload'
				WHERE `eventtriggerid` = 'resourceupload'
		");

		$this->query("
			UPDATE `xf_dbtech_credits_eventtrigger`
				SET `callback_class` = 'DBTech_Credits_Model_Event_Gallery_Comment'
				WHERE `eventtriggerid` = 'gallerycomment'
		");

		$this->query("
			UPDATE `xf_dbtech_credits_eventtrigger`
				SET `callback_class` = 'DBTech_Credits_Model_Event_Gallery_Commented'
				WHERE `eventtriggerid` = 'gallerycommented'
		");

		$this->query("
			UPDATE `xf_dbtech_credits_eventtrigger`
				SET `callback_class` = 'DBTech_Credits_Model_Event_Gallery_Download'
				WHERE `eventtriggerid` = 'gallerydownload'
		");

		$this->query("
			UPDATE `xf_dbtech_credits_eventtrigger`
				SET `callback_class` = 'DBTech_Credits_Model_Event_Gallery_Downloaded'
				WHERE `eventtriggerid` = 'gallerydownloaded'
		");

		$this->query("
			UPDATE `xf_dbtech_credits_eventtrigger`
				SET `callback_class` = 'DBTech_Credits_Model_Event_Gallery_Rate'
				WHERE `eventtriggerid` = 'galleryrate'
		");

		$this->query("
			UPDATE `xf_dbtech_credits_eventtrigger`
				SET `callback_class` = 'DBTech_Credits_Model_Event_Gallery_Rated'
				WHERE `eventtriggerid` = 'galleryrated'
		");

		$this->query("
			UPDATE `xf_dbtech_credits_eventtrigger`
				SET `callback_class` = 'DBTech_Credits_Model_Event_Gallery_Upload'
				WHERE `eventtriggerid` = 'galleryupload'
		");

		$this->query("
			UPDATE `xf_dbtech_credits_eventtrigger`
				SET `callback_class` = REPLACE(`callback_class`, '_', '\\\')
				WHERE `callback_class` LIKE 'DBTech_Credits_%'
		");

		// Purge the cache
		\XF::registry()->delete([
			'dbt_credits_eventtrigger',
		]);
	}

	/**
	 *
	 * @throws \XF\Db\Exception
	 */
	public function upgrade905000370Step1()
	{
		foreach ([
			'punish',
			'warning',
		] as $eventTriggerId)
		{
			$this->query("
				UPDATE `xf_dbtech_credits_eventtrigger`
					SET `cancel` = 0
					WHERE `eventtriggerid` = '$eventTriggerId'
			");
		}

		// Purge the cache of both possible copies of this
		\XF::registry()->delete([
			'dbt_credits_eventtrigger',
		]);
	}
}