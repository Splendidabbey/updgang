<?php

namespace DBTech\Credits;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;
use XF\Mvc\Entity\Structure;
use XF\Service\AbstractService;
use function str_replace;
use function strpos;

class Helper
{
	/**
	 * Private constructor, use statically.
	 */
	private function __construct()
	{
	}

	/**
	 * @template T
	 * @param class-string<T> $classname
	 * @param                 ...$args
	 * @return T
	 */
	public static function newExtendedClass(string $classname, ...$args)
	{
		$classname = \XF::extendClass($classname);

		/** @var T $obj */
		$obj = new $classname(...$args);
		return $obj;
	}

	/**
	 * @template T of Entity
	 * @param class-string<T>|null $entityName
	 * @return Structure|null
	 * @noinspection PhpMissingParamTypeInspection
	 */
	public static function getEntityStructure(?string $entityName): ?Structure
	{
		if ($entityName === null || $entityName === '')
		{
			return null;
		}

		// XF2.2 entity cache key is on the short name, not the class name. So map to the expected thing
		if (\XF::$versionId < 2030000 && strpos($entityName, ':') === false)
		{
			$entityName = str_replace('\\Entity\\', ':', $entityName);
		}

		$class = \XF::stringToClass($entityName, '%s\Entity\%s');
		try
		{
			if (@!class_exists($class))
			{
				return null;
			}
			return \XF::app()->em()->getEntityStructure($entityName);
		}
		catch(\Throwable $e)
		{
			return null;
		}
	}

	/**
	 * @template T of Entity
	 * @param class-string<T> $identifier
	 * @return T
	 */
	public static function createEntity(string $identifier)
	{
		// XF2.2 entity cache key is on the short name, not the class name. So map to the expected thing
		if (\XF::$versionId < 2030000 && strpos($identifier, ':') === false)
		{
			$identifier = str_replace('\\Entity\\', ':', $identifier);
		}

		/** @var T $e */
		$e = \XF::app()->em()->create($identifier);

		return $e;
	}

	/**
	 * @template T of Entity
	 * @param class-string<T> $identifier
	 * @param array $values Values for the columns in the entity, in source encoded form
	 * @param array $relations
	 * @param int $options Bit field of the INSTANTIATE_* options
	 *
	 * @return T
	 */
	public static function instantiateEntity(string $identifier, array $values = [], array $relations = [], int $options = 0)
	{
		// XF2.2 entity cache key is on the short name, not the class name. So map to the expected thing
		if (\XF::$versionId < 2030000 && strpos($identifier, ':') === false)
		{
			$identifier = str_replace('\\Entity\\', ':', $identifier);
		}

		/** @var T $e */
		$e = \XF::app()->em()->instantiateEntity($identifier, $values, $relations, $options);

		return $e;
	}

	/**
	 * @template T of Finder
	 * @param class-string<T> $identifier
	 * @param bool $includeDefaultWith
	 * @return T
	 */
	public static function getFinder(string $identifier, bool $includeDefaultWith = true)
	{
		// XF2.2 entity cache key is on the short name, not the class name. So map to the expected thing
		if (\XF::$versionId < 2030000 && strpos($identifier, ':') === false)
		{
			$identifier = str_replace('\\Finder\\', ':', $identifier);
		}

		/** @var T $finder */
		$finder = \XF::app()->em()->getFinder($identifier, $includeDefaultWith);

		return $finder;
	}

	/**
	 * @template T of Finder
	 * @param class-string<T> $identifier
	 * @return T
	 */
	public static function finder(string $identifier)
	{
		// XF2.2 entity cache key is on the short name, not the class name. So map to the expected thing
		if (\XF::$versionId < 2030000 && strpos($identifier, ':') === false)
		{
			$identifier = str_replace('\\Finder\\', ':', $identifier);
		}

		/** @var T $finder */
		$finder = \XF::app()->finder($identifier);

		return $finder;
	}

	/**
	 * @template T of Repository
	 * @param class-string<T> $identifier
	 * @return T
	 */
	public static function repository(string $identifier)
	{
		// XF2.2 repository cache key is on the short name, not the class name. So map to the expected thing
		if (\XF::$versionId < 2030000 && strpos($identifier, ':') === false)
		{
			$identifier = str_replace('\\Repository\\', ':', $identifier);
		}

		/** @var T $repo */
		$repo = \XF::app()->repository($identifier);

		return $repo;
	}

	/**
	 * @template T of AbstractService
	 * @param class-string<T> $identifier
	 * @return T
	 */
	public static function service(string $identifier, ...$arguments)
	{
		/** @var T $service */
		$service = \XF::app()->service($identifier, ...$arguments);

		return $service;
	}

	/**
	 * @template T of Entity
	 * @param class-string<T>  $identifier
	 * @param array|int|string $id
	 * @param array            $with
	 * @return T|null
	 */
	public static function find(string $identifier, $id, array $with = [])
	{
		// XF2.2 entity cache key is on the short name, not the class name. So map to the expected thing
		if (\XF::$versionId < 2030000 && strpos($identifier, ':') === false)
		{
			$identifier = str_replace('\\Entity\\', ':', $identifier);
		}

		/** @var T|null $entity */
		$entity = \XF::app()->em()->find($identifier, $id, $with);

		return $entity;
	}

	/**
	 * @template T of Entity
	 * @param class-string<T>  $identifier
	 * @param array            $where
	 * @param array            $with
	 * @return T|null
	 */
	public static function findOne(string $identifier, array $where, array $with = [])
	{
		// XF2.2 entity cache key is on the short name, not the class name. So map to the expected thing
		if (\XF::$versionId < 2030000 && strpos($identifier, ':') === false)
		{
			$identifier = str_replace('\\Entity\\', ':', $identifier);
		}

		/** @var T|null $entity */
		$entity = \XF::app()->em()->findOne($identifier, $where, $with);

		return $entity;
	}

	/**
	 * @template T of Entity
	 * @param class-string<T>  $identifier
	 * @param array            $ids
	 * @param array            $with
	 * @return T[]|\XF\Mvc\Entity\AbstractCollection<T>
	 */
	public static function findByIds(string $identifier, array $ids, array $with = [])
	{
		// XF2.2 entity cache key is on the short name, not the class name. So map to the expected thing
		if (\XF::$versionId < 2030000 && strpos($identifier, ':') === false)
		{
			$identifier = str_replace('\\Entity\\', ':', $identifier);
		}

		/** @var T[]|\XF\Mvc\Entity\AbstractCollection<T> $entities */
		$entities = \XF::app()->em()->findByIds($identifier, $ids, $with);

		return $entities;
	}

	/**
	 * @template T of Entity
	 * @param class-string<T>  $identifier
	 * @param array|int|string $id
	 * @return T|null
	 */
	public static function findCached(string $identifier, $id)
	{
		// XF2.2 entity cache key is on the short name, not the class name. So map to the expected thing
		if (\XF::$versionId < 2030000 && strpos($identifier, ':') === false)
		{
			$identifier = str_replace('\\Entity\\', ':', $identifier);
		}

		$entity = \XF::app()->em()->findCached($identifier, $id);
		if (!$entity)
		{
			return null;
		}

		/** @var T $entity */
		return $entity;
	}
}