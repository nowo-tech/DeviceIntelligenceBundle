<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Resolves the entity manager per call and keeps bundle entities out of the identity map.
 *
 * With a {@see ManagerRegistry}, a manager closed by a failed flush in an earlier request is reset, so a
 * long-running worker recovers without a kernel reset. Entities are detached once mapped to core value
 * objects, so every read hits the database (no stale trust/status from another worker) and the identity
 * map does not grow with traffic.
 *
 * @internal
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
trait ResolvesEntityManagerTrait
{
    /**
     * @param class-string $entityClass
     */
    private function entityManager(string $entityClass): EntityManagerInterface
    {
        if ($this->em instanceof EntityManagerInterface) {
            return $this->em;
        }

        $manager = $this->em->getManagerForClass($entityClass);
        if (!$manager instanceof EntityManagerInterface) {
            throw new \LogicException(\sprintf('No Doctrine ORM entity manager is configured for "%s".', $entityClass));
        }
        if ($manager->isOpen()) {
            return $manager;
        }

        foreach (array_keys($this->em->getManagerNames()) as $name) {
            if ($this->em->getManager($name) === $manager) {
                $this->em->resetManager($name);

                break;
            }
        }

        $manager = $this->em->getManagerForClass($entityClass);
        if (!$manager instanceof EntityManagerInterface) {
            throw new \LogicException(\sprintf('No Doctrine ORM entity manager is configured for "%s".', $entityClass));
        }

        return $manager;
    }

    /**
     * Reloads a managed entity from the database so a host-held identity map cannot
     * serve a stale trust/status row under long-running workers.
     *
     * @template T of object
     *
     * @param T|null $entity
     *
     * @return T|null
     */
    private function fresh(EntityManagerInterface $em, ?object $entity): ?object
    {
        if (null === $entity) {
            return null;
        }
        if ($em->contains($entity)) {
            $em->refresh($entity);
        }

        return $entity;
    }

    private function withRefresh(Query $query): Query
    {
        return $query->setHint(Query::HINT_REFRESH, true);
    }

    /**
     * @param iterable<mixed> $entities
     */
    private function detachAll(EntityManagerInterface $em, iterable $entities): void
    {
        foreach ($entities as $entity) {
            if (\is_object($entity) && $em->contains($entity)) {
                $em->detach($entity);
            }
        }
    }
}
