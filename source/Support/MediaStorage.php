<?php

/**
 * Moves Code | Media Storage
 *
 * Aplica os limites e resolve arquivos de mídia usando MovesCode Storage.
 *
 * @author Djalma Martins
 * @package Moves\Support
 */

namespace Moves\Support;

use MovesCode\Storage\Image;
use RuntimeException;

class MediaStorage
{
    private const ALLOWED = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/webp' => ['webp'],
    ];
    private const MAX_PIXELS = 12_000_000;
    private const RESIZE_WIDTH = 2000;
    private const DEFAULT_MAX_SIZE = 5 * 1024 * 1024;
    private const HARD_MAX_SIZE = 20 * 1024 * 1024;
    private const DIRECTORY = 'moves-media';

    /**
     * Valida o upload e armazena a imagem por meio da biblioteca MovesCode.
     *
     * @return array{storage_path: string, mime_type: string, file_size: int, original_name: string, title: string}
     */
    public function store(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException($this->uploadError((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE)));
        }

        $temporaryPath = (string) ($file['tmp_name'] ?? '');
        if ($temporaryPath === '' || !is_file($temporaryPath)
            || (PHP_SAPI !== 'cli' && !is_uploaded_file($temporaryPath))
        ) {
            throw new RuntimeException('O arquivo recebido não é um upload válido.');
        }

        $fileSize = filesize($temporaryPath);
        if ($fileSize === false || $fileSize < 1 || $fileSize > self::maxFileSize()) {
            throw new RuntimeException('A imagem excede o limite permitido ou está vazia.');
        }

        $fileInfo = (new \finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
        $imageInfo = @getimagesize($temporaryPath);
        $mime = is_string($fileInfo) ? strtolower($fileInfo) : '';
        if (!isset(self::ALLOWED[$mime]) || !is_array($imageInfo)
            || strtolower((string) ($imageInfo['mime'] ?? '')) !== $mime
            || !isset($imageInfo[0], $imageInfo[1])
            || (int) $imageInfo[0] < 1 || (int) $imageInfo[1] < 1
            || (int) $imageInfo[0] * (int) $imageInfo[1] > self::MAX_PIXELS
        ) {
            throw new RuntimeException('Formato de imagem inválido ou não permitido.');
        }

        $originalName = $this->originalName((string) ($file['name'] ?? ''));
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($extension, self::ALLOWED[$mime], true)) {
            throw new RuntimeException('A extensão não corresponde ao formato real da imagem.');
        }

        $root = $this->root();
        $generatedName = 'image-' . bin2hex(random_bytes(12));
        $previousUmask = umask(0027);
        try {
            $stored = (new Image($root, self::DIRECTORY))->upload($file, $generatedName, self::RESIZE_WIDTH);
        } finally {
            umask($previousUmask);
        }
        $realPath = realpath($stored);
        if ($realPath === false || is_link($stored) || !is_file($realPath)
            || !str_starts_with($realPath, $root . DIRECTORY_SEPARATOR)
        ) {
            if (is_file($stored) && !is_link($stored)) {
                unlink($stored);
            }
            throw new RuntimeException('Não foi possível confirmar o armazenamento seguro da imagem.');
        }

        $relativePath = substr($realPath, strlen($root) + 1);
        if (!is_string($relativePath) || preg_match('~^' . self::DIRECTORY . '/\d{4}/\d{2}/[a-z0-9-]+\.(?:jpg|jpeg|png|webp)$~D', $relativePath) !== 1) {
            unlink($realPath);
            throw new RuntimeException('O caminho armazenado não passou pela validação.');
        }

        $storedSize = filesize($realPath);
        if ($storedSize === false || $storedSize < 1 || $storedSize > self::maxFileSize()) {
            unlink($realPath);
            throw new RuntimeException('A imagem processada excede o limite permitido.');
        }
        if (!chmod($realPath, 0640)) {
            unlink($realPath);
            throw new RuntimeException('Não foi possível aplicar permissões privadas à imagem.');
        }

        return [
            'storage_path' => $relativePath,
            'mime_type' => $mime,
            'file_size' => $storedSize,
            'original_name' => $originalName,
            'title' => $this->defaultTitle($originalName),
        ];
    }

    /** Resolve somente caminhos relativos no formato criado pela biblioteca. */
    public function resolve(string $storagePath): string
    {
        if (preg_match('~^' . self::DIRECTORY . '/\d{4}/\d{2}/[a-z0-9-]+\.(?:jpg|jpeg|png|webp)$~D', $storagePath) !== 1) {
            throw new RuntimeException('Caminho de mídia inválido.');
        }

        $root = $this->root();
        $candidate = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $storagePath);
        if (is_link($candidate)) {
            throw new RuntimeException('Arquivo de mídia indisponível.');
        }
        $realPath = realpath($candidate);
        if ($realPath === false || !is_file($realPath)
            || !str_starts_with($realPath, $root . DIRECTORY_SEPARATOR)
        ) {
            throw new RuntimeException('Arquivo de mídia indisponível.');
        }

        return $realPath;
    }

    /** Exclui somente um arquivo armazenado e validado pela aplicação. */
    public function delete(string $storagePath): bool
    {
        return unlink($this->resolve($storagePath));
    }

    /** Move o arquivo para quarentena privada antes da remoção transacional dos metadados. */
    public function quarantine(string $storagePath): array
    {
        $source = $this->resolve($storagePath);
        $directory = dirname($source);
        $quarantine = $directory . DIRECTORY_SEPARATOR . '.pending-delete-' . bin2hex(random_bytes(16));
        if (!rename($source, $quarantine)) {
            throw new RuntimeException('Não foi possível preparar a exclusão segura da imagem.');
        }

        return [$source, $quarantine];
    }

    /** Restaura o arquivo caso a exclusão dos metadados falhe. */
    public function restoreQuarantine(array $quarantine): bool
    {
        [$source, $pending] = $quarantine;
        if (!is_file($pending) || is_link($pending) || file_exists($source)) {
            return false;
        }

        return rename($pending, $source);
    }

    /** Apaga o arquivo em quarentena após a confirmação do banco. */
    public function removeQuarantine(array $quarantine): bool
    {
        [, $pending] = $quarantine;

        return is_file($pending) && !is_link($pending) && unlink($pending);
    }

    /** Limite de upload configurável pelo ambiente, limitado a 20 MiB. */
    public static function maxFileSize(): int
    {
        $configured = getenv('MOVES_MEDIA_MAX_FILE_SIZE');
        if ($configured === false || preg_match('/^[1-9][0-9]*$/', $configured) !== 1) {
            return self::DEFAULT_MAX_SIZE;
        }

        return min((int) $configured, self::HARD_MAX_SIZE);
    }

    private function root(): string
    {
        $configured = getenv('MOVES_MEDIA_STORAGE_PATH');
        $path = $configured !== false && trim($configured) !== ''
            ? rtrim(trim($configured), DIRECTORY_SEPARATOR)
            : dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'uploads';

        if (!str_starts_with($path, DIRECTORY_SEPARATOR) || is_link($path)) {
            throw new RuntimeException('O diretório de mídia não está configurado com segurança.');
        }
        if (!is_dir($path) && !mkdir($path, 0750, true) && !is_dir($path)) {
            throw new RuntimeException('Não foi possível preparar o diretório privado de mídia.');
        }
        if (!chmod($path, 0750)) {
            throw new RuntimeException('Não foi possível aplicar permissões privadas ao armazenamento de mídia.');
        }

        $root = realpath($path);
        $public = realpath(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public');
        if ($root === false || $public === false || is_link($path)
            || $root === $public || str_starts_with($root, $public . DIRECTORY_SEPARATOR)
        ) {
            throw new RuntimeException('O armazenamento de mídia não pode ficar dentro do diretório público.');
        }

        return $root;
    }

    private function originalName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '';
        if (!mb_check_encoding($name, 'UTF-8')) {
            $name = iconv('UTF-8', 'UTF-8//IGNORE', $name) ?: '';
        }
        $name = trim(mb_substr($name, 0, 255, 'UTF-8'));

        return $name !== '' && $name !== '.' && $name !== '..' ? $name : 'imagem.' . 'bin';
    }

    private function defaultTitle(string $originalName): string
    {
        $title = pathinfo($originalName, PATHINFO_FILENAME);
        $title = trim(preg_replace('/\s+/u', ' ', $title) ?? '');

        return mb_substr($title !== '' ? $title : 'Imagem', 0, 200, 'UTF-8');
    }

    private function uploadError(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'A imagem excede o limite permitido pelo servidor.',
            UPLOAD_ERR_PARTIAL => 'O envio da imagem foi interrompido.',
            UPLOAD_ERR_NO_FILE => 'Selecione uma imagem para enviar.',
            default => 'Não foi possível receber a imagem enviada.',
        };
    }
}
