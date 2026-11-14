<?php

declare(strict_types=1);

namespace Admin\Handler;

use Psr\Http\Message\ServerRequestInterface;

use function array_slice;
use function ceil;
use function count;
use function in_array;
use function is_string;
use function max;
use function min;

/**
 * Reads a list page's "sort", "dir" and "page" query parameters, and picks out the page asked for
 */
trait ListQueryTrait
{
    /**
     * @param list<string> $sorts The columns the list can be sorted by, the first being the default
     * @return array{sort: string, descending: bool}
     */
    private function sortQuery(ServerRequestInterface $request, array $sorts): array
    {
        $query = $request->getQueryParams();

        return [
            'sort'       => in_array($query['sort'] ?? null, $sorts, true) ? (string) $query['sort'] : $sorts[0],
            'descending' => ($query['dir'] ?? null) === 'desc',
        ];
    }

    /**
     * The items on the page asked for, which is kept within the pages there are
     *
     * @template T
     * @param list<T> $items
     * @return array{items: list<T>, total: int, page: int, pages: int}
     */
    private function paginate(ServerRequestInterface $request, array $items, int $perPage): array
    {
        $query = $request->getQueryParams();
        $pages = max(1, (int) ceil(count($items) / $perPage));
        $page  = is_string($query['page'] ?? null) ? (int) $query['page'] : 1;
        $page  = min($pages, max(1, $page));

        return [
            'items' => array_slice($items, ($page - 1) * $perPage, $perPage),
            'total' => count($items),
            'page'  => $page,
            'pages' => $pages,
        ];
    }
}
