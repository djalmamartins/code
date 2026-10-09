<?php

/**
 * Moves Code | CMS Content
 *
 * Consulta e persiste destaques, depoimentos e perguntas frequentes.
 *
 * @author Djalma Martins
 * @package Moves\Models
 */

namespace Moves\Models;

use MovesCode\Model\Connection;
use PDO;

class CmsContent
{
    private const TABLES = [
        'slides' => 'slides',
        'testimonials' => 'testimonials',
        'faqs' => 'faqs',
    ];

    public static function supports(string $type): bool
    {
        return isset(self::TABLES[$type]);
    }

    /** Retorna itens pesquisados e ordenados para a listagem administrativa. */
    public function search(string $type, string $query, int $limit, int $offset): array
    {
        $table = $this->table($type);
        $where = '';
        $params = [];
        if (trim($query) !== '') {
            $field = $type === 'testimonials' ? 'name' : ($type === 'faqs' ? 'question' : 'title');
            $where = ' WHERE ' . $field . ' LIKE :query';
            $params['query'] = '%' . trim($query) . '%';
        }
        $statement = Connection::get()->prepare('SELECT * FROM ' . $table . $where . ' ORDER BY sort_order ASC, id DESC LIMIT :limit OFFSET :offset');
        foreach ($params as $key => $value) { $statement->bindValue(':' . $key, $value, PDO::PARAM_STR); }
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countSearch(string $type, string $query): int
    {
        $table = $this->table($type);
        if (trim($query) === '') { return (int) Connection::get()->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn(); }
        $field = $type === 'testimonials' ? 'name' : ($type === 'faqs' ? 'question' : 'title');
        $statement = Connection::get()->prepare('SELECT COUNT(*) FROM ' . $table . ' WHERE ' . $field . ' LIKE :query');
        $statement->execute(['query' => '%' . trim($query) . '%']);

        return (int) $statement->fetchColumn();
    }

    public function find(string $type, int $id): ?array
    {
        $statement = Connection::get()->prepare('SELECT * FROM ' . $this->table($type) . ' WHERE id = :id');
        $statement->execute(['id' => $id]);
        $record = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($record) ? $record : null;
    }

    /** Persiste campos explícitos de um dos três tipos permitidos. */
    public function save(string $type, array $record, ?int $id = null): int
    {
        $table = $this->table($type);
        $fields = match ($type) {
            'slides' => ['title', 'subtitle', 'image_media_id', 'button_text', 'button_url', 'sort_order', 'status', 'published_at'],
            'testimonials' => ['name', 'identification', 'company', 'testimonial', 'photo_media_id', 'sort_order', 'status', 'published_at'],
            'faqs' => ['question', 'answer', 'category', 'sort_order', 'status'],
        };
        $values = [];
        foreach ($fields as $field) { $values[$field] = $record[$field] ?? null; }
        $pdo = Connection::get();
        if ($id === null) {
            $columns = implode(', ', $fields);
            $parameters = implode(', ', array_map(static fn (string $field): string => ':' . $field, $fields));
            $statement = $pdo->prepare('INSERT INTO ' . $table . ' (' . $columns . ') VALUES (' . $parameters . ')');
            $statement->execute($values);

            return (int) $pdo->lastInsertId();
        }
        $assignments = implode(', ', array_map(static fn (string $field): string => $field . ' = :' . $field, $fields));
        $values['id'] = $id;
        $statement = $pdo->prepare('UPDATE ' . $table . ' SET ' . $assignments . ' WHERE id = :id');
        $statement->execute($values);

        return $id;
    }

    public function delete(string $type, int $id): bool
    {
        $statement = Connection::get()->prepare('DELETE FROM ' . $this->table($type) . ' WHERE id = :id');

        return $statement->execute(['id' => $id]) && $statement->rowCount() === 1;
    }

    /** Conteúdos que podem ser apresentados publicamente neste instante. */
    public function publishedSlides(string $now): array
    {
        $statement = Connection::get()->prepare("SELECT * FROM slides WHERE status = 'active' AND (published_at IS NULL OR published_at <= :now) ORDER BY sort_order ASC, id DESC");
        $statement->execute(['now' => $now]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function publicFaqs(): array
    {
        return Connection::get()->query("SELECT question, answer, category FROM faqs WHERE status = 'active' ORDER BY category ASC, sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Confirma uso público antes de entregar uma imagem privada da biblioteca. */
    public function isPublicMedia(int $mediaId, string $now): bool
    {
        $statement = Connection::get()->prepare(
            "SELECT EXISTS(SELECT 1 FROM slides WHERE image_media_id = :slide AND status = 'active' AND (published_at IS NULL OR published_at <= :slide_now)) "
            . "OR EXISTS(SELECT 1 FROM testimonials WHERE photo_media_id = :testimonial AND status = 'published' AND (published_at IS NULL OR published_at <= :testimonial_now)) "
            . "OR EXISTS(SELECT 1 FROM pages WHERE status = 'published' AND published_at IS NOT NULL AND published_at <= :page_now "
            . "AND (content LIKE :page_double_quote OR content LIKE :page_single_quote)) "
            . "OR EXISTS(SELECT 1 FROM articles WHERE status = 'published' AND published_at IS NOT NULL AND published_at <= :article_now "
            . "AND (cover_image = :article_cover OR content LIKE :article_double_quote OR content LIKE :article_single_quote))"
        );
        $publicImage = '/media/' . $mediaId;
        $statement->execute([
            'slide' => $mediaId,
            'slide_now' => $now,
            'testimonial' => $mediaId,
            'testimonial_now' => $now,
            'page_now' => $now,
            'page_double_quote' => '%src="' . $publicImage . '"%',
            'page_single_quote' => "%src='" . $publicImage . "'%",
            'article_now' => $now,
            'article_cover' => $publicImage,
            'article_double_quote' => '%src="' . $publicImage . '"%',
            'article_single_quote' => "%src='" . $publicImage . "'%",
        ]);

        return (bool) $statement->fetchColumn();
    }

    private function table(string $type): string
    {
        if (!isset(self::TABLES[$type])) { throw new \InvalidArgumentException('Tipo de conteúdo inválido.'); }

        return self::TABLES[$type];
    }
}
