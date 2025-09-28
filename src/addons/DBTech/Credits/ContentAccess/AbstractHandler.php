<?php

namespace DBTech\Credits\ContentAccess;

use DBTech\Credits\Helper;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Entity;

/**
 * Class AbstractHandler
 *
 * @package DBTech\Credits\EventTrigger
 */
abstract class AbstractHandler
{
	/** @var string */
	protected string $contentType;


	/**
	 * AbstractHandler constructor.
	 *
	 * @param string $contentType
	 */
	public function __construct(string $contentType)
	{
		$this->contentType = $contentType;

		$this->setupOptions();
	}

	/**
	 * Designed to be overridden if need be
	 */
	protected function setupOptions(): void
	{
	}

	/**
	 * @return array
	 */
	public function getOptions(): array
	{
		return $this->options;
	}

	/**
	 * @param string $key
	 *
	 * @return mixed|null
	 */
	public function getOption(string $key)
	{
		return $this->options[$key] ?? null;
	}

	/**
	 * @param string $key
	 * @param $value
	 *
	 * @return $this
	 */
	public function setOption(string $key, $value): AbstractHandler
	{
		$this->options[$key] = $value;

		return $this;
	}

	/**
	 * @param bool $forView
	 *
	 * @return array
	 */
	public function getEntityWith(bool $forView = false): array
	{
		return [];
	}

	/**
	 * @param int|string $id
	 *
	 * @return null|AbstractCollection|\XF\Mvc\Entity\Entity
	 */
	public function getContent($id)
	{
		return $this->findByContentType($this->contentType, $id, $this->getEntityWith());
	}

	/**
	 * @return string
	 */
	public function getContentType(): string
	{
		return $this->contentType;
	}

	/**
	 * @param string $contentType
	 * @param int|array $contentId
	 * @param string|array $with
	 *
	 * @return null|AbstractCollection|\XF\Mvc\Entity\Entity
	 */
	public function findByContentType(string $contentType, $contentId, array $with = [])
	{
		$entity = $this->getContentTypeEntity($contentType);

		if (is_array($contentId))
		{
			return Helper::findByIds($entity, $contentId, $with);
		}
		else
		{
			return Helper::find($entity, $contentId, $with);
		}
	}

	/**
	 * @param string $contentType
	 * @param bool $throw
	 *
	 * @return string|null
	 */
	public function getContentTypeEntity(string $contentType, bool $throw = true): ?string
	{
		$entityId = \XF::app()->getContentTypeFieldValue($contentType, 'dbtech_credits_entity');
		if (!$entityId && $throw)
		{
			throw new \LogicException("Content type $contentType must define a 'dbtech_credits_entity' value");
		}

		return $entityId;
	}

	/**
	 * @return \XF\Phrase
	 */
	public function getTitle(): \XF\Phrase
	{
		return \XF::phrase('dbtech_credits_content_access_handler_title.' . $this->contentType);
	}

	/**
	 * @return bool
	 */
	public function isActive(): bool
	{
		return true;
	}

	/**
	 * @param int $lastId
	 * @param int $amount
	 *
	 * @return mixed
	 * @noinspection PhpMissingReturnTypeInspection
	 */
	public function rebuildRange(int $lastId, int $amount)
	{
		$entities = $this->getContentInRange($lastId, $amount);
		if (!$entities->count())
		{
			return false;
		}

		$this->rebuildEntities($entities);

		$keys = $entities->keys();
		return $keys ? max($keys) : false;
	}

	/**
	 * @param int $lastId
	 * @param int $amount
	 * @param bool $forView
	 *
	 * @return \XF\Mvc\Entity\AbstractCollection
	 */
	public function getContentInRange(int $lastId, int $amount, bool $forView = false): AbstractCollection
	{
		$entityId = $this->getContentTypeEntity($this->contentType);

		$em = \XF::em();
		try
		{
			$key = Helper::getEntityStructure($entityId)->primaryKey;
		}
		catch (\LogicException $e)
		{
			return $em->getEmptyCollection();
		}

		if (is_array($key))
		{
			if (count($key) > 1)
			{
				throw new \LogicException("Entity $entityId must only have a single primary key");
			}
			$key = reset($key);
		}

		$finder = Helper::getFinder($entityId)
			->where($key, '>', $lastId)
			->order($key)
			->with($this->getEntityWith($forView));

		$this->applyFinderConstraints($finder);

		return $finder->fetch($amount);
	}

	/**
	 * @param \XF\Mvc\Entity\AbstractCollection $entities
	 */
	public function rebuildEntities(AbstractCollection $entities): void
	{
		foreach ($entities AS $entity)
		{
			$this->rebuild($entity);
		}
	}

	/**
	 * @param \XF\Mvc\Entity\Entity $entity
	 */
	public function rebuild(Entity $entity): void
	{
	}

	/**
	 * @param \XF\Mvc\Entity\Finder $finder
	 *
	 * @return void
	 */
	protected function applyFinderConstraints(\XF\Mvc\Entity\Finder $finder): void
	{
	}

	/**
	 * @return \ArrayObject
	 */
	protected function options(): \ArrayObject
	{
		return \XF::app()->options();
	}

	/**
	 * @return \XF\Mvc\Entity\Manager
	 */
	protected function em(): \XF\Mvc\Entity\Manager
	{
		return \XF::app()->em();
	}

	/**
	 * @return \XF\App
	 */
	protected function app(): \XF\App
	{
		return \XF::app();
	}
}