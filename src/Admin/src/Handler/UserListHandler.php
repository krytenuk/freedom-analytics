<?php

declare(strict_types=1);

namespace Admin\Handler;

use Admin\Model\UserModel;
use Laminas\Diactoros\Response\HtmlResponse;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Lists the users who can log in
 */
final readonly class UserListHandler implements RequestHandlerInterface
{
    public const NOTICES = [
        'added'   => 'The user has been added.',
        'saved'   => 'The user has been saved.',
        'deleted' => 'The user has been deleted.',
    ];

    public function __construct(private UserModel $users, private TemplateRendererInterface $template)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new HtmlResponse($this->template->render('admin::users', [
            'users'  => $this->users->findAll(),
            'notice' => self::NOTICES[$request->getQueryParams()['notice'] ?? ''] ?? null,
        ]), 200, ['Cache-Control' => 'no-store']);
    }
}
