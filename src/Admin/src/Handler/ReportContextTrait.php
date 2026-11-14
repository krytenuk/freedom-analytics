<?php

declare(strict_types=1);

namespace Admin\Handler;

use Admin\Report\ReportContext;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

trait ReportContextTrait
{
    private function getContext(ServerRequestInterface $request): ReportContext
    {
        $context = $request->getAttribute(ReportContext::class);
        if (! $context instanceof ReportContext) {
            throw new RuntimeException('ReportContextMiddleware must run before ' . self::class);
        }

        return $context;
    }
}
