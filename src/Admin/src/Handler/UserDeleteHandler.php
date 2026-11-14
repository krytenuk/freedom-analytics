<?php

declare(strict_types=1);

namespace Admin\Handler;

use Admin\Model\UserModel;
use App\Entity\User;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Router\RouterInterface;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Deletes a user. Its route must only allow POST. Users cannot delete themselves or the last active administrator.
 */
final readonly class UserDeleteHandler implements RequestHandlerInterface
{
    use RequestDataTrait;

    public const CSRF_KEY = 'user-delete';

    public function __construct(
        private UserModel $users,
        private RouterInterface $router,
        private TemplateRendererInterface $template
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $user        = $this->users->find((int) $request->getAttribute('id'));
        $currentUser = $request->getAttribute(User::class);
        if ($user === null || ! $currentUser instanceof User) {
            return new HtmlResponse($this->template->render('error::404'), 404);
        }

        $token = $this->getPostData($request)['csrf'] ?? '';
        if (
            $token === ''
            || ! $this->getCsrfGuard($request)->validateToken($token, self::CSRF_KEY)
            || $this->users->whyNotRemovable($user, $currentUser) !== null
        ) {
            return new RedirectResponse($this->router->generateUri('users'));
        }

        $this->users->delete($user);

        return new RedirectResponse($this->router->generateUri('users') . '?notice=deleted');
    }
}
