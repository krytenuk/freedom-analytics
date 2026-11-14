<?php

declare(strict_types=1);

namespace Admin\Handler;

use Admin\Forms\TwoFactorCodeForm;
use FwsDoctrineAuth\AppTwoFactor\AuthenticatorAppMethod;
use FwsDoctrineAuth\AppTwoFactor\AuthenticatorAppService;
use FwsDoctrineAuth\AppTwoFactor\QrCode;
use FwsDoctrineAuth\Mezzio\SessionLogin;
use FwsDoctrineAuth\Throttle\LoginThrottleInterface;
use FwsDoctrineAuth\User\UserProviderInterface;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use Laminas\Form\FormElementManager;
use Mezzio\Router\RouterInterface;
use Mezzio\Session\SessionInterface;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function in_array;
use function is_string;

/**
 * Pairs an authenticator app with the logged-in user's account, and manages it afterwards. Its route must include
 * AuthenticationMiddleware.
 *
 * Replacing the recovery codes or turning the app off needs a current code, so that someone at a computer left logged
 * in cannot turn the protection off. Wrong codes count as failed logins.
 *
 * The page is shown in the admin layout.
 */
readonly class TwoFactorSettingsHandler implements RequestHandlerInterface
{
    use RequestDataTrait;

    /**
     * The page's template
     *
     * @var string
     */
    protected const TEMPLATE = 'admin::two-factor';

    /**
     * The key of the page's form token
     *
     * @var string
     */
    protected const CSRF_KEY = 'two-factor';

    public const ACTION_REGENERATE = 'regenerate';
    public const ACTION_UNPAIR     = 'unpair';

    public const RESPONSE_MESSAGES = [
        'paired'   => 'Your authenticator app is set up. You will be asked for a code each time you log in.',
        'unpaired' => 'Your authenticator app has been removed. You will no longer be asked for a code.',
        'invalid'  => 'That code is incorrect. Please try again.',
    ];

    final public function __construct(
        private FormElementManager $formElementManager,
        private AuthenticatorAppService $apps,
        private AuthenticatorAppMethod $appMethod,
        private QrCode $qrCode,
        private UserProviderInterface $users,
        private LoginThrottleInterface $throttle,
        private RouterInterface $router,
        private TemplateRendererInterface $template
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $session = $this->getSession($request);
        $userId  = $session->get(SessionLogin::SESSION_KEY);
        $user    = is_string($userId) ? $this->users->findById($userId) : null;
        if ($user === null) {
            return new RedirectResponse($this->router->generateUri('login'));
        }

        $form = $this->formElementManager->get(TwoFactorCodeForm::class);
        $form->setCsrfGuard($this->getCsrfGuard($request), static::CSRF_KEY);

        if ($request->getMethod() !== 'POST') {
            return $this->render($session, $user, $form);
        }

        $data = $this->getPostData($request);
        $form->setData($data);
        if (! $form->isValid()) {
            return $this->render($session, $user, $form);
        }

        /** @var array{code: string} $values */
        $values = $form->getData();

        return $this->apps->isPaired($user)
            ? $this->manage($request, $session, $user, $form, $values['code'], $data['action'] ?? null)
            : $this->pair($session, $user, $form, $values['code']);
    }

    private function pair(
        SessionInterface $session,
        object $user,
        TwoFactorCodeForm $form,
        string $code
    ): ResponseInterface {
        $recoveryCodes = $this->apps->confirmPairing($session, $user, $code);
        if ($recoveryCodes === null) {
            // The same secret is shown again, as the user may simply have mistyped the code
            return $this->render($session, $user, $form, error: self::RESPONSE_MESSAGES['invalid']);
        }

        return $this->render($session, $user, $form, self::RESPONSE_MESSAGES['paired'], recoveryCodes: $recoveryCodes);
    }

    private function manage(
        ServerRequestInterface $request,
        SessionInterface $session,
        object $user,
        TwoFactorCodeForm $form,
        string $code,
        ?string $action
    ): ResponseInterface {
        if (! in_array($action, [self::ACTION_REGENERATE, self::ACTION_UNPAIR], true)) {
            return $this->render($session, $user, $form);
        }

        $identity  = $this->users->getIdentity($user);
        $ipAddress = $this->getClientIp($request);
        $status    = $this->throttle->check($identity, $ipAddress);
        if ($status->blocked) {
            $message = $this->throttledMessage($status->retryAfter, TwoFactorCodeForm::$responseMessages);

            return $this->render($session, $user, $form, error: $message, status: 429);
        }

        if (! $this->appMethod->verify($user, $code)) {
            $this->throttle->recordFailure($identity, $ipAddress);

            return $this->render($session, $user, $form, error: self::RESPONSE_MESSAGES['invalid']);
        }

        if ($action === self::ACTION_UNPAIR) {
            $this->apps->unpair($user);

            return $this->render($session, $user, $form, self::RESPONSE_MESSAGES['unpaired']);
        }

        return $this->render($session, $user, $form, recoveryCodes: $this->apps->regenerateRecoveryCodes($user));
    }

    /**
     * @param list<string>|null $recoveryCodes New codes, which are only ever shown this once
     */
    private function render(
        SessionInterface $session,
        object $user,
        TwoFactorCodeForm $form,
        ?string $notice = null,
        ?string $error = null,
        ?array $recoveryCodes = null,
        int $status = 200
    ): ResponseInterface {
        $paired  = $this->apps->isPaired($user);
        $pairing = $paired ? null : $this->apps->startPairing($session, $user);

        // The page holds the secret, or recovery codes, so it must not be kept by the browser or a proxy
        return new HtmlResponse($this->template->render(static::TEMPLATE, [
            'form'          => $form->generateCsrfToken(),
            'paired'        => $paired,
            'secret'        => $pairing?->secret,
            'qrCode'        => $pairing === null ? null : $this->qrCode->svg($pairing->uri),
            'codesLeft'     => $paired ? $this->apps->countRecoveryCodes($user) : null,
            'recoveryCodes' => $recoveryCodes,
            'notice'        => $notice,
            'error'         => $error,
        ]), $status, ['Cache-Control' => 'no-store']);
    }
}
