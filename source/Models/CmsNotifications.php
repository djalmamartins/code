<?php

/**
 * Moves Code | CMS Notifications
 *
 * Entrega eventos do CMS por usuário e limita cada consulta ao destinatário.
 *
 * @author Djalma Martins
 * @package Moves\Models
 */

namespace Moves\Models;

use MovesCode\Model\Connection;
use PDO;
use Throwable;

class CmsNotifications
{
    /** Notifica administradores ativos, exceto quem executou a alteração. */
    public function broadcast(string $eventKey, string $title, string $message, string $targetPath, ?int $actorId): void
    {
        if (preg_match('/^[a-z][a-z0-9:._-]{2,189}$/D', $eventKey) !== 1
            || trim($title) === '' || mb_strlen($title, 'UTF-8') > 160
            || mb_strlen($message, 'UTF-8') > 500
            || preg_match('~^/admin/[a-z0-9/?=&._/-]*$~D', $targetPath) !== 1
        ) {
            throw new \InvalidArgumentException('Invalid CMS notification.');
        }

        $pdo = Connection::get();
        $recipients = $pdo->prepare("SELECT id FROM users WHERE status = 'active' AND role IN ('admin', 'superadmin') AND (:actor_null IS NULL OR id <> :actor_id)");
        $recipients->execute(['actor_null' => $actorId, 'actor_id' => $actorId]);
        $insertVerb = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? 'INSERT OR IGNORE' : 'INSERT IGNORE';
        $insert = $pdo->prepare($insertVerb . ' INTO cms_notifications (recipient_user_id, actor_user_id, event_key, title, message, target_path, created_at) VALUES (:recipient, :actor, :event, :title, :message, :path, :created)');
        foreach ($recipients->fetchAll(PDO::FETCH_COLUMN) as $recipientId) {
            $insert->execute([
                'recipient' => (int) $recipientId, 'actor' => $actorId, 'event' => $eventKey,
                'title' => $title, 'message' => $message, 'path' => $targetPath, 'created' => gmdate('Y-m-d H:i:s'),
            ]);
        }
    }

    public function unreadCount(int $recipientId): int
    {
        $statement = Connection::get()->prepare('SELECT COUNT(*) FROM cms_notifications WHERE recipient_user_id = :recipient AND read_at IS NULL');
        $statement->execute(['recipient' => $recipientId]);
        return (int) $statement->fetchColumn();
    }

    /** @return array{unreadCount:int,preview:array<int,array<string,mixed>>} */
    public function headerContext(int $recipientId): array
    {
        try {
            return [
                'unreadCount' => $this->unreadCount($recipientId),
                'preview' => $this->search($recipientId, 'all', 5, 0),
            ];
        } catch (Throwable) {
            return ['unreadCount' => 0, 'preview' => []];
        }
    }

    public function count(int $recipientId, string $filter): int
    {
        [$where, $params] = $this->filter($recipientId, $filter);
        $statement = Connection::get()->prepare('SELECT COUNT(*) FROM cms_notifications' . $where);
        $statement->execute($params);
        return (int) $statement->fetchColumn();
    }

    public function search(int $recipientId, string $filter, int $limit, int $offset): array
    {
        [$where, $params] = $this->filter($recipientId, $filter);
        $statement = Connection::get()->prepare(
            'SELECT n.*, u.name AS actor_name FROM cms_notifications n LEFT JOIN users u ON u.id = n.actor_user_id'
            . $where . ' ORDER BY n.created_at DESC, n.id DESC LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $key => $value) { $statement->bindValue(':' . $key, $value, PDO::PARAM_STR); }
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Atualiza apenas uma notificação pertencente ao usuário atual. */
    public function markRead(int $id, int $recipientId): bool
    {
        $statement = Connection::get()->prepare('UPDATE cms_notifications SET read_at = :read WHERE id = :id AND recipient_user_id = :recipient AND read_at IS NULL');
        $statement->execute(['read' => gmdate('Y-m-d H:i:s'), 'id' => $id, 'recipient' => $recipientId]);
        if ($statement->rowCount() === 1) { return true; }
        $check = Connection::get()->prepare('SELECT id FROM cms_notifications WHERE id = :id AND recipient_user_id = :recipient');
        $check->execute(['id' => $id, 'recipient' => $recipientId]);
        return $check->fetchColumn() !== false;
    }

    public function markAllRead(int $recipientId): void
    {
        Connection::get()->prepare('UPDATE cms_notifications SET read_at = :read WHERE recipient_user_id = :recipient AND read_at IS NULL')
            ->execute(['read' => gmdate('Y-m-d H:i:s'), 'recipient' => $recipientId]);
    }

    /** @return array{0:string,1:array<string,string|int>} */
    private function filter(int $recipientId, string $filter): array
    {
        $where = ' WHERE recipient_user_id = :recipient';
        $params = ['recipient' => $recipientId];
        if ($filter === 'unread') { $where .= ' AND read_at IS NULL'; }
        elseif ($filter === 'read') { $where .= ' AND read_at IS NOT NULL'; }
        return [$where, $params];
    }
}
