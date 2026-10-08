<?php

/**
 * Moves Code | Helpers
 *
 * Disponibiliza funções auxiliares utilizadas pela aplicação.
 *
 * @author Djalma Martins
 * @package Moves\Boot
 */

if (!function_exists("url")) {
    /**
     * Gera uma URL local absoluta a partir da URL base da aplicação.
     */
    function url(?string $path = null): string
    {
        $base = rtrim(APP_URL, "/");
        $path = trim($path ?? "");

        if ($path === "" || $path === "/") {
            return $base;
        }

        if (
            preg_match('/[\\x00-\\x1F\\x7F]/', $path)
            || str_contains($path, "\\")
            || str_starts_with($path, "//")
            || preg_match('/^[a-z][a-z0-9+.-]*:/i', $path)
        ) {
            throw new InvalidArgumentException("Invalid local URL path.");
        }

        $fragment = "";
        if (str_contains($path, "#")) {
            [$path, $fragment] = explode("#", $path, 2);
            $fragment = "#" . $fragment;
        }

        $query = "";
        if (str_contains($path, "?")) {
            [$path, $query] = explode("?", $path, 2);
            $query = "?" . $query;
        }

        $path = trim($path, "/");
        if ($path !== "") {
            $segments = explode("/", preg_replace('~/+~', '/', $path));
            foreach ($segments as $segment) {
                if (in_array(rawurldecode($segment), [".", ".."], true)) {
                    throw new InvalidArgumentException("Invalid local URL path.");
                }
            }
            $path = "/" . implode("/", $segments);
        }

        return $base . $path . $query . $fragment;
    }
}

if (!function_exists("theme")) {
    /**
     * Gera a URL pública dos assets de um tema da aplicação.
     */
    function theme(
        ?string $path = null,
        string $type = "web",
        ?string $name = null,
        ?string $version = null
    ): string {
        if (!isset(APP_THEMES[$type])) {
            throw new InvalidArgumentException("Unknown theme type.");
        }

        $name ??= APP_THEMES[$type]["name"];
        $version ??= APP_THEMES[$type]["version"];

        foreach ([$type, $name, $version] as $segment) {
            if (!preg_match('/^[a-z0-9][a-z0-9_-]*$/i', $segment)) {
                throw new InvalidArgumentException("Invalid theme identifier.");
            }
        }

        $assetPath = trim(APP_THEME_ASSET_BASE, "/") . "/{$type}/{$name}/{$version}";
        $path = trim($path ?? "");

        if ($path === "" || $path === "/") {
            return url($assetPath);
        }

        if (str_contains($path, "\\") || preg_match('/[\\x00-\\x1F\\x7F?#]/', $path)) {
            throw new InvalidArgumentException("Invalid theme asset path.");
        }

        $path = trim(preg_replace('~/+~', '/', $path), "/");
        foreach (explode("/", $path) as $segment) {
            if (
                $segment === ""
                || in_array(rawurldecode($segment), [".", ".."], true)
                || !preg_match('/^[a-z0-9][a-z0-9._-]*$/i', $segment)
            ) {
                throw new InvalidArgumentException("Invalid theme asset path.");
            }
        }

        return url("{$assetPath}/{$path}");
    }
}

if (!function_exists("is_email")) {
    /**
     * Verifica se um endereço de e-mail possui formato válido.
     */
    function is_email(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}

if (!function_exists("str_slug")) {
    /**
     * Converte um texto UTF-8 em um identificador URL amigável.
     */
    function str_slug(string $string): string
    {
        $string = mb_strtolower(trim($string), "UTF-8");
        if ($string === "") {
            return "";
        }

        $string = strtr($string, [
            "á" => "a", "à" => "a", "â" => "a", "ã" => "a", "ä" => "a", "å" => "a",
            "æ" => "ae", "ç" => "c", "é" => "e", "è" => "e", "ê" => "e", "ë" => "e",
            "í" => "i", "ì" => "i", "î" => "i", "ï" => "i", "ñ" => "n", "ó" => "o",
            "ò" => "o", "ô" => "o", "õ" => "o", "ö" => "o", "ø" => "o", "œ" => "oe",
            "ú" => "u", "ù" => "u", "û" => "u", "ü" => "u", "ý" => "y", "ÿ" => "y", "ß" => "ss",
        ]);
        $ascii = iconv("UTF-8", "ASCII//TRANSLIT//IGNORE", $string);
        $ascii = preg_replace('/\p{Mn}+/u', "", $ascii ?: $string);
        $slug = preg_replace('/[^a-z0-9]+/', "-", strtolower($ascii ?: $string));

        return trim($slug ?? "", "-");
    }
}

if (!function_exists("str_title")) {
    /**
     * Converte um texto em título sem aplicar escape HTML.
     */
    function str_title(string $string): string
    {
        $string = preg_replace('/\\s+/u', " ", trim($string));

        return mb_convert_case($string ?? "", MB_CASE_TITLE, "UTF-8");
    }
}

if (!function_exists("str_limit_words")) {
    /**
     * Limita um texto pela quantidade de palavras.
     */
    function str_limit_words(string $string, int $limit, string $pointer = "..."): string
    {
        if ($limit < 1) {
            throw new InvalidArgumentException("Word limit must be positive.");
        }

        $string = trim(preg_replace('/\\s+/u', " ", $string) ?? "");
        if ($string === "") {
            return "";
        }

        $words = preg_split('/\\s+/u', $string) ?: [];

        return count($words) <= $limit
            ? $string
            : implode(" ", array_slice($words, 0, $limit)) . $pointer;
    }
}

if (!function_exists("str_limit_chars")) {
    /**
     * Limita um texto pela quantidade de caracteres UTF-8.
     */
    function str_limit_chars(string $string, int $limit, string $pointer = "..."): string
    {
        if ($limit < 1) {
            throw new InvalidArgumentException("Character limit must be positive.");
        }

        $string = trim($string);

        return mb_strlen($string, "UTF-8") <= $limit
            ? $string
            : rtrim(mb_substr($string, 0, $limit, "UTF-8")) . $pointer;
    }
}

if (!function_exists("str_price")) {
    /**
     * Formata um valor numérico com o padrão decimal brasileiro.
     */
    function str_price(int|float|string|null $price): string
    {
        if ($price === null || $price === "") {
            return "0,00";
        }

        if (!is_numeric($price) || !is_finite((float) $price)) {
            throw new InvalidArgumentException("Price must be numeric.");
        }

        return number_format((float) $price, 2, ",", ".");
    }
}

if (!function_exists("str_search")) {
    /**
     * Normaliza um termo de pesquisa preservando caracteres Unicode.
     */
    function str_search(?string $search): string
    {
        $search = preg_replace('/[^\\p{L}\\p{N}@._ -]+/u', "", trim($search ?? ""));
        $search = trim(preg_replace('/\\s+/u', " ", $search ?? "") ?? "");

        return $search !== "" ? $search : "all";
    }
}

if (!function_exists("date_fmt")) {
    /**
     * Formata uma data válida sem assumir valor para datas ausentes.
     */
    function date_fmt(?string $date, string $format = "d/m/Y H\\hi"): ?string
    {
        $date = trim($date ?? "");
        if ($date === "") {
            return null;
        }

        try {
            $value = new DateTimeImmutable($date);
        } catch (Exception $exception) {
            throw new InvalidArgumentException("Invalid date.", 0, $exception);
        }

        $errors = DateTimeImmutable::getLastErrors();
        if (is_array($errors) && ($errors["warning_count"] > 0 || $errors["error_count"] > 0)) {
            throw new InvalidArgumentException("Invalid date.");
        }

        return $value->format($format);
    }
}

if (!function_exists("date_fmt_back")) {
    /**
     * Converte uma data brasileira válida para o formato de persistência.
     */
    function date_fmt_back(?string $date): ?string
    {
        $date = trim($date ?? "");
        if ($date === "") {
            return null;
        }

        $hasTime = str_contains($date, " ");
        $inputFormat = $hasTime ? "!d/m/Y H:i:s" : "!d/m/Y";
        $outputFormat = $hasTime ? "Y-m-d H:i:s" : "Y-m-d";
        $value = DateTimeImmutable::createFromFormat($inputFormat, $date);
        $errors = DateTimeImmutable::getLastErrors();

        if (
            $value === false
            || (is_array($errors) && ($errors["warning_count"] > 0 || $errors["error_count"] > 0))
            || $value->format(substr($inputFormat, 1)) !== $date
        ) {
            throw new InvalidArgumentException("Invalid Brazilian date.");
        }

        return $value->format($outputFormat);
    }
}
