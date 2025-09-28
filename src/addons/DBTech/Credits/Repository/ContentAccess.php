<?php

namespace DBTech\Credits\Repository;

use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\Entity\Repository;
use DBTech\Credits\ContentAccess\AbstractHandler;

/**
 * @package DBTech\Credits\Repository
 */
class ContentAccess extends Repository
{
	/**
	 * @return AbstractHandler[]
	 * @throws \Exception
	 */
	public function getHandlers(): array
	{
		$handlers = [];

		foreach (\XF::app()->getContentTypeField('dbtech_credits_content_access_handler_class') AS $contentType => $handlerClass)
		{
			if (class_exists($handlerClass))
			{
				$handlerClass = \XF::extendClass($handlerClass);
				$handlers[$contentType] = new $handlerClass($contentType);
			}
		}

		return $handlers;
	}

	/**
	 * @param string $type
	 * @param bool $throw
	 *
	 * @return AbstractHandler|null
	 * @throws \Exception
	 */
	public function getHandler(string $type, bool $throw = true): ?AbstractHandler
	{
		$handlerClass = \XF::app()->getContentTypeFieldValue($type, 'dbtech_credits_content_access_handler_class');
		if (!$handlerClass)
		{
			if ($throw)
			{
				throw new \InvalidArgumentException("No content access handler for '$type'");
			}
			return null;
		}

		if (!class_exists($handlerClass))
		{
			if ($throw)
			{
				throw new \InvalidArgumentException("Content access handler for '$type' does not exist: $handlerClass");
			}
			return null;
		}

		$handlerClass = \XF::extendClass($handlerClass);
		return new $handlerClass($type);
	}

	/**
	 * @return \XF\Mvc\Entity\ArrayCollection
	 * @throws \Exception
	 */
	public function getContentAccessHandlers(): ArrayCollection
	{
		return new ArrayCollection($this->getHandlers());
	}

	/**
	 * @return array|ArrayCollection
	 * @throws \Exception
	 */
	public function getRebuildableContentTypes()
	{
		$contentAccessHandlers = $this->getContentAccessHandlers();

		return $contentAccessHandlers->pluck(function (AbstractHandler $contentAccessHandler): array
		{
			return [$contentAccessHandler->getContentType(), $contentAccessHandler->getContentType()];
		}, false);
	}

	/**
	 * @return array|ArrayCollection
	 * @throws \Exception
	 */
	public function getRebuildableContentTypePairs()
	{
		$contentAccessHandlers = $this->getContentAccessHandlers();

		$arr = $contentAccessHandlers->pluck(function (AbstractHandler $contentAccessHandler): array
		{
			return [$contentAccessHandler->getContentType(), $contentAccessHandler->getTitle()->render()];
		}, false);

		asort($arr);

		return $arr;
	}
}