<?php

namespace DBTech\Credits\Repository;

use DBTech\Credits\EventTrigger\AbstractHandler;
use DBTech\Credits\Entity\Event;
use DBTech\Credits\Helper;
use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\Entity\Repository;

/**
 * Class EventTrigger
 * @package DBTech\Credits\Repository
 */
class EventTrigger extends Repository
{
	/**
	 * @return AbstractHandler[]
	 * @throws \Exception
	 */
	public function getHandlers(): array
	{
		$handlers = [];

		foreach (\XF::app()->getContentTypeField('dbtech_credits_eventtrigger_handler_class') AS $contentType => $handlerClass)
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
		$handlerClass = \XF::app()->getContentTypeFieldValue($type, 'dbtech_credits_eventtrigger_handler_class');
		if (!$handlerClass)
		{
			if ($throw)
			{
				throw new \InvalidArgumentException("No event trigger handler for '$type'");
			}
			return null;
		}

		if (!class_exists($handlerClass))
		{
			if ($throw)
			{
				throw new \InvalidArgumentException("Event trigger handler for '$type' does not exist: $handlerClass");
			}
			return null;
		}

		$handlerClass = \XF::extendClass($handlerClass);
		return new $handlerClass($type);
	}

	/**
	 * @param bool $onlyActive
	 * @param bool $onlyWithEvents
	 *
	 * @return ArrayCollection
	 * @throws \Exception
	 */
	public function getEventTriggers(bool $onlyActive = false, bool $onlyWithEvents = false): ?ArrayCollection
	{
		$eventTriggers = new ArrayCollection($this->getHandlers());

		if ($onlyActive)
		{
			$eventTriggers = $eventTriggers->filter(function (AbstractHandler $eventTrigger): ?AbstractHandler
			{
				if (!$eventTrigger->isActive())
				{
					return null;
				}

				return $eventTrigger;
			});
		}

		if ($onlyWithEvents)
		{
			/** @var \DBTech\Credits\Entity\Event[]|ArrayCollection $events */
			$events = Helper::finder(\DBTech\Credits\Finder\Event::class)->fetch();

			$eventTriggers = $eventTriggers->filter(function (AbstractHandler $eventTrigger) use ($events, $onlyActive): ?AbstractHandler
			{
				if ($onlyActive)
				{
					$events = $events->filterViewable();
				}

				$activeEvents = $events->filter(function (Event $event) use ($eventTrigger): ?Event
				{
					if ($event->event_trigger_id == $eventTrigger->getContentType())
					{
						return $event;
					}

					return null;
				});

				if (!$activeEvents->count())
				{
					return null;
				}

				// Caching purposes
				$eventTrigger->setEvents($activeEvents);

				return $eventTrigger;
			});
		}

		return $eventTriggers;
	}

	/**
	 * @return array|ArrayCollection
	 * @throws \Exception
	 */
	public function getRebuildableEventTriggers()
	{
		$eventTriggers = $this->getEventTriggers(true, true)
			->filter(function (AbstractHandler $eventTrigger): ?AbstractHandler
			{
				if (!$eventTrigger->getOption('canRebuild'))
				{
					return null;
				}

				return $eventTrigger;
			})
		;

		return $eventTriggers->pluck(function (AbstractHandler $eventTrigger): array
		{
			return [$eventTrigger->getContentType(), $eventTrigger->getContentType()];
		}, false);
	}

	/**
	 * @return array|ArrayCollection
	 * @throws \Exception
	 */
	public function getRebuildableEventTriggerPairs()
	{
		$eventTriggers = $this->getEventTriggers(true, true)
			->filter(function (AbstractHandler $eventTrigger): ?AbstractHandler
			{
				if (!$eventTrigger->getOption('canRebuild'))
				{
					return null;
				}

				return $eventTrigger;
			})
		;

		$arr = $eventTriggers->pluck(function (AbstractHandler $eventTrigger): array
		{
			return [$eventTrigger->getContentType(), $eventTrigger->getTitle()->render()];
		}, false);

		asort($arr);

		return $arr;
	}

	/**
	 * @return \DBTech\Credits\Finder\Event
	 */
	public function findEventsForList(): \DBTech\Credits\Finder\Event
	{
		/** @var \DBTech\Credits\Finder\Event $finder */
		$finder = Helper::finder(\DBTech\Credits\Finder\Event::class);

		return $finder->orderForList();
	}

	/**
	 * @param bool $onlyActive
	 * @param bool $forFullView
	 *
	 * @return array
	 */
	public function getEventTitlePairs(bool $onlyActive = false, bool $forFullView = false): array
	{
		$eventFinder = $this->findEventsForList();
		if ($forFullView)
		{
			$eventFinder->with('Currency');
		}

		$events = $eventFinder->fetch();
		if ($onlyActive)
		{
			$events = $events->filterViewable();
		}

		$arr = $events->pluck(function (Event $e, $k) use ($forFullView): array
		{
			$title = $e->getTitle();
			if ($title instanceof \XF\Phrase)
			{
				$title = $title->render();
			}

			if ($forFullView)
			{
				$title .= ' (' . $e->Currency->title . ')';
			}

			return [$k, $title];
		}, false);

		asort($arr);

		return $arr;
	}

	/**
	 * @param bool $filterActive
	 * @param bool $onlyWithEvents
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function getEventTriggerTitlePairs(bool $filterActive = false, bool $onlyWithEvents = false): array
	{
		$arr = $this->getEventTriggers($filterActive, $onlyWithEvents)->pluck(function (AbstractHandler $e, $k): array
		{
			return [$k, $e->getTitle()->render()];
		}, false);

		asort($arr);

		return $arr;
	}

	/**
	 * @param string $eventTrigger
	 * @param array $extraParams
	 *
	 * @return array|\XF\Mvc\Entity\AbstractCollection
	 * @throws \Exception
	 */
	public function getEventsForEventTrigger(
		string $eventTrigger,
		array $extraParams = []
	) {
		$visitor = \XF::visitor();

		$handler = $this->getHandler($eventTrigger);

		$extraParams = $handler->initExtraParams($visitor, false, $extraParams);
		return $handler->getApplicableEvents($extraParams, $visitor, 0);
	}

	/**
	 * @throws \Exception
	 */
	public function cronBirthday()
	{
		/** @var \DBTech\Credits\EventTrigger\Birthday $birthdayHandler */
		$birthdayHandler = $this->getHandler('birthday');

		/** @var \XF\Finder\User $userFinder */
		$userFinder = Helper::finder(\XF\Finder\User::class);

		/** @var \DBTech\Credits\XF\Entity\User[]|\XF\Mvc\Entity\AbstractCollection $birthdays */
		$birthdays = $userFinder
			->isBirthday(false)
			->isValidUser()
			->where('Profile.dob_year', '!=', 0)
			->fetch();

		foreach ($birthdays as $user)
		{
			$age = $user->Profile->getAge(true);
			if (!$age)
			{
				// I guess in case they haven't provided YOB?
				continue;
			}

			$birthdayHandler->apply($user->user_id, [
				'multiplier' => $age,
				'content_type' => 'user',
				'content_id' => $user->user_id
			], $user);
		}
	}

	/**
	 * @throws \Exception
	 */
	public function cronExpiry()
	{
	}
}