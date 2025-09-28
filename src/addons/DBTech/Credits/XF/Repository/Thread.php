<?php /** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Credits\XF\Repository;

use DBTech\Credits\Helper;

class Thread extends XFCP_Thread
{
	/**
	 * @param \XF\Entity\Thread $thread
	 *
	 * @throws \Exception
	 */
	public function logThreadView(\XF\Entity\Thread $thread)
	{
		/** @var \DBTech\Credits\XF\Entity\User $visitor */
		$visitor = \XF::visitor();

		$eventTriggerRepo = Helper::repository(\DBTech\Credits\Repository\EventTrigger::class);

		$eventTriggerRepo->getHandler('read')
			->apply($thread->thread_id, [
				'node_id' => $thread->node_id,
				'owner_id' => $thread->user_id,
				'content_type' => 'thread',
				'content_id' => $thread->thread_id
			], $visitor)
		;

		if ($visitor->user_id != $thread->user_id)
		{
			$eventTriggerRepo->getHandler('view')
				->apply($thread->thread_id, [
					'node_id' => $thread->node_id,
					'source_user_id' => $visitor->user_id,
					'content_type' => 'thread',
					'content_id' => $thread->thread_id
				], $thread->User)
			;
		}

		parent::logThreadView($thread);
	}
}