<?php

declare(strict_types=1);

namespace Admin\Handler;

use Admin\Forms\UserForm;
use Admin\Model\SiteModel;
use Admin\Model\UserModel;
use App\Entity\Site;
use App\Entity\User;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use Laminas\Form\FormElementManager;
use Mezzio\Router\RouterInterface;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function array_filter;
use function array_map;
use function array_values;
use function in_array;
use function is_array;

/**
 * The add user page, and the edit user page when the route has an "id". It will not let the last active administrator
 * be made a viewer or turned off, so nobody is locked out of managing the sites.
 */
final readonly class UserFormHandler implements RequestHandlerInterface
{
    use RequestDataTrait;

    public const LAST_ADMIN = 'There must be at least one active administrator, so this user must stay one.';

    public function __construct(
        private FormElementManager $formElementManager,
        private UserModel $users,
        private SiteModel $sites,
        private RouterInterface $router,
        private TemplateRendererInterface $template
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $id   = (string) $request->getAttribute('id', '');
        $user = $id === '' ? new User() : $this->users->find((int) $id);
        if ($user === null) {
            return new HtmlResponse($this->template->render('error::404'), 404);
        }

        $isNew    = $user->getId() === null;
        $allSites = $this->sites->findAll();
        $options  = [];
        foreach ($allSites as $site) {
            $options[(string) $site->getId()] = $site->getName() . ' (' . $site->getDomain() . ')';
        }

        $form = $this->formElementManager->get(UserForm::class);
        $form
            ->setCsrfGuard($this->getCsrfGuard($request), $isNew ? 'user-add' : 'user-' . $id)
            ->setTakenCheck(fn (string $email): bool => $this->users->isEmailTaken($email, $isNew ? null : $user))
            ->setPasswordRequired($isNew)
            ->setSiteOptions($options);

        if ($request->getMethod() !== 'POST') {
            $form->setData([
                'name'   => $user->getName(),
                'email'  => $user->getEmail(),
                'role'   => $user->getRole(),
                'active' => $user->isActive() ? '1' : '0',
                'sites'  => array_map(static fn (Site $site): string => (string) $site->getId(), $user->getSites()->getValues()),
            ]);

            return $this->render($request, $form, $user);
        }

        $data = $this->getPostData($request);
        // Checkbox lists arrive as arrays, which getPostData() leaves out
        $body          = $request->getParsedBody();
        $data['sites'] = is_array($body) && is_array($body['sites'] ?? null) ? array_values($body['sites']) : [];
        $form->setData($data);
        if (! $form->isValid()) {
            return $this->render($request, $form, $user);
        }

        /** @var array<string, mixed> $values */
        $values = $form->getData();
        if ($this->users->wouldRemoveLastAdmin($user, $values)) {
            return $this->render($request, $form, $user, self::LAST_ADMIN);
        }

        $chosen = is_array($values['sites'] ?? null) ? $values['sites'] : [];
        $this->users->save($user, $values, array_values(array_filter(
            $allSites,
            static fn (Site $site): bool => in_array((string) $site->getId(), $chosen, true)
        )));

        return new RedirectResponse($this->router->generateUri('users') . '?notice=' . ($isNew ? 'added' : 'saved'));
    }

    private function render(
        ServerRequestInterface $request,
        UserForm $form,
        User $user,
        ?string $error = null
    ): ResponseInterface {
        $currentUser = $request->getAttribute(User::class);

        return new HtmlResponse($this->template->render('admin::user-form', [
            'form'            => $form->generateCsrfToken(),
            'user'            => $user,
            'error'           => $error,
            'whyNotRemovable' => $user->getId() === null || ! $currentUser instanceof User
                ? null
                : $this->users->whyNotRemovable($user, $currentUser),
            'deleteToken'     => $this->getCsrfGuard($request)->generateToken(UserDeleteHandler::CSRF_KEY),
        ]), 200, ['Cache-Control' => 'no-store']);
    }
}
