<?php

namespace XFMG\Import\DataHelper;

use XF\Import\DataHelper\AbstractHelper;

class Watch extends AbstractHelper
{
	public function importWatchBulk($newId, $importType, array $userConfigs)
	{
		list ($columnName, $tableName, $canIncludeChildren) = $this->getTableConfigForType($importType);

		$insert = [];

		foreach ($userConfigs AS $userId => $config)
		{
			$row = [
				'user_id' => $userId,
				$columnName => $newId,
				'notify_on' => $config['notify_on'],
				'send_alert' => $config['send_alert'],
				'send_email' => $config['send_email']
			];

			if ($canIncludeChildren)
			{
				$row['include_children'] = $config['include_children'];
			}

			$insert[] = $row;
		}

		if ($insert)
		{
			$onDupe = '
				notify_on = VALUES(notify_on),
				send_alert = VALUES(send_alert),
				send_email = VALUES(send_email)
			';

			if ($canIncludeChildren)
			{
				$onDupe .= ', include_children = VALUES(include_children)';
			}

			$this->db()->insertBulk(
				$tableName,
				$insert,
				false,
				$onDupe
			);
		}
	}

	public function getTableConfigForType($importType)
	{
		$columnName = null;
		$tableName = null;
		$canIncludeChildren = false;

		switch ($importType)
		{
			case 'xfmg_album':
				$columnName = 'album_id';
				$tableName = 'xf_mg_album_watch';
				break;
			case 'xfmg_category':
				$columnName = 'category_id';
				$tableName = 'xf_mg_category_watch';
				$canIncludeChildren = true;
				break;
			case 'xfmg_media':
				$columnName = 'media_id';
				$tableName = 'xf_mg_media_watch';
				break;

			default:
				throw new \InvalidArgumentException('Cannot infer watch table config for type.');
		}

		return [$columnName, $tableName, $canIncludeChildren];
	}
}