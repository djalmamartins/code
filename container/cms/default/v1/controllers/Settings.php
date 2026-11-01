<?php

/**
 * Moves Code | CMS Settings Controller
 *
 * Valida e gerencia as configurações públicas permitidas do site.
 *
 * @author Djalma Martins
 * @package Moves\Themes\Cms\DefaultV1\Controllers
 */

namespace Moves\Themes\Cms\DefaultV1\Controllers;

use Moves\Core\Controller;
use Moves\Core\Session;
use Moves\Models\Auth;
use Moves\Models\CmsSettings;
use Moves\Models\CmsNotifications;
use Moves\Models\MediaItem;
use Moves\Models\User;
use MovesCode\Model\Message;
use MovesCode\Router\Router;
use Throwable;

class Settings extends Controller
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
        try {
            $settings = (new CmsSettings())->all();
            $media = (new MediaItem())->search('', 'all', 200, 0);
            $tab = $this->tab();
            $this->screen('settings', [
                'settings' => $settings,
                'mediaOptions' => $media,
                'tab' => $tab,
                'mailSender' => [
                    'name' => (string) (getenv('MAIL_FROM_NAME') ?: (defined('APP_NAME') ? APP_NAME : 'Moves')),
                    'address' => (string) (getenv('MAIL_FROM_ADDRESS') ?: ''),
                    'configured' => trim((string) getenv('MAIL_HOST')) !== '',
                ],
            ]);
        } catch (Throwable) { $this->unavailable(); }
    }

    public function update(): void
    {
        if (!$this->guard()) { return; }
        if (!$this->session->validateCsrf($this->postString('_token'))) { $this->forbidden(); return; }
        $tab = $this->tab();
        try { $settings = (new CmsSettings())->all(); } catch (Throwable) { $this->unavailable(); return; }
        $defaults = ['site_name' => 'Moves', 'site_tagline' => 'Tecnologia. Pessoas. Resultados. Sempre em movimento.', 'contact_email' => '', 'contact_phone' => '', 'contact_address' => '', 'meta_description' => '', 'logo_media_id' => ''];
        $settings = array_replace($defaults, $settings);
        $fields = match ($tab) {
            'general' => ['site_name', 'site_tagline'],
            'brand' => ['logo_media_id'],
            'contact' => ['contact_email', 'contact_phone', 'contact_address'],
            'seo' => ['meta_description'],
            default => [],
        };
        $updates = [];
        foreach ($fields as $field) { $updates[$field] = trim($this->postString($field)); }
        $settings = array_replace($settings, $updates);
        $errors = [];
        foreach ($updates as $key => $value) {
            $maximum = ['site_name' => 120, 'site_tagline' => 240, 'contact_email' => 254, 'contact_phone' => 80, 'contact_address' => 240, 'meta_description' => 320, 'logo_media_id' => 20][$key];
            if (mb_strlen($value, 'UTF-8') > $maximum) { $errors[] = 'Um dos campos excede o tamanho máximo permitido.'; break; }
        }
        if (isset($updates['site_name']) && $settings['site_name'] === '') { $errors[] = 'Informe o nome do site.'; }
        if (isset($updates['contact_email']) && $settings['contact_email'] !== '' && filter_var($settings['contact_email'], FILTER_VALIDATE_EMAIL) === false) { $errors[] = 'Informe um e-mail de contato válido.'; }
        $logoId = isset($updates['logo_media_id']) ? ($settings['logo_media_id'] === '' ? null : filter_var($settings['logo_media_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) : null;
        if ($logoId === false) { $errors[] = 'Selecione uma imagem válida da biblioteca.'; }
        if ($logoId !== null && $logoId !== false && (new MediaItem())->findMedia((int) $logoId) === null) { $errors[] = 'A imagem selecionada não existe na biblioteca.'; }

        if ($errors !== []) {
            $this->showForm($settings, $tab, $errors, 422);
            return;
        }
        try {
            if (isset($updates['logo_media_id'])) { $updates['logo_media_id'] = $logoId === null ? null : (string) $logoId; }
            $actor = $this->auth->user();
            (new CmsSettings())->save($updates, (int) $actor->id);
            $this->auth->recordEvent((int) $actor->id, (int) $actor->id, 'cms_settings_updated');
            $this->session->set('flash', (new Message())->success('Configurações atualizadas.'));
            $this->redirect('admin/settings?tab=' . rawurlencode($tab));
        } catch (Throwable) {
            $this->showForm(array_replace($settings, $updates), $tab, ['Não foi possível salvar as configurações.'], 422);
        }
    }

    private function showForm(array $settings, string $tab, array $errors, int $status): void
    {
        $media = [];
        try { $media = (new MediaItem())->search('', 'all', 200, 0); } catch (Throwable) {}
        $this->screen('settings', [
            'settings' => $settings, 'mediaOptions' => $media, 'tab' => $tab,
            'mailSender' => [
                'name' => (string) (getenv('MAIL_FROM_NAME') ?: (defined('APP_NAME') ? APP_NAME : 'Moves')),
                'address' => (string) (getenv('MAIL_FROM_ADDRESS') ?: ''),
                'configured' => trim((string) getenv('MAIL_HOST')) !== '',
            ],
            'errors' => $errors,
        ], $status);
    }

    private function guard(): bool
    {
        try { $user = $this->auth->user(); } catch (Throwable) { $this->unavailable(); return false; }
        if (!$user instanceof User) { $this->redirect('login'); return false; }
        if (!$this->auth->can('settings.manage')) { $this->forbidden(); return false; }
        return true;
    }

    private function tab(): string
    {
        $tab = $_GET['tab'] ?? $_POST['tab'] ?? 'general';
        return in_array($tab, ['general', 'brand', 'contact', 'seo', 'email'], true) ? $tab : 'general';
    }

    private function postString(string $key): string
    {
        return is_string($_POST[$key] ?? null) ? $_POST[$key] : '';
    }

    private function screen(string $view, array $data = [], int $status = 200): void
    {
        http_response_code($status);
        header('Cache-Control: no-store');
        header('Content-Type: text/html; charset=utf-8');
        $actor = $this->auth->user();
        $flash = $this->session->flash();
        if ($actor instanceof User && $this->auth->can('notifications.view')) { $data += (new CmsNotifications())->headerContext((int) $actor->id); }
        $data += [
            'view' => $view, 'csrf' => $this->session->csrfToken(), 'actor' => $actor?->publicData(),
            'canManageRoles' => $this->auth->can('roles.manage'), 'canManageUsers' => $this->auth->can('users.manage'),
            'canAccessAdmin' => $this->auth->can('admin.access'),
            'permissions' => [
                'edit' => $this->auth->can('pages.edit'), 'view' => $this->auth->can('media.view'),
                'upload' => $this->auth->can('media.upload'), 'delete' => $this->auth->can('media.delete'),
                'settings' => ['manage' => $this->auth->can('settings.manage')],
                'slides' => ['view' => $this->auth->can('slides.view')],
                'testimonials' => ['view' => $this->auth->can('testimonials.view')],
                'faqs' => ['view' => $this->auth->can('faqs.view')],
            ],
            'currentPath' => parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/',
            'flash' => $flash?->text(), 'errors' => [], 'settings' => [], 'mediaOptions' => [],
        ];
        echo $this->view->render('screen', $data);
    }

    private function redirect(string $path): void
    {
        header('Location: ' . url($path), true, 303);
    }

    private function forbidden(): void
    {
        http_response_code(403);
        header('Cache-Control: no-store');
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Acesso negado';
    }

    private function unavailable(): void
    {
        http_response_code(503);
        header('Cache-Control: no-store');
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Serviço temporariamente indisponível';
    }
}
