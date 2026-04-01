<?php

declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;

/**
 * DomainTagRepository — verwaltet Tags und deren Zuordnung zu Domains.
 *
 * Konzept:
 *   Tags sind user-scoped (ein Tag gehört genau einem User).
 *   Eine Domain kann beliebig viele Tags haben (n:m über domain_tags).
 *   Farbe ist ein CSS-Farbwert (Hex oder tailwind-kompatibel), Default grau.
 *
 * Voraussetzung: SQL-Migration add_domain_tags.sql muss eingespielt sein.
 */
class DomainTagRepository
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
    ) {
    }

    // -------------------------------------------------------------------------
    // Tag-Verwaltung (CRUD)
    // -------------------------------------------------------------------------

    /**
     * Legt einen neuen Tag für einen User an.
     * Gibt die neue Tag-ID zurück.
     */
    public function createTag(int $userId, string $name, string $color = '#6b7280'): int
    {
        $this->connection->insert('tags', [
            'user_id'    => $userId,
            'name'       => trim($name),
            'color'      => $color,
            'created_at' => $this->clock->now()->format('Y-m-d H:i:s'),
        ]);

        return (int) $this->connection->lastInsertId();
    }

    /**
     * Alle Tags eines Users.
     *
     * @return array<int, array{id: int, name: string, color: string}>
     */
    public function findTagsByUser(int $userId): array
    {
        /** @var array<int, array{id: int, name: string, color: string}> */
        return $this->connection->fetchAllAssociative(
            'SELECT id, name, color FROM tags WHERE user_id = ? ORDER BY name ASC',
            [$userId]
        );
    }

    /**
     * Einen Tag nach ID + User prüfen (Ownership-Check).
     *
     * @return array{id: int, name: string, color: string}|null
     */
    public function findTagByIdAndUser(int $tagId, int $userId): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT id, name, color FROM tags WHERE id = ? AND user_id = ?',
            [$tagId, $userId]
        );

        /** @var array{id: int, name: string, color: string}|null */
        return $row ?: null;
    }

    public function updateTag(int $tagId, int $userId, string $name, string $color): bool
    {
        $affected = $this->connection->update(
            'tags',
            ['name' => trim($name), 'color' => $color],
            ['id' => $tagId, 'user_id' => $userId]
        );

        return $affected > 0;
    }

    /**
     * Löscht einen Tag (CASCADE entfernt domain_tags-Einträge automatisch).
     */
    public function deleteTag(int $tagId, int $userId): bool
    {
        $affected = $this->connection->delete(
            'tags',
            ['id' => $tagId, 'user_id' => $userId]
        );

        return $affected > 0;
    }

    // -------------------------------------------------------------------------
    // Domain ↔ Tag Zuordnung
    // -------------------------------------------------------------------------

    /**
     * Weist einer Domain einen Tag zu.
     * Ignoriert stille Duplikate (INSERT OR IGNORE / ON CONFLICT DO NOTHING).
     */
    public function attachTag(int $domainId, int $tagId): void
    {
        // Plattformunabhängig via try/catch auf Unique-Violation
        try {
            $this->connection->insert('domain_tags', [
                'domain_id' => $domainId,
                'tag_id'    => $tagId,
            ]);
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException) {
            // bereits zugewiesen — kein Fehler
        }
    }

    /**
     * Entfernt die Zuordnung Domain ↔ Tag.
     */
    public function detachTag(int $domainId, int $tagId): void
    {
        $this->connection->delete('domain_tags', [
            'domain_id' => $domainId,
            'tag_id'    => $tagId,
        ]);
    }

    /**
     * Setzt die Tags einer Domain (ersetzt alle bisherigen Zuordnungen).
     *
     * @param int[] $tagIds
     */
    public function setTagsForDomain(int $domainId, array $tagIds): void
    {
        $this->connection->transactional(function () use ($domainId, $tagIds): void {
            $this->connection->delete('domain_tags', ['domain_id' => $domainId]);

            foreach (array_unique($tagIds) as $tagId) {
                $this->connection->insert('domain_tags', [
                    'domain_id' => $domainId,
                    'tag_id'    => (int) $tagId,
                ]);
            }
        });
    }

    /**
     * Alle Tags einer Domain (mit Farbe, für die Anzeige bereit).
     *
     * @return array<int, array{id: int, name: string, color: string}>
     */
    public function findTagsForDomain(int $domainId): array
    {
        /** @var array<int, array{id: int, name: string, color: string}> */
        return $this->connection->fetchAllAssociative(
            'SELECT t.id, t.name, t.color
               FROM tags t
               JOIN domain_tags dt ON dt.tag_id = t.id
              WHERE dt.domain_id = ?
              ORDER BY t.name ASC',
            [$domainId]
        );
    }

    /**
     * Alle Domains eines Users die mit einem bestimmten Tag verknüpft sind.
     * Gibt Domain-IDs zurück (kompatibel mit DomainRepository).
     *
     * @return int[]
     */
    public function findDomainIdsByTag(int $tagId, int $userId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT dt.domain_id
               FROM domain_tags dt
               JOIN tags t ON t.id = dt.tag_id
              WHERE dt.tag_id = ? AND t.user_id = ?',
            [$tagId, $userId]
        );

        return array_column($rows, 'domain_id');
    }

    /**
     * Lädt Tags für mehrere Domains auf einmal (für Listen-Ansichten).
     * Vermeidet N+1 Queries beim Rendern einer Domain-Liste.
     *
     * @param int[]  $domainIds
     * @return array<int, list<array{id: int, name: string, color: string}>>  domainId => tags[]
     */
    public function findTagsForDomains(array $domainIds): array
    {
        if ($domainIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($domainIds), '?'));

        $rows = $this->connection->fetchAllAssociative(
            "SELECT dt.domain_id, t.id, t.name, t.color
               FROM tags t
               JOIN domain_tags dt ON dt.tag_id = t.id
              WHERE dt.domain_id IN ({$placeholders})
              ORDER BY t.name ASC",
            array_values($domainIds)
        );

        $result = [];
        foreach ($rows as $row) {
            $domainId            = (int) $row['domain_id'];
            $result[$domainId][] = [
                'id'    => (int)    $row['id'],
                'name'  => (string) $row['name'],
                'color' => (string) $row['color'],
            ];
        }

        return $result;
    }
}
