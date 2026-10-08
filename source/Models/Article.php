<?php

/**
 * Moves Code | Article
 *
 * Consulta e persiste artigos editoriais do Moves CMS e do blog público.
 *
 * @author Djalma Martins
 * @package Moves\Models
 */

namespace Moves\Models;

use MovesCode\Model\Connection;
use MovesCode\Model\Model;
use PDO;

class Article extends Model
{
    public function __construct()
    {
        parent::__construct('articles', ['id', 'created_at', 'updated_at'], ['title', 'slug', 'excerpt', 'content', 'cover_image', 'author_id', 'category_id', 'status', 'published_at']);
    }

    /** Lista artigos do CMS com autor e categoria. */
    public function search(string $search, int $limit, int $offset): array
    {
        $where = '';
        $params = [];
        if (trim($search) !== '') {
            $where = ' WHERE a.title LIKE :title OR a.slug LIKE :slug';
            $params = ['title' => '%' . trim($search) . '%', 'slug' => '%' . trim($search) . '%'];
        }

        $statement = Connection::get()->prepare(
            'SELECT a.*, c.name AS category_name, u.name AS author_name FROM articles a '
            . 'JOIN article_categories c ON c.id = a.category_id JOIN users u ON u.id = a.author_id'
            . $where . ' ORDER BY a.updated_at DESC, a.id DESC LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $key => $value) {
            $statement->bindValue(':' . $key, $value, PDO::PARAM_STR);
        }
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Conta artigos para pesquisa e paginação administrativa. */
    public function countSearch(string $search): int
    {
        if (trim($search) === '') {
            return (int) Connection::get()->query('SELECT COUNT(*) FROM articles')->fetchColumn();
        }
        $statement = Connection::get()->prepare('SELECT COUNT(*) FROM articles WHERE title LIKE :title OR slug LIKE :slug');
        $statement->execute(['title' => '%' . trim($search) . '%', 'slug' => '%' . trim($search) . '%']);

        return (int) $statement->fetchColumn();
    }

    /** Busca artigo administrativo por ID com seus relacionamentos. */
    public function findArticle(int $id): ?array
    {
        $statement = Connection::get()->prepare(
            'SELECT a.*, c.name AS category_name, u.name AS author_name FROM articles a '
            . 'JOIN article_categories c ON c.id = a.category_id JOIN users u ON u.id = a.author_id WHERE a.id = :id'
        );
        $statement->execute(['id' => $id]);
        $article = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($article) ? $article : null;
    }

    /** Procura um slug duplicado. */
    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $sql = 'SELECT 1 FROM articles WHERE slug = :slug';
        $params = ['slug' => $slug];
        if ($excludeId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $excludeId;
        }
        $statement = Connection::get()->prepare($sql . ' LIMIT 1');
        $statement->execute($params);

        return $statement->fetchColumn() !== false;
    }

    /** Salva somente campos explícitos; novos artigos começam como rascunho. */
    public function saveArticle(array $article, ?int $id = null): int
    {
        $pdo = Connection::get();
        if ($id === null) {
            $statement = $pdo->prepare(
                "INSERT INTO articles (title, slug, excerpt, content, cover_image, author_id, category_id, status, published_at) VALUES (:title, :slug, :excerpt, :content, :cover_image, :author_id, :category_id, 'draft', NULL)"
            );
            $statement->execute($article);

            return (int) $pdo->lastInsertId();
        }

        $statement = $pdo->prepare('UPDATE articles SET title = :title, slug = :slug, excerpt = :excerpt, content = :content, cover_image = :cover_image, category_id = :category_id WHERE id = :id');
        $statement->execute([
            'title' => $article['title'], 'slug' => $article['slug'], 'excerpt' => $article['excerpt'],
            'content' => $article['content'], 'cover_image' => $article['cover_image'],
            'category_id' => $article['category_id'], 'id' => $id,
        ]);

        return $id;
    }

    /** Publica imediatamente ou retira de publicação. */
    public function setPublished(int $id, bool $published): bool
    {
        $sql = $published
            ? "UPDATE articles SET status = 'published', published_at = :published_at WHERE id = :id"
            : "UPDATE articles SET status = 'draft', published_at = NULL WHERE id = :id";
        $params = ['id' => $id];
        if ($published) {
            $params['published_at'] = gmdate('Y-m-d H:i:s');
        }
        $statement = Connection::get()->prepare($sql);

        return $statement->execute($params) && $statement->rowCount() > 0;
    }

    /** Remove definitivamente um artigo identificado pelo CMS. */
    public function deleteArticle(int $id): bool
    {
        $statement = Connection::get()->prepare('DELETE FROM articles WHERE id = :id');

        return $statement->execute(['id' => $id]) && $statement->rowCount() > 0;
    }

    /** Lista artigos publicáveis, opcionalmente filtrados por categoria. */
    public function publishedList(string $now, int $limit, int $offset, ?int $categoryId = null): array
    {
        $where = "a.status = 'published' AND a.published_at IS NOT NULL AND a.published_at <= :now";
        $params = ['now' => $now];
        if ($categoryId !== null) {
            $where .= ' AND a.category_id = :category_id';
            $params['category_id'] = $categoryId;
        }
        $statement = Connection::get()->prepare(
            'SELECT a.*, c.name AS category_name, c.slug AS category_slug, u.name AS author_name FROM articles a '
            . 'JOIN article_categories c ON c.id = a.category_id JOIN users u ON u.id = a.author_id WHERE '
            . $where . ' ORDER BY a.published_at DESC, a.id DESC LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $key => $value) {
            $statement->bindValue(':' . $key, $value, $key === 'category_id' ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Conta artigos atualmente visíveis ao público, opcionalmente por categoria. */
    public function countPublished(string $now, ?int $categoryId = null): int
    {
        $sql = "SELECT COUNT(*) FROM articles WHERE status = 'published' AND published_at IS NOT NULL AND published_at <= :now";
        $params = ['now' => $now];
        if ($categoryId !== null) {
            $sql .= ' AND category_id = :category_id';
            $params['category_id'] = $categoryId;
        }
        $statement = Connection::get()->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }

    /** Conta artigos publicados e disponíveis em determinada categoria. */
    public function countPublishedInCategory(string $now, int $categoryId): int
    {
        return $this->countPublished($now, $categoryId);
    }

    /** Busca artigo publicado por slug, excluindo rascunhos e publicações futuras. */
    public function findPublishedBySlug(string $slug, string $now): ?array
    {
        $statement = Connection::get()->prepare(
            "SELECT a.*, c.name AS category_name, c.slug AS category_slug, u.name AS author_name FROM articles a "
            . "JOIN article_categories c ON c.id = a.category_id JOIN users u ON u.id = a.author_id "
            . "WHERE a.slug = :slug AND a.status = 'published' AND a.published_at IS NOT NULL AND a.published_at <= :now LIMIT 1"
        );
        $statement->execute(['slug' => $slug, 'now' => $now]);
        $article = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($article) ? $article : null;
    }
}
