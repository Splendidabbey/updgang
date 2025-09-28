<?php

namespace XFMG\Import\Importer;

trait vBulletinSourceTrait
{
	protected function getBaseConfigDefault()
	{
		return [
			'db'         => [
				'host'     => '',
				'username' => '',
				'password' => '',
				'dbname'   => '',
				'port'     => 3306,
				'tablePrefix' => '',
				'charset'  => '', // used for the DB connection
			],
			'attachpath'     => null,
			'charset'        => '', // used for UTF8 conversion
			'forum_import_log' => ''
		];
	}

	public function renderBaseConfigOptions(array $vars)
	{
		if (empty($vars['fullConfig']['db']['host']))
		{
			$configPath = getcwd() . '/includes/config.php';
			if (file_exists($configPath) && is_readable($configPath))
			{
				$config = [];
				include($configPath);

				$vars['db'] = [
					'host'        => $config['MasterServer']['servername'],
					'port'        => $config['MasterServer']['port'],
					'username'    => $config['MasterServer']['username'],
					'password'    => $config['MasterServer']['password'],
					'dbname'      => $config['Database']['dbname'],
					'tablePrefix' => $config['Database']['tableprefix'],
					'charset'     => $config['Mysqli']['charset']
				];
			}
			else
			{
				$vars['db'] = [
					'host' => $this->app->config['db']['host'],
					'port' => $this->app->config['db']['port'],
					'username' => $this->app->config['db']['username']
				];
			}
		}

		return $this->app->templater()->renderTemplate('admin:xfmg_import_config_vb_albums', $vars);
	}

	abstract protected function getAttachPathConfig(array &$baseConfig, \XF\Db\Mysqli\Adapter $sourceDb);

	public function validateBaseConfig(array &$baseConfig, array &$errors)
	{
		$baseConfig['db']['tablePrefix'] = preg_replace('/[^a-z0-9_]/i', '', $baseConfig['db']['tablePrefix']);

		$fullConfig = array_replace_recursive($this->getBaseConfigDefault(), $baseConfig);
		$missingFields = false;

		if ($fullConfig['db']['host'])
		{
			$validDbConnection = false;

			try
			{
				$sourceDb = new \XF\Db\Mysqli\Adapter($fullConfig['db'], false);
				$sourceDb->getConnection();
				$validDbConnection = true;
			}
			catch (\XF\Db\Exception $e)
			{
				$errors[] = \XF::phrase('source_database_connection_details_not_correct_x', ['message' => $e->getMessage()]);
			}

			if ($validDbConnection)
			{
				try
				{
					$options = $sourceDb->fetchPairs("
						SELECT varname, value
						FROM setting
						WHERE varname IN('languageid')
					");
				}
				catch (\XF\Db\Exception $e)
				{
					if ($fullConfig['db']['dbname'] === '')
					{
						$errors[] = \XF::phrase('please_enter_database_name');
					}
					else
					{
						$errors[] = \XF::phrase('table_prefix_or_database_name_is_not_correct');
					}
				}

				if ($fullConfig['forum_import_log'])
				{
					$logExists = $this->app->db()->getSchemaManager()->tableExists($fullConfig['forum_import_log']);
					if (!$logExists)
					{
						$errors[] = \XF::phrase('forum_import_log_cannot_be_found');
					}
				}
				else
				{
					$missingFields = true;
				}

				if (!$errors)
				{
					$this->getAttachPathConfig($baseConfig, $sourceDb);
				}

				if (!empty($options['languageid']))
				{
					$defaultCharset = $sourceDb->fetchOne("
						SELECT charset
						FROM language
						WHERE languageid = ?
					", $options['languageid']);

					if (!$defaultCharset || str_replace('-', '', strtolower($defaultCharset)) == 'iso88591')
					{
						$baseConfig['charset'] = 'windows-1252';
					}
					else
					{
						$baseConfig['charset'] = strtolower($defaultCharset);
					}
				}
			}
			else
			{
				$missingFields = true;
			}
		}

		if ($missingFields)
		{
			$errors[] = \XF::phrase('please_complete_required_fields');
		}

		return $errors ? false : true;
	}

	protected function getStepConfigDefault()
	{
		return [
			'mediaItems' => [
				'path' => $this->baseConfig['attachpath']
			]
		];
	}

	public function validateStepConfig(array $steps, array &$stepConfig, array &$errors)
	{
		// attachments as files - path
		if (!empty($this->baseConfig['attachpath']))
		{
			if (!empty($stepConfig['mediaItems']['path']))
			{
				$path = realpath(trim($stepConfig['mediaItems']['path']));

				if (!file_exists($path) || !is_dir($path) || !is_readable($path))
				{
					$errors['attachPath'] = \XF::phrase('directory_specified_as_x_y_not_found_is_not_readable', [
						'type' => 'attachpath',
						'dir'  => $stepConfig['mediaItems']['path']
					]);
				}

				$config['mediaItems']['path'] = $path;
			}
		}

		return $errors ? false : true;
	}

	protected function doInitializeSource()
	{
		$this->sourceDb = new \XF\Db\Mysqli\Adapter($this->baseConfig['db'], false);

		$this->dataManager->setSourceCharset($this->baseConfig['charset'], true);

		$this->forumLog = new \XF\Import\Log(
			$this->app->db(), $this->baseConfig['forum_import_log']
		);
	}
}