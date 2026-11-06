<?php

declare(strict_types=1);

namespace Glavweb\AdminBundle\SonataAdmin;

use Sonata\AdminBundle\Translator\LabelTranslatorStrategyInterface;

/**
 * Tabs/groups are named as translation keys (`tab.company`, `group.default`).
 * Underscore strategy would rewrite those to `show.group_tab_company`.
 */
final class DottedGroupLabelTranslatorStrategy implements LabelTranslatorStrategyInterface
{
    public function __construct(
        private readonly LabelTranslatorStrategyInterface $inner,
    ) {
    }

    public function getLabel(string $label, string $context = '', string $type = ''): string
    {
        if ('group' === $type && str_contains($label, '.')) {
            return $label;
        }

        return $this->inner->getLabel($label, $context, $type);
    }
}
