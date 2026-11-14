<?php

declare(strict_types=1);

namespace Admin\Report;

use App\Entity\Site;
use App\Entity\User;

/**
 * The site and period a report page is for, which ReportContextMiddleware puts on the request
 */
final readonly class ReportContext
{
    /**
     * @param list<Site> $sites The sites the user can see, for the site menu
     */
    public function __construct(
        public User $user,
        public Site $site,
        public array $sites,
        public Period $period,
    ) {
    }
}
