<?php

declare(strict_types=1);

namespace Glavweb\AdminBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * Admin login/logout. Route names match typical app_admin_* security config.
 *
 * Renders the app template `security/login.html.twig`, which is expected to
 * `{% extends '@GlavwebAdmin/Security/login.html.twig' %}` and override blocks.
 *
 * @author Andrey Nilov <nilov@glavweb.ru>
 */
#[Route('/admin', name: 'app_admin_')]
class SecurityController extends AbstractController
{
    #[Route(path: '/login', name: 'login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route(path: '/logout', name: 'logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}
