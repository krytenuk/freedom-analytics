<?php

declare(strict_types=1);

namespace Admin\Handler;

use Admin\Forms\TwoFactorCodeForm;
use FwsDoctrineAuth\AppTwoFactor\ConfigProvider as AppTwoFactorConfigProvider;
use FwsDoctrineAuth\Mezzio\TwoFactorLogin;
use FwsDoctrineAuth\TwoFactor\TwoFactorStatus;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use Laminas\Form\FormElementManager;
use Mezzio\Authentication\AuthenticationInterface;
use Mezzio\Router\RouterInterface;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function in_array;

/**
 * The second step of logging in, for users who have paired an authenticator app. LoginHandler sends them here.
 */
final readonly class TwoFactorLoginHandler implements RequestHandlerInterface
{
    use RequestDataTrait;

    private const METHOD = AppTwoFactorConfigProvider::METHOD_NAME;

    public function __construct(
        private FormElementManager $formElementManager,
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

        // No login waiting, it took too long, or the user cannot use the app, such as after it was turned off
        if (! in_array(self::METHOD, $this->twoFactorLogin->availableMethods($session), true)) {
            $this->twoFactorLogin->cancel($session);

            return new RedirectResponse($this->router->generateUri('login'));
        }

        $form = $this->formElementManager->get(TwoFactorCodeForm::class);
        $form->setCsrfGuard($this->getCsrfGuard($request), 'login-code');

        if ($request->getMethod() !== 'POST') {
            $this->twoFactorLogin->start($session, self::METHOD);

            return $this->render($form);
        }

        $form->setData($this->getPostData($request));
        if (! $form->isValid()) {
            return $this->render($form);
        }

        /** @var array{code: string} $data */
        $data   = $form->getData();
        $result = $this->twoFactorLogin->verify($session, self::METHOD, $data['code'], $this->getClientIp($request));

        return match ($result->status) {
            TwoFactorStatus::Success        => new RedirectResponse($this->router->generateUri('dashboard')),
            TwoFactorStatus::InvalidCode    => $this->render($form, TwoFactorCodeForm::$responseMessages['invalid']),
            // The waiting login has been cancelled, so the user starts again from the login page
            TwoFactorStatus::Throttled      => $this->render(
                $form,
                $this->throttledMessage($result->retryAfter, TwoFactorCodeForm::$responseMessages),
                429,
                expired: true
            ),
            TwoFactorStatus::NoPendingLogin => $this->render(
                $form,
                TwoFactorCodeForm::$responseMessages['expired'],
                expired: true
            ),
        };
    }

    /**
     * @param bool $expired The login cannot carry on, so the form is replaced by a link back to the login page
     */
    private function render(
        TwoFactorCodeForm $form,
        ?string $message = null,
        int $status = 200,
        bool $expired = false
    ): ResponseInterface {
        return new HtmlResponse($this->template->render('admin::login-code', [
            'form'    => $form->generateCsrfToken(),
            'message' => $message,
            'expired' => $expired,
        ]), $status, ['Cache-Control' => 'no-store']);
    }
}
