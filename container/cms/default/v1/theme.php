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
use Moves\Themes\Cms\DefaultV1\Controllers\Articles;
use Moves\Themes\Cms\DefaultV1\Controllers\Media;
use Moves\Themes\Cms\DefaultV1\Controllers\EditorialContent;
use Moves\Themes\Cms\DefaultV1\Controllers\Assets;
use Moves\Themes\Cms\DefaultV1\Controllers\Settings;
use Moves\Themes\Cms\DefaultV1\Controllers\Notifications;
use Moves\Themes\Cms\DefaultV1\Controllers\Reports;
use Moves\Themes\Cms\DefaultV1\Controllers\SystemLogs;
use Moves\Themes\Cms\DefaultV1\Controllers\Versions;
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
        $router->get('/admin/reports', Reports::class . ':index', 'cms.reports');
        $router->get('/admin/reports/export.csv', Reports::class . ':export', 'cms.reports.export');
        $router->get('/admin/notifications', Notifications::class . ':index', 'cms.notifications');
        $router->post('/admin/notifications/{id}/read', Notifications::class . ':markRead', 'cms.notifications.read');
        $router->post('/admin/notifications/read-all', Notifications::class . ':markAllRead', 'cms.notifications.read-all');
        $router->get('/admin/proposals', Users::class . ':placeholder', 'cms.proposals');
        $router->get('/admin/pages', Pages::class . ':index', 'cms.pages.index');
        $router->get('/admin/pages/new', Pages::class . ':createForm', 'cms.pages.create');
        $router->post('/admin/pages', Pages::class . ':create', 'cms.pages.store');
        $router->get('/admin/pages/{id}/edit', Pages::class . ':editForm', 'cms.pages.edit');
        $router->post('/admin/pages/{id}', Pages::class . ':update', 'cms.pages.update');
        $router->post('/admin/pages/{id}/publish', Pages::class . ':publish', 'cms.pages.publish');
        $router->post('/admin/pages/{id}/unpublish', Pages::class . ':unpublish', 'cms.pages.unpublish');
        $router->post('/admin/pages/{id}/delete', Pages::class . ':delete', 'cms.pages.delete');
        $router->get('/admin/blog', Articles::class . ':index', 'cms.articles.index');
        $router->get('/admin/blog/new', Articles::class . ':createForm', 'cms.articles.create');
        $router->post('/admin/blog', Articles::class . ':create', 'cms.articles.store');
        $router->get('/admin/blog/categories', Articles::class . ':categories', 'cms.articles.categories');
        $router->post('/admin/blog/categories', Articles::class . ':saveCategory', 'cms.articles.categories.store');
        $router->get('/admin/blog/categories/{id}/edit', Articles::class . ':editCategoryForm', 'cms.articles.categories.edit');
        $router->post('/admin/blog/categories/{id}', Articles::class . ':saveCategory', 'cms.articles.categories.update');
        $router->post('/admin/blog/categories/{id}/delete', Articles::class . ':deleteCategory', 'cms.articles.categories.delete');
        $router->get('/admin/blog/{id}/edit', Articles::class . ':editForm', 'cms.articles.edit');
        $router->post('/admin/blog/{id}', Articles::class . ':update', 'cms.articles.update');
        $router->post('/admin/blog/{id}/publish', Articles::class . ':publish', 'cms.articles.publish');
        $router->post('/admin/blog/{id}/unpublish', Articles::class . ':unpublish', 'cms.articles.unpublish');
        $router->post('/admin/blog/{id}/delete', Articles::class . ':delete', 'cms.articles.delete');
        $router->get('/admin/media', Media::class . ':index', 'cms.media.index');
        $router->get('/admin/media/picker', Media::class . ':picker', 'cms.media.picker');
        $router->post('/admin/media', Media::class . ':upload', 'cms.media.upload');
        $router->get('/admin/media/{id}/edit', Media::class . ':editForm', 'cms.media.edit');
        $router->post('/admin/media/{id}', Media::class . ':update', 'cms.media.update');
        $router->post('/admin/media/{id}/delete', Media::class . ':delete', 'cms.media.delete');
        $router->get('/admin/media/{id}/file', Media::class . ':file', 'cms.media.file');
        $router->get('/media/{id}', Media::class . ':publicFile', 'public.media.file');
        $router->get('/admin/slides', EditorialContent::class . ':slides', 'cms.slides');
        $router->get('/admin/slides/new', EditorialContent::class . ':newSlide', 'cms.slides.create');
        $router->post('/admin/slides', EditorialContent::class . ':createSlide', 'cms.slides.store');
        $router->get('/admin/slides/{id}/edit', EditorialContent::class . ':editSlide', 'cms.slides.edit');
        $router->post('/admin/slides/{id}', EditorialContent::class . ':updateSlide', 'cms.slides.update');
        $router->post('/admin/slides/{id}/delete', EditorialContent::class . ':deleteSlide', 'cms.slides.delete');
        $router->get('/admin/testimonials', EditorialContent::class . ':testimonials', 'cms.testimonials');
        $router->get('/admin/testimonials/new', EditorialContent::class . ':newTestimonial', 'cms.testimonials.create');
        $router->post('/admin/testimonials', EditorialContent::class . ':createTestimonial', 'cms.testimonials.store');
        $router->get('/admin/testimonials/{id}/edit', EditorialContent::class . ':editTestimonial', 'cms.testimonials.edit');
        $router->post('/admin/testimonials/{id}', EditorialContent::class . ':updateTestimonial', 'cms.testimonials.update');
        $router->post('/admin/testimonials/{id}/delete', EditorialContent::class . ':deleteTestimonial', 'cms.testimonials.delete');
        $router->get('/admin/faqs', EditorialContent::class . ':faqs', 'cms.faqs');
        $router->get('/admin/faqs/new', EditorialContent::class . ':newFaq', 'cms.faqs.create');
        $router->post('/admin/faqs', EditorialContent::class . ':createFaq', 'cms.faqs.store');
        $router->get('/admin/faqs/{id}/edit', EditorialContent::class . ':editFaq', 'cms.faqs.edit');
        $router->post('/admin/faqs/{id}', EditorialContent::class . ':updateFaq', 'cms.faqs.update');
        $router->post('/admin/faqs/{id}/delete', EditorialContent::class . ':deleteFaq', 'cms.faqs.delete');
        $router->get('/admin/settings', Settings::class . ':index', 'cms.settings');
        $router->post('/admin/settings', Settings::class . ':update', 'cms.settings.update');
        $router->get('/admin/versions', Versions::class . ':index', 'cms.versions');
        $router->post('/admin/versions', Versions::class . ':publish', 'cms.versions.publish');
        $router->get('/admin/system-logs', SystemLogs::class . ':index', 'cms.system-logs');
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
