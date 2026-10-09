<?php

/**
 * Moves Code | Authorization HTTP Tests
 *
 * Exercita login, sessão, CSRF, logout e autorização HTTP com banco isolado.
 *
 * @author Djalma Martins
 * @package Moves\Tests
 */

require dirname(__DIR__) . '/index.php';

function assertAuthorizationHttpTest(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function authorizationHttpRequest(string $url, string $method = 'GET', string $cookie = '', string $body = ''): array
{
    $headers = ['Accept: text/html'];
    if ($cookie !== '') {
        $headers[] = 'Cookie: ' . $cookie;
    }
    if ($method === 'POST') {
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
    }

    $context = stream_context_create(['http' => [
        'method' => $method,
        'header' => implode("\r\n", $headers),
        'content' => $body,
        'ignore_errors' => true,
        'follow_location' => 0,
        'timeout' => 4,
    ]]);
    $content = file_get_contents($url, false, $context);
    $responseHeaders = $http_response_header ?? [];
    preg_match('/\s(\d{3})\s/', $responseHeaders[0] ?? '', $statusMatch);
    $status = (int) ($statusMatch[1] ?? 0);
    $cookieHeader = '';
    $location = '';
    foreach ($responseHeaders as $header) {
        if (stripos($header, 'Set-Cookie:') === 0
            && preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i', $header, $cookieMatch)
        ) {
            $cookieHeader = $cookieMatch[1] . '=' . $cookieMatch[2];
        }
        if (stripos($header, 'Location:') === 0) {
            $location = trim(substr($header, strlen('Location:')));
        }
    }

    return [$status, is_string($content) ? $content : '', $cookieHeader, $location, $responseHeaders];
}

function authorizationHttpMultipart(string $url, string $cookie, array $fields, string $fileName, string $mime, string $fileContent): array
{
    $boundary = 'moves-test-' . bin2hex(random_bytes(12));
    $body = '';
    foreach ($fields as $name => $value) {
        $body .= '--' . $boundary . "\r\nContent-Disposition: form-data; name=\"" . $name . "\"\r\n\r\n" . $value . "\r\n";
    }
    $body .= '--' . $boundary . "\r\nContent-Disposition: form-data; name=\"image\"; filename=\"" . addslashes($fileName) . "\"\r\nContent-Type: " . $mime . "\r\n\r\n" . $fileContent . "\r\n--" . $boundary . "--\r\n";
    $headers = ['Accept: text/html', 'Content-Type: multipart/form-data; boundary=' . $boundary];
    if ($cookie !== '') { $headers[] = 'Cookie: ' . $cookie; }
    $context = stream_context_create(['http' => [
        'method' => 'POST', 'header' => implode("\r\n", $headers), 'content' => $body,
        'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 8,
    ]]);
    $content = file_get_contents($url, false, $context);
    $responseHeaders = $http_response_header ?? [];
    preg_match('/\s(\d{3})\s/', $responseHeaders[0] ?? '', $statusMatch);

    return [(int) ($statusMatch[1] ?? 0), is_string($content) ? $content : '', $responseHeaders];
}

function authorizationHttpCsrf(string $html): string
{
    if (!preg_match('/name="_token"\s+value="([a-f0-9]{64})"/', $html, $match)) {
        throw new RuntimeException('Could not obtain the CSRF token from the isolated test session.');
    }

    return $match[1];
}

function authorizationHttpTotp(string $secret, ?int $counter = null): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $buffer = 0; $bits = 0; $key = '';
    foreach (str_split($secret) as $char) {
        $buffer = ($buffer << 5) | strpos($alphabet, $char); $bits += 5;
        if ($bits >= 8) { $bits -= 8; $key .= chr(($buffer >> $bits) & 255); $buffer &= (1 << $bits) - 1; }
    }
    $counter ??= intdiv(time(), 30); $hash = hash_hmac('sha1', pack('N2', intdiv($counter, 0x100000000), $counter % 0x100000000), $key, true);
    $offset = ord($hash[19]) & 15; $number = unpack('N', substr($hash, $offset, 4))[1] & 0x7fffffff;
    return str_pad((string) ($number % 1000000), 6, '0', STR_PAD_LEFT);
}

$databasePath = sys_get_temp_dir() . '/moves-code-auth-http-' . bin2hex(random_bytes(8)) . '.sqlite';
$mediaRoot = sys_get_temp_dir() . '/moves-code-media-http-' . bin2hex(random_bytes(8));
mkdir($mediaRoot, 0700);
$database = new PDO('sqlite:' . $databasePath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$database->exec("CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(160) NOT NULL,
    email VARCHAR(254) NOT NULL COLLATE NOCASE UNIQUE,
    password VARCHAR(255) NOT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'active',
    role VARCHAR(16) NOT NULL DEFAULT 'user',
    last_login_at DATETIME NULL,
    totp_secret_encrypted VARCHAR(255) NULL,
    totp_enabled_at DATETIME NULL,
    last_totp_counter BIGINT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
)");
$database->exec('CREATE TABLE auth_attempts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    identifier CHAR(64) NOT NULL,
    attempted_at DATETIME NOT NULL
)');
$database->exec('CREATE TABLE user_sessions (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, session_hash CHAR(64) UNIQUE, device_label TEXT, created_at DATETIME, last_activity_at DATETIME, revoked_at DATETIME)');
$database->exec('CREATE TABLE security_events (id INTEGER PRIMARY KEY AUTOINCREMENT, actor_user_id INTEGER NULL, subject_user_id INTEGER NULL, event_type TEXT, created_at DATETIME)');
$database->exec('CREATE TABLE user_recovery_codes (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, code_hash TEXT, used_at DATETIME NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)');
$database->exec('CREATE TABLE pages (id INTEGER PRIMARY KEY AUTOINCREMENT, title VARCHAR(200) NOT NULL, slug VARCHAR(200) NOT NULL UNIQUE, content TEXT NOT NULL, status VARCHAR(16) NOT NULL DEFAULT \'draft\', author_id INTEGER NOT NULL, published_at DATETIME NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP)');
$database->exec('CREATE TABLE article_categories (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(120) NOT NULL, slug VARCHAR(140) NOT NULL UNIQUE, description VARCHAR(500) NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP)');
$database->exec('CREATE TABLE articles (id INTEGER PRIMARY KEY AUTOINCREMENT, title VARCHAR(200) NOT NULL, slug VARCHAR(200) NOT NULL UNIQUE, excerpt VARCHAR(500) NOT NULL, content TEXT NOT NULL, cover_image VARCHAR(500) NULL, author_id INTEGER NOT NULL, category_id INTEGER NOT NULL, status VARCHAR(16) NOT NULL DEFAULT \'draft\', published_at DATETIME NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP)');
$database->exec('CREATE TABLE media (id INTEGER PRIMARY KEY AUTOINCREMENT, original_name VARCHAR(255) NOT NULL, stored_name VARCHAR(255) NOT NULL, storage_path VARCHAR(500) NOT NULL UNIQUE, mime_type VARCHAR(100) NOT NULL, file_size INTEGER NOT NULL, title VARCHAR(200) NOT NULL, alt_text VARCHAR(300) NOT NULL DEFAULT \'\', uploaded_by INTEGER NOT NULL, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');
$database->exec('CREATE TABLE slides (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, subtitle TEXT NOT NULL DEFAULT \'\', image_media_id INTEGER NULL REFERENCES media(id) ON DELETE RESTRICT, button_text TEXT NOT NULL DEFAULT \'\', button_url TEXT NOT NULL DEFAULT \'\', sort_order INTEGER NOT NULL DEFAULT 0, status TEXT NOT NULL DEFAULT \'inactive\', published_at TEXT NULL, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');
$database->exec('CREATE TABLE testimonials (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, identification TEXT NOT NULL DEFAULT \'\', company TEXT NOT NULL DEFAULT \'\', testimonial TEXT NOT NULL, photo_media_id INTEGER NULL REFERENCES media(id) ON DELETE RESTRICT, sort_order INTEGER NOT NULL DEFAULT 0, status TEXT NOT NULL DEFAULT \'draft\', published_at TEXT NULL, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');
$database->exec('CREATE TABLE faqs (id INTEGER PRIMARY KEY AUTOINCREMENT, question TEXT NOT NULL, answer TEXT NOT NULL, category TEXT NOT NULL DEFAULT \'\', sort_order INTEGER NOT NULL DEFAULT 0, status TEXT NOT NULL DEFAULT \'inactive\', created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');
$insert = $database->prepare('INSERT INTO users (name, email, password, status, role) VALUES (?, ?, ?, ?, ?)');
$insert->execute(['HTTP Admin Fixture', 'http-admin@example.test', password_hash('test-password-admin', PASSWORD_DEFAULT), 'active', 'admin']);
$insert->execute(['HTTP User Fixture', 'http-user@example.test', password_hash('test-password-user', PASSWORD_DEFAULT), 'active', 'user']);
$insert->execute(['HTTP Superadmin Fixture', 'http-superadmin@example.test', password_hash('test-password-superadmin', PASSWORD_DEFAULT), 'active', 'superadmin']);
unset($database);
chmod($databasePath, 0600);

$listener = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
if ($listener === false) {
    unlink($databasePath);
    throw new RuntimeException('Could not reserve a local port for the authorization HTTP test.');
}
$address = stream_socket_get_name($listener, false);
fclose($listener);
$port = (int) substr((string) $address, strrpos((string) $address, ':') + 1);
$baseUrl = 'http://127.0.0.1:' . $port;

putenv('APP_ENV=local');
putenv('APP_DEBUG=false');
putenv('APP_URL=' . $baseUrl);
putenv('MOVES_DB_DSN=sqlite:' . $databasePath);
putenv('MOVES_DB_USER=');
putenv('MOVES_DB_PASSWORD=');
putenv('MOVES_MEDIA_STORAGE_PATH=' . $mediaRoot);
putenv('MOVES_MEDIA_MAX_FILE_SIZE=4096');
$testKey = base64_encode(random_bytes(32));
putenv('MOVES_2FA_ENCRYPTION_KEY=' . $testKey);
$testTotpSecret = (new \Moves\Support\Totp())->generateSecret();
$testTotpCipher = (new \Moves\Support\Totp())->encrypt($testTotpSecret);
$database = new PDO('sqlite:' . $databasePath);
$database->prepare('INSERT INTO users (name, email, password, status, role, totp_secret_encrypted, totp_enabled_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
    ->execute(['HTTP 2FA Fixture', 'http-2fa@example.test', password_hash('test-password-2fa', PASSWORD_DEFAULT), 'active', 'admin', $testTotpCipher, gmdate('Y-m-d H:i:s')]);
unset($database);

$pipes = [];
$server = proc_open([
    PHP_BINARY,
    '-S', '127.0.0.1:' . $port,
    '-t', dirname(__DIR__) . '/public',
    dirname(__DIR__) . '/public/index.php',
], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__));

if (!is_resource($server)) {
    unlink($databasePath);
    throw new RuntimeException('Could not start the isolated authorization HTTP server.');
}

try {
    $ready = false;
    for ($attempt = 0; $attempt < 60; $attempt++) {
        $socket = @stream_socket_client('tcp://127.0.0.1:' . $port, $errorCode, $errorMessage, 0.1);
        if ($socket !== false) {
            fclose($socket);
            $ready = true;
            break;
        }
        usleep(100_000);
    }
    assertAuthorizationHttpTest($ready, 'Isolated authorization HTTP server did not start.');

    [$status, , $cookie] = authorizationHttpRequest($baseUrl . '/admin');
    assertAuthorizationHttpTest($status === 303, 'Anonymous HTTP request was not denied by /admin.');

    [$status, $loginPage, $cookie] = authorizationHttpRequest($baseUrl . '/login');
    assertAuthorizationHttpTest($status === 200 && $cookie !== '', 'HTTP login form or isolated session cookie was unavailable.');
    $csrf = authorizationHttpCsrf($loginPage);
    [$status, , $cookie, $location] = authorizationHttpRequest(
        $baseUrl . '/login',
        'POST',
        $cookie,
        http_build_query(['_token' => $csrf, 'email' => 'http-admin@example.test', 'password' => 'test-password-admin'])
    );
    assertAuthorizationHttpTest($status === 303 && str_ends_with($location, '/admin'), 'HTTP admin login failed.');

    $adminCookie = $cookie;
    [$status, $adminPage] = authorizationHttpRequest($baseUrl . '/admin', 'GET', $adminCookie);
    assertAuthorizationHttpTest($status === 200 && str_contains($adminPage, 'HTTP Admin Fixture'), 'Authorized HTTP admin access failed.');
    [$status, $adminPage] = authorizationHttpRequest($baseUrl . '/admin', 'GET', $adminCookie);
    assertAuthorizationHttpTest($status === 200 && str_contains($adminPage, 'HTTP Admin Fixture'), 'Authenticated HTTP session did not persist.');
    [$status, $editorPage] = authorizationHttpRequest($baseUrl . '/admin/editor', 'GET', $adminCookie);
    assertAuthorizationHttpTest($status === 200 && str_contains($editorPage, 'data-moves-editor-demo') && str_contains($editorPage, 'HTML gerado'), 'Admin editor demonstration was unavailable.');
    assertAuthorizationHttpTest(str_contains($editorPage, 'cms-assets/wysiwyg/js/app.js') && str_contains($editorPage, 'cms-assets/icons/css/moves-icons.css'), 'Editor or icon package assets were not included.');
    [$status, $editorScript] = authorizationHttpRequest($baseUrl . '/cms-assets/wysiwyg/js/app.js', 'GET', $adminCookie);
    assertAuthorizationHttpTest($status === 200 && str_contains($editorScript, 'MovesEditor.version'), 'Protected editor JavaScript asset did not load (HTTP ' . $status . ': ' . substr($editorScript, 0, 160) . ').');
    [$status, $pagesList] = authorizationHttpRequest($baseUrl . '/admin/pages', 'GET', $adminCookie);
    assertAuthorizationHttpTest($status === 200 && str_contains($pagesList, 'Nenhuma página cadastrada.'), 'Pages management listing did not render.');
    [$status, $pageForm] = authorizationHttpRequest($baseUrl . '/admin/pages/new', 'GET', $adminCookie);
    assertAuthorizationHttpTest($status === 200 && str_contains($pageForm, 'data-moves-page-editor') && str_contains($pageForm, 'data-open-media-picker') && str_contains($pageForm, 'cms-assets/wysiwyg/js/app.js'), 'Moves Editor or media picker was not integrated into page creation.');
    $pageCsrf = authorizationHttpCsrf($pageForm);
    [$status] = authorizationHttpRequest($baseUrl . '/admin/pages', 'POST', $adminCookie, http_build_query([
        'title' => 'Must fail CSRF', 'slug' => 'csrf-fail', 'content' => '<p>Sem token</p>',
    ]));
    assertAuthorizationHttpTest($status === 403, 'Page creation without a valid CSRF token was accepted.');
    [$status, , , $pageLocation] = authorizationHttpRequest($baseUrl . '/admin/pages', 'POST', $adminCookie, http_build_query([
        '_token' => $pageCsrf,
        'title' => 'Página XSS de teste',
        'slug' => 'pagina-xss-teste',
        'content' => '<h2>Conteúdo seguro</h2><p onclick="alert(1)">Texto <strong>permitido</strong></p><script>alert(1)</script><img src="data:image/svg+xml;base64,PHN2Zz4=" onerror="alert(1)"><a href="javascript:alert(1)">link</a>',
    ]));
    assertAuthorizationHttpTest($status === 303 && preg_match('~/admin/pages/(\d+)/edit$~', $pageLocation, $pageIdMatch) === 1, 'Page creation did not save a draft.');
    $pageId = (int) $pageIdMatch[1];
    $database = new PDO('sqlite:' . $databasePath);
    $savedPage = $database->query('SELECT * FROM pages WHERE id = ' . $pageId)->fetch(PDO::FETCH_ASSOC);
    assertAuthorizationHttpTest(is_array($savedPage) && $savedPage['status'] === 'draft' && str_contains($savedPage['content'], '<strong>permitido</strong>'), 'Page draft or allowed markup was not persisted.');
    foreach (['<script', 'onclick', '<img', 'data:image', 'javascript:'] as $unsafeMarkup) {
        assertAuthorizationHttpTest(!str_contains(strtolower($savedPage['content']), $unsafeMarkup), 'Unsafe page markup was persisted: ' . $unsafeMarkup);
    }
    unset($database);
    [$status, $editPage] = authorizationHttpRequest($pageLocation, 'GET', $adminCookie);
    assertAuthorizationHttpTest($status === 200 && str_contains($editPage, 'Editar página'), 'Created page could not be edited.');
    $pageCsrf = authorizationHttpCsrf($editPage);
    [$status] = authorizationHttpRequest($baseUrl . '/admin/pages', 'POST', $adminCookie, http_build_query([
        '_token' => $pageCsrf, 'title' => 'Slug duplicado', 'slug' => 'pagina-xss-teste', 'content' => '<p>Teste</p>',
    ]));
    assertAuthorizationHttpTest($status === 422, 'Duplicate page slug was accepted.');
    [$status, , , $updatedLocation] = authorizationHttpRequest($baseUrl . '/admin/pages/' . $pageId, 'POST', $adminCookie, http_build_query([
        '_token' => $pageCsrf, 'title' => 'Página editada', 'slug' => 'pagina-xss-teste', 'content' => '<p>Conteúdo <em>atualizado</em></p>',
    ]));
    assertAuthorizationHttpTest($status === 303 && str_ends_with($updatedLocation, '/admin/pages/' . $pageId . '/edit'), 'Page update failed.');
    [$status, $editPage] = authorizationHttpRequest($updatedLocation, 'GET', $adminCookie);
    $pageCsrf = authorizationHttpCsrf($editPage);
    [$status, , , $publicLocation] = authorizationHttpRequest($baseUrl . '/admin/pages/' . $pageId . '/publish', 'POST', $adminCookie, http_build_query(['_token' => $pageCsrf]));
    assertAuthorizationHttpTest($status === 303, 'Admin could not publish a page.');
    [$status, $publicPage] = authorizationHttpRequest($baseUrl . '/pagina/pagina-xss-teste');
    assertAuthorizationHttpTest($status === 200 && str_contains($publicPage, 'Página editada') && str_contains($publicPage, '<em>atualizado</em>'), 'Published page was not rendered publicly.');
    assertAuthorizationHttpTest(preg_match('/<div class="editorial-content">(.*?)<\/div>/s', $publicPage, $publicContentMatch) === 1, 'Sanitized editorial content container was not rendered.');
    $renderedContent = strtolower($publicContentMatch[1]);
    foreach (['<script', 'onclick', '<img', 'data:image', 'javascript:'] as $unsafeMarkup) {
        assertAuthorizationHttpTest(!str_contains($renderedContent, $unsafeMarkup), 'Unsafe markup reached public page content: ' . $unsafeMarkup);
    }
    [$status, $editPage] = authorizationHttpRequest($baseUrl . '/admin/pages/' . $pageId . '/edit', 'GET', $adminCookie);
    $pageCsrf = authorizationHttpCsrf($editPage);
    [$status] = authorizationHttpRequest($baseUrl . '/admin/pages/' . $pageId . '/unpublish', 'POST', $adminCookie, http_build_query(['_token' => $pageCsrf]));
    assertAuthorizationHttpTest($status === 303, 'Admin could not unpublish a page.');
    [$status] = authorizationHttpRequest($baseUrl . '/pagina/pagina-xss-teste');
    assertAuthorizationHttpTest($status === 404, 'Unpublished page remained publicly accessible.');
    $database = new PDO('sqlite:' . $databasePath);
    $adminId = (int) $database->query("SELECT id FROM users WHERE email='http-admin@example.test'")->fetchColumn();
    $database->prepare("INSERT INTO pages (title, slug, content, status, author_id, published_at) VALUES (?, ?, ?, 'published', ?, ?)")
        ->execute(['Future page', 'future-page', '<p>Não deve aparecer</p>', $adminId, '2999-01-01 00:00:00']);
    unset($database);
    [$status] = authorizationHttpRequest($baseUrl . '/pagina/future-page');
    assertAuthorizationHttpTest($status === 404, 'Future publication was publicly accessible.');
    [$status, $editPage] = authorizationHttpRequest($baseUrl . '/admin/pages/' . $pageId . '/edit', 'GET', $adminCookie);
    $pageCsrf = authorizationHttpCsrf($editPage);
    [$status] = authorizationHttpRequest($baseUrl . '/admin/pages/' . $pageId . '/delete', 'POST', $adminCookie, http_build_query(['_token' => $pageCsrf]));
    assertAuthorizationHttpTest($status === 403, 'Admin was allowed to delete a page without pages.delete.');
    [$status, $articleList] = authorizationHttpRequest($baseUrl . '/admin/blog', 'GET', $adminCookie);
    assertAuthorizationHttpTest($status === 200 && str_contains($articleList, 'Nenhum artigo cadastrado.') && !str_contains($articleList, 'Funcionalidade pendente'), 'Article management listing did not render.');
    [$status, $categoryPage] = authorizationHttpRequest($baseUrl . '/admin/blog/categories', 'GET', $adminCookie);
    assertAuthorizationHttpTest($status === 200 && str_contains($categoryPage, 'Nova categoria'), 'Article category management was unavailable.');
    $categoryCsrf = authorizationHttpCsrf($categoryPage);
    [$status, , , $categoryLocation] = authorizationHttpRequest($baseUrl . '/admin/blog/categories', 'POST', $adminCookie, http_build_query([
        '_token' => $categoryCsrf, 'name' => 'Estratégia', 'slug' => 'estrategia', 'description' => 'Conteúdos de estratégia.',
    ]));
    assertAuthorizationHttpTest($status === 303, 'Article category could not be created.');
    $database = new PDO('sqlite:' . $databasePath);
    $categoryId = (int) $database->query("SELECT id FROM article_categories WHERE slug='estrategia'")->fetchColumn();
    assertAuthorizationHttpTest($categoryId > 0, 'Created article category was not persisted.');
    unset($database);
    [$status, $categoryPage] = authorizationHttpRequest($categoryLocation, 'GET', $adminCookie);
    $categoryCsrf = authorizationHttpCsrf($categoryPage);
    [$status, $duplicateCategoryResponse] = authorizationHttpRequest($baseUrl . '/admin/blog/categories', 'POST', $adminCookie, http_build_query([
        '_token' => $categoryCsrf, 'name' => 'Outra categoria', 'slug' => 'estrategia', 'description' => '',
    ]));
    assertAuthorizationHttpTest($status === 422, 'Duplicate category slug was accepted (HTTP ' . $status . '): ' . substr(strip_tags($duplicateCategoryResponse), 0, 240));

    [$status, $articleForm] = authorizationHttpRequest($baseUrl . '/admin/blog/new', 'GET', $adminCookie);
    assertAuthorizationHttpTest($status === 200 && str_contains($articleForm, 'data-moves-article-editor') && str_contains($articleForm, 'data-media-picker-cover') && str_contains($articleForm, 'data-media-picker-insert') && str_contains($articleForm, 'cms-assets/wysiwyg/js/app.js'), 'Moves Editor or media picker was not integrated into article creation.');
    $articleCsrf = authorizationHttpCsrf($articleForm);
    $articlePayload = [
        '_token' => $articleCsrf, 'title' => 'Artigo de teste XSS', 'slug' => 'artigo-teste-xss',
        'excerpt' => 'Resumo editorial seguro.', 'category_id' => $categoryId, 'cover_image' => '',
        'content' => '<h2>Conteúdo seguro</h2><p onclick="alert(1)">Permitido <strong>negrito</strong></p><script>alert(1)</script><img src="data:image/png;base64,AA=="><a href="javascript:alert(1)">perigoso</a>',
    ];
    [$status] = authorizationHttpRequest($baseUrl . '/admin/blog', 'POST', $adminCookie, http_build_query(array_replace($articlePayload, ['cover_image' => 'https://external.invalid/cover.jpg'])));
    assertAuthorizationHttpTest($status === 422, 'Article cover accepted an external image that is not authorized by the media library.');
    [$status] = authorizationHttpRequest($baseUrl . '/admin/blog', 'POST', $adminCookie, http_build_query(array_diff_key($articlePayload, ['_token' => true])));
    assertAuthorizationHttpTest($status === 403, 'Article creation without CSRF was accepted.');
    [$status, , , $articleLocation] = authorizationHttpRequest($baseUrl . '/admin/blog', 'POST', $adminCookie, http_build_query($articlePayload));
    assertAuthorizationHttpTest($status === 303 && preg_match('~/admin/blog/(\d+)/edit$~', $articleLocation, $articleIdMatch) === 1, 'Article creation did not save a draft.');
    $articleId = (int) $articleIdMatch[1];
    $database = new PDO('sqlite:' . $databasePath);
    $savedArticle = $database->query('SELECT * FROM articles WHERE id = ' . $articleId)->fetch(PDO::FETCH_ASSOC);
    assertAuthorizationHttpTest(is_array($savedArticle) && $savedArticle['status'] === 'draft' && str_contains($savedArticle['content'], '<strong>negrito</strong>'), 'Article draft or allowed content was not persisted.');
    foreach (['<script', 'onclick', '<img', 'data:image', 'javascript:'] as $unsafeMarkup) {
        assertAuthorizationHttpTest(!str_contains(strtolower($savedArticle['content']), $unsafeMarkup), 'Unsafe article markup was persisted: ' . $unsafeMarkup);
    }
    unset($database);
    [$status, $articleEdit] = authorizationHttpRequest($articleLocation, 'GET', $adminCookie);
    assertAuthorizationHttpTest($status === 200 && str_contains($articleEdit, 'Editar artigo'), 'Created article could not be edited.');
    $articleCsrf = authorizationHttpCsrf($articleEdit);
    [$status] = authorizationHttpRequest($baseUrl . '/admin/blog', 'POST', $adminCookie, http_build_query([
        '_token' => $articleCsrf, 'title' => 'Slug duplicado', 'slug' => 'artigo-teste-xss', 'excerpt' => 'Resumo', 'category_id' => $categoryId, 'content' => '<p>Teste</p>',
    ]));
    assertAuthorizationHttpTest($status === 422, 'Duplicate article slug was accepted.');
    [$status, , , $articleUpdatedLocation] = authorizationHttpRequest($baseUrl . '/admin/blog/' . $articleId, 'POST', $adminCookie, http_build_query([
        '_token' => $articleCsrf, 'title' => 'Artigo seguro publicado', 'slug' => 'artigo-teste-xss', 'excerpt' => 'Resumo atualizado.', 'category_id' => $categoryId, 'cover_image' => '', 'content' => '<p>Conteúdo <em>editorial</em>.</p>',
    ]));
    assertAuthorizationHttpTest($status === 303, 'Article update failed.');
    [$status, $articleEdit] = authorizationHttpRequest($articleUpdatedLocation, 'GET', $adminCookie);
    $articleCsrf = authorizationHttpCsrf($articleEdit);
    [$status] = authorizationHttpRequest($baseUrl . '/admin/blog/' . $articleId . '/publish', 'POST', $adminCookie, http_build_query(['_token' => $articleCsrf]));
    assertAuthorizationHttpTest($status === 303, 'Admin could not publish an article.');
    [$status, $publicBlog] = authorizationHttpRequest($baseUrl . '/blog');
    assertAuthorizationHttpTest($status === 200 && str_contains($publicBlog, 'Artigo seguro publicado') && str_contains($publicBlog, 'Resumo atualizado.') && str_contains($publicBlog, 'Estratégia'), 'Published article was missing from the public blog.');
    [$status, $publicArticle] = authorizationHttpRequest($baseUrl . '/blog/artigo-teste-xss');
    assertAuthorizationHttpTest($status === 200 && preg_match('/<div class="editorial-content">(.*?)<\/div>/s', $publicArticle, $articleContentMatch) === 1, 'Published article content container was not rendered.');
    $renderedArticle = strtolower($articleContentMatch[1]);
    assertAuthorizationHttpTest(str_contains($renderedArticle, '<em>editorial</em>'), 'Published article formatting was not preserved.');
    foreach (['<script', 'onclick', '<img', 'data:image', 'javascript:'] as $unsafeMarkup) {
        assertAuthorizationHttpTest(!str_contains($renderedArticle, $unsafeMarkup), 'Unsafe markup reached public article content: ' . $unsafeMarkup);
    }
    [$status, $publicCategory] = authorizationHttpRequest($baseUrl . '/blog/categoria/estrategia');
    assertAuthorizationHttpTest($status === 200 && str_contains($publicCategory, 'Artigo seguro publicado'), 'Published category page was unavailable.');
    [$status, $articleEdit] = authorizationHttpRequest($baseUrl . '/admin/blog/' . $articleId . '/edit', 'GET', $adminCookie);
    $articleCsrf = authorizationHttpCsrf($articleEdit);
    [$status] = authorizationHttpRequest($baseUrl . '/admin/blog/categories/' . $categoryId . '/delete', 'POST', $adminCookie, http_build_query(['_token' => $articleCsrf]));
    assertAuthorizationHttpTest($status === 409, 'Category with articles was deleted.');
    [$status] = authorizationHttpRequest($baseUrl . '/admin/blog/' . $articleId . '/delete', 'POST', $adminCookie, http_build_query(['_token' => $articleCsrf]));
    assertAuthorizationHttpTest($status === 403, 'Admin was allowed to delete an article without articles.delete.');
    [$status] = authorizationHttpRequest($baseUrl . '/admin/blog/' . $articleId . '/unpublish', 'POST', $adminCookie, http_build_query(['_token' => $articleCsrf]));
    assertAuthorizationHttpTest($status === 303, 'Admin could not unpublish an article.');
    [$status] = authorizationHttpRequest($baseUrl . '/blog/artigo-teste-xss');
    assertAuthorizationHttpTest($status === 404, 'Unpublished article remained public.');
    $database = new PDO('sqlite:' . $databasePath);
    $database->prepare("INSERT INTO articles (title, slug, excerpt, content, author_id, category_id, status, published_at) VALUES (?, ?, ?, ?, ?, ?, 'published', ?)")
        ->execute(['Artigo futuro', 'artigo-futuro', 'Resumo futuro', '<p>Futuro</p>', $adminId, $categoryId, '2999-01-01 00:00:00']);
    unset($database);
    [$status] = authorizationHttpRequest($baseUrl . '/blog/artigo-futuro');
    assertAuthorizationHttpTest($status === 404, 'Future article was publicly accessible.');
    [$status, $superadminLogin, $superadminCookie] = authorizationHttpRequest($baseUrl . '/login');
    $superadminLoginCsrf = authorizationHttpCsrf($superadminLogin);
    [$status, , $superadminCookie, $location] = authorizationHttpRequest($baseUrl . '/login', 'POST', $superadminCookie, http_build_query([
        '_token' => $superadminLoginCsrf, 'email' => 'http-superadmin@example.test', 'password' => 'test-password-superadmin',
    ]));
    assertAuthorizationHttpTest($status === 303, 'Superadmin fixture could not authenticate for article deletion.');
    [$status, $superadminEdit] = authorizationHttpRequest($baseUrl . '/admin/blog/' . $articleId . '/edit', 'GET', $superadminCookie);
    $superadminCsrf = authorizationHttpCsrf($superadminEdit);
    [$status] = authorizationHttpRequest($baseUrl . '/admin/blog/' . $articleId . '/delete', 'POST', $superadminCookie, http_build_query(['_token' => $superadminCsrf]));
    assertAuthorizationHttpTest($status === 303, 'Superadmin could not delete an article.');
    $database = new PDO('sqlite:' . $databasePath);
    $futureArticleId = (int) $database->query("SELECT id FROM articles WHERE slug='artigo-futuro'")->fetchColumn();
    unset($database);
    [$status, $superadminEdit] = authorizationHttpRequest($baseUrl . '/admin/blog/' . $futureArticleId . '/edit', 'GET', $superadminCookie);
    $superadminCsrf = authorizationHttpCsrf($superadminEdit);
    [$status] = authorizationHttpRequest($baseUrl . '/admin/blog/' . $futureArticleId . '/delete', 'POST', $superadminCookie, http_build_query(['_token' => $superadminCsrf]));
    assertAuthorizationHttpTest($status === 303, 'Superadmin could not clean up its isolated future-publication fixture.');
    [$status, $categoryPage] = authorizationHttpRequest($baseUrl . '/admin/blog/categories', 'GET', $adminCookie);
    $categoryCsrf = authorizationHttpCsrf($categoryPage);
    [$status] = authorizationHttpRequest($baseUrl . '/admin/blog/categories/' . $categoryId . '/delete', 'POST', $adminCookie, http_build_query(['_token' => $categoryCsrf]));
    assertAuthorizationHttpTest($status === 303, 'Unused article category could not be removed.');

    [$status, $iconCss] = authorizationHttpRequest($baseUrl . '/cms-assets/icons/css/moves-icons.css', 'GET', $adminCookie);
    assertAuthorizationHttpTest($status === 200 && str_contains($iconCss, '.icon-grid-outline:before'), 'Protected Moves Icons stylesheet did not load.');
    [$status] = authorizationHttpRequest($baseUrl . '/cms-assets/icons/fonts/moves-icons.woff2', 'GET', $adminCookie);
    assertAuthorizationHttpTest($status === 200, 'Protected Moves Icons font did not load.');
    [$status] = authorizationHttpRequest($baseUrl . '/cms-assets/wysiwyg/js/../../../../../../.env', 'GET', $adminCookie);
    assertAuthorizationHttpTest($status === 404, 'CMS asset allowlist permitted an unapproved path.');
    foreach ([
        '/admin/reports' => 'Relatórios', '/admin/notifications' => 'Notificações', '/admin/proposals' => 'Propostas',
        '/admin/settings' => 'Configurações', '/admin/versions' => 'Versões', '/admin/system-logs' => 'Log',
    ] as $path => $label) {
        [$status, $modulePage] = authorizationHttpRequest($baseUrl . $path, 'GET', $adminCookie);
        assertAuthorizationHttpTest($status === 200 && str_contains($modulePage, 'Funcionalidade pendente') && str_contains($modulePage, $label), 'CMS menu route did not render its honest placeholder: ' . $path);
    }
    [$status, $mediaPage] = authorizationHttpRequest($baseUrl . '/admin/media', 'GET', $adminCookie);
    assertAuthorizationHttpTest($status === 200 && str_contains($mediaPage, 'Biblioteca de mídia') && str_contains($mediaPage, 'Enviar imagem') && !str_contains($mediaPage, 'Funcionalidade pendente'), 'Media library did not render its management screen (HTTP ' . $status . ': ' . substr($mediaPage, 0, 240) . ').');
    assertAuthorizationHttpTest(str_contains($mediaPage, 'cms-default.min.css') && str_contains($mediaPage, 'cms-default.min.js'), 'Media library did not load CMS assets.');
    [$status, $pickerPage] = authorizationHttpRequest($baseUrl . '/admin/media/picker', 'GET', $adminCookie);
    assertAuthorizationHttpTest($status === 200 && str_contains($pickerPage, 'Selecionar imagem'), 'Reusable media picker did not render.');
    $mediaCsrf = authorizationHttpCsrf($mediaPage);
    $smallImage = imagecreatetruecolor(24, 24);
    $color = imagecolorallocate($smallImage, 40, 120, 80);
    imagefill($smallImage, 0, 0, $color);
    ob_start(); imagepng($smallImage); $validPng = (string) ob_get_clean(); unset($smallImage);
    [$status] = authorizationHttpMultipart($baseUrl . '/admin/media', $adminCookie, [
        '_token' => $mediaCsrf, 'title' => 'Imagem HTTP fixture', 'alt_text' => 'Imagem de teste isolado',
    ], 'valid.png', 'image/png', $validPng);
    assertAuthorizationHttpTest($status === 303, 'Authorized PNG upload failed with HTTP ' . $status . '.');
    $database = new PDO('sqlite:' . $databasePath);
    $mediaRow = $database->query("SELECT * FROM media WHERE title='Imagem HTTP fixture'")->fetch(PDO::FETCH_ASSOC);
    assertAuthorizationHttpTest(is_array($mediaRow) && $mediaRow['mime_type'] === 'image/png' && (int) $mediaRow['file_size'] > 0, 'Media metadata was not persisted accurately.');
    assertAuthorizationHttpTest(preg_match('~^moves-media/\d{4}/\d{2}/[a-z0-9-]+\.png$~D', $mediaRow['storage_path']) === 1, 'Stored media path did not use a generated, contained name.');
    unset($database);
    $mediaId = (int) $mediaRow['id'];
    $storedMediaPath = $mediaRoot . '/' . $mediaRow['storage_path'];
    assertAuthorizationHttpTest(is_file($storedMediaPath), 'Uploaded image was not stored outside the public directory.');
    [$status, $pickerWithMedia] = authorizationHttpRequest($baseUrl . '/admin/media/picker', 'GET', $adminCookie);
    assertAuthorizationHttpTest($status === 200 && str_contains($pickerWithMedia, 'data-media-public-url="' . $baseUrl . '/media/' . $mediaId . '"') && str_contains($pickerWithMedia, 'data-media-alt="Imagem de teste isolado"'), 'Media picker did not expose validated public URL and alt text.');
    [$status, $servedImage, , , $servedHeaders] = authorizationHttpRequest($baseUrl . '/admin/media/' . $mediaId . '/file', 'GET', $adminCookie);
    assertAuthorizationHttpTest($status === 200 && $servedImage === $validPng, 'Protected media file delivery failed.');
    $joinedHeaders = strtolower(implode("\n", $servedHeaders));
    assertAuthorizationHttpTest(str_contains($joinedHeaders, 'content-type: image/png') && str_contains($joinedHeaders, 'x-content-type-options: nosniff') && str_contains($joinedHeaders, 'cross-origin-resource-policy: same-origin') && str_contains($joinedHeaders, 'cache-control: private, no-store'), 'Protected media response security headers were incomplete.');
    [$status] = authorizationHttpRequest($baseUrl . '/media/' . $mediaId, 'GET');
    assertAuthorizationHttpTest($status === 404, 'Unreferenced media was delivered publicly.');

    [$status, $pageEdit] = authorizationHttpRequest($baseUrl . '/admin/pages/' . $pageId . '/edit', 'GET', $adminCookie);
    $pageCsrf = authorizationHttpCsrf($pageEdit);
    [$status] = authorizationHttpRequest($baseUrl . '/admin/pages/' . $pageId, 'POST', $adminCookie, http_build_query([
        '_token' => $pageCsrf, 'title' => 'Página inválida', 'slug' => 'pagina-xss-teste',
        'content' => '<img src="/admin/media/999999/file" alt="ausente">',
    ]));
    assertAuthorizationHttpTest($status === 422, 'Page accepted an image that does not exist in the media library.');
    $pageImageContent = '<p>Imagem editorial</p><figure><img src="/admin/media/' . $mediaId . '/file" alt="Imagem autorizada" onerror="alert(1)"></figure><img src="https://external.invalid/image.png"><img src="data:image/png;base64,AA==">';
    [$status, , , $pageEditLocation] = authorizationHttpRequest($baseUrl . '/admin/pages/' . $pageId, 'POST', $adminCookie, http_build_query([
        '_token' => $pageCsrf, 'title' => 'Página com mídia', 'slug' => 'pagina-xss-teste', 'content' => $pageImageContent,
    ]));
    assertAuthorizationHttpTest($status === 303, 'Page media selection could not be saved.');
    $database = new PDO('sqlite:' . $databasePath);
    $savedPageContent = (string) $database->query('SELECT content FROM pages WHERE id=' . $pageId)->fetchColumn();
    assertAuthorizationHttpTest(str_contains($savedPageContent, 'src="/media/' . $mediaId . '"') && str_contains($savedPageContent, 'alt="Imagem autorizada"'), 'Selected page media was not normalized to its public media URL.');
    foreach (['onerror', 'external.invalid', 'data:image'] as $unsafeImageInput) {
        assertAuthorizationHttpTest(!str_contains(strtolower($savedPageContent), strtolower($unsafeImageInput)), 'Unsafe page image input was persisted: ' . $unsafeImageInput);
    }
    unset($database);
    [$status, $pageEdit] = authorizationHttpRequest($pageEditLocation, 'GET', $adminCookie);
    $pageCsrf = authorizationHttpCsrf($pageEdit);
    [$status] = authorizationHttpRequest($baseUrl . '/admin/pages/' . $pageId . '/publish', 'POST', $adminCookie, http_build_query(['_token' => $pageCsrf]));
    assertAuthorizationHttpTest($status === 303, 'Page with selected media could not be published.');
    [$status, $publishedImagePage] = authorizationHttpRequest($baseUrl . '/pagina/pagina-xss-teste');
    assertAuthorizationHttpTest($status === 200 && str_contains($publishedImagePage, 'src="/media/' . $mediaId . '"') && str_contains($publishedImagePage, 'alt="Imagem autorizada"'), 'Published page did not render its authorized media.');
    [$status, $publicImage] = authorizationHttpRequest($baseUrl . '/media/' . $mediaId, 'GET');
    assertAuthorizationHttpTest($status === 200 && $publicImage === $validPng, 'Published page media was not delivered publicly.');

    $database = new PDO('sqlite:' . $databasePath);
    $database->prepare('INSERT INTO article_categories (name, slug, description) VALUES (?, ?, ?)')->execute(['Mídia de teste', 'midia-de-teste', 'Categoria isolada de teste.']);
    $mediaCategoryId = (int) $database->lastInsertId();
    unset($database);
    [$status, $mediaArticleForm] = authorizationHttpRequest($baseUrl . '/admin/blog/new', 'GET', $adminCookie);
    $articleCsrf = authorizationHttpCsrf($mediaArticleForm);
    [$status, , , $mediaArticleLocation] = authorizationHttpRequest($baseUrl . '/admin/blog', 'POST', $adminCookie, http_build_query([
        '_token' => $articleCsrf, 'title' => 'Artigo com imagem de teste', 'slug' => 'artigo-com-imagem-de-teste',
        'excerpt' => 'Artigo de validação de mídia.', 'category_id' => $mediaCategoryId, 'cover_image' => '',
        'content' => '<p>Conteúdo de teste.</p>',
    ]));
    assertAuthorizationHttpTest($status === 303 && preg_match('~/admin/blog/(\d+)/edit$~', $mediaArticleLocation, $mediaArticleIdMatch) === 1, 'Isolated article for media authorization could not be created.');
    $mediaArticleId = (int) $mediaArticleIdMatch[1];
    [$status, $articleEdit] = authorizationHttpRequest($baseUrl . '/admin/blog/' . $mediaArticleId . '/edit', 'GET', $adminCookie);
    $articleCsrf = authorizationHttpCsrf($articleEdit);
    [$status, , , $articleEditLocation] = authorizationHttpRequest($baseUrl . '/admin/blog/' . $mediaArticleId, 'POST', $adminCookie, http_build_query([
        '_token' => $articleCsrf, 'title' => 'Artigo com imagem de teste', 'slug' => 'artigo-com-imagem-de-teste',
        'excerpt' => 'Artigo de validação de mídia.', 'category_id' => $mediaCategoryId, 'cover_image' => '/media/' . $mediaId,
        'content' => '<p>Conteúdo editorial.</p><img src="/admin/media/' . $mediaId . '/file" alt="Imagem no artigo" onerror="alert(1)">',
    ]));
    assertAuthorizationHttpTest($status === 303, 'Article media cover selection could not be saved.');
    [$status, $articleEdit] = authorizationHttpRequest($articleEditLocation, 'GET', $adminCookie);
    $articleCsrf = authorizationHttpCsrf($articleEdit);
    [$status] = authorizationHttpRequest($baseUrl . '/admin/blog/' . $mediaArticleId . '/publish', 'POST', $adminCookie, http_build_query(['_token' => $articleCsrf]));
    assertAuthorizationHttpTest($status === 303, 'Article with selected media could not be published.');
    [$status, $publicArticleWithCover] = authorizationHttpRequest($baseUrl . '/blog/artigo-com-imagem-de-teste');
    assertAuthorizationHttpTest($status === 200 && str_contains($publicArticleWithCover, 'src="/media/' . $mediaId . '"') && str_contains($publicArticleWithCover, 'alt="Imagem no artigo"') && !str_contains($publicArticleWithCover, 'onerror'), 'Published article cover or editorial image did not render safely.');
    [$status] = authorizationHttpRequest($baseUrl . '/media/' . $mediaId, 'GET');
    assertAuthorizationHttpTest($status === 200, 'Published article cover did not authorize public media delivery.');
    [$status, $pageEdit] = authorizationHttpRequest($baseUrl . '/admin/pages/' . $pageId . '/edit', 'GET', $adminCookie);
    $pageCsrf = authorizationHttpCsrf($pageEdit);
    [$status] = authorizationHttpRequest($baseUrl . '/admin/pages/' . $pageId . '/unpublish', 'POST', $adminCookie, http_build_query(['_token' => $pageCsrf]));
    assertAuthorizationHttpTest($status === 303, 'Page with selected media could not be unpublished during article authorization test.');
    [$status, $superadminMediaDetail] = authorizationHttpRequest($baseUrl . '/admin/media/' . $mediaId . '/edit', 'GET', $superadminCookie);
    $mediaDeleteCsrf = authorizationHttpCsrf($superadminMediaDetail);
    [$status] = authorizationHttpRequest($baseUrl . '/admin/media/' . $mediaId . '/delete', 'POST', $superadminCookie, http_build_query(['_token' => $mediaDeleteCsrf]));
    assertAuthorizationHttpTest($status === 409, 'Media referenced by a published article cover could be deleted.');
    [$status, $articleEdit] = authorizationHttpRequest($baseUrl . '/admin/blog/' . $mediaArticleId . '/edit', 'GET', $adminCookie);
    $articleCsrf = authorizationHttpCsrf($articleEdit);
    [$status] = authorizationHttpRequest($baseUrl . '/admin/blog/' . $mediaArticleId, 'POST', $adminCookie, http_build_query([
        '_token' => $articleCsrf, 'title' => 'Artigo com imagem de teste', 'slug' => 'artigo-com-imagem-de-teste',
        'excerpt' => 'Artigo de validação de mídia.', 'category_id' => $mediaCategoryId, 'cover_image' => '', 'content' => '<p>Conteúdo editorial.</p>',
    ]));
    assertAuthorizationHttpTest($status === 303, 'Article cover could not be removed during test cleanup.');
    [$status] = authorizationHttpRequest($baseUrl . '/media/' . $mediaId, 'GET');
    assertAuthorizationHttpTest($status === 404, 'Unpublished page or unreferenced article kept media publicly available.');

    [$status] = authorizationHttpMultipart($baseUrl . '/admin/media', $adminCookie, ['_token' => 'invalid', 'title' => '', 'alt_text' => ''], 'csrf.png', 'image/png', $validPng);
    assertAuthorizationHttpTest($status === 403, 'Media upload accepted an invalid CSRF token.');
    [$status] = authorizationHttpMultipart($baseUrl . '/admin/media', $adminCookie, ['_token' => $mediaCsrf], 'invalid.png', 'image/png', 'not an image');
    assertAuthorizationHttpTest($status === 422, 'A non-image with an image filename passed MIME validation.');
    [$status] = authorizationHttpMultipart($baseUrl . '/admin/media', $adminCookie, ['_token' => $mediaCsrf], 'mismatch.jpg', 'image/jpeg', $validPng);
    assertAuthorizationHttpTest($status === 422, 'An image with an incompatible extension was accepted.');
    [$status] = authorizationHttpMultipart($baseUrl . '/admin/media', $adminCookie, ['_token' => $mediaCsrf], 'executable.php', 'image/png', $validPng);
    assertAuthorizationHttpTest($status === 422, 'An image with an executable extension was accepted.');
    $noisyImage = imagecreatetruecolor(128, 128);
    for ($y = 0; $y < 128; $y++) { for ($x = 0; $x < 128; $x++) { $pixel = imagecolorallocate($noisyImage, random_int(0, 255), random_int(0, 255), random_int(0, 255)); imagesetpixel($noisyImage, $x, $y, $pixel); } }
    ob_start(); imagepng($noisyImage); $largePng = (string) ob_get_clean(); unset($noisyImage);
    assertAuthorizationHttpTest(strlen($largePng) > 4096, 'Oversize fixture did not exceed configured test limit.');
    [$status] = authorizationHttpMultipart($baseUrl . '/admin/media', $adminCookie, ['_token' => $mediaCsrf], 'too-large.png', 'image/png', $largePng);
    assertAuthorizationHttpTest($status === 422, 'Image above configured size limit was accepted.');
    try { (new \Moves\Support\MediaStorage())->resolve('../etc/passwd'); $traversalRejected = false; } catch (RuntimeException) { $traversalRejected = true; }
    assertAuthorizationHttpTest($traversalRejected, 'Media storage resolver allowed path traversal.');
    [$status, $mediaDetail] = authorizationHttpRequest($baseUrl . '/admin/media/' . $mediaId . '/edit', 'GET', $adminCookie);
    assertAuthorizationHttpTest($status === 200 && str_contains($mediaDetail, 'valid.png') && str_contains($mediaDetail, 'Imagem HTTP fixture'), 'Uploaded media details were unavailable.');
    $mediaCsrf = authorizationHttpCsrf($mediaDetail);
    [$status] = authorizationHttpRequest($baseUrl . '/admin/media/' . $mediaId, 'POST', $adminCookie, http_build_query([
        '_token' => $mediaCsrf, 'title' => 'Título alterado', 'alt_text' => 'Alt alterado',
    ]));
    assertAuthorizationHttpTest($status === 303, 'Authorized media description update failed.');
    $database = new PDO('sqlite:' . $databasePath);
    $updatedMedia = $database->query('SELECT title, alt_text FROM media WHERE id=' . $mediaId)->fetch(PDO::FETCH_ASSOC);
    assertAuthorizationHttpTest(is_array($updatedMedia) && $updatedMedia['title'] === 'Título alterado' && $updatedMedia['alt_text'] === 'Alt alterado', 'Media description update was not persisted.');
    unset($database);
    [$status] = authorizationHttpRequest($baseUrl . '/admin/media/' . $mediaId . '/delete', 'POST', $adminCookie, http_build_query(['_token' => $mediaCsrf]));
    assertAuthorizationHttpTest($status === 403, 'Admin without media.delete could delete an image.');
    [$status, $superadminMediaDetail] = authorizationHttpRequest($baseUrl . '/admin/media/' . $mediaId . '/edit', 'GET', $superadminCookie);
    assertAuthorizationHttpTest($status === 200, 'Superadmin could not view media details.');
    $superadminMediaCsrf = authorizationHttpCsrf($superadminMediaDetail);
    [$status] = authorizationHttpRequest($baseUrl . '/admin/media/' . $mediaId . '/delete', 'POST', $superadminCookie, http_build_query(['_token' => 'invalid']));
    assertAuthorizationHttpTest($status === 403 && is_file($storedMediaPath), 'Media deletion accepted invalid CSRF or removed the file.');
    $database = new PDO('sqlite:' . $databasePath);
    $database->prepare("UPDATE pages SET content=? WHERE slug='pagina-xss-teste'")->execute(['<p><a href="/admin/media/' . $mediaId . '/file">imagem</a></p>']);
    unset($database);
    [$status] = authorizationHttpRequest($baseUrl . '/admin/media/' . $mediaId . '/delete', 'POST', $superadminCookie, http_build_query(['_token' => $superadminMediaCsrf]));
    assertAuthorizationHttpTest($status === 409 && is_file($storedMediaPath), 'Associated media was deleted despite a content reference (HTTP ' . $status . ').');

    [$status, $slidesPage] = authorizationHttpRequest($baseUrl . '/admin/slides', 'GET', $adminCookie);
    assertAuthorizationHttpTest($status === 200 && str_contains($slidesPage, 'Lista de destaques') && !str_contains($slidesPage, 'Funcionalidade pendente'), 'Slides management screen did not render.');
    [$status, $slideForm] = authorizationHttpRequest($baseUrl . '/admin/slides/new', 'GET', $adminCookie);
    assertAuthorizationHttpTest($status === 200, 'Authorized slide form did not render.');
    $slideCsrf = authorizationHttpCsrf($slideForm);
    [$status] = authorizationHttpRequest($baseUrl . '/admin/slides', 'POST', $adminCookie, http_build_query([
        '_token' => $slideCsrf, 'title' => 'Destaque de teste', 'subtitle' => 'Publicação de teste',
        'image_media_id' => $mediaId, 'button_text' => 'Contato', 'button_url' => '/contato',
        'sort_order' => 2, 'status' => 'active', 'published_at' => '',
    ]));
    assertAuthorizationHttpTest($status === 303, 'Slide CRUD create failed with HTTP ' . $status . '.');
    $database = new PDO('sqlite:' . $databasePath);
    $slideId = (int) $database->query("SELECT id FROM slides WHERE title='Destaque de teste'")->fetchColumn();
    assertAuthorizationHttpTest($slideId > 0, 'Slide was not persisted.');
    unset($database);
    [$status, $publicImage, , , $publicImageHeaders] = authorizationHttpRequest($baseUrl . '/media/' . $mediaId, 'GET');
    assertAuthorizationHttpTest($status === 200 && $publicImage === $validPng && str_contains(strtolower(implode("\n", $publicImageHeaders)), 'cache-control: public'), 'Published slide image was not safely delivered publicly.');
    [$status] = authorizationHttpRequest($baseUrl . '/admin/slides/' . $slideId, 'POST', $adminCookie, http_build_query([
        '_token' => $slideCsrf, 'title' => 'Link inválido', 'button_url' => 'javascript:alert(1)', 'status' => 'active',
        'sort_order' => 0,
    ]));
    assertAuthorizationHttpTest($status === 422, 'Slide accepted a javascript: button URL.');
    [$status, $slideEdit] = authorizationHttpRequest($baseUrl . '/admin/slides/' . $slideId . '/edit', 'GET', $adminCookie);
    $slideCsrf = authorizationHttpCsrf($slideEdit);
    [$status] = authorizationHttpRequest($baseUrl . '/admin/slides/' . $slideId, 'POST', $adminCookie, http_build_query([
        '_token' => $slideCsrf, 'title' => 'Destaque de teste', 'subtitle' => 'Atualizado', 'image_media_id' => $mediaId,
        'button_text' => 'Contato', 'button_url' => '/contato', 'sort_order' => 1, 'status' => 'inactive', 'published_at' => '',
    ]));
    assertAuthorizationHttpTest($status === 303, 'Slide update/status change failed.');
    [$status] = authorizationHttpRequest($baseUrl . '/media/' . $mediaId, 'GET');
    assertAuthorizationHttpTest($status === 404, 'Inactive slide image remained publicly available.');
    [$status, $slideEdit] = authorizationHttpRequest($baseUrl . '/admin/slides/' . $slideId . '/edit', 'GET', $superadminCookie);
    $slideCsrf = authorizationHttpCsrf($slideEdit);
    [$status] = authorizationHttpRequest($baseUrl . '/admin/slides/' . $slideId . '/delete', 'POST', $superadminCookie, http_build_query(['_token' => $slideCsrf]));
    assertAuthorizationHttpTest($status === 303, 'Slide deletion failed.');

    [$status, $testimonialForm] = authorizationHttpRequest($baseUrl . '/admin/testimonials/new', 'GET', $adminCookie);
    $testimonialCsrf = authorizationHttpCsrf($testimonialForm);
    [$status] = authorizationHttpRequest($baseUrl . '/admin/testimonials', 'POST', $adminCookie, http_build_query([
        '_token' => $testimonialCsrf, 'name' => 'Pessoa de teste', 'identification' => 'Cliente', 'company' => 'Moves',
        'testimonial' => '<p>Ótimo serviço.</p><script>alert(1)</script><img src=x onerror=alert(2)>',
        'photo_media_id' => $mediaId, 'sort_order' => 4, 'status' => 'published', 'published_at' => '',
    ]));
    assertAuthorizationHttpTest($status === 303, 'Testimonial CRUD create failed.');
    $database = new PDO('sqlite:' . $databasePath);
    $testimonial = $database->query("SELECT * FROM testimonials WHERE name='Pessoa de teste'")->fetch(PDO::FETCH_ASSOC);
    assertAuthorizationHttpTest(is_array($testimonial) && !str_contains(strtolower($testimonial['testimonial']), '<script') && !str_contains(strtolower($testimonial['testimonial']), 'onerror'), 'Testimonial HTML was not sanitized.');
    $testimonialId = (int) $testimonial['id'];
    unset($database);
    [$status] = authorizationHttpRequest($baseUrl . '/admin/media/' . $mediaId . '/delete', 'POST', $superadminCookie, http_build_query(['_token' => $superadminMediaCsrf]));
    assertAuthorizationHttpTest($status === 409, 'Referenced testimonial photo could be deleted.');
    [$status, $testimonialEdit] = authorizationHttpRequest($baseUrl . '/admin/testimonials/' . $testimonialId . '/edit', 'GET', $superadminCookie);
    $testimonialCsrf = authorizationHttpCsrf($testimonialEdit);
    [$status] = authorizationHttpRequest($baseUrl . '/admin/testimonials/' . $testimonialId . '/delete', 'POST', $superadminCookie, http_build_query(['_token' => $testimonialCsrf]));
    assertAuthorizationHttpTest($status === 303, 'Testimonial deletion failed.');

    [$status, $faqForm] = authorizationHttpRequest($baseUrl . '/admin/faqs/new', 'GET', $adminCookie);
    assertAuthorizationHttpTest($status === 200, 'Authorized FAQ form did not render.');
    $faqCsrf = authorizationHttpCsrf($faqForm);
    [$status] = authorizationHttpRequest($baseUrl . '/admin/faqs', 'POST', $adminCookie, http_build_query([
        '_token' => $faqCsrf, 'question' => 'Como funciona?', 'answer' => '<p>Resposta segura.</p><script>alert(1)</script><a href="javascript:alert(2)">link</a>',
        'category' => 'Geral', 'sort_order' => 1, 'status' => 'active',
    ]));
    assertAuthorizationHttpTest($status === 303, 'FAQ CRUD create failed.');
    $database = new PDO('sqlite:' . $databasePath);
    $faqId = (int) $database->query("SELECT id FROM faqs WHERE question='Como funciona?'")->fetchColumn();
    $faqAnswer = (string) $database->query('SELECT answer FROM faqs WHERE id=' . $faqId)->fetchColumn();
    assertAuthorizationHttpTest($faqId > 0 && !str_contains(strtolower($faqAnswer), '<script') && !str_contains(strtolower($faqAnswer), 'javascript:'), 'FAQ HTML was not sanitized.');
    unset($database);
    [$status, $faqPublic] = authorizationHttpRequest($baseUrl . '/faq', 'GET');
    preg_match('/<section class="section-shell page-section faq-public".*?<\/section>\s*<\/section>/s', $faqPublic, $faqSectionMatch);
    $publicFaqMarkup = $faqSectionMatch[0] ?? '';
    assertAuthorizationHttpTest($status === 200 && str_contains($publicFaqMarkup, 'Como funciona?') && str_contains($publicFaqMarkup, 'Resposta segura.') && !str_contains(strtolower($publicFaqMarkup), '<script'), 'Public FAQ page did not render safe active answers (HTTP ' . $status . ').');
    [$status] = authorizationHttpRequest($baseUrl . '/admin/faqs', 'POST', $adminCookie, http_build_query(['_token' => 'invalid', 'question' => 'CSRF', 'answer' => 'sem token']));
    assertAuthorizationHttpTest($status === 403, 'FAQ write accepted invalid CSRF (HTTP ' . $status . ').');
    [$status, $faqEdit] = authorizationHttpRequest($baseUrl . '/admin/faqs/' . $faqId . '/edit', 'GET', $superadminCookie);
    $faqCsrf = authorizationHttpCsrf($faqEdit);
    [$status] = authorizationHttpRequest($baseUrl . '/admin/faqs/' . $faqId, 'POST', $superadminCookie, http_build_query([
        '_token' => $faqCsrf, 'question' => 'Como funciona?', 'answer' => '<p>Resposta segura.</p>', 'category' => 'Geral', 'sort_order' => 0, 'status' => 'inactive',
    ]));
    assertAuthorizationHttpTest($status === 303, 'FAQ update/status change failed.');
    [$status, $faqPublic] = authorizationHttpRequest($baseUrl . '/faq', 'GET');
    assertAuthorizationHttpTest($status === 200 && !str_contains($faqPublic, 'Como funciona?'), 'Inactive FAQ remained public.');
    [$status] = authorizationHttpRequest($baseUrl . '/admin/faqs/' . $faqId . '/delete', 'POST', $superadminCookie, http_build_query(['_token' => $faqCsrf]));
    assertAuthorizationHttpTest($status === 303, 'FAQ deletion failed.');

    $database = new PDO('sqlite:' . $databasePath);
    $database->prepare("UPDATE pages SET content='<p>Conteúdo sem referência</p>' WHERE slug='pagina-xss-teste'")->execute();
    unset($database);
    [$status] = authorizationHttpRequest($baseUrl . '/admin/media/' . $mediaId . '/delete', 'POST', $superadminCookie, http_build_query(['_token' => $superadminMediaCsrf]));
    assertAuthorizationHttpTest($status === 303, 'Superadmin could not delete an unreferenced media item.');
    $database = new PDO('sqlite:' . $databasePath);
    assertAuthorizationHttpTest((int) $database->query('SELECT COUNT(*) FROM media WHERE id=' . $mediaId)->fetchColumn() === 0, 'Deleted media metadata remained in the database.');
    unset($database);
    assertAuthorizationHttpTest(!file_exists($storedMediaPath), 'Deleted media file remained in private storage.');
    foreach (['Dashboard', 'Relatórios', 'Notificações', 'Propostas', 'Páginas', 'Artigos', 'Editor', 'Mídia', 'Destaques', 'Depoimentos', 'FAQ', 'Usuários', 'Configurações', 'Versões', 'Log', 'Visualizar site'] as $menuLabel) {
        assertAuthorizationHttpTest(str_contains($adminPage, $menuLabel), 'CMS navigation is missing menu item: ' . $menuLabel);
    }

    [$status, $superadminEdit] = authorizationHttpRequest($baseUrl . '/admin/pages/' . $pageId . '/edit', 'GET', $superadminCookie);
    $csrf = authorizationHttpCsrf($superadminEdit);
    [$status, , , $location] = authorizationHttpRequest($baseUrl . '/admin/pages/' . $pageId . '/delete', 'POST', $superadminCookie, http_build_query(['_token' => $csrf]));
    assertAuthorizationHttpTest($status === 303, 'Superadmin could not delete a page.');

    [$status, $userList] = authorizationHttpRequest($baseUrl . '/admin/users', 'GET', $adminCookie);
    assertAuthorizationHttpTest($status === 200 && str_contains($userList, 'HTTP Admin Fixture'), 'Authorized user-management listing failed.');
    [$status, $createPage] = authorizationHttpRequest($baseUrl . '/admin/users/new', 'GET', $adminCookie);
    assertAuthorizationHttpTest($status === 200, 'Authorized user creation form failed.');
    $createCsrf = authorizationHttpCsrf($createPage);
    [$status, , , $createdLocation] = authorizationHttpRequest($baseUrl . '/admin/users', 'POST', $adminCookie, http_build_query([
        '_token' => $createCsrf, 'name' => 'Created by HTTP', 'email' => 'created-http@example.test',
        'password' => 'test-password-created', 'role' => 'superadmin',
    ]));
    assertAuthorizationHttpTest($status === 303 && str_contains($createdLocation, '/admin/users/'), 'Authorized user creation failed.');
    [$status, $createdProfile] = authorizationHttpRequest($createdLocation, 'GET', $adminCookie);
    assertAuthorizationHttpTest($status === 200 && str_contains($createdProfile, 'Usuário'), 'Admin escalated a new account through a forged role field.');
    [$status] = authorizationHttpRequest($baseUrl . '/admin/users', 'POST', $adminCookie, http_build_query([
        '_token' => $createCsrf, 'name' => 'Duplicate', 'email' => 'created-http@example.test', 'password' => 'test-password-duplicate',
    ]));
    assertAuthorizationHttpTest($status === 422, 'Duplicate email was not rejected.');

    [$status, $profileSecurity] = authorizationHttpRequest($baseUrl . '/profile?tab=security', 'GET', $adminCookie);
    assertAuthorizationHttpTest($status === 200, 'Personal security profile was unavailable.');
    $csrf = authorizationHttpCsrf($profileSecurity);
    [$status, , $setupCookie, $location] = authorizationHttpRequest($baseUrl . '/profile/two-factor/setup', 'POST', $adminCookie, http_build_query([
        '_token' => $csrf, 'current_password' => 'test-password-admin',
    ]));
    if ($setupCookie !== '') { $adminCookie = $setupCookie; }
    assertAuthorizationHttpTest($status === 303 && str_contains($location, 'profile?tab=security'), 'Password-confirmed TOTP setup did not begin.');
    [$status, $setupPage] = authorizationHttpRequest($location, 'GET', $adminCookie);
    assertAuthorizationHttpTest($status === 200, 'TOTP setup view failed with HTTP ' . $status . ' at ' . $location . '.');
    assertAuthorizationHttpTest(str_contains($setupPage, 'data:image/svg+xml;base64,'), 'Local QR generation did not render the expected embedded SVG.');
    assertAuthorizationHttpTest(preg_match('/class="manual-key">([A-Z2-7]+)<\/code>/', $setupPage, $manualMatch) === 1, 'Manual TOTP key was unavailable in the setup view.');
    $setupSecret = $manualMatch[1];
    $csrf = authorizationHttpCsrf($setupPage);
    [$status, , $adminCookie] = authorizationHttpRequest($baseUrl . '/profile/two-factor/confirm', 'POST', $adminCookie, http_build_query([
        '_token' => $csrf, 'code' => authorizationHttpTotp($setupSecret),
    ]));
    assertAuthorizationHttpTest($status === 303, 'Valid code did not activate TOTP.');
    [$status, $recoveryPage] = authorizationHttpRequest($baseUrl . '/profile?tab=security', 'GET', $adminCookie);
    assertAuthorizationHttpTest($status === 200 && str_contains($recoveryPage, 'Guarde seus códigos de recuperação'), 'Recovery codes were not presented after activation.');
    assertAuthorizationHttpTest(preg_match_all('/<code>([A-Z0-9]+)<\/code>/', $recoveryPage) === 8, 'Recovery codes were not shown exactly once as a set of eight.');
    $csrf = authorizationHttpCsrf($recoveryPage);
    [$status, $nextSecurityPage] = authorizationHttpRequest($baseUrl . '/profile?tab=security', 'GET', $adminCookie);
    assertAuthorizationHttpTest($status === 200 && !str_contains($nextSecurityPage, 'Guarde seus códigos de recuperação'), 'Recovery codes were shown again after the one-time display.');
    [$status, , $adminCookie] = authorizationHttpRequest($baseUrl . '/profile/two-factor/disable', 'POST', $adminCookie, http_build_query([
        '_token' => $csrf, 'current_password' => 'test-password-admin', 'code' => authorizationHttpTotp($setupSecret, intdiv(time(), 30) + 1),
    ]));
    assertAuthorizationHttpTest($status === 303, 'Password and valid second factor did not disable TOTP.');

    [$status, $adminPage] = authorizationHttpRequest($baseUrl . '/admin', 'GET', $adminCookie);
    assertAuthorizationHttpTest($status === 200, 'Admin session did not persist after TOTP disable.');
    $logoutCsrf = authorizationHttpCsrf($adminPage);
    [$status, , $cookie, $location] = authorizationHttpRequest(
        $baseUrl . '/logout',
        'POST',
        $adminCookie,
        http_build_query(['_token' => $logoutCsrf])
    );
    assertAuthorizationHttpTest($status === 303 && str_ends_with($location, '/login'), 'HTTP logout did not complete.');
    [$status] = authorizationHttpRequest($baseUrl . '/admin', 'GET', $cookie);
    assertAuthorizationHttpTest($status === 303, 'HTTP /admin remained accessible after logout.');
    [$status] = authorizationHttpRequest($baseUrl . '/admin', 'GET', $adminCookie);
    assertAuthorizationHttpTest($status === 303, 'The pre-logout session remained valid.');

    [$status, $loginPage, $userCookie] = authorizationHttpRequest($baseUrl . '/login');
    assertAuthorizationHttpTest($status === 200, 'Could not open the login form for a standard user.');
    $csrf = authorizationHttpCsrf($loginPage);
    [$status, , $userCookie, $location] = authorizationHttpRequest(
        $baseUrl . '/login',
        'POST',
        $userCookie,
        http_build_query(['_token' => $csrf, 'email' => 'http-user@example.test', 'password' => 'test-password-user'])
    );
    assertAuthorizationHttpTest($status === 303 && rtrim($location, '/') === $baseUrl, 'Standard user login did not remain outside admin.');
    [$status, , , $location] = authorizationHttpRequest($baseUrl . '/admin', 'GET', $userCookie);
    assertAuthorizationHttpTest($status === 403, 'Standard user was not denied /admin.');
    foreach (['/admin/reports', '/admin/notifications', '/admin/proposals', '/admin/pages', '/admin/blog', '/admin/media', '/admin/media/picker', '/admin/slides', '/admin/testimonials', '/admin/faqs', '/admin/users', '/admin/settings', '/admin/versions', '/admin/system-logs', '/admin/editor'] as $path) {
        [$status] = authorizationHttpRequest($baseUrl . $path, 'GET', $userCookie);
        assertAuthorizationHttpTest($status === 403, 'Standard user was not denied CMS route ' . $path . '.');
    }
    [$status] = authorizationHttpRequest($baseUrl . '/cms-assets/wysiwyg/js/app.js', 'GET', $userCookie);
    assertAuthorizationHttpTest($status === 403, 'Standard user was not denied protected CMS assets.');
    [$status] = authorizationHttpMultipart($baseUrl . '/admin/media', $userCookie, ['_token' => 'invalid'], 'unauthorized.png', 'image/png', $validPng);
    assertAuthorizationHttpTest($status === 403, 'Standard user could upload media without authorization.');
    [$status] = authorizationHttpRequest($baseUrl . '/admin/media/' . $mediaId . '/file', 'GET', $userCookie);
    assertAuthorizationHttpTest($status === 403, 'Standard user could access protected media delivery.');

    [$status, $twoFactorPage, $twoFactorCookie] = authorizationHttpRequest($baseUrl . '/login');
    $csrf = authorizationHttpCsrf($twoFactorPage);
    [$status, , $twoFactorCookie, $location] = authorizationHttpRequest($baseUrl . '/login', 'POST', $twoFactorCookie, http_build_query([
        '_token' => $csrf, 'email' => 'http-2fa@example.test', 'password' => 'test-password-2fa',
    ]));
    assertAuthorizationHttpTest($status === 303 && str_ends_with($location, '/login/2fa'), 'Password login did not enter the pending 2FA stage.');
    [$status, $twoFactorPage] = authorizationHttpRequest($baseUrl . '/login/2fa', 'GET', $twoFactorCookie);
    assertAuthorizationHttpTest($status === 200, 'Pending 2FA form was unavailable.');
    [$status] = authorizationHttpRequest($baseUrl . '/admin', 'GET', $twoFactorCookie);
    assertAuthorizationHttpTest($status === 303, 'Pending 2FA state accessed /admin.');
    $csrf = authorizationHttpCsrf($twoFactorPage);
    [$status, , $twoFactorCookie, $location] = authorizationHttpRequest($baseUrl . '/login/2fa', 'POST', $twoFactorCookie, http_build_query([
        '_token' => $csrf, 'code' => authorizationHttpTotp($testTotpSecret),
    ]));
    assertAuthorizationHttpTest($status === 303 && str_ends_with($location, '/admin'), 'Valid TOTP did not complete HTTP authentication.');
    [$status, $twoFactorAdmin] = authorizationHttpRequest($baseUrl . '/admin', 'GET', $twoFactorCookie);
    assertAuthorizationHttpTest($status === 200 && str_contains($twoFactorAdmin, 'HTTP 2FA Fixture'), 'Completed 2FA session could not access /admin.');

    echo "Authorization HTTP tests passed; page/article/category CRUD, CSRF, RBAC, slug uniqueness, publication, future-date denial and XSS sanitization passed alongside user management, 2FA, session/logout.\n";
} finally {
    proc_terminate($server);
    foreach ($pipes as $pipe) {
        if (is_resource($pipe)) {
            fclose($pipe);
        }
    }
    proc_close($server);
    @unlink($databasePath);
    @unlink($databasePath . '-journal');
    @unlink($databasePath . '-wal');
    @unlink($databasePath . '-shm');
    $mediaFiles = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($mediaRoot, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($mediaFiles as $mediaFile) { $mediaFile->isDir() ? rmdir($mediaFile->getPathname()) : unlink($mediaFile->getPathname()); }
    rmdir($mediaRoot);
}
