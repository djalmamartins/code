<?php

/**
 * Moves Code | Media Item
 *
 * Consulta e persiste metadados dos arquivos privados da biblioteca de mídia.
 *
 * @author Djalma Martins
 * @package Moves\Models
 */

namespace Moves\Models;

use MovesCode\Model\Connection;
use MovesCode\Model\Model;
use PDO;

class MediaItem extends Model
{
    private const TYPES = [
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
    ];

    public function __construct()
    {
        parent::__construct('media', ['id', 'created_at', 'updated_at'], [
            'original_name', 'stored_name', 'storage_path', 'mime_type', 'file_size', 'title', 'alt_text', 'uploaded_by',
        ]);
    }

    /** Lista imagens pesquisáveis para grade ou lista. */
    public function search(string $query, string $type, int $limit, int $offset): array
    {
        [$where, $params] = $this->filters($query, $type);
        $statement = Connection::get()->prepare(
            'SELECT m.*, u.name AS uploader_name FROM media m JOIN users u ON u.id = m.uploaded_by'
            . $where . ' ORDER BY m.created_at DESC, m.id DESC LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $key => $value) {
            $statement->bindValue(':' . $key, $value, PDO::PARAM_STR);
        }
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Conta o mesmo conjunto filtrado usado pela paginação. */
    public function countSearch(string $query, string $type): int
    {
        [$where, $params] = $this->filters($query, $type);
        $statement = Connection::get()->prepare('SELECT COUNT(*) FROM media m' . $where);
        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }

    /** Busca os metadados de uma imagem sem expor sua localização física. */
    public function findMedia(int $id): ?array
    {
        $statement = Connection::get()->prepare(
            'SELECT m.*, u.name AS uploader_name FROM media m JOIN users u ON u.id = m.uploaded_by WHERE m.id = :id'
        );
        $statement->execute(['id' => $id]);
        $item = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($item) ? $item : null;
    }

    /** Persiste metadados de um arquivo já armazenado com nome de servidor. */
    public function create(array $media): int
    {
        $pdo = Connection::get();
        $statement = $pdo->prepare(
            'INSERT INTO media (original_name, stored_name, storage_path, mime_type, file_size, title, alt_text, uploaded_by) '
            . 'VALUES (:original_name, :stored_name, :storage_path, :mime_type, :file_size, :title, :alt_text, :uploaded_by)'
        );
        $statement->execute($media);

        return (int) $pdo->lastInsertId();
    }

    /** Atualiza somente título descritivo e texto alternativo. */
    public function updateDescription(int $id, string $title, string $altText): bool
    {
        $statement = Connection::get()->prepare('UPDATE media SET title = :title, alt_text = :alt_text WHERE id = :id');

        return $statement->execute(['title' => $title, 'alt_text' => $altText, 'id' => $id]);
    }

    /** Remove os metadados depois da exclusão segura do arquivo correspondente. */
    public function deleteMedia(int $id): bool
    {
        $statement = Connection::get()->prepare('DELETE FROM media WHERE id = :id');

        return $statement->execute(['id' => $id]) && $statement->rowCount() === 1;
    }

    /** Detecta URLs internas de mídia associadas a artigos ou páginas antes da exclusão. */
    public function isReferenced(int $id): bool
    {
        $pattern = '%/admin/media/' . $id . '/file%';
        $pdo = Connection::get();
        $checks = [];
        $params = [];

        foreach ([
            'articles' => ['cover_image', 'content'],
            'pages' => ['content'],
        ] as $table => $columns) {
            if (!$this->tableExists($table)) {
                continue;
            }
            $conditions = [];
            foreach ($columns as $column) {
                $key = $table . '_' . $column;
                $conditions[] = $column . ' LIKE :' . $key;
                $params[$key] = $pattern;
            }
            $checks[] = 'EXISTS(SELECT 1 FROM ' . $table . ' WHERE ' . implode(' OR ', $conditions) . ')';
        }

        foreach ([
            'slides' => ['image_media_id'],
            'testimonials' => ['photo_media_id'],
        ] as $table => $columns) {
            if (!$this->tableExists($table)) {
                continue;
            }
            $key = $table . '_media_id';
            $checks[] = 'EXISTS(SELECT 1 FROM ' . $table . ' WHERE ' . $columns[0] . ' = :' . $key . ')';
            $params[$key] = $id;
        }

        if ($checks === []) {
            return false;
        }

        $statement = $pdo->prepare('SELECT ' . implode(' OR ', $checks));
        $statement->execute($params);

        return (bool) $statement->fetchColumn();
    }

    private function tableExists(string $table): bool
    {
        $pdo = Connection::get();
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = :table");
            $statement->execute(['table' => $table]);

            return (bool) $statement->fetchColumn();
        }

        $statement = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table');
        $statement->execute(['table' => $table]);

        return (bool) $statement->fetchColumn();
    }

    private function filters(string $query, string $type): array
    {
        $conditions = [];
        $params = [];
        if (trim($query) !== '') {
            $conditions[] = '(m.original_name LIKE :original_name OR m.title LIKE :title OR m.alt_text LIKE :alt_text)';
            $needle = '%' . trim($query) . '%';
            $params += ['original_name' => $needle, 'title' => $needle, 'alt_text' => $needle];
        }

        if ($type !== 'all' && isset(self::TYPES[$type])) {
            $conditions[] = 'm.mime_type = :mime_type';
            $params['mime_type'] = self::TYPES[$type][0];
        }

        return [$conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions), $params];
    }
}
