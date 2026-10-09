<?php

/**
 * Moves Code | CMS Reports Controller
 *
 * Exibe métricas agregadas e verificáveis do CMS.
 *
 * @author Djalma Martins
 * @package Moves\Themes\Cms\DefaultV1\Controllers
 */

namespace Moves\Themes\Cms\DefaultV1\Controllers;

use Moves\Core\Controller;
use Moves\Core\Session;
use Moves\Models\Auth;
use Moves\Models\CmsReports;
use Moves\Models\CmsNotifications;
use Moves\Models\User;
use MovesCode\Router\Router;
use Throwable;

class Reports extends Controller
{
    private Session $session;
    private Auth $auth;

    public function __construct(?Router $router = null)
    {
        parent::__construct(dirname(__DIR__));
        $this->session = new Session();
        $this->auth = new Auth($this->session);
    }

    public function index(): void
    {
        if (!$this->guard()) { return; }
        [$from, $to] = $this->dateRange();
        try {
            $reports = new CmsReports();
            $this->screen(['view' => 'reports', 'summary' => $reports->summary($from, $to), 'publications' => $reports->publicationSeries($from, $to), 'from' => $from, 'to' => $to]);
        } catch (Throwable) { $this->unavailable(); }
    }

    public function export(): void
    {
        if (!$this->guard()) { return; }
        [$from, $to] = $this->dateRange();
        try { $summary = (new CmsReports())->summary($from, $to); } catch (Throwable) { $this->unavailable(); return; }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="moves-cms-relatorio.csv"');
        header('Cache-Control: no-store');
        $output = fopen('php://output', 'wb');
        if ($output === false) { $this->unavailable(); return; }
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, ['Indicador', 'Quantidade'], ';', '"', '\\');
        foreach ($summary as $key => $value) {
            $label = match ($key) {
                'users' => 'Usuários cadastrados', 'active_users' => 'Usuários ativos',
                'pages' => 'Páginas cadastradas', 'published_pages' => 'Páginas publicadas',
                'articles' => 'Artigos cadastrados', 'published_articles' => 'Artigos publicados',
                'categories' => 'Categorias de artigos', 'media' => 'Arquivos de mídia', default => $key,
            };
            fputcsv($output, [$label, (string) $value], ';', '"', '\\');
        }
        fclose($output);
    }

    /** @return array{0:?string,1:?string} */
    private function dateRange(): array
    {
        $from = is_string($_GET['from'] ?? null) ? $_GET['from'] : '';
        $to = is_string($_GET['to'] ?? null) ? $_GET['to'] : '';
        $valid = static fn (string $date): bool => $date === '' || (preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date) === 1 && \DateTimeImmutable::createFromFormat('!Y-m-d', $date)?->format('Y-m-d') === $date);
        if (!$valid($from)) { $from = ''; }
        if (!$valid($to)) { $to = ''; }
        if ($from !== '' && $to !== '' && $from > $to) { [$from, $to] = [$to, $from]; }
        return [$from === '' ? null : $from, $to === '' ? null : $to];
    }

    private function guard(): bool
    {
        try { $user = $this->auth->user(); } catch (Throwable) { $this->unavailable(); return false; }
        if (!$user instanceof User) { header('Location: ' . url('login'), true, 303); return false; }
        if (!$this->auth->can('reports.view')) { http_response_code(403); echo 'Acesso negado'; return false; }
        return true;
    }

    private function screen(array $data): void
    {
        $actor = $this->auth->user();
        http_response_code(200); header('Cache-Control: no-store'); header('Content-Type: text/html; charset=utf-8');
        $data += $actor instanceof User && $this->auth->can('notifications.view')
            ? (new CmsNotifications())->headerContext((int) $actor->id)
            : ['unreadCount' => 0, 'preview' => []];
        echo $this->view->render('screen', $data + [
            'csrf' => $this->session->csrfToken(), 'actor' => $actor?->publicData(),
            'canManageUsers' => $this->auth->can('users.manage'), 'canManageRoles' => $this->auth->can('roles.manage'),
            'canAccessAdmin' => $this->auth->can('admin.access'), 'currentPath' => '/admin/reports',
            'permissions' => ['reports' => ['view' => $this->auth->can('reports.view')], 'notifications' => ['view' => $this->auth->can('notifications.view')]],
            'flash' => null, 'errors' => [],
        ]);
    }

    private function unavailable(): void { http_response_code(503); header('Cache-Control: no-store'); echo 'Serviço temporariamente indisponível'; }
}
