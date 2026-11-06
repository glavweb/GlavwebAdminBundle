<?php

declare(strict_types=1);

namespace Glavweb\AdminBundle\SonataAdmin;

/**
 * Interface NativeItemProcessor.
 *
 * @author Sergey Zvyagintsev <nitron.ru@gmail.com>
 */
interface ItemProcessorInterface
{
    public function processItem(array &$item): void;
}
