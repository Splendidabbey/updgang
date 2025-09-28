<?php

namespace XF\Cron;

class EmailBounce
{
	public static function process()
	{
		/** @var \XF\Repository\EmailBounce $bounceRepo */
		$bounceRepo = \XF::repository('XF:EmailBounce');
		$bounceRepo->pruneEmailBounceLogs();
		$bounceRepo->pruneSoftBounceHistory();

		if (!self::canProcessEmailBounce())
		{
			return;
		}

		\XF::app()->jobManager()->enqueueUnique('EmailBounce', 'XF:EmailBounce', [], false);
	}

	protected static function canProcessEmailBounce(): bool
	{
		if (!\XF::config('enableMail'))
		{
			return false;
		}

		$handler = \XF::options()->emailBounceHandler;

		return !empty($handler);
	}
}