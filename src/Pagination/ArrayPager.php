<?php

declare(strict_types=1);

namespace Glavweb\AdminBundle\Pagination;

use Glavweb\ExtraBundle\Http\RequestBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Class ArrayPager.
 *
 * @author Sergey Zvyagintsev <nitron.ru@gmail.com>
 */
class ArrayPager
{
    private int $page;

    private int $maxPageLinks = 25;

    private int $maxPerPage = 20;

    private array $items;

    private UrlGeneratorInterface $router;

    private Request $request;

    public function __construct(array $items, UrlGeneratorInterface $router, Request $request)
    {
        $this->items = $items;
        $this->router = $router;
        $this->request = $request;

        $this->setPage((int) RequestBag::get($request, '_page', 1));
    }

    public function getResults(): array
    {
        return array_slice($this->items, ($this->page - 1) * $this->getMaxPerPage(), $this->getMaxPerPage());
    }

    public function getMaxPerPage(): int
    {
        return $this->maxPerPage;
    }

    public function setMaxPerPage(int $maxPerPage): void
    {
        $this->maxPerPage = $maxPerPage;
    }

    public function isLastPage(): bool
    {
        return $this->page === $this->getLastPage();
    }

    public function getLastPage(): int
    {
        return max(floor($this->getTotalPages() / $this->getMaxPerPage()), 1);
    }

    public function getTotalPages(): int
    {
        return count($this->items);
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function setPage(int $page): void
    {
        $this->page = $page;
    }

    public function generatePageUrl(int $page): string
    {
        return $this->router->generate(
            $this->request->attributes->get('_route'),
            array_merge($this->request->attributes->get('_route_params', []), ['_page' => $page])
        );
    }

    public function getPreviousPage(): int
    {
        return max($this->page - 1, 1);
    }

    /**
     * @return int[]
     */
    public function getLinks(): array
    {
        $total = $this->getMaxPageLinks();
        $lastPage = $this->getLastPage();
        $links = [];
        $tmp = $this->page - floor($total / 2);
        $check = $lastPage - $total + 1;
        $limit = $check > 0 ? $check : 1;
        $begin = $tmp > 0 ? min($tmp, $limit) : 1;

        $i = (int) $begin;
        while ($i < $begin + $total && $i <= $lastPage) {
            $links[] = $i++;
        }

        return $links;
    }

    public function getMaxPageLinks(): int
    {
        return $this->maxPageLinks;
    }

    public function setMaxPageLinks(int $maxPageLinks): void
    {
        $this->maxPageLinks = $maxPageLinks;
    }

    public function getNextPage(): int
    {
        return min($this->page + 1, $this->getLastPage());
    }
}
