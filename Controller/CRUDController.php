<?php

namespace Glavweb\AdminBundle\Controller;

use Sonata\AdminBundle\Admin\BaseFieldDescription;
use Sonata\AdminBundle\Controller\CRUDController as BaseCRUDController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class CRUDController extends BaseCRUDController
{
    /**
     * Show action
     *
     * @param int|string|null $id
     *
     * @return Response
     *
     * @throws NotFoundHttpException If the object does not exist
     * @throws AccessDeniedException If access is not granted
     */
    public function showAction($id = null)
    {
        $request = $this->container->get('request_stack')->getCurrentRequest();
        $id = $request->get($this->admin->getIdParameter());

        $object = $this->admin->getObject($id);
        if (!$object) {
            throw new NotFoundHttpException(sprintf('unable to find the object with id : %s', $id));
        }

        if (false === $this->admin->isGranted('VIEW', $object)) {
            throw new AccessDeniedException();
        }

        $this->admin->setSubject($object);

        $response = $this->handleForms($request);

        if ($response instanceof Response) {
            return $response;
        }

        return $this->render($this->admin->getTemplate('show'), array(
            'action'   => 'show',
            'object'   => $object,
            'elements' => $this->admin->getShow(),
        ));
    }

    /**
     * Handle forms
     *
     * @param Request $request
     * @return Response
     */
    protected function handleForms(Request $request)
    {
        if ($request->getMethod() == 'POST') {
            $actions = $request->request->keys();
            foreach ($actions as $action) {
                $camelizedAction = 'handle' . BaseFieldDescription::camelize($action);
                if (method_exists($this, $camelizedAction)) {
                    $response = $this->$camelizedAction($request, $request->request->get($action));

                    if ($response instanceof Response) {
                        return $response;
                    }
                }
            }
        }
    }
}