<?php

namespace App\EventListener;

use App\Service\JwtCookieService;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Symfony\Component\Security\Core\User\UserInterface;


class JwtCookieListener
{
    public function __construct(
        private JwtCookieService $jwtCookieService
    ) {
    }


    public function onAuthenticationSuccess(AuthenticationSuccessEvent $event): void
    {
        $response = $event->getResponse();
        $user = $event->getUser();

        if (!$user instanceof UserInterface) {
            return;
        }


        $this->jwtCookieService->addCookieToResponse($response, $user);


        $data = json_decode($response->getContent(), true);

        if (isset($data['token'])) {
            unset($data['token']);
            $response->setContent(json_encode($data));
        }
    }
}