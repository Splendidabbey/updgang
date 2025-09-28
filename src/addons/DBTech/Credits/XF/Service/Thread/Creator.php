<?php /** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Credits\XF\Service\Thread;

class Creator extends XFCP_Creator
{
	public function setDbtechCreditsAccessCost(float $cost, int $currencyId)
	{
		$this->thread->dbtech_credits_access_cost = $cost;
		$this->thread->dbtech_credits_access_currency_id = $currencyId;
	}

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