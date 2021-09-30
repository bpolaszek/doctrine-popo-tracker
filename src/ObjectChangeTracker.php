<?php

declare(strict_types=1);

namespace BenTools\DoctrinePopoTracker;

use Doctrine\Common\EventSubscriber;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\Persistence\ManagerRegistry;
use ReflectionProperty;

use function array_keys;
use function spl_object_id;

final class ObjectChangeTracker implements EventSubscriber
{
    /**
     * @var array<string, array<string, ReflectionProperty>>
     */
    private array $config;
    private array $tracked = [];

    public function __construct(
        private ManagerRegistry $managerRegistry,
    ) {
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::postLoad,
            Events::preFlush,
            Events::postFlush,
        ];
    }

    public function postLoad(LifecycleEventArgs $eventArgs): void
    {
        $this->init();
        $entity = $eventArgs->getEntity();
        $this->track($entity);
    }

    public function preFlush(PreFlushEventArgs $eventArgs): void
    {
        $this->init();
        $uow = $eventArgs->getEntityManager()->getUnitOfWork();

        foreach ($uow->getIdentityMap() as $class => $entities) {
            foreach ($entities as $entity) {
                foreach (array_keys($this->config[$class] ?? []) as $property) {
                    if ($this->shouldTriggerChangeNotification($entity, $property)) {
                        $reflProperty = $this->config[$class][$property];
                        $reflProperty->isPublic() || $reflProperty->setAccessible(true);
                        $value = $reflProperty->getValue($entity);
                        if (\is_object($value)) {
                            $value = clone $value;
                        }
                        $reflProperty->setValue($entity, $value);
                        $oldValue = $this->tracked[spl_object_id($entity)][$property];
                        $uow->propertyChanged($entity, $property, $oldValue, $value);
                        $uow->scheduleExtraUpdate($entity, [$property => [$oldValue, $value]]);
                    }
                }
            }
        }
    }

    public function postFlush(PostFlushEventArgs $eventArgs): void
    {
        $this->init();
        $uow = $eventArgs->getEntityManager()->getUnitOfWork();

        foreach ($uow->getIdentityMap() as $entities) {
            foreach ($entities as $entity) {
                $this->track($entity);
            }
        }
    }

    private function track(object $entity): void
    {
        $class = ClassUtils::getClass($entity);

        foreach ($this->config[$class] ?? [] as $property => $reflProperty) {
            $reflProperty->isPublic() || $reflProperty->setAccessible(true);
            $value = $reflProperty->getValue($entity);
            if (\is_object($value)) {
                $value = clone $value;
            }
            $this->tracked[spl_object_id($entity)][$property] = $value;
        }
    }

    private function shouldTriggerChangeNotification(object $entity, string $property): bool
    {
        $class = ClassUtils::getClass($entity);
        $reflProperty = $this->config[$class][$property] ?? null;
        if (isset($this->tracked[spl_object_id($entity)][$property]) && $reflProperty instanceof ReflectionProperty) {
            $reflProperty->isPublic() || $reflProperty->setAccessible(true);
            $value = $reflProperty->getValue($entity);
            if (\is_object($value)) {
                $value = clone $value;
            }

            return 0 !== ($value <=> $this->tracked[spl_object_id($entity)][$property]);
        }

        return false;
    }

    private function init(): void
    {
        if (isset($this->config)) {
            return;
        }

        foreach ($this->managerRegistry->getManagers() as $manager) {
            foreach ($manager->getMetadataFactory()->getAllMetadata() as $metadata) {
                $reflClass = $metadata->getReflectionClass();
                foreach ($reflClass->getProperties() as $reflProperty) {
                    foreach ($reflProperty->getAttributes(TrackChanges::class) as $reflAttribute) {
                        $this->config[$reflClass->getName()][$reflProperty->getName()] = $reflProperty;
                    }
                }
            }
        }
    }
}
