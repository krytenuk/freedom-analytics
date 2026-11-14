<?php

declare(strict_types=1);

namespace Admin\Handler;

use Admin\Forms\SiteForm;
use Admin\Model\SiteModel;
use App\Entity\Site;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use Laminas\Form\FormElementManager;
use Mezzio\Router\RouterInterface;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * The add site page, and the edit site page when the route has an "id"
 */
final readonly class SiteFormHandler implements RequestHandlerInterface
{
    use RequestDataTrait;

    public function __construct(
        private FormElementManager $formElementManager,
        private SiteModel $sites,
        private RouterInterface $router,
        private TemplateRendererInterface $template
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $id   = (string) $request->getAttribute('id', '');
        $site = $id === '' ? new Site() : $this->sites->find((int) $id);
        if ($site === null) {
            return new HtmlResponse($this->template->render('error::404'), 404);
        }

        $isNew = $site->getId() === null;

        $form = $this->formElementManager->get(SiteForm::class);
        $form
            ->setCsrfGuard($this->getCsrfGuard($request), $isNew ? 'site-add' : 'site-' . $id)
            ->setTakenCheck(fn (string $domain): bool => $this->sites->isDomainTaken($domain, $isNew ? null : $site));

        if ($request->getMethod() !== 'POST') {
            $form->setData([
                'name'              => $site->getName(),
                'domain'            => $site->getDomain(),
                'description'       => (string) $site->getDescription(),
                'excludedIps'       => (string) $site->getExcludedIps(),
                'checkDomain'       => $site->isCheckDomain() ? '1' : '0',
                'respectDoNotTrack' => $site->isRespectDoNotTrack() ? '1' : '0',
                'active'            => $site->isActive() ? '1' : '0',
            ]);

            return $this->render($form, $site);
        }

        $form->setData($this->getPostData($request));
        if (! $form->isValid()) {
            return $this->render($form, $site);
        }

        /** @var array<string, mixed> $values */
        $values = $form->getData();
        $this->sites->save($site, $values);

        return new RedirectResponse(
            $this->router->generateUri('site', ['id' => $site->getId()]) . '?notice=' . ($isNew ? 'added' : 'saved')
        );
    }

    private function render(SiteForm $form, Site $site): ResponseInterface
    {
        return new HtmlResponse($this->template->render('admin::site-form', [
            'form' => $form->generateCsrfToken(),
            'site' => $site,
        ]), 200, ['Cache-Control' => 'no-store']);
    }
}
