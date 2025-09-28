<?php /** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Credits\XF\Repository;

use DBTech\Credits\Helper;

class Trophy extends XFCP_Trophy
{
	/**
	 * @param \XF\Entity\Trophy $trophy
	 * @param \XF\Entity\User $user
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function awardTrophyToUser(\XF\Entity\Trophy $trophy, \XF\Entity\User $user)
	{
		$previous = parent::awardTrophyToUser($trophy, $user);

		if ($previous)
		{
			$eventTriggerRepo = Helper::repository(\DBTech\Credits\Repository\EventTrigger::class);

			$eventTriggerRepo->getHandler('trophy')
				->apply($trophy->trophy_id, [
					'trophy_id' => $trophy->trophy_id,
					'timestamp' => \XF::$time,
					'content_type' => 'trophy',
					'content_id' => $trophy->trophy_id
				], $user)
			;
		}

		return $previous;
	}
}