<?php /** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Credits\XF\Pub\Controller;

use DBTech\Credits\Helper;
use XF\Mvc\ParameterBag;

class Member extends XFCP_Member
{
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \Exception
	 */
	public function actionView(ParameterBag $params)
	{
		$previous = parent::actionView($params);

		if ($previous instanceof \XF\Mvc\Reply\View)
		{
			/** @var \DBTech\Credits\XF\Entity\User $user */
			$user = $this->assertViewableUser($params->user_id);

			/** @var \DBTech\Credits\XF\Entity\User $visitor */
			$visitor = \XF::visitor();

			$eventTriggerRepo = Helper::repository(\DBTech\Credits\Repository\EventTrigger::class);
			$eventTriggerRepo->getHandler('profile')
				->apply($user->user_id, [
					'source_user_id' => $user->user_id,
					'content_type' => 'user',
					'content_id' => $user->user_id
				], $visitor)
			;

			if ($visitor->user_id != $user->user_id)
			{
				$eventTriggerRepo->getHandler('visit')
					->apply($visitor->user_id, [
						'source_user_id' => $visitor->user_id,
						'content_type' => 'user',
						'content_id' => $user->user_id
					], $user)
				;
			}
		}

		return $previous;
	}
}