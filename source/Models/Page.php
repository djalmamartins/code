<?php

/**
 * Moves Code | Page
 *
 * Consulta e persiste páginas editoriais usando a conexão MovesCode Model.
 *
 * @author Djalma Martins
 * @package Moves\Models
 */

namespace Moves\Models;

use MovesCode\Model\Connection;
use MovesCode\Model\Model;
use PDO;

class Page extends Model
{
    public function __construct()
    {
        parent::__construct('pages', ['id', 'created_at', 'updated_at'], ['title', 'slug', 'content', 'status', 'author_id']);
    }

    /** Retorna páginas pesquisadas, com autor, em ordem de atualização. */
    public function searchPage(string $search, int $limit, int $offset): array
    {
        $where = '';
        $params = [];
        $search = trim($search);
        if ($search !== '') {
            $where = ' WHERE p.title LIKE :title OR p.slug LIKE :slug';
            $params = ['title' => '%' . $search . '%', 'slug' => '%' . $search . '%'];
        }

        $statement = Connection::get()->prepare(
            'SELECT p.*, u.name AS author_name FROM pages p JOIN users u ON u.id = p.author_id'
            . $where . ' ORDER BY p.updated_at DESC, p.id DESC LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $key => $value) {
            $statement->bindValue(':' . $key, $value, PDO::PARAM_STR);
        }
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Conta os resultados da pesquisa. */
    public function countSearch(string $search): int
    {
        $search = trim($search);
        if ($search === '') {
            return (int) Connection::get()->query('SELECT COUNT(*) FROM pages')->fetchColumn();
        }

        $statement = Connection::get()->prepare('SELECT COUNT(*) FROM pages WHERE title LIKE :title OR slug LIKE :slug');
        $statement->execute(['title' => '%' . $search . '%', 'slug' => '%' . $search . '%']);

        return (int) $statement->fetchColumn();
    }

    /** Busca uma página pelo identificador. */
    public function findPage(int $id): ?array
    {
        $statement = Connection::get()->prepare('SELECT * FROM pages WHERE id = :id');
        $statement->execute(['id' => $id]);
        $page = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($page) ? $page : null;
    }

    /** Busca uma página que já pode ser exibida publicamente. */
    public function findPublishedBySlug(string $slug, string $now): ?array
    {
        $statement = Connection::get()->prepare(
            "SELECT * FROM pages WHERE slug = :slug AND status = 'published' AND published_at IS NOT NULL AND published_at <= :now LIMIT 1"
        );
        $statement->execute(['slug' => $slug, 'now' => $now]);
        $page = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($page) ? $page : null;
    }

    /** Informa se um slug já pertence a outra página. */
    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $sql = 'SELECT 1 FROM pages WHERE slug = :slug';
        $params = ['slug' => $slug];
        if ($excludeId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $excludeId;
        }
        $statement = Connection::get()->prepare($sql . ' LIMIT 1');
        $statement->execute($params);

        return $statement->fetchColumn() !== false;
    }

    /** Persiste somente os campos editoriais validados pelo controller. */
    public function savePage(array $page, ?int $id = null): int
    {
        $pdo = Connection::get();
        if ($id === null) {
            $statement = $pdo->prepare(
                "INSERT INTO pages (title, slug, content, status, author_id, published_at) VALUES (:title, :slug, :content, 'draft', :author_id, NULL)"
            );
            $statement->execute($page);

            return (int) $pdo->lastInsertId();
        }

        $statement = $pdo->prepare('UPDATE pages SET title = :title, slug = :slug, content = :content WHERE id = :id');
        $statement->execute([
            'title' => $page['title'],
            'slug' => $page['slug'],
            'content' => $page['content'],
            'id' => $id,
        ]);

        return $id;
    }

    /** Publica imediatamente uma página ou retira sua publicação. */
    public function setPublished(int $id, bool $published): bool
    {
        $sql = $published
            ? "UPDATE pages SET status = 'published', published_at = :published_at WHERE id = :id"
            : "UPDATE pages SET status = 'draft', published_at = NULL WHERE id = :id";
        $statement = Connection::get()->prepare($sql);
        $params = ['id' => $id];
        if ($published) {
            $params['published_at'] = gmdate('Y-m-d H:i:s');
        }

        return $statement->execute($params) && $statement->rowCount() > 0;
    }

    /** Exclui uma página pelo identificador. */
    public function deletePage(int $id): bool
    {
        $statement = Connection::get()->prepare('DELETE FROM pages WHERE id = :id');

        return $statement->execute(['id' => $id]) && $statement->rowCount() > 0;
    }
}
