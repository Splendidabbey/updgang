<?php /** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Credits\XFRM\Repository;

use DBTech\Credits\Helper;

/**
 * @extends \XFRM\Repository\ResourceVersion
 */
class ResourceVersion extends XFCP_ResourceVersion
{
	/**
	 * @param \XFRM\Entity\ResourceVersion $version
	 *
	 * @throws \XF\PrintableException
	 * @throws \Exception
	 */
	public function logDownload(\XFRM\Entity\ResourceVersion $version)
	{
		$attachments = $version->Attachments;
		if ($attachments->count() > 1)
		{
			$attachmentId = $this->app()->inputFilterer()->filter('file', 'uint');
			$attachment = $attachmentId ? $attachments[$attachmentId] : null;
		}
		else
		{
			$attachment = $attachments->first();
		}

		$resource = $version->Resource;

		$fileSize = ($attachment ? $attachment->getFileSize() : 1);
		$extension = ($attachment ? $attachment->getExtension() : '');

		/** @var \DBTech\Credits\XF\Entity\User $visitor */
		$visitor = \XF::visitor();

		$eventTriggerRepo = Helper::repository(\DBTech\Credits\Repository\EventTrigger::class);

		$downloadHandler = $eventTriggerRepo->getHandler('resourcedownload');
		$downloadedHandler = $eventTriggerRepo->getHandler('resourcedownloaded');

		$downloadHandler
			->testApply([
				'multiplier' => $fileSize,
				'extension' => $extension,
				'owner_id' => $resource->user_id
			], $visitor)
		;

		if ($resource->user_id != $visitor->user_id)
		{
			$downloadedHandler
				->testApply([
					'multiplier' => $fileSize,
					'extension' => $extension,
					'source_user_id' => $visitor->user_id
				], $resource->User)
			;
		}

		$downloadHandler
			->apply($version->resource_version_id, [
				'multiplier' => $fileSize,
				'extension' => $extension,
				'owner_id' => $resource->user_id,
				'content_type' => 'resource_version',
				'content_id' => $version->resource_version_id
			], $visitor)
		;

		if ($resource->user_id != $visitor->user_id)
		{
			$downloadedHandler
				->apply($version->resource_version_id, [
					'multiplier' => $fileSize,
					'extension' => $extension,
					'source_user_id' => $visitor->user_id,
					'content_type' => 'resource_version',
					'content_id' => $version->resource_version_id
				], $resource->User)
			;
		}

		parent::logDownload($version);
	}
}