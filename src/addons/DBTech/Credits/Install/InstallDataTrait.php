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
trait InstallDataTrait
{
	/**
	 * @return \Closure[]
	 */
	protected function getTables(): array
	{
		$tables = [];

		$tables['xf_dbtech_credits_adjust_log'] = function ($table)
		{
			/** @var Create|Alter $table */
			$this->addOrChangeColumn($table, 'adjust_log_id', 'int')->autoIncrement();
			$this->addOrChangeColumn($table, 'user_id', 'int');
			$this->addOrChangeColumn($table, 'adjust_date', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'adjust_user_id', 'int');
			$this->addOrChangeColumn($table, 'event_id', 'int');
			$this->addOrChangeColumn($table, 'currency_id', 'int');
			$this->addOrChangeColumn($table, 'amount', 'decimal', '65,8')->unsigned(false)->setDefault(0);
			$this->addOrChangeColumn($table, 'message', 'blob')->nullable(true);
			$table->addKey(['adjust_date', 'user_id'], 'adjust_date');
			$table->addKey('user_id');
			$table->addKey('adjust_user_id');
		};

		$tables['xf_dbtech_credits_charge'] = function ($table)
		{
			/** @var Create|Alter $table */
			$this->addOrChangeColumn($table, 'content_type', 'varbinary', 25);
			$this->addOrChangeColumn($table, 'content_id', 'int');
			$this->addOrChangeColumn($table, 'content_hash', 'char', 32);
			$this->addOrChangeColumn($table, 'cost', 'double')->unsigned(false)->setDefault(0);
			$table->addPrimaryKey(['content_type', 'content_id', 'content_hash']);
		};

		$tables['xf_dbtech_credits_charge_purchase'] = function ($table)
		{
			/** @var Create|Alter $table */
			$this->addOrChangeColumn($table, 'content_type', 'varbinary', 25);
			$this->addOrChangeColumn($table, 'content_id', 'int');
			$this->addOrChangeColumn($table, 'content_hash', 'char', 32);
			$this->addOrChangeColumn($table, 'user_id', 'int');
			$table->addPrimaryKey(['content_type', 'content_id', 'content_hash', 'user_id']);
		};

		$tables['xf_dbtech_credits_content_access_purchase'] = function ($table)
		{
			/** @var Create|Alter $table */
			$this->addOrChangeColumn($table, 'content_type', 'varbinary', 25);
			$this->addOrChangeColumn($table, 'content_id', 'int');
			$this->addOrChangeColumn($table, 'user_id', 'int');
			$table->addPrimaryKey(['content_type', 'content_id', 'user_id']);
		};

		$tables['xf_dbtech_credits_currency'] = function ($table)
		{
			/** @var Create|Alter $table */
			$this->addOrChangeColumn($table, 'currency_id', 'int')->autoIncrement();
			$this->addOrChangeColumn($table, 'title', 'varchar', 255)->setDefault('');
			$this->addOrChangeColumn($table, 'description', 'blob')->nullable(true);
			$this->addOrChangeColumn($table, 'active', 'tinyint')->setDefault(1);
			$this->addOrChangeColumn($table, 'display_order', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'table', 'varchar', 255)->setDefault('');
			$this->addOrChangeColumn($table, 'use_table_prefix', 'tinyint')->setDefault(1);
			$this->addOrChangeColumn($table, 'column', 'varchar', 255)->setDefault('');
			$this->addOrChangeColumn($table, 'use_user_id', 'tinyint')->setDefault(1);
			$this->addOrChangeColumn($table, 'user_id_column', 'varchar', 255)->setDefault('user_id');
			$this->addOrChangeColumn($table, 'decimals', 'tinyint', 2)->setDefault(0);
			$this->addOrChangeColumn($table, 'privacy', 'tinyint')->setDefault(0);
			$this->addOrChangeColumn($table, 'blacklist', 'tinyint')->setDefault(0);
			$this->addOrChangeColumn($table, 'prefix', 'varchar', 50)->setDefault('');
			$this->addOrChangeColumn($table, 'suffix', 'varchar', 50)->setDefault('');
			$this->addOrChangeColumn($table, 'negative', 'tinyint')->setDefault(0);
			$this->addOrChangeColumn($table, 'maxtime', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'earnmax', 'double')->setDefault(0);
			$this->addOrChangeColumn($table, 'value', 'double')->unsigned(false)->setDefault(1);
			$this->addOrChangeColumn($table, 'inbound', 'tinyint')->setDefault(1);
			$this->addOrChangeColumn($table, 'outbound', 'tinyint')->setDefault(1);
			$this->addOrChangeColumn($table, 'is_display_currency', 'tinyint')->setDefault(0);
			$this->addOrChangeColumn($table, 'show_amounts', 'tinyint')->setDefault(1);
			$this->addOrChangeColumn($table, 'sidebar', 'tinyint')->setDefault(1);
			$this->addOrChangeColumn($table, 'postbit', 'tinyint')->setDefault(1);
			$this->addOrChangeColumn($table, 'member_dropdown', 'tinyint')->setDefault(0);
			$table->addKey('active');
		};

		$tables['xf_dbtech_credits_donation_log'] = function ($table)
		{
			/** @var Create|Alter $table */
			$this->addOrChangeColumn($table, 'donation_log_id', 'int')->autoIncrement();
			$this->addOrChangeColumn($table, 'user_id', 'int');
			$this->addOrChangeColumn($table, 'donation_date', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'donation_user_id', 'int');
			$this->addOrChangeColumn($table, 'event_id', 'int');
			$this->addOrChangeColumn($table, 'currency_id', 'int');
			$this->addOrChangeColumn($table, 'amount', 'decimal', '65,8')->unsigned(false)->setDefault(0);
			$this->addOrChangeColumn($table, 'message', 'blob')->nullable(true);
			$table->addKey(['donation_date', 'user_id'], 'donation_date');
			$table->addKey('user_id');
			$table->addKey('donation_user_id');
		};

		$tables['xf_dbtech_credits_event'] = function ($table)
		{
			/** @var Create|Alter $table */
			$this->addOrChangeColumn($table, 'event_id', 'int')->autoIncrement();
			$this->addOrChangeColumn($table, 'title', 'varchar', 255)->setDefault('');
			$this->addOrChangeColumn($table, 'active', 'tinyint')->setDefault(1);
			$this->addOrChangeColumn($table, 'currency_id', 'int');
			$this->addOrChangeColumn($table, 'event_trigger_id', 'varchar', 255)->setDefault('');
			$this->addOrChangeColumn($table, 'user_group_ids', 'blob')->nullable(true);
			$this->addOrChangeColumn($table, 'node_ids', 'blob')->nullable(true);
			$this->addOrChangeColumn($table, 'moderate', 'tinyint')->setDefault(0);
			$this->addOrChangeColumn($table, 'charge', 'tinyint')->setDefault(0);
			$this->addOrChangeColumn($table, 'main_add', 'double')->unsigned(false)->setDefault(0);
			$this->addOrChangeColumn($table, 'main_sub', 'double')->unsigned(false)->setDefault(0);
			$this->addOrChangeColumn($table, 'mult_add', 'double')->unsigned(false)->setDefault(0);
			$this->addOrChangeColumn($table, 'mult_sub', 'double')->unsigned(false)->setDefault(0);
			$this->addOrChangeColumn($table, 'delay', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'frequency', 'int')->setDefault('1');
			$this->addOrChangeColumn($table, 'maxtime', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'applymax', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'applymax_peruser', 'tinyint', 1)->setDefault(0);
			$this->addOrChangeColumn($table, 'upperrand', 'double')->unsigned(false)->setDefault(0);
			$this->addOrChangeColumn($table, 'multmin', 'double')->unsigned(false)->setDefault(0);
			$this->addOrChangeColumn($table, 'multmax', 'double')->unsigned(false)->setDefault(0);
			$this->addOrChangeColumn($table, 'minaction', 'tinyint')->setDefault(0);
			$this->addOrChangeColumn($table, 'owner', 'tinyint')->setDefault(0);
			$this->addOrChangeColumn($table, 'curtarget', 'tinyint')->setDefault(0);
			$this->addOrChangeColumn($table, 'alert', 'tinyint')->setDefault(0);
			$this->addOrChangeColumn($table, 'display', 'tinyint', 3)->setDefault(1);
			$this->addOrChangeColumn($table, 'settings', 'mediumblob')->nullable(true);
			$table->addKey(['active', 'display'], 'transaction_display');
		};

		$tables['xf_dbtech_credits_event_trigger'] = function ($table)
		{
			/** @var Create|Alter $table */
			$this->addOrChangeColumn($table, 'event_trigger_id', 'varchar', 100);
			$this->addOrChangeColumn($table, 'title', 'varchar', 255)->setDefault('');
			$this->addOrChangeColumn($table, 'description', 'blob')->nullable(true);
			$this->addOrChangeColumn($table, 'active', 'tinyint')->setDefault(1);
			$this->addOrChangeColumn($table, 'callback_class', 'varchar', 75)->setDefault('');
			$this->addOrChangeColumn($table, 'multiplier', 'tinyint')->setDefault(0);
			$this->addOrChangeColumn($table, 'multiplier_label', 'varchar', 255)->setDefault('');
			$this->addOrChangeColumn($table, 'multiplier_popup', 'tinyint')->setDefault(0);
			$this->addOrChangeColumn($table, 'parent', 'varchar', 255)->setDefault('');
			$this->addOrChangeColumn($table, 'category', 'varchar', 255)->setDefault('');
			$this->addOrChangeColumn($table, 'global', 'tinyint')->setDefault(1);
			$this->addOrChangeColumn($table, 'revert', 'tinyint')->setDefault(0);
			$this->addOrChangeColumn($table, 'cancel', 'tinyint')->setDefault(0);
			$this->addOrChangeColumn($table, 'rebuild', 'tinyint')->setDefault(0);
			$this->addOrChangeColumn($table, 'charge', 'tinyint')->setDefault(1);
			$this->addOrChangeColumn($table, 'usergroups', 'tinyint')->setDefault(1);
			$this->addOrChangeColumn($table, 'currency', 'tinyint')->setDefault(0);
			$this->addOrChangeColumn($table, 'referformat', 'varchar', 255)->setDefault('');
			$this->addOrChangeColumn($table, 'outbound', 'tinyint')->setDefault(1);
			$this->addOrChangeColumn($table, 'inbound', 'tinyint')->setDefault(1);
			$this->addOrChangeColumn($table, 'value', 'double')->unsigned(false)->setDefault(1);
			$this->addOrChangeColumn($table, 'settings', 'mediumblob')->nullable(true);
			$table->addPrimaryKey('event_trigger_id');
		};

		$tables['xf_dbtech_credits_purchase_transaction'] = function ($table)
		{
			/** @var Create|Alter $table */
			$this->addOrChangeColumn($table, 'transaction_id', 'int')->autoIncrement();
			$this->addOrChangeColumn($table, 'user_id', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'from_user_id', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'transaction_date', 'int', 10)->setDefault(0);
			$this->addOrChangeColumn($table, 'amount', 'double', '10,2')->setDefault('0.00');
			$this->addOrChangeColumn($table, 'cost', 'double', '10,2')->setDefault('0.00');
			$this->addOrChangeColumn($table, 'event_id', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'currency_id', 'char', 3)->setDefault('');
			$this->addOrChangeColumn($table, 'message', 'blob')->nullable(true);
			$this->addOrChangeColumn($table, 'ip_id', 'int', 10)->setDefault(0);
			$table->addKey(['transaction_date', 'user_id'], 'transaction_date');
			$table->addKey('from_user_id');
			$table->addKey('user_id');
		};

		$tables['xf_dbtech_credits_redeem_log'] = function ($table)
		{
			/** @var Create|Alter $table */
			$this->addOrChangeColumn($table, 'redeem_log_id', 'int')->autoIncrement();
			$this->addOrChangeColumn($table, 'user_id', 'int');
			$this->addOrChangeColumn($table, 'redeem_date', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'redeem_code', 'varchar', 255);
			$this->addOrChangeColumn($table, 'event_id', 'int');
			$this->addOrChangeColumn($table, 'currency_id', 'int');
			$this->addOrChangeColumn($table, 'amount', 'decimal', '65,8')->unsigned(false)->setDefault(0);
			$this->addOrChangeColumn($table, 'message', 'blob')->nullable(true);
			$table->addKey(['redeem_date', 'user_id'], 'redeem_date');
			$table->addKey('user_id');
		};

		$tables['xf_dbtech_credits_transaction'] = function ($table)
		{
			/** @var Create|Alter $table */
			$this->addOrChangeColumn($table, 'transaction_id', 'bigint')->autoIncrement();
			$this->addOrChangeColumn($table, 'event_id', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'event_trigger_id', 'varchar', 255)->setDefault('');
			$this->addOrChangeColumn($table, 'user_id', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'dateline', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'source_user_id', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'amount', 'decimal', '65,8')->unsigned(false)->setDefault(0);
			$this->addOrChangeColumn($table, 'transaction_state', 'enum')->values(['visible', 'moderated', 'skipped', 'skipped_maximum'])->setDefault('visible');
			$this->addOrChangeColumn($table, 'reference_id', 'varchar', 255)->nullable(true);
			$this->addOrChangeColumn($table, 'content_type', 'varbinary', 25);
			$this->addOrChangeColumn($table, 'content_id', 'int');
			$this->addOrChangeColumn($table, 'node_id', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'owner_id', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'multiplier', 'double')->unsigned(false)->setDefault(0);
			$this->addOrChangeColumn($table, 'currency_id', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'negate', 'tinyint')->setDefault(0);
			$this->addOrChangeColumn($table, 'message', 'blob')->nullable(true);
			$this->addOrChangeColumn($table, 'is_disputed', 'tinyint')->setDefault(0);
			$this->addOrChangeColumn($table, 'expiry_date', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'balance', 'decimal', '65,8')->unsigned(false)->setDefault(0);
			$table->addKey(['dateline', 'user_id', 'transaction_state'], 'dateline');
			$table->addKey(['dateline', 'transaction_id'], 'transaction_date');
			$table->addKey('user_id');
			$table->addKey(['user_id', 'event_id', 'transaction_state', 'negate', 'dateline'], 'user_id_stats');
		};

		$tables['xf_dbtech_credits_transfer_log'] = function ($table)
		{
			/** @var Create|Alter $table */
			$this->addOrChangeColumn($table, 'transfer_log_id', 'int')->autoIncrement();
			$this->addOrChangeColumn($table, 'user_id', 'int');
			$this->addOrChangeColumn($table, 'transfer_date', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'event_id', 'int');
			$this->addOrChangeColumn($table, 'currency_id', 'int');
			$this->addOrChangeColumn($table, 'amount', 'decimal', '65,8')->unsigned(false)->setDefault(0);
			$this->addOrChangeColumn($table, 'message', 'blob')->nullable(true);
			$table->addKey(['transfer_date', 'user_id'], 'transfer_date');
			$table->addKey('user_id');
		};

		return $tables;
	}

	/**
	 * @return array
	 */
	protected function getAlterDefinitions(): array
	{
		$definitions = [];

		$definitions['xf_thread'] = [
			'columns' => [
				'dbtech_credits_access_cost' => [
					'type'    => 'decimal',
					'length'  => '65,8',
					'unsigned' => false,
					'default' => 0
				],
				'dbtech_credits_access_currency_id' => [
					'type'    => 'int',
					'length'  => null,
					'default' => 0
				],
			],
		];

		$definitions['xf_user'] = [
			'columns' => [
				'dbtech_credits_credits'         => [
					'type'    => 'decimal',
					'length'  => '65,8',
					'unsigned' => false,
					'default' => 0
				],
				'dbtech_credits_lastdaily' => [
					'type'    => 'int',
					'length'  => null,
					'default' => 0
				],
				'dbtech_credits_lastinterest' => [
					'type'    => 'int',
					'length'  => null,
					'default' => 0
				],
				'dbtech_credits_lastpaycheck' => [
					'type'    => 'int',
					'length'  => null,
					'default' => 0
				],
				'dbtech_credits_lasttaxation' => [
					'type'    => 'int',
					'length'  => null,
					'default' => 0
				],
			],
		];

		return $definitions;
	}

	/**
	 * @return string[]
	 */
	protected function getInstallQueries(): array
	{
		return [
			"
				INSERT IGNORE INTO `xf_dbtech_credits_currency`
					(`currency_id`, `title`, `description`, `display_order`, `table`, `use_table_prefix`, `column`, `use_user_id`, `user_id_column`, `decimals`, `negative`, `privacy`)
				VALUES
					(1, 'Credits', 'Classic DragonByte Credits points field.', 10, 'user', 1, 'dbtech_credits_credits', 1, 'user_id', 0, 2, 2)
			",
			"
				INSERT IGNORE INTO `xf_dbtech_credits_event`
					(`event_id`, `currency_id`, `event_trigger_id`, `user_group_ids`, `node_ids`, `active`, `moderate`, `charge`, `main_add`, `main_sub`, `mult_add`, `mult_sub`, `delay`, `frequency`, `maxtime`, `applymax`, `upperrand`, `multmin`, `multmax`, `minaction`, `owner`, `curtarget`, `alert`)
				VALUES
					(1, 1, 'post', '[]', '[]', 1, 0, 0, 5, 5, 0.01, 0.01, 0, 1, 0, 0, '0', 0, 0, 0, 0, 0, 0),
					(2, 1, 'thread', '[]', '[]', 1, 0, 0, 10, 10, 0.01, 0.01, 0, 1, 0, 0, '0', 0, 0, 0, 0, 0, 0)
			",
			"
				INSERT IGNORE INTO `xf_dbtech_credits_event_trigger`
					(`event_trigger_id`, `title`, `description`, `active`, `callback_class`, `multiplier`, `multiplier_label`, `multiplier_popup`, `parent`, `category`, `global`, `revert`, `cancel`, `rebuild`, `charge`, `usergroups`, `currency`, `referformat`, `outbound`, `inbound`, `value`, `settings`)
				VALUES
					('adjust', 'Adjust', X'4D616E6970756C6174696E67207468652063757272656E6379206F6620736F6D656F6E6520656C73652E', 1, 'DBTech\\\Credits\\\Model\\\Event\\\Adjust', 3, '', 1, '', 'accounts', 1, 1, 1, 0, 1, 1, 2, 'member.php?u=', 1, 1, 1, '[]'),
					('avatar', 'Upload Avatar', X'55706C6F6164696E672061206E6577206176617461722E204D756C7469706C6965722069732066696C6573697A652E', 1, 'DBTech\\\Credits\\\Model\\\Event\\\Avatar', 0, '', 0, '', 'accounts', 1, 1, 1, 1, 1, 1, 0, '', 1, 1, 1, '[]'),
					('birthday', 'Birthday', X'41776172646564206F6E206D69646E69676874206163636F7264696E6720746F2070726F66696C652E204576656E74732073686F756C64206265206C696D6974656420746F20616E6E75616C2E204D756C7469706C696572206973206167652E', 1, 'DBTech\\\Credits\\\Model\\\Event\\\Birthday', 1, 'Years|Year', 0, '', 'time', 1, 0, 0, 1, 1, 1, 0, '', 1, 1, 1, '[]'),
					('content', 'Content', X'4368617267696E67206F7468657220757365727320746F207669657720796F7572206D61726B656420636F6E74656E742E', 1, 'DBTech\\\Credits\\\Model\\\Event\\\Content', 3, '', 0, 'Post', 'discuss', 0, 0, 1, 0, 1, 1, 1, '', 1, 1, 1, '[]'),
					('daily', 'Daily Activity', X'41776172646564206F6E206669727374206C6F67696E2065616368206461792E', 1, 'DBTech\\\Credits\\\Model\\\Event\\\Daily', 0, '', 0, '', 'time', 1, 0, 0, 0, 1, 1, 0, '', 1, 1, 1, '[]'),
					('donate', 'Donate', X'5472616E7366657272696E672063757272656E637920746F20616E6F7468657220757365722E', 1, 'DBTech\\\Credits\\\Model\\\Event\\\Donate', 3, '', 0, '', 'accounts', 1, 0, 1, 0, 1, 1, 1, 'member.php?u=', 1, 1, 1, '[]'),
					('download', 'Download', X'446F776E6C6F6164696E67206120666F72756D206174746163686D656E742E204D756C7469706C6965722069732066696C6573697A652E', 1, 'DBTech\\\Credits\\\Model\\\Event\\\Download', 1, 'Bytes|Byte', 0, 'Attachment', 'share', 0, 0, 1, 0, 1, 1, 0, 'attachment.php?attachmentid=', 1, 1, 1, '[]'),
					('downloaded', 'Downloaded', X'536F6D656F6E6520656C736520646F776E6C6F6164696E6720796F7572206174746163686D656E742E204D756C7469706C6965722069732066696C6573697A652E', 1, 'DBTech\\\Credits\\\Model\\\Event\\\Downloaded', 1, 'Bytes|Byte', 0, '', 'share', 0, 0, 0, 0, 1, 1, 0, 'attachment.php?attachmentid=', 1, 1, 1, '[]'),
					('follow', 'Follow', X'466F6C6C6F77696E6720736F6D656F6E6520656C73652E', 1, 'DBTech\\\Credits\\\Model\\\Event\\\Follow', 0, '', 0, '', 'network', 1, 1, 1, 1, 1, 1, 0, 'member.php?u=', 1, 1, 1, '[]'),
					('followed', 'Followed', X'536F6D656F6E6520656C736520666F6C6C6F77696E6720796F752E', 1, 'DBTech\\\Credits\\\Model\\\Event\\\Followed', 0, '', 0, '', 'network', 1, 1, 1, 1, 1, 1, 0, 'member.php?u=', 1, 1, 1, '[]'),
					('gallerycomment', 'MediaGallery Comment', X'416464696E67206120636F6D6D656E7420746F2061204D6564696147616C6C657279206D65646961207265736F757263652E', 1, 'DBTech\\\Credits\\\Model\\\Event\\\Gallery\\\Comment', 2, '', 0, 'Media', 'discuss', 1, 1, 1, 1, 1, 1, 0, 'showpost.php?p=', 1, 1, 1, '[]'),
					('gallerycommented', 'MediaGallery Commented', X'536F6D656F6E6520656C736520636F6D6D656E74696E67206F6E20796F7572204D6564696147616C6C657279206D65646961207265736F757263652E', 1, 'DBTech\\\Credits\\\Model\\\Event\\\Gallery\\\Commented', 2, '', 0, '', 'discuss', 1, 1, 0, 1, 1, 1, 0, 'showpost.php?p=', 1, 1, 1, '[]'),
					('gallerydownload', 'MediaGallery Download', X'446F776E6C6F6164696E67204D6564696147616C6C657279206D656469612E204D756C7469706C6965722069732066696C6573697A652E', 1, 'DBTech\\\Credits\\\Model\\\Event\\\Gallery\\\Download', 1, 'Bytes|Byte', 0, 'Media', 'share', 1, 0, 1, 0, 1, 1, 0, 'attachment.php?attachmentid=', 1, 1, 1, '[]'),
					('gallerydownloaded', 'MediaGallery Downloaded', X'536F6D656F6E6520656C736520646F776E6C6F6164696E6720796F7572204D6564696147616C6C657279206D656469612E204D756C7469706C6965722069732066696C6573697A652E', 1, 'DBTech\\\Credits\\\Model\\\Event\\\Gallery\\\Downloaded', 1, 'Bytes|Byte', 0, '', 'share', 1, 0, 0, 0, 1, 1, 0, 'attachment.php?attachmentid=', 1, 1, 1, '[]'),
					('galleryrate', 'MediaGallery Rate', X'526174696E67204D6564696147616C6C657279206D656469612E204D756C7469706C69657220697320726174696E672E', 1, 'DBTech\\\Credits\\\Model\\\Event\\\Gallery\\\Rate', 1, 'Stars|Star', 0, 'Media', 'opinion', 1, 1, 1, 1, 1, 1, 0, 'showthread.php?t=', 1, 1, 1, '[]'),
					('galleryrated', 'MediaGallery Rated', X'536F6D656F6E6520656C736520726174656420796F7572204D6564696147616C6C657279206D656469612E204D756C7469706C69657220697320726174696E672E', 1, 'DBTech\\\Credits\\\Model\\\Event\\\Gallery\\\Rated', 1, 'Stars|Star', 0, '', 'opinion', 1, 1, 0, 1, 1, 1, 0, 'showthread.php?t=', 1, 1, 1, '[]'),
					('galleryupload', 'MediaGallery Upload', X'55706C6F6164696E67206E6577204D6564696147616C6C657279204D656469612E204D756C7469706C6965722069732066696C6573697A652E', 1, 'DBTech\\\Credits\\\Model\\\Event\\\Gallery\\\Upload', 1, 'Bytes|Byte', 0, '', 'share', 1, 1, 1, 1, 1, 1, 0, 'attachment.php?attachmentid=', 1, 1, 1, '[]'),
					('interest', 'Interest', X'47726F77696E67207468652076616C7565206F6620796F75722063757272656E6379206F7665722074696D652E', 1, 'DBTech\\\Credits\\\Model\\\Event\\\Interest', 1, 'Currency|Currency', 0, '', 'time', 1, 0, 0, 0, 1, 1, 0, '', 1, 1, 1, '[]'),
					('like', 'Post Like', X'4C696B696E67206120706F73742E', 1, 'DBTech\\\Credits\\\Model\\\Event\\\Like', 0, '', 0, 'Post', 'opinion', 0, 1, 1, 1, 1, 1, 0, 'showpost.php?p=', 1, 1, 1, '[]'),
					('liked', 'Post Liked', X'536F6D656F6E6520656C7365206C696B656420796F757220706F73742E', 1, 'DBTech\\\Credits\\\Model\\\Event\\\Liked', 0, '', 0, '', 'opinion', 0, 1, 1, 1, 1, 1, 0, 'showpost.php?p=', 1, 1, 1, '[]'),
					('message', 'Conversation', X'53656E64696E6720612070726976617465206D6573736167652E', 1, 'DBTech\\\Credits\\\Model\\\Event\\\Message', 2, '', 0, '', 'network', 1, 0, 1, 1, 1, 1, 0, '', 1, 1, 1, '[]'),
					('openbet', 'Sportsbook Open Bet', X'4372656174696E6720616E206F70656E20626574', 1, 'DBTech\\\Credits\\\Model\\\Event\\\SportsBook\\\OpenBet', 0, '', 0, '', 'sportsbook', 1, 1, 1, 0, 1, 1, 0, '', 1, 1, 1, '[]'),
					('openbetaccept', 'Sportsbook Open Bet Accept', X'416363657074696E6720616E206F70656E20626574', 1, 'DBTech\\\Credits\\\Model\\\Event\\\SportsBook\\\OpenBetAccept', 0, '', 0, '', 'sportsbook', 1, 1, 1, 0, 1, 1, 0, '', 1, 1, 1, '[]'),
					('openbetaccepted', 'Sportsbook Open Bet Accepted', X'536F6D656F6E6520616363657074696E6720796F7572206F70656E20626574', 1, 'DBTech\\\Credits\\\Model\\\Event\\\SportsBook\\\OpenBetAccepted', 0, '', 0, '', 'sportsbook', 1, 1, 1, 0, 1, 1, 0, '', 1, 1, 1, '[]'),
					('paycheck', 'Paycheck', X'4F636375727320617420726567756C617220696E74657276616C732E', 1, 'DBTech\\\Credits\\\Model\\\Event\\\Paycheck', 0, '', 0, '', 'time', 1, 0, 0, 1, 1, 1, 0, '', 1, 1, 1, '[]'),
					('poll', 'Poll', X'4372656174696E67206120706F6C6C2E', 1, 'DBTech\\\Credits\\\Model\\\Event\\\Poll', 1, 'Options|Option', 0, '', 'opinion', 0, 1, 1, 1, 1, 1, 0, 'poll.php?do=showresults&pollid=', 1, 1, 1, '[]'),
					('post', 'Post', X'416464696E67206120706F737420746F2061207468726561642E', 1, 'DBTech\\\Credits\\\Model\\\Event\\\Post', 2, '', 0, 'Thread', 'discuss', 0, 1, 1, 1, 1, 1, 0, 'showpost.php?p=', 1, 1, 1, '[]'),
					('postrate', 'Post Rate', X'526174696E67206120706F7374207573696E67207468652022506F737420526174696E677322206D6F642E', 1, 'DBTech\\\Credits\\\Model\\\Event\\\PostRating\\\Rate', 0, '', 1, 'Post', 'opinion', 0, 1, 0, 1, 1, 1, 0, 'showpost.php?p=', 1, 1, 1, '[]'),
					('postrated', 'Post Rated', X'536F6D656F6E6520656C736520726174656420796F757220706F7374207573696E67207468652022506F737420526174696E677322206D6F642E', 1, 'DBTech\\\Credits\\\Model\\\Event\\\PostRating\\\Rated', 0, '', 1, '', 'opinion', 0, 1, 0, 1, 1, 1, 0, 'showpost.php?p=', 1, 1, 1, '[]'),
					('profile', 'Profile', X'56696577696E6720612070726F66696C652E204561726E696E67206576656E74732073686F756C64206265206C696D697465642E2043686172676564206576656E74732077696C6C206C6F636B206F7574206775657374732E', 1, 'DBTech\\\Credits\\\Model\\\Event\\\Profile', 0, '', 0, 'Profile', 'network', 1, 0, 1, 0, 1, 1, 0, 'member.php?u=', 1, 1, 1, '[]'),
					('punish', 'Give Warning', X'4170706C79696E672061207761726E696E6720746F20736F6D656F6E6520656C73652E', 1, 'DBTech\\\Credits\\\Model\\\Event\\\Punish', 1, 'Points|Point', 0, '', 'behave', 1, 1, 0, 1, 1, 1, 0, 'infraction.php?do=view&warningid=', 1, 1, 1, '[]'),
					('purchase', 'Purchase', X'427579696E672063757272656E637920666F72207265616C206D6F6E6579207468726F75676820616E7920636F6E66696775726564207061796D656E742070726F636573736F722E', 1, 'DBTech\\\Credits\\\Model\\\Event\\\Purchase', 3, '', 1, 'Account', 'accounts', 1, 1, 0, 1, 1, 1, 2, '', 1, 1, 1, '[]'),
					('read', 'View', X'56696577696E672061207468726561642E2043686172676564206576656E74732077696C6C206C6F636B206F7574206775657374732E', 1, 'DBTech\\\Credits\\\Model\\\Event\\\Read', 0, '', 0, 'Thread', 'discuss', 0, 0, 1, 0, 1, 1, 0, 'showthread.php?t=', 1, 1, 1, '[]'),
					('redeem', 'Redeem', X'5573696E67206120726564656D7074696F6E20636F6465206F72207669736974696E672061207370656369616C206C696E6B2E', 1, 'DBTech\\\Credits\\\Model\\\Event\\\Redeem', 3, '', 1, 'Account', 'accounts', 1, 0, 0, 0, 1, 1, 2, '', 1, 1, 1, '[]'),
					('registration', 'Registration', X'41206E6577207573657220726567697374657273206F6E2074686520666F72756D2E', 1, 'DBTech\\\Credits\\\Model\\\Event\\\Registration', 0, '', 0, '', 'accounts', 1, 0, 0, 1, 0, 0, 0, '', 1, 1, 1, '[]'),
					('reply', 'Reply', X'536F6D656F6E6520656C736520706F7374696E6720696E20796F7572207468726561642E', 1, 'DBTech\\\Credits\\\Model\\\Event\\\Reply', 0, '', 0, '', 'discuss', 0, 1, 0, 1, 1, 1, 0, 'showpost.php?p=', 1, 1, 1, '[]'),
					('report', 'Report', X'5265706F7274696E672061207069656365206F6620636F6E74656E7420746F20746865206D6F64657261746F72732E', 1, 'DBTech\\\Credits\\\Model\\\Event\\\Report', 0, '', 0, '', 'behave', 1, 0, 1, 1, 1, 1, 0, '', 1, 1, 1, '[]'),
					('reported', 'Reported', X'596F757220636F6E74656E7420776173207265706F7274656420746F20746865206D6F64657261746F72732E', 1, 'DBTech\\\Credits\\\Model\\\Event\\\Reported', 0, '', 0, '', 'behave', 1, 0, 1, 1, 1, 1, 0, '', 1, 1, 1, '[]'),
					('resourcedownload', 'XenResource Download', X'446F776E6C6F6164696E6720612058656E5265736F75726365207265736F757263652E204D756C7469706C6965722069732066696C6573697A652E', 1, 'DBTech\\\Credits\\\Model\\\Event\\\Resource\\\Download', 1, 'Bytes|Byte', 0, 'Resource', 'share', 1, 0, 1, 0, 1, 1, 0, 'attachment.php?attachmentid=', 1, 1, 1, '[]'),
					('resourcedownloaded', 'XenResource Downloaded', X'536F6D656F6E6520656C736520646F776E6C6F6164696E6720796F75722058656E5265736F75726365207265736F757263652E204D756C7469706C6965722069732066696C6573697A652E', 1, 'DBTech\\\Credits\\\Model\\\Event\\\Resource\\\Downloaded', 1, 'Bytes|Byte', 0, '', 'share', 1, 0, 0, 0, 1, 1, 0, 'attachment.php?attachmentid=', 1, 1, 1, '[]'),
					('resourcerate', 'XenResource Rate', X'526174696E6720612058656E5265736F75726365207265736F757263652E204D756C7469706C69657220697320726174696E672E', 1, 'DBTech\\\Credits\\\Model\\\Event\\\Resource\\\Rate', 1, 'Stars|Star', 0, 'Resource', 'opinion', 1, 1, 1, 1, 1, 1, 0, 'showthread.php?t=', 1, 1, 1, '[]'),
					('resourcerated', 'XenResource Rated', X'536F6D656F6E6520656C736520726174656420796F75722058656E5265736F75726365207265736F757263652E204D756C7469706C69657220697320726174696E672E', 1, 'DBTech\\\Credits\\\Model\\\Event\\\Resource\\\Rated', 1, 'Stars|Star', 0, '', 'opinion', 1, 1, 0, 1, 1, 1, 0, 'showthread.php?t=', 1, 1, 1, '[]'),
					('resourceupdate', 'XenResource Update', X'416464696E6720616E2075706461746520746F20612058656E5265736F75726365207265736F757263652E', 1, 'DBTech\\\Credits\\\Model\\\Event\\\Resource\\\Update', 0, '', 0, '', 'share', 1, 1, 1, 1, 1, 1, 0, 'attachment.php?attachmentid=', 1, 1, 1, '[]'),
					('resourceupload', 'XenResource Upload', X'55706C6F6164696E672061206E65772058656E5265736F75726365207265736F757263652E204D756C7469706C6965722069732066696C6573697A652E', 1, 'DBTech\\\Credits\\\Model\\\Event\\\Resource\\\Upload', 1, 'Bytes|Byte', 0, '', 'share', 1, 1, 1, 1, 1, 1, 0, 'attachment.php?attachmentid=', 1, 1, 1, '[]'),
					('revival', 'Revive', X'506F7374696E6720696E206120646F726D616E74207468726561642E', 1, 'DBTech\\\Credits\\\Model\\\Event\\\Revival', 1, 'Days|Day', 0, 'Thread', 'discuss', 0, 0, 1, 0, 1, 1, 0, 'showthread.php?t=', 1, 1, 1, '[]'),
					('sticky', 'Sticky', X'5768656E206F6E65206F6620796F75722074687265616473206265636F6D657320737469636B792E', 1, 'DBTech\\\Credits\\\Model\\\Event\\\Sticky', 0, '', 0, '', 'discuss', 0, 1, 0, 1, 1, 1, 0, 'showthread.php?t=', 1, 1, 1, '[]'),
					('tag', 'Tag', X'4170706C79696E672061206465736372697074697665206C6162656C20746F2061207468726561642E', 1, 'DBTech\\\Credits\\\Model\\\Event\\\Tag', 0, '', 0, 'Thread', 'discuss', 0, 1, 1, 1, 1, 1, 0, 'tags.php?tag=', 1, 1, 1, '[]'),
					('targetbet', 'Sportsbook Bet Challenge', X'4372656174696E67206120626574206368616C6C656E6765207769746820616E6F74686572206D656D626572', 1, 'DBTech\\\Credits\\\Model\\\Event\\\SportsBook\\\TargetBet', 0, '', 0, '', 'sportsbook', 1, 1, 1, 0, 1, 1, 0, '', 1, 1, 1, '[]'),
					('targetbetaccept', 'Sportsbook Bet Challenge Accept', X'416363657074696E67206120626574206368616C6C656E6765', 1, 'DBTech\\\Credits\\\Model\\\Event\\\SportsBook\\\TargetBetAccept', 0, '', 0, '', 'sportsbook', 1, 1, 1, 0, 1, 1, 0, '', 1, 1, 1, '[]'),
					('targetbetaccepted', 'Sportsbook Bet Challenge Accepted', X'536F6D656F6E6520616363657074696E6720796F757220626574206368616C6C656E6765', 1, 'DBTech\\\Credits\\\Model\\\Event\\\SportsBook\\\TargetBetAccepted', 0, '', 0, '', 'sportsbook', 1, 1, 1, 0, 1, 1, 0, '', 1, 1, 1, '[]'),
					('taxation', 'Taxation', X'4175746F6D61746963616C6C7920746178206D656D62657273272063757272656E63696573206F7665722074696D652E', 1, 'DBTech\\\Credits\\\Model\\\Event\\\Taxation', 1, 'Currency|Currency', 0, '', 'time', 1, 0, 0, 0, 1, 1, 0, '', 1, 1, 1, '[]'),
					('thread', 'Thread', X'4372656174696E67206120666F72756D20746F7069632E', 1, 'DBTech\\\Credits\\\Model\\\Event\\\Thread', 2, '', 0, '', 'discuss', 0, 1, 1, 1, 1, 1, 0, 'showthread.php?t=', 1, 1, 1, '[]'),
					('transfer', 'Transfer', X'4D6F76696E6720796F7572206F776E2063757272656E63792066726F6D206F6E6520666F726D20746F20616E6F746865722E', 1, 'DBTech\\\Credits\\\Model\\\Event\\\Transfer', 3, '', 1, '', 'accounts', 1, 0, 1, 0, 1, 1, 2, '', 1, 1, 1, '[]'),
					('trophy', 'Trophy', X'4265696E6720617761726465642061206E65772074726F7068792E', 1, 'DBTech\\\Credits\\\Model\\\Event\\\Trophy', 0, '', 0, '', 'accounts', 1, 0, 0, 1, 1, 1, 0, '', 1, 1, 1, '[]'),
					('upload', 'Upload', X'55706C6F6164696E672061206E6577206174746163686D656E742E204D756C7469706C6965722069732066696C6573697A652E', 1, 'DBTech\\\Credits\\\Model\\\Event\\\Upload', 1, 'Bytes|Byte', 0, '', 'share', 0, 1, 1, 1, 1, 1, 0, 'attachment.php?attachmentid=', 1, 1, 1, '[]'),
					('view', 'Viewed', X'536F6D656F6E6520656C73652076696577696E6720796F7572207468726561642E204576656E74732073686F756C64206265206C696D697465642E', 1, 'DBTech\\\Credits\\\Model\\\Event\\\View', 0, '', 0, '', 'discuss', 0, 0, 0, 0, 1, 1, 0, 'showthread.php?t=', 1, 1, 1, '[]'),
					('visit', 'Visit', X'536F6D656F6E6520656C73652076696577696E6720796F75722070726F66696C652E204576656E74732073686F756C64206265206C696D697465642E', 1, 'DBTech\\\Credits\\\Model\\\Event\\\Visit', 0, '', 0, '', 'network', 1, 0, 0, 0, 1, 1, 0, 'member.php?u=', 1, 1, 1, '[]'),
					('visitor', 'Message', X'506F7374696E6720612076697369746F72206D657373616765206F6E20612070726F66696C652E', 1, 'DBTech\\\Credits\\\Model\\\Event\\\Visitor', 2, '', 0, 'Profile', 'network', 1, 1, 1, 1, 1, 1, 0, 'member.php?u=', 1, 1, 1, '[]'),
					('vote', 'Vote', X'43686F6F73696E6720706F6C6C206F7074696F6E732E204D756C7469706C69657220697320746865206E756D6265722073656C65637465642E', 1, 'DBTech\\\Credits\\\Model\\\Event\\\Vote', 1, 'Options|Option', 0, 'Poll', 'opinion', 0, 1, 1, 1, 1, 1, 0, 'poll.php?do=showresults&pollid=', 1, 1, 1, '[]'),
					('wager', 'Sportsbook Wager', X'506C6163696E672061207761676572206F6E20616E206576656E74', 1, 'DBTech\\\Credits\\\Model\\\Event\\\SportsBook\\\Wager', 0, '', 0, '', 'sportsbook', 1, 1, 1, 0, 1, 1, 0, '', 1, 1, 1, '[]'),
					('wagered', 'Sportsbook Wagered', X'536F6D656F6E6520706C616365642061207761676572206F6E20796F7572206576656E74', 1, 'DBTech\\\Credits\\\Model\\\Event\\\SportsBook\\\Wagered', 0, '', 0, '', 'sportsbook', 1, 1, 1, 0, 1, 1, 0, '', 1, 1, 1, '[]'),
					('wall', 'Messaged', X'536F6D656F6E6520656C736520676976696E6720796F7520612076697369746F72206D657373616765206F6E20796F75722070726F66696C652E', 1, 'DBTech\\\Credits\\\Model\\\Event\\\Wall', 2, '', 0, '', 'network', 1, 1, 0, 1, 1, 1, 0, 'member.php?u=', 1, 1, 1, '[]'),
					('warning', 'Warning', X'47657474696E672061207761726E696E672E', 1, 'DBTech\\\Credits\\\Model\\\Event\\\Warning', 1, 'Points|Point', 0, '', 'behave', 1, 1, 0, 1, 1, 1, 0, 'infraction.php?do=view&infractionid=', 1, 1, 1, '[]'),
					('xengallerycomment', 'XenGallery Comment', X'416464696E67206120636F6D6D656E7420746F20612058656E47616C6C657279206D65646961207265736F757263652E', 1, 'DBTech\\\Credits\\\Model\\\Event\\\SonnbGallery\\\Comment', 2, '', 0, 'Media', 'discuss', 1, 1, 1, 1, 1, 1, 0, 'showpost.php?p=', 1, 1, 1, '[]'),
					('xengallerycommented', 'XenGallery Commented', X'536F6D656F6E6520656C736520636F6D6D656E74696E67206F6E20796F75722058656E47616C6C657279206D65646961207265736F757263652E', 1, 'DBTech\\\Credits\\\Model\\\Event\\\SonnbGallery\\\Commented', 2, '', 0, '', 'discuss', 1, 1, 0, 1, 1, 1, 0, 'showpost.php?p=', 1, 1, 1, '[]'),
					('xengallerydownload', 'XenGallery Download', X'446F776E6C6F6164696E672058656E47616C6C657279206D656469612E204D756C7469706C6965722069732066696C6573697A652E', 1, 'DBTech\\\Credits\\\Model\\\Event\\\SonnbGallery\\\Download', 1, 'Byte|Bytes', 0, 'Media', 'share', 1, 0, 1, 0, 1, 1, 0, 'attachment.php?attachmentid=', 1, 1, 1, '[]'),
					('xengallerydownloaded', 'XenGallery Downloaded', X'536F6D656F6E6520656C736520646F776E6C6F6164696E6720796F75722058656E47616C6C657279206D656469612E204D756C7469706C6965722069732066696C6573697A652E', 1, 'DBTech\\\Credits\\\Model\\\Event\\\SonnbGallery\\\Downloaded', 1, 'Byte|Bytes', 0, '', 'share', 1, 0, 0, 0, 1, 1, 0, 'attachment.php?attachmentid=', 1, 1, 1, '[]'),
					('xengalleryrate', 'XenGallery Rate', X'526174696E672058656E47616C6C657279206D656469612E204D756C7469706C69657220697320726174696E672E', 1, 'DBTech\\\Credits\\\Model\\\Event\\\SonnbGallery\\\Rate', 1, 'Star|Stars', 0, 'Media', 'opinion', 1, 1, 1, 0, 1, 1, 0, 'showthread.php?t=', 1, 1, 1, '[]'),
					('xengalleryrated', 'XenGallery Rated', X'536F6D656F6E6520656C736520726174656420796F75722058656E47616C6C657279206D656469612E204D756C7469706C69657220697320726174696E672E', 1, 'DBTech\\\Credits\\\Model\\\Event\\\SonnbGallery\\\Rated', 1, 'Star|Stars', 0, '', 'opinion', 1, 1, 0, 0, 1, 1, 0, 'showthread.php?t=', 1, 1, 1, '[]'),
					('xengalleryupload', 'XenGallery Upload', X'55706C6F6164696E67206E65772058656E47616C6C657279204D656469612E204D756C7469706C6965722069732066696C6573697A652E', 1, 'DBTech\\\Credits\\\Model\\\Event\\\SonnbGallery\\\Upload', 1, 'Byte|Bytes', 0, '', 'share', 1, 1, 1, 1, 1, 1, 0, 'attachment.php?attachmentid=', 1, 1, 1, '[]'),
					('xenmediocomment', 'XenMedio Comment', X'416464696E67206120636F6D6D656E7420746F20612058656E4D6564696F206D65646961207265736F757263652E', 1, 'DBTech\\\Credits\\\Model\\\Event\\\JaxelMedio\\\Comment', 2, '', 0, 'Media', 'discuss', 1, 1, 1, 1, 1, 1, 0, 'showpost.php?p=', 1, 1, 1, '[]'),
					('xenmediocommented', 'XenMedio Commented', X'536F6D656F6E6520656C736520636F6D6D656E74696E67206F6E20796F75722058656E4D6564696F206D65646961207265736F757263652E', 1, 'DBTech\\\Credits\\\Model\\\Event\\\JaxelMedio\\\Commented', 2, '', 0, '', 'discuss', 1, 1, 0, 1, 1, 1, 0, 'showpost.php?p=', 1, 1, 1, '[]'),
					('xenmedioupload', 'XenMedio Upload', X'416464696E67206E65772058656E4D6564696F204D656469612E', 1, 'DBTech\\\Credits\\\Model\\\Event\\\JaxelMedio\\\Upload', 0, '', 0, '', 'share', 1, 1, 1, 1, 1, 1, 0, 'attachment.php?attachmentid=', 1, 1, 1, '[]')
			",
			"
				REPLACE INTO `xf_purchasable`
					(`purchasable_type_id`, `purchasable_class`, `addon_id`)
				VALUES
					('dbtech_credits_currency', 'DBTech\\\\Credits:Currency', X'4442546563682F43726564697473')
			",
			"
				REPLACE INTO `xf_payment_provider`
					(`provider_id`, `provider_class`, `addon_id`)
				VALUES
					('dbtech_credits', 'DBTech\\\\Credits:Credits', X'4442546563682F43726564697473')
			",
		];
	}

	/**
	 * Returns true if permissions were modified, otherwise false.
	 *
	 * @return bool
	 */
	protected function applyPermissionsInstall(): bool
	{
		// Regular perms
		$this->applyGlobalPermission('dbtechCredits', 'view', 'general', 'viewNode');
		$this->applyGlobalPermissionInt('dbtechCredits', 'charge', 5);

		// Moderator perms
		$this->applyGlobalPermission('dbtechCredits', 'adjust', 'general', 'banUser');
		$this->applyGlobalPermission('dbtechCredits', 'viewAnyLog', 'general', 'bypassUserPrivacy');
		$this->applyGlobalPermission('dbtechCredits', 'bypassCurrencyPrivacy', 'general', 'bypassUserPrivacy');

		return true;
	}

	/**
	 * @return \Closure[]
	 */
	protected function getDefaultWidgetSetup(): array
	{
		return [
			'dbtech_credits_wallet' => function ($key, array $options = [])
			{
				$options = array_replace([], $options);

				$this->createWidget(
					$key,
					'dbtech_credits_wallet',
					[
						'positions' => [
							'dbtech_credits_transactions_sidebar' => 10
						],
						'options' => $options
					]
				);
			},
			'dbtech_credits_richest' => function ($key, array $options = [])
			{
				$options = array_replace([], $options);

				$this->createWidget(
					$key,
					'dbtech_credits_richest',
					[
						'positions' => [
							'dbtech_credits_transactions_sidebar' => 20
						],
						'options' => $options
					]
				);
			},
		];
	}

	/**
	 *
	 */
	protected function runPostInstallActions(): void
	{
		$currencyRepo = \XF::repository('DBTech\Credits:Currency');
		$currencyRepo->rebuildCache();
	}

	/**
	 * @return string[]
	 */
	protected function getAdminPermissions(): array
	{
		return [];
	}

	/**
	 * @return string[]
	 */
	protected function getPermissionGroups(): array
	{
		return [
			'dbtechCredits',
			'dbtechCreditsAdmin'
		];
	}

	/**
	 * @return string[]
	 */
	protected function getContentTypes(): array
	{
		return [
			'dbtech_credits'
		];
	}

	/**
	 * @return string[]
	 */
	protected function getRegistryEntries(): array
	{
		return [
			'dbtCreditsCurrencies'
		];
	}
}