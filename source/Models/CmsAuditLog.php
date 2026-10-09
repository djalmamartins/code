<?php

/**
 * Moves Code | CMS Audit Log
 *
 * Consulta eventos de segurança sem expor payloads ou segredos.
 *
 * @author Djalma Martins
 * @package Moves\Models
 */

namespace Moves\Models;

use MovesCode\Model\Connection;
use PDO;

class CmsAuditLog
{
    /** @return array{total:int,items:array<int,array<string,mixed>>} */
    public function search(string $event, string $from, string $to, int $limit, int $offset): array
    {
        $where = [];
        $params = [];
        if ($event !== '') { $where[] = 'e.event_type = :event'; $params['event'] = $event; }
        if ($from !== '') { $where[] = 'e.created_at >= :from_date'; $params['from_date'] = $from . ' 00:00:00'; }
        if ($to !== '') { $where[] = 'e.created_at < :to_date'; $params['to_date'] = (new \DateTimeImmutable($to))->modify('+1 day')->format('Y-m-d') . ' 00:00:00'; }
        $clause = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $pdo = Connection::get();
        $totalStatement = $pdo->prepare('SELECT COUNT(*) FROM security_events e' . $clause);
        $totalStatement->execute($params);
        $total = (int) $totalStatement->fetchColumn();
        $statement = $pdo->prepare('SELECT e.id, e.event_type, e.created_at, u.name AS actor_name FROM security_events e LEFT JOIN users u ON u.id = e.actor_user_id' . $clause . ' ORDER BY e.created_at DESC, e.id DESC LIMIT :limit OFFSET :offset');
        foreach ($params as $key => $value) { $statement->bindValue(':' . $key, $value, PDO::PARAM_STR); }
        $statement->bindValue(':limit', max(1, min(100, $limit)), PDO::PARAM_INT);
        $statement->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
        $statement->execute();
        return ['total' => $total, 'items' => $statement->fetchAll(PDO::FETCH_ASSOC)];
    }
}
