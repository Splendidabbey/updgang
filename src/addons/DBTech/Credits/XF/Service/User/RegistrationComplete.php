<?php /** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Credits\XF\Service\User;

use DBTech\Credits\Helper;

/**
 * @extends \XF\Service\User\RegistrationComplete
 */
class RegistrationComplete extends XFCP_RegistrationComplete
{
	/**
	 * @throws \XF\PrintableException
	 * @throws \Exception
	 */
	public function triggerCompletionActions()
	{
		parent::triggerCompletionActions();

		$user = $this->user;

		$eventTriggerRepo = Helper::repository(\DBTech\Credits\Repository\EventTrigger::class);

		$eventTriggerRepo->getHandler('registration')
			->apply($user->user_id, [
				'source_user_id' => $user->user_id,
				'content_type'   => 'user',
				'content_id'     => $user->user_id
			], $user)
		;
	}
}