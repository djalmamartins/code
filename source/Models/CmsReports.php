<?php

/**
 * Moves Code | CMS Reports
 *
 * Produz indicadores agregados a partir dos registros reais do CMS.
 *
 * @author Djalma Martins
 * @package Moves\Models
 */

namespace Moves\Models;

use MovesCode\Model\Connection;
use PDO;

class CmsReports
{
    /** @return array<string, int> */
    public function summary(?string $from, ?string $to): array
    {
        $pdo = Connection::get();
        $count = static function (string $sql, array $params = []) use ($pdo): int {
            $statement = $pdo->prepare($sql);
            $statement->execute($params);
            return (int) $statement->fetchColumn();
        };
        $createdRange = static function (string $column) use ($from, $to): array {
            $parts = [];
            $params = [];
            if ($from !== null) { $parts[] = $column . ' >= :from_date'; $params['from_date'] = $from . ' 00:00:00'; }
            if ($to !== null) { $parts[] = $column . ' < :to_date'; $params['to_date'] = (new \DateTimeImmutable($to))->modify('+1 day')->format('Y-m-d') . ' 00:00:00'; }
            return [$parts === [] ? '' : ' WHERE ' . implode(' AND ', $parts), $params];
        };
        [$usersWhere, $usersParams] = $createdRange('created_at');
        [$pagesWhere, $pagesParams] = $createdRange('created_at');
        [$articlesWhere, $articlesParams] = $createdRange('created_at');
        [$categoriesWhere, $categoriesParams] = $createdRange('created_at');
        [$mediaWhere, $mediaParams] = $createdRange('created_at');
        return [
            'users' => $count('SELECT COUNT(*) FROM users' . $usersWhere, $usersParams),
            'active_users' => $count("SELECT COUNT(*) FROM users" . ($usersWhere === '' ? " WHERE status='active'" : $usersWhere . " AND status='active'"), $usersParams),
            'pages' => $count('SELECT COUNT(*) FROM pages' . $pagesWhere, $pagesParams),
            'published_pages' => $count("SELECT COUNT(*) FROM pages" . ($pagesWhere === '' ? " WHERE status='published'" : $pagesWhere . " AND status='published'"), $pagesParams),
            'articles' => $count('SELECT COUNT(*) FROM articles' . $articlesWhere, $articlesParams),
            'published_articles' => $count("SELECT COUNT(*) FROM articles" . ($articlesWhere === '' ? " WHERE status='published'" : $articlesWhere . " AND status='published'"), $articlesParams),
            'categories' => $count('SELECT COUNT(*) FROM article_categories' . $categoriesWhere, $categoriesParams),
            'media' => $count('SELECT COUNT(*) FROM media' . $mediaWhere, $mediaParams),
        ];
    }

    /** @return array<int,array{period:string,total:int}> */
    public function publicationSeries(?string $from, ?string $to): array
    {
        $pdo = Connection::get();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $periodExpression = $driver === 'sqlite' ? "strftime('%Y-%m', published_at)" : "DATE_FORMAT(published_at, '%Y-%m')";
        $conditions = [];
        $params = [];
        foreach (['pages', 'articles'] as $table) {
            $parts = ["status = 'published'", 'published_at IS NOT NULL'];
            if ($from !== null) { $parts[] = 'published_at >= :' . $table . '_from'; $params[$table . '_from'] = $from . ' 00:00:00'; }
            if ($to !== null) { $parts[] = 'published_at < :' . $table . '_to'; $params[$table . '_to'] = (new \DateTimeImmutable($to))->modify('+1 day')->format('Y-m-d') . ' 00:00:00'; }
            $conditions[] = "SELECT {$periodExpression} AS period, COUNT(*) AS total FROM {$table} WHERE " . implode(' AND ', $parts) . ' GROUP BY period';
        }
        $statement = $pdo->prepare(
            'SELECT period, SUM(total) AS total FROM (' . implode(' UNION ALL ', $conditions)
            . ') publication_counts GROUP BY period ORDER BY period DESC LIMIT 12'
        );
        $statement->execute($params);
        $items = array_reverse($statement->fetchAll(PDO::FETCH_ASSOC));
        return array_map(static fn (array $item): array => ['period' => (string) $item['period'], 'total' => (int) $item['total']], $items);
    }
}
