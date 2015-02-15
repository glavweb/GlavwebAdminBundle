<?php

/*
 * This file is part of the Sonata package.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Glavweb\AdminBundle\Twig\Extension;

use Sonata\AdminBundle\Twig\Extension\SonataAdminExtension;
use Sonata\AdminBundle\Admin\FieldDescriptionInterface;
use Sonata\AdminBundle\Exception\NoValueException;

class GlavwebAdminExtension extends SonataAdminExtension
{
    /**
     * {@inheritDoc}
     */
    public function getFilters()
    {
        $filters = parent::getFilters();
        $filters['render_view_element_value'] = new \Twig_Filter_Method($this, 'renderViewElementValue', array('is_safe' => array('html')));
        $filters['render_view_element_label'] = new \Twig_Filter_Method($this, 'renderViewElementLabel', array('is_safe' => array('html')));

        return $filters;
    }

    /**
     * render a view element
     *
     * @param FieldDescriptionInterface $fieldDescription
     * @param mixed                     $object
     * @param array                     $additionalParameters
     *
     * @return string
     */
    public function baseRenderViewElement(FieldDescriptionInterface $fieldDescription, $object, array $additionalParameters = array())
    {
        $template = $this->getTemplate($fieldDescription, 'SonataAdminBundle:CRUD:base_show_field.html.twig');

        try {
            $value = $fieldDescription->getValue($object);
        } catch (NoValueException $e) {
            $value = null;
        }

        return $this->output($fieldDescription, $template, array_merge(array(
            'field_description' => $fieldDescription,
            'object'            => $object,
            'value'             => $value,
            'admin'             => $fieldDescription->getAdmin(),
            'mode'              => 'value'
        ), $additionalParameters));
    }

    /**
     * Render a view element (only value)
     *
     * @param FieldDescriptionInterface $fieldDescription
     * @param mixed                     $object
     *
     * @return string
     */
    public function renderViewElementValue(FieldDescriptionInterface $fieldDescription, $object)
    {
        return $this->baseRenderViewElement($fieldDescription, $object, array(
            'mode' => 'value'
        ));
    }

    /**
     * Render a view element (only label)
     *
     * @param FieldDescriptionInterface $fieldDescription
     * @param mixed                     $object
     *
     * @return string
     */
    public function renderViewElementLabel(FieldDescriptionInterface $fieldDescription, $object)
    {
        return $this->baseRenderViewElement($fieldDescription, $object, array(
            'mode' => 'label'
        ));
    }
}
