<?php

/**
 * Moves Code | Default CMS Theme
 *
 * Define as rotas administrativas e do perfil pessoal.
 *
 * @author Djalma Martins
 * @package Moves\Themes\Cms\DefaultV1
 */

use Moves\Themes\Cms\DefaultV1\Controllers\Users;
use Moves\Themes\Cms\DefaultV1\Controllers\Pages;
use Moves\Themes\Cms\DefaultV1\Controllers\Assets;
use MovesCode\Router\Router;

return [
    'type' => 'cms',
    'name' => 'default',
    'version' => 'v1',
    'controller' => Users::class,
    'routes' => static function (Router $router): void {
        $router->get('/cms-assets/{package}/{directory}/{file}', Assets::class . ':show', 'cms.assets');
        $router->get('/admin', Users::class . ':dashboard', 'cms.dashboard');
        $router->get('/admin/editor', Users::class . ':editorDemo', 'cms.editor.demo');
        $router->get('/admin/reports', Users::class . ':placeholder', 'cms.reports');
        $router->get('/admin/notifications', Users::class . ':placeholder', 'cms.notifications');
        $router->get('/admin/proposals', Users::class . ':placeholder', 'cms.proposals');
        $router->get('/admin/pages', Pages::class . ':index', 'cms.pages.index');
        $router->get('/admin/pages/new', Pages::class . ':createForm', 'cms.pages.create');
        $router->post('/admin/pages', Pages::class . ':create', 'cms.pages.store');
        $router->get('/admin/pages/{id}/edit', Pages::class . ':editForm', 'cms.pages.edit');
        $router->post('/admin/pages/{id}', Pages::class . ':update', 'cms.pages.update');
        $router->post('/admin/pages/{id}/publish', Pages::class . ':publish', 'cms.pages.publish');
        $router->post('/admin/pages/{id}/unpublish', Pages::class . ':unpublish', 'cms.pages.unpublish');
        $router->post('/admin/pages/{id}/delete', Pages::class . ':delete', 'cms.pages.delete');
        $router->get('/admin/blog', Users::class . ':placeholder', 'cms.blog');
        $router->get('/admin/media', Users::class . ':placeholder', 'cms.media');
        $router->get('/admin/slides', Users::class . ':placeholder', 'cms.slides');
        $router->get('/admin/testimonials', Users::class . ':placeholder', 'cms.testimonials');
        $router->get('/admin/faqs', Users::class . ':placeholder', 'cms.faqs');
        $router->get('/admin/settings', Users::class . ':placeholder', 'cms.settings');
        $router->get('/admin/versions', Users::class . ':placeholder', 'cms.versions');
        $router->get('/admin/system-logs', Users::class . ':placeholder', 'cms.system-logs');
        $router->get('/admin/users', Users::class . ':index', 'cms.users.index');
        $router->get('/admin/users/new', Users::class . ':createForm', 'cms.users.create');
        $router->post('/admin/users', Users::class . ':create', 'cms.users.store');
        $router->get('/admin/users/{id}', Users::class . ':show', 'cms.users.show');
        $router->post('/admin/users/{id}', Users::class . ':update', 'cms.users.update');
        $router->get('/profile', Users::class . ':profile', 'cms.profile');
        $router->post('/profile', Users::class . ':updateProfile', 'cms.profile.update');
        $router->post('/profile/password', Users::class . ':changePassword', 'cms.profile.password');
        $router->post('/profile/two-factor/setup', Users::class . ':beginTwoFactor', 'cms.profile.2fa.setup');
        $router->post('/profile/two-factor/confirm', Users::class . ':confirmTwoFactor', 'cms.profile.2fa.confirm');
        $router->post('/profile/two-factor/disable', Users::class . ':disableTwoFactor', 'cms.profile.2fa.disable');
        $router->get('/profile/sessions', Users::class . ':sessions', 'cms.profile.sessions');
        $router->post('/profile/sessions/revoke-others', Users::class . ':revokeOtherSessions', 'cms.profile.sessions.revoke');
        $router->get('/profile/activity', Users::class . ':activity', 'cms.profile.activity');
    },
];
