<?php /** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Credits\XF\Service\User;

class Merge extends XFCP_Merge
{
	protected function combineData()
	{
		parent::combineData();
		
		$container = \XF::app()->container();
		if (isset($container['dbtechCredits.currencies']) && $currencies = $container['dbtechCredits.currencies'])
		{
			foreach ($currencies as $currencyId => $currency)
			{
				// Add all currencies matching
				$this->target->{$currency['column']} += $this->source->{$currency['column']};
			}
		}

		$this->target->dbtech_credits_lastdaily = max($this->target->dbtech_credits_lastdaily, $this->source->dbtech_credits_lastdaily);
		$this->target->dbtech_credits_lastinterest = max($this->target->dbtech_credits_lastinterest, $this->source->dbtech_credits_lastinterest);
		$this->target->dbtech_credits_lastpaycheck = max($this->target->dbtech_credits_lastpaycheck, $this->source->dbtech_credits_lastpaycheck);
		$this->target->dbtech_credits_lasttaxation = max($this->target->dbtech_credits_lasttaxation, $this->source->dbtech_credits_lasttaxation);
	}

	protected function postMergeCleanUp()
	{
		// Update adjust log
		$this->db()->update('xf_dbtech_credits_adjust_log', ['user_id' => $this->target->user_id], 'user_id = ?', [$this->source->user_id]);
		$this->db()->update('xf_dbtech_credits_adjust_log', ['adjust_user_id' => $this->target->user_id], 'adjust_user_id = ?', [$this->source->user_id]);
		
		// Update charge tag purchases
		$this->db()->update('xf_dbtech_credits_charge_purchase', ['user_id' => $this->target->user_id], 'user_id = ?', [$this->source->user_id]);
		
		// Update donation log
		$this->db()->update('xf_dbtech_credits_donation_log', ['user_id' => $this->target->user_id], 'user_id = ?', [$this->source->user_id]);
		$this->db()->update('xf_dbtech_credits_donation_log', ['donation_user_id' => $this->target->user_id], 'donation_user_id = ?', [$this->source->user_id]);
		
		// Update redeem log
		$this->db()->update('xf_dbtech_credits_redeem_log', ['user_id' => $this->target->user_id], 'user_id = ?', [$this->source->user_id]);
		
		// Update purchase transaction log
		$this->db()->update('xf_dbtech_credits_purchase_transaction', ['user_id' => $this->target->user_id], 'user_id = ?', [$this->source->user_id]);
		$this->db()->update('xf_dbtech_credits_purchase_transaction', ['from_user_id' => $this->target->user_id], 'from_user_id = ?', [$this->source->user_id]);
		
		// Update transactions (owner)
		$this->db()->update('xf_dbtech_credits_transaction', ['user_id' => $this->target->user_id], 'user_id = ?', [$this->source->user_id]);

		// Update transactions (source)
		$this->db()->update('xf_dbtech_credits_transaction', ['source_user_id' => $this->target->user_id], 'source_user_id = ?', [$this->source->user_id]);

		// Update transactions (owner #2)
		$this->db()->update('xf_dbtech_credits_transaction', ['owner_id' => $this->target->user_id], 'owner_id = ?', [$this->source->user_id]);
		
		// Update transfer log
		$this->db()->update('xf_dbtech_credits_transfer_log', ['user_id' => $this->target->user_id], 'user_id = ?', [$this->source->user_id]);
	}
}