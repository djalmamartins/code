<?php

/**
 * Moves Code | CMS Editorial Content
 *
 * Gerencia destaques, depoimentos e perguntas frequentes no CMS.
 *
 * @author Djalma Martins
 * @package Moves\Themes\Cms\DefaultV1\Controllers
 */

namespace Moves\Themes\Cms\DefaultV1\Controllers;

use Moves\Core\Controller;
use Moves\Core\Session;
use Moves\Models\Auth;
use Moves\Models\CmsContent;
use Moves\Models\MediaItem;
use Moves\Models\User;
use Moves\Support\HtmlSanitizer;
use MovesCode\Model\Message;
use MovesCode\Pager\Pager;
use MovesCode\Router\Router;
use Throwable;

class EditorialContent extends Controller
{
    private const MODULES = [
        'slides' => ['label' => 'Destaques', 'singular' => 'Destaque', 'permission' => 'slides'],
        'testimonials' => ['label' => 'Depoimentos', 'singular' => 'Depoimento', 'permission' => 'testimonials'],
        'faqs' => ['label' => 'FAQ', 'singular' => 'Pergunta', 'permission' => 'faqs'],
    ];

    private Session $session;
    private Auth $auth;

    public function __construct(?Router $router = null)
    {
        parent::__construct(dirname(__DIR__));
        $this->session = new Session();
        $this->auth = new Auth($this->session);
    }

    public function slides(): void { $this->index('slides'); }
    public function testimonials(): void { $this->index('testimonials'); }
    public function faqs(): void { $this->index('faqs'); }
    public function newSlide(): void { $this->form('slides', null); }
    public function newTestimonial(): void { $this->form('testimonials', null); }
    public function newFaq(): void { $this->form('faqs', null); }
    public function editSlide(array $route): void { $this->edit('slides', $route); }
    public function editTestimonial(array $route): void { $this->edit('testimonials', $route); }
    public function editFaq(array $route): void { $this->edit('faqs', $route); }
    public function createSlide(): void { $this->save('slides'); }
    public function createTestimonial(): void { $this->save('testimonials'); }
    public function createFaq(): void { $this->save('faqs'); }
    public function updateSlide(array $route): void { $this->save('slides', (int) ($route['id'] ?? 0)); }
    public function updateTestimonial(array $route): void { $this->save('testimonials', (int) ($route['id'] ?? 0)); }
    public function updateFaq(array $route): void { $this->save('faqs', (int) ($route['id'] ?? 0)); }
    public function deleteSlide(array $route): void { $this->remove('slides', $route); }
    public function deleteTestimonial(array $route): void { $this->remove('testimonials', $route); }
    public function deleteFaq(array $route): void { $this->remove('faqs', $route); }

    private function index(string $type): void
    {
        if (!$this->guard($type, 'view')) { return; }
        try {
            $query = $this->queryString('q');
            $pageNumber = max(1, min(100000, (int) ($_GET['page'] ?? 1)));
            $content = new CmsContent();
            $pager = (new Pager(url('admin/' . $type . '?q=' . rawurlencode($query) . '&page={page}')))
                ->pager($content->countSearch($type, $query), 20, $pageNumber);
            $this->screen('editorial-content', [
                'type' => $type, 'items' => $content->search($type, $query, $pager->limit(), $pager->offset()),
                'search' => $query, 'pagination' => $pager->render(), 'total' => $content->countSearch($type, $query),
                'permissions' => $this->permissions(),
            ]);
        } catch (Throwable) { $this->unavailable(); }
    }

    private function form(string $type, ?array $record, array $errors = [], int $status = 200): void
    {
        if (!$this->guard($type, $record === null ? 'manage' : 'view')) { return; }
        $media = [];
        if ($type !== 'faqs') {
            try { $media = (new MediaItem())->search('', 'all', 200, 0); }
            catch (Throwable) { $errors[] = 'A biblioteca de mídia está temporariamente indisponível.'; }
        }
        $this->screen('editorial-content-form', [
            'type' => $type, 'record' => $record, 'mediaOptions' => $media,
            'errors' => $errors, 'editing' => is_array($record), 'permissions' => $this->permissions(),
        ], $status);
    }

    private function edit(string $type, array $route): void
    {
        if (!$this->guard($type, 'view')) { return; }
        try {
            $record = (new CmsContent())->find($type, (int) ($route['id'] ?? 0));
            if ($record === null) { $this->notFound(); return; }
            $this->form($type, $record);
        } catch (Throwable) { $this->unavailable(); }
    }

    private function save(string $type, ?int $id = null): void
    {
        if (!$this->guard($type, 'manage') || !$this->csrf()) { return; }
        try {
            $content = new CmsContent();
            $current = $id === null ? null : $content->find($type, $id);
            if ($id !== null && $current === null) { $this->notFound(); return; }
            $data = $this->validated($type);
            if ($data['errors'] !== []) { $this->form($type, $data['record'] + ($id === null ? [] : ['id' => $id]), $data['errors'], 422); return; }
            if ($type !== 'faqs') {
                $imageId = $data['record'][$type === 'slides' ? 'image_media_id' : 'photo_media_id'];
                if ($imageId !== null && (new MediaItem())->findMedia($imageId) === null) {
                    $this->form($type, $data['record'] + ($id === null ? [] : ['id' => $id]), ['Selecione uma imagem existente na biblioteca.'], 422);
                    return;
                }
            }
            if ($id !== null) {
                $data['record']['status'] = $data['record']['status'] ?? $current['status'];
                if ($type === 'slides' || $type === 'testimonials') {
                    $data['record']['published_at'] = $data['record']['published_at'] ?? $current['published_at'];
                }
            }
            $content->save($type, $data['record'], $id);
            $this->flash(($id === null ? 'Conteúdo criado.' : 'Conteúdo atualizado.'));
            $this->redirect('admin/' . $type);
        } catch (Throwable) {
            $this->form($type, $_POST + ($id === null ? [] : ['id' => $id]), ['Não foi possível salvar o conteúdo. Verifique os campos e as referências.'], 422);
        }
    }

    private function validated(string $type): array
    {
        $record = ['sort_order' => filter_var($_POST['sort_order'] ?? '0', FILTER_VALIDATE_INT)];
        $errors = [];
        if ($record['sort_order'] === false || $record['sort_order'] < 0 || $record['sort_order'] > 100000) {
            $record['sort_order'] = 0;
            $errors[] = 'A ordem deve ser um número entre 0 e 100000.';
        }
        if ($type === 'slides') {
            $record += [
                'title' => trim($this->postString('title')), 'subtitle' => trim($this->postString('subtitle')),
                'image_media_id' => $this->optionalId('image_media_id'), 'button_text' => trim($this->postString('button_text')),
                'button_url' => trim($this->postString('button_url')), 'status' => $this->postString('status') === 'active' ? 'active' : 'inactive',
                'published_at' => $this->dateValue('published_at'),
            ];
            if ($record['title'] === '' || mb_strlen($record['title'], 'UTF-8') > 200) { $errors[] = 'Informe um título com até 200 caracteres.'; }
            if (mb_strlen($record['subtitle'], 'UTF-8') > 300) { $errors[] = 'O subtítulo deve ter até 300 caracteres.'; }
            if (mb_strlen($record['button_text'], 'UTF-8') > 80) { $errors[] = 'O texto do botão deve ter até 80 caracteres.'; }
            if (mb_strlen($record['button_url'], 'UTF-8') > 500 || !$this->safeLink($record['button_url'])) { $errors[] = 'Use um caminho local seguro ou uma URL HTTPS válida para o botão.'; }
            if ($record['image_media_id'] === -1) { $errors[] = 'Selecione uma imagem válida da biblioteca.'; $record['image_media_id'] = null; }
            if ($record['published_at'] === 'invalid-date') { $errors[] = 'Informe uma data de publicação válida.'; $record['published_at'] = null; }
        } elseif ($type === 'testimonials') {
            $testimonial = $this->postString('testimonial');
            $record += [
                'name' => trim($this->postString('name')), 'identification' => trim($this->postString('identification')),
                'company' => trim($this->postString('company')), 'testimonial' => '', 'photo_media_id' => $this->optionalId('photo_media_id'),
                'status' => $this->postString('status') === 'published' ? 'published' : 'draft',
                'published_at' => $this->dateValue('published_at') ?? gmdate('Y-m-d H:i:s'),
            ];
            if ($record['name'] === '' || mb_strlen($record['name'], 'UTF-8') > 160) { $errors[] = 'Informe um nome com até 160 caracteres.'; }
            if (mb_strlen($record['identification'], 'UTF-8') > 160 || mb_strlen($record['company'], 'UTF-8') > 160) { $errors[] = 'Cargo e empresa devem ter até 160 caracteres.'; }
            if ($record['photo_media_id'] === -1) { $errors[] = 'Selecione uma foto válida da biblioteca.'; $record['photo_media_id'] = null; }
            if ($record['published_at'] === 'invalid-date') { $errors[] = 'Informe uma data de publicação válida.'; $record['published_at'] = gmdate('Y-m-d H:i:s'); }
            if (strlen($testimonial) > 20000) { $errors[] = 'O depoimento excede o limite permitido.'; }
            try { $record['testimonial'] = (new HtmlSanitizer())->purify($testimonial); }
            catch (Throwable) { $errors[] = 'A sanitização está indisponível; nenhum conteúdo foi salvo.'; }
            if (trim(strip_tags($record['testimonial'])) === '') { $errors[] = 'Informe o conteúdo do depoimento.'; }
        } else {
            $answer = $this->postString('answer');
            $record += [
                'question' => trim($this->postString('question')), 'answer' => '', 'category' => trim($this->postString('category')),
                'status' => $this->postString('status') === 'active' ? 'active' : 'inactive',
            ];
            if ($record['question'] === '' || mb_strlen($record['question'], 'UTF-8') > 300) { $errors[] = 'Informe uma pergunta com até 300 caracteres.'; }
            if (mb_strlen($record['category'], 'UTF-8') > 100) { $errors[] = 'O grupo deve ter até 100 caracteres.'; }
            if (strlen($answer) > 30000) { $errors[] = 'A resposta excede o limite permitido.'; }
            try { $record['answer'] = (new HtmlSanitizer())->purify($answer); }
            catch (Throwable) { $errors[] = 'A sanitização está indisponível; nenhum conteúdo foi salvo.'; }
            if (trim(strip_tags($record['answer'])) === '') { $errors[] = 'Informe a resposta.'; }
        }

        return ['record' => $record, 'errors' => $errors];
    }

    private function remove(string $type, array $route): void
    {
        if (!$this->guard($type, 'manage') || !$this->csrf()) { return; }
        try {
            if (!(new CmsContent())->delete($type, (int) ($route['id'] ?? 0))) { $this->notFound(); return; }
            $this->flash('Conteúdo excluído.');
            $this->redirect('admin/' . $type);
        } catch (Throwable) { $this->screen('editorial-content', ['type' => $type, 'errors' => ['Não foi possível excluir este conteúdo. Verifique se ele está em uso.']], 409); }
    }

    private function permissions(): array
    {
        $result = [];
        foreach (self::MODULES as $type => $module) {
            $result[$type] = ['view' => $this->auth->can($module['permission'] . '.view'), 'manage' => $this->auth->can($module['permission'] . '.manage')];
        }

        return $result;
    }

    private function guard(string $type, string $action): bool
    {
        if (!isset(self::MODULES[$type])) { $this->notFound(); return false; }
        try { $user = $this->auth->user(); } catch (Throwable) { $this->unavailable(); return false; }
        if (!$user instanceof User) { $this->redirect('login'); return false; }
        if (!$this->auth->can(self::MODULES[$type]['permission'] . '.' . $action)) { $this->forbidden(); return false; }

        return true;
    }

    private function csrf(): bool
    {
        if ($this->session->validateCsrf($this->postString('_token'))) { return true; }
        $this->forbidden();
        return false;
    }

    private function safeLink(string $url): bool
    {
        if ($url === '') { return true; }
        if (str_starts_with($url, '/') && preg_match('~^/(?![/\\\\])[A-Za-z0-9/_#?=&.%+-]*$~D', $url) === 1) { return true; }
        return filter_var($url, FILTER_VALIDATE_URL) !== false && strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https'
            && parse_url($url, PHP_URL_USER) === null && parse_url($url, PHP_URL_PASS) === null;
    }

    private function optionalId(string $field): ?int
    {
        $value = $this->postString($field);
        if ($value === '') { return null; }
        $id = filter_var($value, FILTER_VALIDATE_INT);
        return $id !== false && $id > 0 ? $id : -1;
    }

    private function dateValue(string $field): ?string
    {
        $value = $this->postString($field);
        if ($value === '') { return null; }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, new \DateTimeZone(date_default_timezone_get()));
        $errors = \DateTimeImmutable::getLastErrors();
        if (!$date instanceof \DateTimeImmutable || (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) { return 'invalid-date'; }
        return $date->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    private function queryString(string $key): string
    {
        return is_string($_GET[$key] ?? null) ? mb_substr($_GET[$key], 0, 160, 'UTF-8') : '';
    }

    private function postString(string $key): string
    {
        return is_string($_POST[$key] ?? null) ? $_POST[$key] : '';
    }

    private function flash(string $text): void
    {
        $this->session->set('flash', (new Message())->success($text));
    }

    private function redirect(string $path): never
    {
        header('Location: ' . url($path), true, 303);
        exit;
    }

    private function screen(string $view, array $data = [], int $status = 200): void
    {
        http_response_code($status);
        header('Cache-Control: no-store');
        header('Content-Type: text/html; charset=utf-8');
        $actor = $this->auth->user();
        $flash = $this->session->flash();
        echo $this->view->render('screen', $data + [
            'view' => $view, 'csrf' => $this->session->csrfToken(), 'actor' => $actor?->publicData(),
            'permissions' => $this->permissions(), 'canManageRoles' => $this->auth->can('roles.manage'),
            'canManageUsers' => $this->auth->can('users.manage'), 'canAccessAdmin' => $this->auth->can('admin.access'),
            'currentPath' => parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/',
            'flash' => $flash?->text(), 'errors' => [], 'tab' => 'general', 'record' => null,
        ]);
    }

    private function forbidden(): void
    {
        http_response_code(403);
        header('Cache-Control: no-store');
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Acesso negado';
    }

    private function notFound(): void
    {
        http_response_code(404);
        header('Cache-Control: no-store');
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Não encontrado';
    }

    private function unavailable(): void
    {
        http_response_code(503);
        header('Cache-Control: no-store');
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Serviço temporariamente indisponível';
    }
}
