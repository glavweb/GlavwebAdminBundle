<?php

namespace Glavweb\AdminBundle\Menu;

use Knp\Menu\FactoryInterface;
use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * Class TopMenuBuilder
 * @package Glavweb\AdminBundle\Menu
 */
class TopMenuBuilder extends ContainerAware
{
    /**
     * @var array
     */
    protected $config;

    /**
     * @var \Knp\Menu\FactoryInterface
     */
    protected $factory;

    /**
     * Translator
     * @var \Symfony\Component\Translation\TranslatorInterface
     */
    protected $translator;

    /**
     * Constructor
     *
     * @param FactoryInterface $factory
     * @param TranslatorInterface $translator
     */
    public function __construct(array $config, FactoryInterface $factory, TranslatorInterface $translator)
    {
        $this->config     = $config;
        $this->factory    = $factory;
        $this->translator = $translator;
    }

    /**
     * Create top menu
     *
     * @return \Knp\Menu\ItemInterface
     */
    public function createTopMenu()
    {
        /**
         * Root menu
         */
        $menu = $this->factory->createItem('root');
        $configTopMenu = isset($this->config['top_menu']) ? $this->config['top_menu'] : array();

        foreach ($configTopMenu as $menuInfo) {
            $menu->addChild($this->translator->trans($menuInfo['label']), array(
                'route' => $menuInfo['route'],
            ));
        }

        return $menu;
    }
}