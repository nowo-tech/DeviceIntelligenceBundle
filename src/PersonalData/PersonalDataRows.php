<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\PersonalData;

use Doctrine\ORM\EntityManagerInterface;
use Generator;

/**
 * Rows of one user identifier, matched byte for byte (1.9, BUNDLES_FIX_RULES "Identifier columns").
 *
 * The SQL `=` only pre-filters (a `*_ci` / `*_ai_ci` MySQL collation also returns `UserA` or `josé` for `usera` or
 * `jose`); every candidate's stored value is compared with `===`. Keyset batches on the primary key keep memory flat.
 *
 * @internal
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class PersonalDataRows
{
    /**
     * @param class-string $class  a bundle entity with `id` and `userIdentifier` fields
     * @param list<string> $fields extra fields to select (returned under their own name)
     *
     * @return Generator<int, array<string, mixed>> each row: `id` plus the requested fields
     */
    public static function exact(EntityManagerInterface $em, string $class, string $userIdentifier, array $fields, int $batchSize = DeviceIntelligencePersonalDataEraser::BATCH_SIZE): Generator
    {
        $select = 'r.id AS id, r.userIdentifier AS owner';
        foreach ($fields as $field) {
            $select .= \sprintf(', r.%1$s AS %1$s', $field);
        }

        $after = null;
        do {
            $query = $em->createQuery(\sprintf(
                'SELECT %s FROM %s r WHERE r.userIdentifier = :user%s ORDER BY r.id ASC',
                $select,
                $class,
                null === $after ? '' : ' AND r.id > :after',
            ))
                ->setParameter('user', $userIdentifier)
                ->setMaxResults($batchSize);
            if (null !== $after) {
                $query->setParameter('after', $after);
            }

            /** @var list<array<string, mixed>> $rows */
            $rows = $query->getArrayResult();
            foreach ($rows as $row) {
                if ($row['owner'] === $userIdentifier) {
                    unset($row['owner']);

                    yield $row;
                }
            }
            $after = [] === $rows ? null : $rows[\count($rows) - 1]['id'] ?? null;
        } while (\count($rows) === $batchSize);
    }
}
