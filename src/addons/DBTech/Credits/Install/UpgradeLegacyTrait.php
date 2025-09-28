<?php

namespace DBTech\Credits\Install;

use XF\Db\Schema\Alter;
use XF\Db\Schema\Create;

/**
 * @property \XF\AddOn\AddOn addOn
 * @property \XF\App app
 *
 * @method \XF\Db\AbstractAdapter db()
 * @method \XF\Db\SchemaManager schemaManager()
 * @method \XF\Db\Schema\Column addOrChangeColumn($table, $name, $type = null, $length = null)
 */
trait UpgradeLegacyTrait
{
	/**
	 *
	 */
	public function upgrade20160301Step1()
	{
		$sm = $this->schemaManager();

		$sm->createTable('xf_dbtech_credits_charge', function (Create $table)
		{
			$table->addColumn('postid', 'int');
			$table->addColumn('contenthash', 'char', 32);
			$table->addColumn('cost', 'double')->unsigned(false)->setDefault(0);
			$table->addPrimaryKey(['postid', 'contenthash']);
		});

		$sm->createTable('xf_dbtech_credits_charge_purchase', function (Create $table)
		{
			$table->addColumn('postid', 'int');
			$table->addColumn('contenthash', 'char', 32);
			$table->addColumn('userid', 'int');
			$table->addPrimaryKey(['postid', 'contenthash', 'userid']);
		});

		$sm->createTable('xf_dbtech_credits_purchase_log', function (Create $table)
		{
			$table->addColumn('logid', 'int')->autoIncrement();
			$table->addColumn('eventid', 'int')->setDefault(0);
			$table->addColumn('processor', 'varchar', 25)->setDefault('');
			$table->addColumn('transaction_id', 'varchar', 50)->setDefault('');
			$table->addColumn('subscriber_id', 'varchar', 50)->setDefault('');
			$table->addColumn('transaction_type', 'enum')->values(['payment','cancel','info','error'])->setDefault('info');
			$table->addColumn('message', 'varchar', 255)->setDefault('');
			$table->addColumn('transaction_details', 'mediumblob')->nullable(true);
			$table->addColumn('log_date', 'int')->setDefault(0);
			$table->addKey('transaction_id');
			$table->addKey('subscriber_id');
			$table->addKey('log_date');
		});
	}

	/**
	 *
	 * @throws \XF\Db\Exception
	 */
	public function upgrade20160301Step2()
	{
		$this->query("
			UPDATE `xf_dbtech_credits_eventtrigger`
				SET `active` = '1'
				WHERE `eventtriggerid` IN('purchase', 'content')
		");
	}

	/**
	 *
	 * @throws \XF\Db\Exception
	 * @throws \XF\Db\Exception
	 */
	public function upgrade20160308Step1()
	{
		$this->query("
			UPDATE `xf_dbtech_credits_eventtrigger`
				SET `active` = '1'
				WHERE `eventtriggerid` IN('birthday', 'warning', 'wall', 'visitor')
		");

		$this->query("
			UPDATE `xf_dbtech_credits_eventtrigger`
				SET `multiplier` = '2', `cancel` = '1'
				WHERE `eventtriggerid` = 'wall'
		");
	}

	/**
	 *
	 */
	public function upgrade20160315Step1()
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_user', function (Alter $table)
		{
			$table->addColumn('dbtech_credits_lastdaily', 'int')->setDefault(0);
		});
	}

	/**
	 *
	 * @throws \XF\Db\Exception
	 * @throws \XF\Db\Exception
	 * @throws \XF\Db\Exception
	 */
	public function upgrade20160315Step2()
	{
		$this->query("
			UPDATE `xf_dbtech_credits_eventtrigger` SET `active` = '1' WHERE `eventtriggerid` IN('poll', 'sticky', 'vote')
		");

		$this->query("
			DELETE FROM `xf_dbtech_credits_eventtrigger` WHERE `eventtriggerid` IN('friend', 'evaluate', 'rate')
		");

		$this->query("
			INSERT IGNORE INTO `xf_dbtech_credits_eventtrigger`
				(`eventtriggerid`, `title`, `description`, `active`, `multiplier`, `multiplier_label`, `multiplier_popup`, `parent`, `category`, `global`, `revert`, `cancel`, `rebuild`, `currency`, `referformat`, `outbound`, `inbound`, `value`, `settings`)
			VALUES
				('follow', 'Follow', X'466F6C6C6F77696E6720736F6D656F6E6520656C73652E', 1, 0, '', 0, '', 'network', 1, 1, 1, 1, 0, 'member.php?u=', 1, 1, 1, NULL),
				('followed', 'Followed', X'536F6D656F6E6520656C736520666F6C6C6F77696E6720796F752E', 1, 0, '', 0, '', 'network', 1, 1, 1, 1, 0, 'member.php?u=', 1, 1, 1, NULL),
				('daily', 'Daily Activity', X'41776172646564206F6E206669727374206C6F67696E2065616368206461792E', 1, 0, '', 0, '', 'time', 1, 0, 0, 0, 0, '', 1, 1, 1, NULL)
		");
	}

	/**
	 *
	 * @throws \XF\Db\Exception
	 * @throws \XF\Db\Exception
	 */
	public function upgrade20160322Step1()
	{
		$this->query("
			UPDATE `xf_option` SET `option_value` = 'a:3:{s:7:\"enabled\";s:1:\"1\";s:13:\"left_position\";s:3:\"end\";s:14:\"right_position\";b:0;}' WHERE `option_id` = 'dbtech_credits_navbar'
		");

		$this->query("
			INSERT IGNORE INTO `xf_dbtech_credits_eventtrigger`
				(`eventtriggerid`, `title`, `description`, `active`, `multiplier`, `multiplier_label`, `multiplier_popup`, `parent`, `category`, `global`, `revert`, `cancel`, `rebuild`, `currency`, `referformat`, `outbound`, `inbound`, `value`, `settings`)
			VALUES
				('galleryupload', 'XenGallery Upload', X'55706C6F6164696E67206E65772058656E47616C6C657279204D656469612E204D756C7469706C6965722069732066696C6573697A652E', 1, 1, 'Bytes|Byte', 0, '', 'share', 1, 1, 1, 1, 0, 'attachment.php?attachmentid=', 1, 1, 1, NULL),
				('gallerydownload', 'XenGallery Download', X'446F776E6C6F6164696E672058656E47616C6C657279206D656469612E204D756C7469706C6965722069732066696C6573697A652E', 1, 1, 'Bytes|Byte', 0, 'Media', 'share', 1, 0, 1, 0, 0, 'attachment.php?attachmentid=', 1, 1, 1, NULL),
				('gallerydownloaded', 'XenGallery Downloaded', X'536F6D656F6E6520656C736520646F776E6C6F6164696E6720796F75722058656E47616C6C657279206D656469612E204D756C7469706C6965722069732066696C6573697A652E', 1, 1, 'Bytes|Byte', 0, '', 'share', 1, 0, 0, 0, 0, 'attachment.php?attachmentid=', 1, 1, 1, NULL),
				('galleryrate', 'XenGallery Rate', X'526174696E672058656E47616C6C657279206D656469612E204D756C7469706C69657220697320726174696E672E', 1, 1, 'Stars|Star', 0, 'Media', 'opinion', 1, 1, 1, 0, 0, 'showthread.php?t=', 1, 1, 1, NULL),
				('galleryrated', 'XenGallery Rated', X'536F6D656F6E6520656C736520726174656420796F75722058656E47616C6C657279206D656469612E204D756C7469706C69657220697320726174696E672E', 1, 1, 'Stars|Star', 0, '', 'opinion', 1, 1, 0, 0, 0, 'showthread.php?t=', 1, 1, 1, NULL),
				('gallerycomment', 'XenGallery Comment', X'416464696E67206120636F6D6D656E7420746F20612058656E47616C6C657279206D65646961207265736F757263652E', 1, 2, '', 0, 'Media', 'discuss', 1, 1, 1, 1, 0, 'showpost.php?p=', 1, 1, 1, NULL),
				('gallerycommented', 'XenGallery Commented', X'536F6D656F6E6520656C736520636F6D6D656E74696E67206F6E20796F75722058656E47616C6C657279206D65646961207265736F757263652E', 1, 2, '', 0, '', 'discuss', 1, 1, 0, 1, 0, 'showpost.php?p=', 1, 1, 1, NULL)
		");
	}

	/**
	 *
	 */
	public function upgrade20160328Step1()
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_dbtech_credits_currency', function (Alter $table)
		{
			$table->changeColumn('usercol', 'varchar', 255)->setDefault('user_id');
		});

		$sm->alterTable('xf_dbtech_credits_transaction', function (Alter $table)
		{
			$table->changeColumn('touserid', 'int')->setDefault(0)->renameTo('sourceuserid');
		});

		$sm->alterTable('xf_dbtech_credits_transaction_pending', function (Alter $table)
		{
			$table->changeColumn('touserid', 'int')->setDefault(0)->renameTo('sourceuserid');
		});
	}

	/**
	 *
	 */
	public function upgrade20160328Step2()
	{
		$sm = $this->schemaManager();

		foreach ([
			'conversion',
			'display',
			'payment',
			'redemption',
		] as $table)
		{
			// Quickly drop all tables
			$sm->dropTable("xf_dbtech_credits_$table");
		}
	}

	/**
	 *
	 * @throws \XF\Db\Exception
	 * @throws \XF\Db\Exception
	 * @throws \XF\Db\Exception
	 * @throws \XF\Db\Exception
	 */
	public function upgrade20160328Step3()
	{
		$this->query("
			INSERT IGNORE INTO `xf_dbtech_credits_eventtrigger` (`eventtriggerid`, `title`, `description`, `active`, `multiplier`, `multiplier_label`, `multiplier_popup`, `parent`, `category`, `global`, `revert`, `cancel`, `rebuild`, `currency`, `referformat`, `outbound`, `inbound`, `value`, `settings`)
			VALUES
				('resourcedownload', 'XenResource Download', X'446F776E6C6F6164696E6720612058656E5265736F75726365207265736F757263652E204D756C7469706C6965722069732066696C6573697A652E', 1, 1, 'Bytes|Byte', 0, 'Resource', 'share', 1, 0, 1, 0, 0, 'attachment.php?attachmentid=', 1, 1, 1, NULL),
				('resourcedownloaded', 'XenResource Downloaded', X'536F6D656F6E6520656C736520646F776E6C6F6164696E6720796F75722058656E5265736F75726365207265736F757263652E204D756C7469706C6965722069732066696C6573697A652E', 1, 1, 'Bytes|Byte', 0, '', 'share', 1, 0, 0, 0, 0, 'attachment.php?attachmentid=', 1, 1, 1, NULL),
				('resourcerate', 'XenResource Rate', X'526174696E6720612058656E5265736F75726365207265736F757263652E204D756C7469706C69657220697320726174696E672E', 1, 1, 'Stars|Star', 0, 'Resource', 'opinion', 1, 1, 1, 0, 0, 'showthread.php?t=', 1, 1, 1, NULL),
				('resourcerated', 'XenResource Rated', X'536F6D656F6E6520656C736520726174656420796F75722058656E5265736F75726365207265736F757263652E204D756C7469706C69657220697320726174696E672E', 1, 1, 'Stars|Star', 0, '', 'opinion', 1, 1, 0, 0, 0, 'showthread.php?t=', 1, 1, 1, NULL),
				('resourceupdate', 'XenResource Update', X'416464696E6720616E2075706461746520746F20612058656E5265736F75726365207265736F757263652E', 1, 0, '', 0, '', 'share', 1, 1, 1, 1, 0, 'attachment.php?attachmentid=', 1, 1, 1, NULL),
				('resourceupload', 'XenResource Upload', X'55706C6F6164696E672061206E65772058656E5265736F75726365207265736F757263652E204D756C7469706C6965722069732066696C6573697A652E', 1, 1, 'Bytes|Byte', 0, '', 'share', 1, 1, 1, 1, 0, 'attachment.php?attachmentid=', 1, 1, 1, NULL)
		");

		$this->query("
			UPDATE `xf_dbtech_credits_eventtrigger`
				SET `active` = '1' WHERE `eventtriggerid` IN('transfer')
		");

		$this->query("
			UPDATE `xf_dbtech_credits_eventtrigger`
				SET `title` = 'Visit' WHERE `eventtriggerid` = 'visit'
		");

		$this->query("
			UPDATE `xf_dbtech_credits_eventtrigger`
				SET `title` = 'Profile' WHERE `eventtriggerid` = 'profile'
		");
	}

	/**
	 *
	 */
	public function upgrade20160329Step1()
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_dbtech_credits_eventtrigger', function (Alter $table)
		{
			$table->addColumn('charge', 'tinyint')->setDefault(1)->after('rebuild');
			$table->addColumn('usergroups', 'tinyint')->setDefault(1)->after('charge');
		});

		$sm->alterTable('xf_user', function (Alter $table)
		{
			$table->addColumn('dbtech_credits_lastinterest', 'int')->setDefault(0)->after('dbtech_credits_lastdaily');
			$table->addColumn('dbtech_credits_lastpaycheck', 'int')->setDefault(0)->after('dbtech_credits_lastinterest');
		});
	}

	/**
	 *
	 * @throws \XF\Db\Exception
	 * @throws \XF\Db\Exception
	 * @throws \XF\Db\Exception
	 */
	public function upgrade20160329Step2()
	{
		$this->query("
			INSERT IGNORE INTO `xf_dbtech_credits_eventtrigger` (`eventtriggerid`, `title`, `description`, `active`, `multiplier`, `multiplier_label`, `multiplier_popup`, `parent`, `category`, `global`, `revert`, `cancel`, `rebuild`, `charge`, `usergroups`, `currency`, `referformat`, `outbound`, `inbound`, `value`, `settings`)
			VALUES
				('registration', 'Registration', X'41206E6577207573657220726567697374657273206F6E2074686520666F72756D2E', 1, 0, '', 0, '', 'accounts', 1, 0, 0, 0, 0, 0, 0, '', 1, 1, 1, NULL)
		");

		$this->query("
			UPDATE `xf_dbtech_credits_eventtrigger`
				SET `active` = '1' WHERE `eventtriggerid` IN('transfer', 'interest', 'paycheck')
		");

		$this->query("
			UPDATE `xf_dbtech_credits_eventtrigger`
				SET `category` = 'time' WHERE `eventtriggerid` = 'interest'
		");
	}

	/**
	 *
	 */
	public function upgrade20160402Step1()
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_dbtech_credits_eventtrigger', function (Alter $table)
		{
			$table->changeColumn('referformat', 'varchar', 255)->setDefault('');
		});
	}

	/**
	 *
	 * @throws \XF\Db\Exception
	 */
	public function upgrade20160402Step2()
	{
		$this->query("
			INSERT IGNORE INTO `xf_dbtech_credits_eventtrigger` (`eventtriggerid`, `title`, `description`, `active`, `multiplier`, `multiplier_label`, `multiplier_popup`, `parent`, `category`, `global`, `revert`, `cancel`, `rebuild`, `charge`, `usergroups`, `currency`, `referformat`, `outbound`, `inbound`, `value`, `settings`)
			VALUES
				('trophy', 'Trophy', X'4265696E6720617761726465642061206E65772074726F7068792E', 1, 0, '', 0, '', 'accounts', 1, 0, 0, 1, 1, 1, 0, '', 1, 1, 1, NULL);
		");
	}

	/**
	 *
	 */
	public function upgrade20160410Step1()
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_dbtech_credits_eventtrigger', function (Alter $table)
		{
			$table->changeColumn('parent', 'varchar', 255)->setDefault('');
			$table->changeColumn('category', 'varchar', 255)->setDefault('');
		});
	}

	/**
	 *
	 * @throws \XF\Db\Exception
	 * @throws \XF\Db\Exception
	 */
	public function upgrade20160416Step1()
	{
		$this->query("
			UPDATE `xf_dbtech_credits_eventtrigger`
				SET `active` = '1'
				WHERE `eventtriggerid` IN('punish')
		");

		$this->query("
			UPDATE `xf_dbtech_credits_eventtrigger`
				SET `cancel` = '1'
				WHERE `eventtriggerid` IN('punish', 'warning')
		");
	}

	/**
	 *
	 * @throws \XF\Db\Exception
	 * @throws \XF\Db\Exception
	 * @throws \XF\Db\Exception
	 */
	public function upgrade20160518Step1()
	{
		$this->query("
			UPDATE xf_dbtech_credits_eventtrigger
				SET title = REPLACE(title, 'XenGallery', 'MediaGallery')
		");

		$this->query("
			UPDATE xf_dbtech_credits_eventtrigger
				SET description = REPLACE(description, 'XenGallery', 'MediaGallery')
		");

		$this->query("
			INSERT IGNORE INTO `xf_dbtech_credits_eventtrigger` (`eventtriggerid`, `title`, `description`, `active`, `multiplier`, `multiplier_label`, `multiplier_popup`, `parent`, `category`, `global`, `revert`, `cancel`, `rebuild`, `charge`, `usergroups`, `currency`, `referformat`, `outbound`, `inbound`, `value`, `settings`)
			VALUES
				('xengallerycomment', 'XenGallery Comment', X'416464696E67206120636F6D6D656E7420746F20612058656E47616C6C657279206D65646961207265736F757263652E', 1, 2, '', 0, 'Media', 'discuss', 1, 1, 1, 1, 1, 1, 0, 'showpost.php?p=', 1, 1, 1, NULL),
				('xengallerycommented', 'XenGallery Commented', X'536F6D656F6E6520656C736520636F6D6D656E74696E67206F6E20796F75722058656E47616C6C657279206D65646961207265736F757263652E', 1, 2, '', 0, '', 'discuss', 1, 1, 0, 1, 1, 1, 0, 'showpost.php?p=', 1, 1, 1, NULL),
				('xengallerydownload', 'XenGallery Download', X'446F776E6C6F6164696E672058656E47616C6C657279206D656469612E204D756C7469706C6965722069732066696C6573697A652E', 1, 1, 'Bytes|Byte', 0, 'Media', 'share', 1, 0, 1, 0, 1, 1, 0, 'attachment.php?attachmentid=', 1, 1, 1, NULL),
				('xengallerydownloaded', 'XenGallery Downloaded', X'536F6D656F6E6520656C736520646F776E6C6F6164696E6720796F75722058656E47616C6C657279206D656469612E204D756C7469706C6965722069732066696C6573697A652E', 1, 1, 'Bytes|Byte', 0, '', 'share', 1, 0, 0, 0, 1, 1, 0, 'attachment.php?attachmentid=', 1, 1, 1, NULL),
				('xengalleryrate', 'XenGallery Rate', X'526174696E672058656E47616C6C657279206D656469612E204D756C7469706C69657220697320726174696E672E', 1, 1, 'Stars|Star', 0, 'Media', 'opinion', 1, 1, 1, 0, 1, 1, 0, 'showthread.php?t=', 1, 1, 1, NULL),
				('xengalleryrated', 'XenGallery Rated', X'536F6D656F6E6520656C736520726174656420796F75722058656E47616C6C657279206D656469612E204D756C7469706C69657220697320726174696E672E', 1, 1, 'Stars|Star', 0, '', 'opinion', 1, 1, 0, 0, 1, 1, 0, 'showthread.php?t=', 1, 1, 1, NULL),
				('xengalleryupload', 'XenGallery Upload', X'55706C6F6164696E67206E65772058656E47616C6C657279204D656469612E204D756C7469706C6965722069732066696C6573697A652E', 1, 1, 'Bytes|Byte', 0, '', 'share', 1, 1, 1, 1, 1, 1, 0, 'attachment.php?attachmentid=', 1, 1, 1, NULL)
		");
	}

	/**
	 *
	 * @throws \XF\Db\Exception
	 */
	public function upgrade20160608Step1()
	{
		$this->query("
			INSERT IGNORE INTO `xf_dbtech_credits_eventtrigger` (`eventtriggerid`, `title`, `description`, `active`, `multiplier`, `multiplier_label`, `multiplier_popup`, `parent`, `category`, `global`, `revert`, `cancel`, `rebuild`, `charge`, `usergroups`, `currency`, `referformat`, `outbound`, `inbound`, `value`, `settings`)
			VALUES
				('avatar', 'Upload Avatar', X'55706C6F6164696E672061206E6577206176617461722E204D756C7469706C6965722069732066696C6573697A652E', 1, 0, '', 0, '', 'accounts', 1, 1, 1, 0, 1, 1, 0, '', 1, 1, 1, NULL),
				('report', 'Report', X'5265706F7274696E672061207069656365206F6620636F6E74656E7420746F20746865206D6F64657261746F72732E', 1, 0, '', 0, '', 'behave', 1, 0, 1, 1, 1, 1, 0, '', 1, 1, 1, NULL),
				('reported', 'Reported', X'596F757220636F6E74656E7420776173207265706F7274656420746F20746865206D6F64657261746F72732E', 1, 0, '', 0, '', 'behave', 1, 0, 1, 1, 1, 1, 0, '', 1, 1, 1, NULL)
		");
	}

	/**
	 *
	 * @throws \XF\Db\Exception
	 */
	public function upgrade20160618Step1()
	{
		$this->query("
			INSERT IGNORE INTO `xf_dbtech_credits_eventtrigger` (`eventtriggerid`, `title`, `description`, `active`, `multiplier`, `multiplier_label`, `multiplier_popup`, `parent`, `category`, `global`, `revert`, `cancel`, `rebuild`, `charge`, `usergroups`, `currency`, `referformat`, `outbound`, `inbound`, `value`, `settings`)
			VALUES
				('targetbet', 'Sportsbook Bet Challenge', X'4372656174696E67206120626574206368616C6C656E6765207769746820616E6F74686572206D656D626572', 1, 0, '', 0, '', 'sportsbook', 1, 1, 1, 0, 1, 1, 0, '', 1, 1, 1, NULL),
				('targetbetaccept', 'Sportsbook Bet Challenge Accept', X'416363657074696E67206120626574206368616C6C656E6765', 1, 0, '', 0, '', 'sportsbook', 1, 1, 1, 0, 1, 1, 0, '', 1, 1, 1, NULL),
				('targetbetaccepted', 'Sportsbook Bet Challenge Accepted', X'536F6D656F6E6520616363657074696E6720796F757220626574206368616C6C656E6765', 1, 0, '', 0, '', 'sportsbook', 1, 1, 1, 0, 1, 1, 0, '', 1, 1, 1, NULL),
				('openbet', 'Sportsbook Open Bet', X'4372656174696E6720616E206F70656E20626574', 1, 0, '', 0, '', 'sportsbook', 1, 1, 1, 0, 1, 1, 0, '', 1, 1, 1, NULL),
				('openbetaccept', 'Sportsbook Open Bet Accept', X'416363657074696E6720616E206F70656E20626574', 1, 0, '', 0, '', 'sportsbook', 1, 1, 1, 0, 1, 1, 0, '', 1, 1, 1, NULL),
				('openbetaccepted', 'Sportsbook Open Bet Accepted', X'536F6D656F6E6520616363657074696E6720796F7572206F70656E20626574', 1, 0, '', 0, '', 'sportsbook', 1, 1, 1, 0, 1, 1, 0, '', 1, 1, 1, NULL),
				('wager', 'Sportsbook Wager', X'506C6163696E672061207761676572206F6E20616E206576656E74', 1, 0, '', 0, '', 'sportsbook', 1, 1, 1, 0, 1, 1, 0, '', 1, 1, 1, NULL),
				('wagered', 'Sportsbook Wagered', X'536F6D656F6E6520706C616365642061207761676572206F6E20796F7572206576656E74', 1, 0, '', 0, '', 'sportsbook', 1, 1, 1, 0, 1, 1, 0, '', 1, 1, 1, NULL)
		");
	}

	/**
	 *
	 */
	public function upgrade20160922Step1()
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_language', function (Alter $table)
		{
			$table->addColumn('dbtech_credits_phrase_cache', 'mediumblob')->nullable(true)->after('phrase_cache');
		});
	}


	public function upgrade20160928Step1()
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_dbtech_credits_currency', function (Alter $table)
		{
			$table->addColumn('displaycurrency', 'tinyint')->setDefault(0)->after('suffix');
		});
	}

	/**
	 *
	 */
	public function upgrade20161007Step1()
	{
		$sm = $this->schemaManager();

		$sm->createTable('xf_dbtech_credits_purchase_transaction', function (Create $table)
		{
			$table->addColumn('transaction_id', 'int')->autoIncrement();
			$table->addColumn('eventid', 'int')->setDefault(0);
			$table->addColumn('fromuserid', 'int')->setDefault(0);
			$table->addColumn('touserid', 'int')->setDefault(0);
			$table->addColumn('ipaddress', 'varchar', 45)->setDefault('');
			$table->addColumn('amount', 'double', '10,2')->setDefault('0.00');
			$table->addColumn('cost', 'double', '10,2')->setDefault('0.00');
			$table->addColumn('currencyid', 'char', 3)->setDefault('');
			$table->addColumn('message', 'blob')->nullable(true);
			$table->addKey('eventid');
			$table->addKey('fromuserid');
			$table->addKey('touserid');
		});
	}

	/**
	 *
	 * @throws \XF\Db\Exception
	 */
	public function upgrade20161019Step1()
	{
		$this->query("
			INSERT IGNORE INTO `xf_dbtech_credits_eventtrigger` (`eventtriggerid`, `title`, `description`, `active`, `multiplier`, `multiplier_label`, `multiplier_popup`, `parent`, `category`, `global`, `revert`, `cancel`, `rebuild`, `charge`, `usergroups`, `currency`, `referformat`, `outbound`, `inbound`, `value`, `settings`)
			VALUES
				('registration', 'Registration', X'41206E6577207573657220726567697374657273206F6E2074686520666F72756D2E', 1, 0, '', 0, '', 'accounts', 1, 0, 0, 0, 0, 0, 0, '', 1, 1, 1, NULL)
		");
	}

	/**
	 *
	 */
	public function upgrade20161101Step1()
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_dbtech_credits_eventtrigger', function (Alter $table)
		{
			$table->addColumn('callback_class', 'varchar', 75)->setDefault('')->after('active');
		});
	}

	/**
	 *
	 * @throws \XF\Db\Exception
	 */
	public function upgrade20161101Step2()
	{
		foreach ([
			'adjust' 							=> 'DBTech_Credits_Model_Event_Adjust',
			'avatar' 							=> 'DBTech_Credits_Model_Event_Avatar',
			'birthday' 						=> 'DBTech_Credits_Model_Event_Birthday',
			'content' 							=> 'DBTech_Credits_Model_Event_Content',
			'daily' 							=> 'DBTech_Credits_Model_Event_Daily',
			'dbtech_donate_donate' 			=> 'DBTech_Donate_Credits_Event_Donate',
			'dbtech_shop_bankdeposit' 			=> 'DBTech_Shop_Credits_Event_BankDeposit',
			'dbtech_shop_bankwithdraw' 		=> 'DBTech_Shop_Credits_Event_BankWithdraw',
			'dbtech_shop_lotterybuyticket' 	=> 'DBTech_Shop_Credits_Event_LotteryBuyTicket',
			'dbtech_shop_lotterypayout' 		=> 'DBTech_Shop_Credits_Event_LotteryPayOut',
			'dbtech_shop_newreply' 			=> 'DBTech_Shop_Credits_Event_NewReply',
			'dbtech_shop_newthread' 			=> 'DBTech_Shop_Credits_Event_NewThread',
			'dbtech_shop_pointsadjust' 		=> 'DBTech_Shop_Credits_Event_PointsAdjust',
			'dbtech_shop_sale' 				=> 'DBTech_Shop_Credits_Event_Sale',
			'dbtech_shop_salebeneficiary' 		=> 'DBTech_Shop_Credits_Event_SaleBeneficiary',
			'dbtech_shop_saleowner' 			=> 'DBTech_Shop_Credits_Event_SaleOwner',
			'dbtech_shop_sellback' 			=> 'DBTech_Shop_Credits_Event_SellBack',
			'dbtech_shop_sellbackbeneficiary' 	=> 'DBTech_Shop_Credits_Event_SellBackBeneficiary',
			'dbtech_shop_sellbackowner' 		=> 'DBTech_Shop_Credits_Event_SellBackOwner',
			'dbtech_shop_stealfail' 			=> 'DBTech_Shop_Credits_Event_StealFail',
			'dbtech_shop_stealsuccess' 		=> 'DBTech_Shop_Credits_Event_StealSuccess',
			'dbtech_shop_trade' 				=> 'DBTech_Shop_Credits_Event_Trade',
			'donate' 							=> 'DBTech_Credits_Model_Event_Donate',
			'download' 						=> 'DBTech_Credits_Model_Event_Download',
			'downloaded' 						=> 'DBTech_Credits_Model_Event_Downloaded',
			'follow' 							=> 'DBTech_Credits_Model_Event_Follow',
			'followed' 						=> 'DBTech_Credits_Model_Event_Followed',
			'gallerycomment' 					=> 'DBTech_Credits_Model_Event_Gallerycomment',
			'gallerycommented' 				=> 'DBTech_Credits_Model_Event_Gallerycommented',
			'gallerydownload' 					=> 'DBTech_Credits_Model_Event_Gallerydownload',
			'gallerydownloaded' 				=> 'DBTech_Credits_Model_Event_Gallerydownloaded',
			'galleryrate' 						=> 'DBTech_Credits_Model_Event_Galleryrate',
			'galleryrated' 					=> 'DBTech_Credits_Model_Event_Galleryrated',
			'galleryupload' 					=> 'DBTech_Credits_Model_Event_Galleryupload',
			'interest' 						=> 'DBTech_Credits_Model_Event_Interest',
			'like' 							=> 'DBTech_Credits_Model_Event_Like',
			'liked' 							=> 'DBTech_Credits_Model_Event_Liked',
			'message' 							=> 'DBTech_Credits_Model_Event_Message',
			'openbet' 							=> 'DBTech_Credits_Model_Event_SportsBook_OpenBet',
			'openbetaccept' 					=> 'DBTech_Credits_Model_Event_SportsBook_OpenBetAccept',
			'openbetaccepted' 					=> 'DBTech_Credits_Model_Event_SportsBook_OpenBetAccepted',
			'paycheck' 						=> 'DBTech_Credits_Model_Event_Paycheck',
			'poll' 							=> 'DBTech_Credits_Model_Event_Poll',
			'post' 							=> 'DBTech_Credits_Model_Event_Post',
			'postrate' 						=> 'DBTech_Credits_Model_Event_PostRate',
			'postrated' 						=> 'DBTech_Credits_Model_Event_PostRated',
			'profile' 							=> 'DBTech_Credits_Model_Event_Profile',
			'punish' 							=> 'DBTech_Credits_Model_Event_Punish',
			'purchase' 						=> 'DBTech_Credits_Model_Event_Purchase',
			'read' 							=> 'DBTech_Credits_Model_Event_Read',
			'redeem' 							=> 'DBTech_Credits_Model_Event_Redeem',
			'registration' 					=> 'DBTech_Credits_Model_Event_Registration',
			'reply' 							=> 'DBTech_Credits_Model_Event_Reply',
			'report' 							=> 'DBTech_Credits_Model_Event_Report',
			'reported' 						=> 'DBTech_Credits_Model_Event_Reported',
			'resourcedownload' 				=> 'DBTech_Credits_Model_Event_ResourceDownload',
			'resourcedownloaded' 				=> 'DBTech_Credits_Model_Event_ResourceDownloaded',
			'resourcerate' 					=> 'DBTech_Credits_Model_Event_ResourceRate',
			'resourcerated' 					=> 'DBTech_Credits_Model_Event_ResourceRated',
			'resourceupdate' 					=> 'DBTech_Credits_Model_Event_ResourceUpdate',
			'resourceupload' 					=> 'DBTech_Credits_Model_Event_ResourceUpload',
			'revival' 							=> 'DBTech_Credits_Model_Event_Revival',
			'sticky' 							=> 'DBTech_Credits_Model_Event_Sticky',
			'tag' 								=> 'DBTech_Credits_Model_Event_Tag',
			'targetbet' 						=> 'DBTech_Credits_Model_Event_SportsBook_TargetBet',
			'targetbetaccept' 					=> 'DBTech_Credits_Model_Event_SportsBook_TargetBetAccept',
			'targetbetaccepted' 				=> 'DBTech_Credits_Model_Event_SportsBook_TargetBetAccepted',
			'thread' 							=> 'DBTech_Credits_Model_Event_Thread',
			'transfer' 						=> 'DBTech_Credits_Model_Event_Transfer',
			'trophy' 							=> 'DBTech_Credits_Model_Event_Trophy',
			'upload' 							=> 'DBTech_Credits_Model_Event_Upload',
			'view' 							=> 'DBTech_Credits_Model_Event_View',
			'visit' 							=> 'DBTech_Credits_Model_Event_Visit',
			'visitor' 							=> 'DBTech_Credits_Model_Event_Visitor',
			'vote' 							=> 'DBTech_Credits_Model_Event_Vote',
			'wager' 							=> 'DBTech_Credits_Model_Event_SportsBook_Wager',
			'wagered' 							=> 'DBTech_Credits_Model_Event_SportsBook_Wagered',
			'wall' 							=> 'DBTech_Credits_Model_Event_Wall',
			'warning' 							=> 'DBTech_Credits_Model_Event_Warning',
			'xengallerycomment' 				=> 'DBTech_Credits_Model_Event_XenGalleryComment',
			'xengallerycommented' 				=> 'DBTech_Credits_Model_Event_XenGalleryCommented',
			'xengallerydownload' 				=> 'DBTech_Credits_Model_Event_XenGalleryDownload',
			'xengallerydownloaded' 			=> 'DBTech_Credits_Model_Event_XenGalleryDownloaded',
			'xengalleryrate' 					=> 'DBTech_Credits_Model_Event_XenGalleryRate',
			'xengalleryrated' 					=> 'DBTech_Credits_Model_Event_XenGalleryRated',
			'xengalleryupload' 				=> 'DBTech_Credits_Model_Event_XenGalleryUpload',
		] as $eventTriggerId => $callbackClass)
		{
			$this->query("
				UPDATE `xf_dbtech_credits_eventtrigger`
					SET `callback_class` = '$callbackClass'
					WHERE `eventtriggerid` = '$eventTriggerId'
			");
		}
	}

	/**
	 *
	 * @throws \XF\Db\Exception
	 */
	public function upgrade20161103Step1()
	{
		foreach ([
			'dbtech_shop_salebeneficiary',
			'dbtech_shop_saleowner',
			'dbtech_shop_sellbackbeneficiary',
			'dbtech_shop_sellbackowner',
			'dbtech_shop_trade',
		] as $eventTriggerId)
		{
			$this->query("
				UPDATE `xf_dbtech_credits_eventtrigger`
					SET `rebuild` = 0
					WHERE `eventtriggerid` = '$eventTriggerId'
			");
		}
	}

	/**
	 *
	 */
	public function upgrade20161111Step1()
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_user', function (Alter $table)
		{
			$table->addColumn('dbtech_credits_lasttaxation', 'int')->setDefault(0)->after('dbtech_credits_lastpaycheck');
		});
	}

	/**
	 *
	 * @throws \XF\Db\Exception
	 */
	public function upgrade20161111Step2()
	{
		$this->query("
			INSERT IGNORE INTO `xf_dbtech_credits_eventtrigger` (`eventtriggerid`, `title`, `description`, `active`, `callback_class`, `multiplier`, `multiplier_label`, `multiplier_popup`, `parent`, `category`, `global`, `revert`, `cancel`, `rebuild`, `charge`, `usergroups`, `currency`, `referformat`, `outbound`, `inbound`, `value`, `settings`)
			VALUES
				('taxation', 'Taxation', X'4175746F6D61746963616C6C7920746178206D656D62657273272063757272656E63696573206F7665722074696D652E', 1, 'DBTech_Credits_Model_Event_Taxation', 1, 'Currency|Currency', 0, '', 'time', 1, 0, 0, 0, 1, 1, 0, '', 1, 1, 1, X'613A323A7B733A31373A227461786174696F6E5F696E74657276616C223B733A323A223330223B733A31343A227461786174696F6E5F7374617274223B733A31303A22323030392D31322D3235223B7D')
		");
	}

	/**
	 *
	 * @throws \XF\Db\Exception
	 * @throws \XF\Db\Exception
	 */
	public function upgrade20161115Step1()
	{
		foreach ([
			'dbtech_shop_salebeneficiary',
			'dbtech_shop_saleowner',
			'dbtech_shop_sellbackbeneficiary',
			'dbtech_shop_sellbackowner',
			'dbtech_shop_trade',

			// Make sure this is updated
			'adjust',
			'content',
			'daily',
			'donate',
			'download',
			'downloaded',
			'gallerydownload',
			'gallerydownloaded',
			'interest',
			'openbet',
			'openbetaccept',
			'openbetaccepted',
			'profile',
			'read',
			'redeem',
			'resourcedownload',
			'resourcedownloaded',
			'revival',
			'targetbet',
			'targetbetaccept',
			'targetbetaccepted',
			'taxation',
			'transfer',
			'view',
			'visit',
			'wager',
			'wagered',
			'xengallerydownload',
			'xengallerydownloaded',
			'xengalleryrate',
			'xengalleryrated',
		] as $eventTriggerId)
		{
			$this->query("
				UPDATE `xf_dbtech_credits_eventtrigger`
					SET `rebuild` = 0
					WHERE `eventtriggerid` = '$eventTriggerId'
			");
		}

		foreach ([
			'avatar',
			'galleryrate',
			'galleryrated',
			'purchase',
			'registration',
			'resourcerate',
			'resourcerated',
			'sticky',
		] as $eventTriggerId)
		{
			$this->query("
				UPDATE `xf_dbtech_credits_eventtrigger`
					SET `rebuild` = 1
					WHERE `eventtriggerid` = '$eventTriggerId'
			");
		}
	}

	/**
	 *
	 * @throws \XF\Db\Exception
	 */
	public function upgrade20161129Step1()
	{
		$this->query("
			INSERT IGNORE INTO `xf_dbtech_credits_eventtrigger`
				(`eventtriggerid`, `title`, `description`, `active`, `callback_class`, `multiplier`, `multiplier_label`, `multiplier_popup`, `parent`, `category`, `global`, `revert`, `cancel`, `rebuild`, `charge`, `usergroups`, `currency`, `referformat`, `outbound`, `inbound`, `value`, `settings`)
			VALUES
				('xenmediocomment', 'XenMedio Comment', X'416464696E67206120636F6D6D656E7420746F20612058656E4D6564696F206D65646961207265736F757263652E', 1, 'DBTech_Credits_Model_Event_XenMedio_Comment', 2, '', 0, 'Media', 'discuss', 1, 1, 1, 1, 1, 1, 0, 'showpost.php?p=', 1, 1, 1, NULL),
				('xenmediocommented', 'XenMedio Commented', X'536F6D656F6E6520656C736520636F6D6D656E74696E67206F6E20796F75722058656E4D6564696F206D65646961207265736F757263652E', 1, 'DBTech_Credits_Model_Event_XenMedio_Commented', 2, '', 0, '', 'discuss', 1, 1, 0, 1, 1, 1, 0, 'showpost.php?p=', 1, 1, 1, NULL),
				('xenmedioupload', 'XenMedio Upload', X'416464696E67206E65772058656E4D6564696F204D656469612E', 1, 'DBTech_Credits_Model_Event_XenMedio_Upload', 0, '', 0, '', 'share', 1, 1, 1, 1, 1, 1, 0, 'attachment.php?attachmentid=', 1, 1, 1, NULL)
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
	public function upgrade20170314Step1()
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

		$eventTriggers = $this->db()->fetchPairs('SELECT eventtriggerid, settings FROM xf_dbtech_credits_eventtrigger');
		foreach ($eventTriggers as $eventTriggerId => $settings)
		{
			if ($settings === null)
			{
				$settings = [];
			}
			else
			{
				$settings = @unserialize($settings);
				$settings = is_array($settings) ? $settings : [];
			}

			$this->db()->update('xf_dbtech_credits_eventtrigger', [
				'settings' => json_encode($settings)
			], 'eventtriggerid = ' . $this->db()->quote($eventTriggerId));
		}

		// Purge the cache
		\XF::registry()->delete([
			'dbt_credits_eventtrigger',
		]);
	}

	/**
	 *
	 * @throws \XF\Db\Exception
	 */
	public function upgrade20170426Step1()
	{
		foreach ([
			'postrate',
			'postrated',
		] as $eventTriggerId)
		{
			$this->query("
				UPDATE `xf_dbtech_credits_eventtrigger`
					SET `cancel` = 1
					WHERE `eventtriggerid` = '$eventTriggerId'
			");
		}
	}

	/**
	 *
	 */
	public function upgrade20170504Step1()
	{
		$items = $this->db()->fetchAll('SELECT * FROM xf_dbtech_credits_event');
		foreach ($items as $item)
		{
			$jsonArray = [
				'usergroups' => '',
				'forums' => '',
				'settings' => ''
			];
			foreach ($jsonArray as $key => $data)
			{
				if ($item[$key] === null)
				{
					$item[$key] = [];
				}
				else
				{
					$item[$key] = @unserialize($item[$key]);
					$item[$key] = is_array($item[$key]) ? $item[$key] : [];
				}

				$jsonArray[$key] = json_encode($item[$key]);
			}

			$this->db()->update('xf_dbtech_credits_event', $jsonArray, 'eventid = ' . $this->db()->quote($item['eventid']));
		}

		// Purge the cache
		\XF::registry()->delete([
			'dbt_credits_event',
		]);
	}

	/**
	 *
	 */
	public function upgrade805000031Step1()
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_dbtech_credits_currency', function (Alter $table)
		{
			$table->addColumn('sidebar', 'tinyint')->setDefault(1);
		});
	}
}