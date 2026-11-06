<?php

declare(strict_types=1);

namespace Glavweb\AdminBundle\Pagination;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Class PagerFactory.
 *
 * @author Sergey Zvyagintsev <nitron.ru@gmail.com>
 */
class PagerFactory
{
    private UrlGeneratorInterface $router;

    private RequestStack $requestStack;

    public function __construct(UrlGeneratorInterface $router, RequestStack $requestStack)
    {
        $this->router = $router;
        $this->requestStack = $requestStack;
    }

    public function createArrayPager(array $items): ArrayPager
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            throw new \LogicException('Нет текущего запроса');
        }

        return new ArrayPager($items, $this->router, $request);
    }
}
