<?php

declare(strict_types=1);

namespace Glavweb\AdminBundle\Controller;

use Doctrine\Persistence\ManagerRegistry;
use Glavweb\AdminBundle\SonataAdmin\AdvancedFilter\AdvancedFilterActionTrait;
use Glavweb\AdminBundle\SonataAdmin\AdvancedFilter\AdvancedFilterLocator;
use Glavweb\AdminBundle\Sortable\PositionHandler;
use Glavweb\ExtraBundle\Http\RequestBag;
use Glavweb\FrameworkBundle\Model\ArchiveManager;
use Sonata\AdminBundle\Controller\CRUDController as BaseCRUDController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Shared Sonata CRUD controller (advanced filters, sortable move, archive).
 *
 * @author Andrey Nilov <nilov@glavweb.ru>
 */
class CRUDController extends BaseCRUDController
{
    use AdvancedFilterActionTrait;

    public static function getSubscribedServices(): array
    {
        return array_merge(parent::getSubscribedServices(), [
            'doctrine' => '?'.ManagerRegistry::class,
        ]);
    }

    public function advancedFilterAction(
        Request $request,
        AdvancedFilterLocator $advancedFilterLocator,
        FormFactoryInterface $formFactory,
    ): Response {
        return $this->renderAdvancedFilter($request, $advancedFilterLocator, $formFactory);
    }

    protected function getDoctrine(): ManagerRegistry
    {
        return $this->container->get('doctrine');
    }

    protected function redirectTo(Request $request, object $object): RedirectResponse
    {
        if (null !== RequestBag::get($request, 'btn_create_and_show')) {
            if ($this->admin->hasRoute('show') && $this->admin->isGranted('SHOW', $object)) {
                return new RedirectResponse($this->admin->generateObjectUrl('show', $object));
            }
        }

        if (null !== RequestBag::get($request, 'btn_update_and_show')) {
            if ($this->admin->hasRoute('show') && $this->admin->isGranted('SHOW', $object)) {
                return new RedirectResponse($this->admin->generateObjectUrl('show', $object));
            }
        }

        $isDeleteRequest = $request->isMethod(Request::METHOD_DELETE)
            || Request::METHOD_DELETE === strtoupper((string) RequestBag::get($request, '_method', ''))
            || null !== RequestBag::get($request, 'btn_delete');

        if ($isDeleteRequest) {
            if ($this->admin->hasRoute('list') && $this->admin->hasAccess('list')) {
                return $this->redirectToList();
            }
        }

        return parent::redirectTo($request, $object);
    }

    public function moveAction(
        Request $request,
        string $position,
        TranslatorInterface $translator,
        PositionHandler $positionHandler,
    ): Response {
        if (!$this->admin->isGranted('EDIT', $this->admin->getSubject())) {
            $this->addFlash(
                'sonata_flash_error',
                $translator->trans('flash_error_no_rights_update_position', [], 'admin')
            );

            return new RedirectResponse($this->admin->generateUrl(
                'list',
                ['filter' => $this->admin->getFilterParameters()]
            ));
        }

        $object = $this->admin->getSubject();
        $lastPositionNumber = $positionHandler->getLastPosition($object);
        $newPositionNumber = $positionHandler->getPosition($object, $position, $lastPositionNumber);

        $accessor = PropertyAccess::createPropertyAccessor();
        $accessor->setValue($object, $positionHandler->getPositionFieldByEntity($object), $newPositionNumber);
        $positionHandler->persist();

        if ($this->isXmlHttpRequest($request)) {
            return $this->renderJson([
                'result' => 'ok',
                'objectId' => $this->admin->getNormalizedIdentifier($object),
            ]);
        }

        $this->addFlash(
            'sonata_flash_success',
            $translator->trans('flash_success_position_updated', [], 'admin')
        );

        return new RedirectResponse($this->admin->generateUrl(
            'list',
            ['filter' => $this->admin->getFilterParameters()]
        ));
    }

    public function archiveAction(
        Request $request,
        ArchiveManager $archiveManager,
        TranslatorInterface $translator,
    ): Response {
        $object = $this->admin->getSubject();

        if ($archiveManager->canArchive($object)) {
            $archiveManager->archive($object);
        } else {
            $this->addFlash('sonata_flash_error', $translator->trans('flash_error_object_is_not_archivable'));
        }

        $referer = $request->headers->get('referer');

        if ($referer) {
            return new RedirectResponse($referer);
        }

        return $this->redirectTo($request, $object);
    }

    public function unarchiveAction(
        Request $request,
        ArchiveManager $archiveManager,
        TranslatorInterface $translator,
    ): Response {
        $object = $this->admin->getSubject();

        if ($archiveManager->isArchived($object)) {
            $archiveManager->unarchive($object);
        } else {
            $this->addFlash('sonata_flash_error', $translator->trans('flash_error_object_is_not_archived'));
        }

        $referer = $request->headers->get('referer');

        if ($referer) {
            return new RedirectResponse($referer);
        }

        return $this->redirectTo($request, $object);
    }
}
