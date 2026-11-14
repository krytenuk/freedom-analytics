<?php

declare(strict_types=1);

namespace Admin\Handler;

use Admin\Forms\ProfileForm;
use Admin\Model\UserModel;
use App\Entity\User;
use FwsDoctrineAuth\Mezzio\SessionLogin;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use Laminas\Form\FormElementManager;
use Mezzio\Router\RouterInterface;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

/**
 * The logged-in user's own name and password
 */
final readonly class ProfileHandler implements RequestHandlerInterface
{
    use RequestDataTrait;

    public const NOTICES = [
        'saved'    => 'Your details have been saved.',
        'password' => 'Your details and new password have been saved.',
    ];

    public function __construct(
        private FormElementManager $formElementManager,
        private UserModel $users,
        private SessionLogin $sessionLogin,
        private RouterInterface $router,
        private TemplateRendererInterface $template
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $user = $request->getAttribute(User::class);
        if (! $user instanceof User) {
            throw new RuntimeException('AdminLayoutMiddleware must run before ' . self::class);
        }

        $form = $this->formElementManager->get(ProfileForm::class);
        $form
            ->setCsrfGuard($this->getCsrfGuard($request), 'profile')
            ->setPasswordCheck(fn (string $password): bool => $this->users->verifyPassword($user, $password));

        if ($request->getMethod() !== 'POST') {
            $form->setData(['name' => $user->getName()]);

            return $this->render($form, self::NOTICES[$request->getQueryParams()['notice'] ?? ''] ?? null);
        }

        $form->setData($this->getPostData($request));
        if (! $form->isValid()) {
            return $this->render($form);
        }

        /** @var array{name: string, newPassword?: string|null} $values */
        $values = $form->getData();
        $this->users->saveProfile($user, $values['name']);

        $notice = 'saved';
        if ((string) ($values['newPassword'] ?? '') !== '') {
            $this->users->changePassword($user, (string) $values['newPassword']);
            // A new password ends the user's sessions, so log this one back in
            $this->sessionLogin->login($this->getSession($request), $user);
            $notice = 'password';
        }

        return new RedirectResponse($this->router->generateUri('profile') . '?notice=' . $notice);
    }

    private function render(ProfileForm $form, ?string $notice = null): ResponseInterface
    {
        return new HtmlResponse($this->template->render('admin::profile', [
            'form'   => $form->generateCsrfToken(),
            'notice' => $notice,
        ]), 200, ['Cache-Control' => 'no-store']);
    }
}
