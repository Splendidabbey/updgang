<?php /** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Credits\XF\Service\Thread;

class Editor extends XFCP_Editor
{
	public function setDbtechCreditsAccessCost(float $cost, int $currencyId)
	{
		$this->thread->dbtech_credits_access_cost = $cost;
		$this->thread->dbtech_credits_access_currency_id = $currencyId;
	}
}