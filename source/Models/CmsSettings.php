<?php

/**
 * Moves Code | CMS Settings
 *
 * Persiste somente as configurações públicas autorizadas do site.
 *
 * @author Djalma Martins
 * @package Moves\Models
 */

namespace Moves\Models;

use MovesCode\Model\Connection;
use PDO;

class CmsSettings
{
    private const KEYS = [
        'site_name', 'site_tagline', 'contact_email', 'contact_phone',
        'contact_address', 'meta_description', 'logo_media_id',
    ];

    /** @return array<string, string> */
    public function all(): array
    {
        $rows = Connection::get()->query('SELECT setting_key, setting_value FROM cms_settings')->fetchAll(PDO::FETCH_KEY_PAIR);
        $settings = [];
        foreach (self::KEYS as $key) {
            if (isset($rows[$key])) { $settings[$key] = (string) $rows[$key]; }
        }
        return $settings;
    }

    /** @param array<string, string|null> $settings */
    public function save(array $settings, int $actorId): void
    {
        if ($actorId < 1 || array_diff(array_keys($settings), self::KEYS) !== []) {
            throw new \InvalidArgumentException('Invalid CMS settings.');
        }

        $pdo = Connection::get();
        $pdo->beginTransaction();
        try {
            foreach ($settings as $key => $value) {
                $find = $pdo->prepare('SELECT setting_key FROM cms_settings WHERE setting_key = :key');
                $find->execute(['key' => $key]);
                if ($find->fetchColumn() !== false) {
                    $statement = $pdo->prepare('UPDATE cms_settings SET setting_value = :value, updated_by = :actor WHERE setting_key = :key');
                } else {
                    $statement = $pdo->prepare('INSERT INTO cms_settings (setting_key, setting_value, updated_by) VALUES (:key, :value, :actor)');
                }
                $statement->execute(['key' => $key, 'value' => $value, 'actor' => $actorId]);
            }
            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            throw $exception;
        }
    }

    /** Campos seguros que podem ser compartilhados com o tema público. */
    public function publicValues(): array
    {
        try { return $this->all(); } catch (\Throwable) { return []; }
    }

    public static function allowedKeys(): array
    {
        return self::KEYS;
    }
}
