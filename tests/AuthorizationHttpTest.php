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

    return [$status, is_string($content) ? $content : '', $cookieHeader, $location];
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
    assertAuthorizationHttpTest($status === 200 && str_contains($pageForm, 'data-moves-page-editor') && str_contains($pageForm, 'cms-assets/wysiwyg/js/app.js'), 'Moves Editor was not integrated into page creation.');
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
    assertAuthorizationHttpTest($status === 200 && str_contains($articleForm, 'data-moves-article-editor') && str_contains($articleForm, 'cms-assets/wysiwyg/js/app.js'), 'Moves Editor was not integrated into article creation.');
    $articleCsrf = authorizationHttpCsrf($articleForm);
    $articlePayload = [
        '_token' => $articleCsrf, 'title' => 'Artigo de teste XSS', 'slug' => 'artigo-teste-xss',
        'excerpt' => 'Resumo editorial seguro.', 'category_id' => $categoryId, 'cover_image' => '',
        'content' => '<h2>Conteúdo seguro</h2><p onclick="alert(1)">Permitido <strong>negrito</strong></p><script>alert(1)</script><img src="data:image/png;base64,AA=="><a href="javascript:alert(1)">perigoso</a>',
    ];
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
        '/admin/media' => 'Mídia',
        '/admin/slides' => 'Destaques', '/admin/testimonials' => 'Depoimentos', '/admin/faqs' => 'FAQ',
        '/admin/settings' => 'Configurações', '/admin/versions' => 'Versões', '/admin/system-logs' => 'Log',
    ] as $path => $label) {
        [$status, $modulePage] = authorizationHttpRequest($baseUrl . $path, 'GET', $adminCookie);
        assertAuthorizationHttpTest($status === 200 && str_contains($modulePage, 'Funcionalidade pendente') && str_contains($modulePage, $label), 'CMS menu route did not render its honest placeholder: ' . $path);
    }
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
    foreach (['/admin/reports', '/admin/notifications', '/admin/proposals', '/admin/pages', '/admin/blog', '/admin/media', '/admin/slides', '/admin/testimonials', '/admin/faqs', '/admin/users', '/admin/settings', '/admin/versions', '/admin/system-logs', '/admin/editor'] as $path) {
        [$status] = authorizationHttpRequest($baseUrl . $path, 'GET', $userCookie);
        assertAuthorizationHttpTest($status === 403, 'Standard user was not denied CMS route ' . $path . '.');
    }
    [$status] = authorizationHttpRequest($baseUrl . '/cms-assets/wysiwyg/js/app.js', 'GET', $userCookie);
    assertAuthorizationHttpTest($status === 403, 'Standard user was not denied protected CMS assets.');

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
}
