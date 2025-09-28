<?php /** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Credits\XF\Service\Thread;

class Replier extends XFCP_Replier
{
	/**
	 *
	 */
	public function setIsAutomated()
	{
		parent::setIsAutomated();

		$this->postPreparer->setApplyDragonByteCreditsEvents(false);
	}

	/**
	 * @return array
	 */
	protected function _validate()
	{
		$errors = parent::_validate();

		$creditsErrors = $this->postPreparer->validateDragonByteCreditsEventsBeforeInsert();
		return array_merge($errors, $creditsErrors);
	}
}