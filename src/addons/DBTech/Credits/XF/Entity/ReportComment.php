<?php /** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Credits\XF\Entity;

use DBTech\Credits\Helper;

/**
 * @extends \XF\Entity\ReportComment
 */
class ReportComment extends XFCP_ReportComment
{
	/**
	 * @throws \Exception
	 */
	protected function _preDelete()
	{
		// Do parent stuff
		parent::_preDelete();

		if ($this->is_report)
		{
			$eventTriggerRepo = Helper::repository(\DBTech\Credits\Repository\EventTrigger::class);

			$eventTriggerRepo->getHandler('report')
				->testUndo([], $this->User)
			;

			$eventTriggerRepo->getHandler('reported')
				->testUndo([], $this->Report->User)
			;
		}
	}

	/**
	 * @throws \Exception
	 */
	protected function _postDelete()
	{
		// Do parent stuff
		parent::_postDelete();

		if ($this->is_report)
		{
			$eventTriggerRepo = Helper::repository(\DBTech\Credits\Repository\EventTrigger::class);

			$eventTriggerRepo->getHandler('report')
				->undo($this->report_id, [
					'content_type' => $this->Report->content_type,
					'content_id'   => $this->Report->content_id
				], $this->User)
			;

			$eventTriggerRepo->getHandler('reported')
				->undo($this->report_id, [
					'content_type' => $this->Report->content_type,
					'content_id'   => $this->Report->content_id
				], $this->Report->User)
			;
		}
	}
}