<?php

/**
 * Moves Code | Article Category
 *
 * Consulta e persiste categorias editoriais dos artigos.
 *
 * @author Djalma Martins
 * @package Moves\Models
 */

namespace Moves\Models;

use MovesCode\Model\Connection;
use MovesCode\Model\Model;
use PDO;

class ArticleCategory extends Model
{
    public function __construct()
    {
        parent::__construct('article_categories', ['id', 'created_at', 'updated_at'], ['name', 'slug', 'description']);
    }

    /** Lista categorias por nome. */
    public function all(): array
    {
        return Connection::get()->query('SELECT * FROM article_categories ORDER BY name ASC, id ASC')->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Busca uma categoria. */
    public function findCategory(int $id): ?array
    {
        $statement = Connection::get()->prepare('SELECT * FROM article_categories WHERE id = :id');
        $statement->execute(['id' => $id]);
        $category = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($category) ? $category : null;
    }

    /** Busca categoria pelo slug. */
    public function findBySlug(string $slug): ?array
    {
        $statement = Connection::get()->prepare('SELECT * FROM article_categories WHERE slug = :slug LIMIT 1');
        $statement->execute(['slug' => $slug]);
        $category = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($category) ? $category : null;
    }

    /** Verifica se slug já está em uso, opcionalmente ignorando um registro. */
    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $sql = 'SELECT 1 FROM article_categories WHERE slug = :slug';
        $params = ['slug' => $slug];
        if ($excludeId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $excludeId;
        }
        $statement = Connection::get()->prepare($sql . ' LIMIT 1');
        $statement->execute($params);

        return $statement->fetchColumn() !== false;
    }

    /** Conta artigos associados, inclusive rascunhos, para proteger remoções. */
    public function articleCount(int $id): int
    {
        $statement = Connection::get()->prepare('SELECT COUNT(*) FROM articles WHERE category_id = :id');
        $statement->execute(['id' => $id]);

        return (int) $statement->fetchColumn();
    }

    /** Persiste nome, slug e descrição validados pela camada administrativa. */
    public function saveCategory(array $category, ?int $id = null): int
    {
        $pdo = Connection::get();
        if ($id === null) {
            $statement = $pdo->prepare('INSERT INTO article_categories (name, slug, description) VALUES (:name, :slug, :description)');
            $statement->execute($category);

            return (int) $pdo->lastInsertId();
        }

        $statement = $pdo->prepare('UPDATE article_categories SET name = :name, slug = :slug, description = :description WHERE id = :id');
        $statement->execute($category + ['id' => $id]);

        return $id;
    }

    /** Exclui somente categorias sem artigos associados. */
    public function deleteCategory(int $id): bool
    {
        $statement = Connection::get()->prepare('DELETE FROM article_categories WHERE id = :id');

        return $statement->execute(['id' => $id]) && $statement->rowCount() > 0;
    }
}
