<?php

declare(strict_types=1);

namespace Admin\Handler;

use Admin\Authentication\LoginRequiredAuthentication;
use Admin\Forms\LoginForm;
use FwsDoctrineAuth\Login\LoginService;
use FwsDoctrineAuth\Mezzio\SessionLogin;
use FwsDoctrineAuth\Mezzio\TwoFactorLogin;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use Laminas\Form\FormElementManager;
use Mezzio\Authentication\AuthenticationInterface;
use Mezzio\Router\RouterInterface;
use Mezzio\Session\SessionInterface;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class LoginHandler implements RequestHandlerInterface
{
    use RequestDataTrait;

    public function __construct(
        private FormElementManager $formElementManager,
        private LoginService $loginService,
        private SessionLogin $sessionLogin,
        private TwoFactorLogin $twoFactorLogin,
        private AuthenticationInterface $authentication,
        private RouterInterface $router,
        private TemplateRendererInterface $template
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->authentication->authenticate($request) !== null) {
            return new RedirectResponse($this->router->generateUri('dashboard'));
        }

        $session = $this->getSession($request);

        $form = $this->formElementManager->get(LoginForm::class);
        $form->setCsrfGuard($this->getCsrfGuard($request));

        if ($request->getMethod() !== 'POST') {
            return $this->render($form, loginRequired: $this->pullLoginRequired($session));
        }

        $form->setData($this->getPostData($request));
        if (! $form->isValid()) {
            return $this->render($form);
        }

        /** @var array{email: string, password: string} $data */
        $data   = $form->getData();
        $result = $this->loginService->attempt($data['email'], $data['password'], $this->getClientIp($request));

        if ($result->isSuccess() && $result->user !== null) {
            $this->sessionLogin->login($session, $result->user);

            return new RedirectResponse($this->router->generateUri('dashboard'));
        }

        // Nobody is logged in until the code from their authenticator app is right
        if ($result->requiresTwoFactor() && $result->user !== null) {
            $this->twoFactorLogin->begin($session, $result->user);

            return new RedirectResponse($this->router->generateUri('login-code'));
        }

        if ($result->isThrottled()) {
            $message = $this->throttledMessage($result->retryAfter, LoginForm::$responseMessages);

            return $this->render($form, $message, 429);
        }

        return $this->render($form, LoginForm::$responseMessages['invalid']);
    }

    /**
     * Removes the note as well as reading it, so the message is only shown once.
     */
    private function pullLoginRequired(SessionInterface $session): bool
    {
        $loginRequired = $session->has(LoginRequiredAuthentication::SESSION_KEY);
        $session->unset(LoginRequiredAuthentication::SESSION_KEY);

        return $loginRequired;
    }

    private function render(
        LoginForm $form,
        ?string $message = null,
        int $status = 200,
        bool $loginRequired = false
    ): ResponseInterface {
        return new HtmlResponse($this->template->render('admin::login', [
            'form'                 => $form->generateCsrfToken(),
            'message'              => $message,
            'loginRequiredMessage' => $loginRequired ? LoginForm::$responseMessages['login-required'] : null,
        ]), $status, ['Cache-Control' => 'no-store']);
    }
}
