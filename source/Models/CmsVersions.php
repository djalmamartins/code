<?php

/**
 * Moves Code | CMS Versions
 *
 * Registra releases imutáveis dos produtos Moves.
 *
 * @author Djalma Martins
 * @package Moves\Models
 */

namespace Moves\Models;

use MovesCode\Model\Connection;
use PDO;

class CmsVersions
{
    public const PRODUCTS = [
        'web' => 'Web',
        'app' => 'App',
        'studio' => 'Studio',
        'erp' => 'ERP',
        'support' => 'Suporte',
    ];

    /** @return array<int,array<string,mixed>> */
    public function countForProduct(string $product): int
    {
        $statement = Connection::get()->prepare('SELECT COUNT(*) FROM cms_product_versions WHERE product = :product');
        $statement->execute(['product' => $product]);
        return (int) $statement->fetchColumn();
    }

    /** @return array<int,array<string,mixed>> */
    public function forProduct(string $product, int $limit = 20, int $offset = 0): array
    {
        $statement = Connection::get()->prepare(
            'SELECT v.id, v.product, v.version_number, v.release_name, v.notes, v.published_at, u.name AS publisher_name '
            . 'FROM cms_product_versions v INNER JOIN users u ON u.id = v.published_by '
            . 'WHERE v.product = :product ORDER BY v.published_at DESC, v.id DESC LIMIT :limit OFFSET :offset'
        );
        $statement->bindValue(':product', $product, PDO::PARAM_STR);
        $statement->bindValue(':limit', max(1, min(100, $limit)), PDO::PARAM_INT);
        $statement->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function publish(string $product, string $version, string $releaseName, string $notes, int $actorId): void
    {
        if (!isset(self::PRODUCTS[$product]) || preg_match('/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/D', $version) !== 1
            || trim($releaseName) === '' || mb_strlen($releaseName, 'UTF-8') > 160 || mb_strlen($notes, 'UTF-8') > 5000 || $actorId < 1
        ) {
            throw new \InvalidArgumentException('Invalid CMS version record.');
        }

        Connection::get()->prepare(
            'INSERT INTO cms_product_versions (product, version_number, release_name, notes, published_by, published_at) '
            . 'VALUES (:product, :version, :name, :notes, :actor, :published)'
        )->execute([
            'product' => $product, 'version' => $version, 'name' => trim($releaseName),
            'notes' => trim($notes), 'actor' => $actorId, 'published' => gmdate('Y-m-d H:i:s'),
        ]);
    }
}
