<?php

// CLI only
if (PHP_SAPI != 'cli')
{
	die('This script may only be run at the command line.');
}

$ds = DIRECTORY_SEPARATOR;

$dir = realpath(dirname(__FILE__) . "{$ds}..{$ds}..{$ds}..{$ds}..{$ds}");
require($dir . $ds . 'src' . $ds . 'XF.php');

XF::start($dir);
$app = XF::setupApp('XF\Pub\App');

@set_time_limit(0);

if ($argc < 2)
{
	if (empty($argv[1]))
	{
		die('No queue ID specified.');
	}
}

$queueItem = $app->find('XFMG:TranscodeQueue', $argv[1]);
if (!$queueItem)
{
	die('Queue record no longer exists.');
}

$queueItem->queue_state = 'processing';
$queueItem->save();

/**
 * Attempt to workaround a very low wait_timeout value by setting back to default for the current session.
 */
try
{
	$query = 'SET SESSION wait_timeout = 28800';
	$app->db()->getConnectionForQuery($query)->query($query);
}
catch (\XF\Db\Exception $e) {}

/** @var \XFMG\Service\Media\Transcoder $transcoder */
$transcoder = $app->service('XFMG:Media\Transcoder', $queueItem);

$outputFile = $transcoder->transcodeProcess();
$transcoder->finalizeTranscode($outputFile);