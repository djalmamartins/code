<?php

/**
 * Moves Code | Web Authentication Controller
 *
 * Atende login, logout e acesso protegido à área administrativa.
 *
 * @author Djalma Martins
 * @package Moves\Themes\Web\MovesV1\Controllers
 */

namespace Moves\Themes\Web\MovesV1\Controllers;

use Moves\Core\Controller;
use Moves\Core\Session;
use Moves\Models\Auth as AuthService;
use Moves\Models\User;
use MovesCode\Router\Router;
use MovesCode\View\Extension\Asset;
use Throwable;

class Auth extends Controller
{
    private Session $session;

    public function __construct(?Router $router = null)
    {
        $themePath = dirname(__DIR__);
        parent::__construct($themePath);
        $this->session = new Session();
        $this->view->loadExtension(new Asset($themePath . '/assets', url('theme-assets/web/moves/v1')));
        $this->view->share('currentPath', parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
    }

    /** Exibe o formulário de autenticação sem revelar estado de contas. */
    public function showLogin(): void
    {
        try {
            if ((new AuthService($this->session))->user() !== null) {
                $this->redirect('admin');
                return;
            }
        } catch (Throwable) {
            $this->unavailable();
            return;
        }

        $this->renderLogin();
    }

    /** Valida CSRF e credenciais antes de iniciar a sessão autenticada. */
    public function login(): void
    {
        if (!$this->session->validateCsrf($this->postString('_token'))) {
            $this->renderLogin('A solicitação expirou. Atualize a página e tente novamente.', 419);
            return;
        }

        $email = $this->postString('email');
        $password = $this->postString('password');
        if (strlen($email) > 254 || strlen($password) > 4096) {
            $this->renderLogin('E-mail ou senha inválidos.', 401);
            return;
        }

        try {
            if (!(new AuthService($this->session))->login($email, $password)) {
                $this->renderLogin('E-mail ou senha inválidos.', 401, $email);
                return;
            }
        } catch (Throwable) {
            $this->unavailable();
            return;
        }

        $this->redirect('admin');
    }

    /** Encerra a sessão somente por POST com token CSRF válido. */
    public function logout(): void
    {
        if (!$this->session->validateCsrf($this->postString('_token'))) {
            http_response_code(419);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Solicitação inválida';
            return;
        }

        (new AuthService($this->session))->logout();
        $this->redirect('login');
    }

    /** Protege a área administrativa e mostra somente dados públicos da conta. */
    public function admin(): void
    {
        try {
            $user = (new AuthService($this->session))->user();
        } catch (Throwable) {
            $this->unavailable();
            return;
        }

        if (!$user instanceof User) {
            $this->redirect('login');
            return;
        }

        header('Cache-Control: no-store');
        header('Content-Type: text/html; charset=utf-8');
        echo $this->view->render('pages/admin', [
            'head' => $this->head('Administração — MOVES', 'Área administrativa protegida da Moves.', url('admin')),
            'user' => $user->publicData(),
            'csrfToken' => $this->session->csrfToken(),
        ]);
    }

    private function renderLogin(string $error = '', int $status = 200, string $email = ''): void
    {
        http_response_code($status);
        header('Cache-Control: no-store');
        header('Content-Type: text/html; charset=utf-8');
        echo $this->view->render('pages/login', [
            'head' => $this->head('Acesso — MOVES', 'Acesse a área administrativa da Moves.', url('login')),
            'csrfToken' => $this->session->csrfToken(),
            'error' => $error,
            'email' => $email,
        ]);
    }

    private function head(string $title, string $description, string $canonical): string
    {
        return $this->seo
            ->openGraph(APP_NAME, 'pt_BR', 'website')
            ->optimize(
                $title,
                $description,
                $canonical,
                url('theme-assets/web/moves/v1/images/hero-notebook.jpg')
            )
            ->render();
    }

    private function postString(string $key): string
    {
        $value = $_POST[$key] ?? '';

        return is_string($value) ? $value : '';
    }

    private function redirect(string $path): void
    {
        header('Location: ' . url($path), true, 303);
    }

    private function unavailable(): void
    {
        http_response_code(503);
        header('Cache-Control: no-store');
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Serviço temporariamente indisponível';
    }
}
