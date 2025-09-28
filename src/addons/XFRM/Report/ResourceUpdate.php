<?php

namespace XFRM\Report;

use XF\Entity\Report;
use XF\Mvc\Entity\Entity;
use XF\Report\AbstractHandler;

class ResourceUpdate extends AbstractHandler
{
	protected function canViewContent(Report $report)
	{
		/** @var \XFRM\XF\Entity\User $visitor */
		$visitor = \XF::visitor();
		$categoryId = $report->content_info['resource']['resource_category_id'];

		if (!method_exists($visitor, 'hasResourceCategoryPermission'))
		{
			return false;
		}

		return $visitor->hasResourceCategoryPermission($categoryId, 'view');
	}

	protected function canActionContent(Report $report)
	{
		/** @var \XFRM\XF\Entity\User $visitor */
		$visitor = \XF::visitor();
		$categoryId = $report->content_info['resource']['resource_category_id'];

		if (!method_exists($visitor, 'hasResourceCategoryPermission'))
		{
			return false;
		}

		return (
			$visitor->hasResourceCategoryPermission($categoryId, 'deleteAny')
			|| $visitor->hasResourceCategoryPermission($categoryId, 'editAny')
		);
	}

	public function setupReportEntityContent(Report $report, Entity $content)
	{
		/** @var \XFRM\Entity\ResourceUpdate $update */
		$update = $content;
		$resource = $content->Resource;
		$category = $resource->Category;

		if (!empty($resource->prefix_id))
		{
			/** @var \XFRM\Entity\ResourcePrefix $prefix */
			$prefix = $resource->Prefix;
			$prefixPhrase = \XF::phrase($prefix->getPhraseName());
			$prefixPhrase = $prefixPhrase->render();

			$title = $prefixPhrase . ' - ' . $resource->title;
		}
		else
		{
			$title = $resource->title;
		}

		$report->content_user_id = $resource->user_id;
		$report->content_info = [
			'update' => [
				'resource_update_id' => $update->resource_update_id,
				'resource_id' => $update->resource_id,
				'message' => $update->message
			],
			'resource' => [
				'resource_id' => $resource->resource_id,
				'title' => $title,
				'prefix_id' => $resource->prefix_id,
				'resource_category_id' => $resource->resource_category_id,
				'user_id' => $resource->user_id,
				'username' => $resource->username
			],
			'category' => [
				'resource_category_id' => $category->resource_category_id,
				'title' => $category->title
			]
		];
	}

	public function getContentTitle(Report $report)
	{
		return \XF::phrase('xfrm_resource_update_in_x', [
			'title' => \XF::app()->stringFormatter()->censorText($report->content_info['resource']['title'])
		]);
	}

	public function getContentMessage(Report $report)
	{
		return $report->content_info['update']['message'];
	}

	public function getContentLink(Report $report)
	{
		$info = $report->content_info;

		return \XF::app()->router()->buildLink(
			'canonical:resources/update',
			[
				'resource_id' => $info['resource']['resource_id'],
				'resource_title' => $info['resource']['title'],
				'resource_update_id' => $info['update']['resource_update_id']
			]
		);
	}

	public function getEntityWith()
	{
		return ['Resource', 'Resource.Category'];
	}
}