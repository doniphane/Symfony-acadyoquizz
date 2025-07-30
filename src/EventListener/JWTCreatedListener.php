<?php

namespace App\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\User\UserInterface;


class JWTCreatedListener implements EventSubscriberInterface
{

    public static function getSubscribedEvents(): array
    {
        return [
            Events::AUTHENTICATION_SUCCESS => 'onAuthenticationSuccess',
        ];
    }


    public function onAuthenticationSuccess(AuthenticationSuccessEvent $event): void
    {
        // Récupérer l'utilisateur depuis l'événement
        $user = $event->getUser();

        // Récupérer les données actuelles de la réponse
        $payload = $event->getData();

        // Ajouter les informations utilisateur dans la réponse
        // On doit vérifier que c'est bien notre entité User
        if ($user instanceof \App\Entity\User) {
            $payload['user'] = [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firtName' => $user->getFirtName(),
                'lastName' => $user->getLastName(),
                'roles' => $user->getRoles(), // ← LES RÔLES SONT ICI
                'createdAt' => $user->getCreatedAt()?->format('Y-m-d H:i:s'),
            ];
        } else {
            // Fallback pour d'autres types d'utilisateurs
            $payload['user'] = [
                'email' => $user->getUserIdentifier(),
                'roles' => $user->getRoles(),
            ];
        }

        // Mettre à jour les données de l'événement
        $event->setData($payload);
    }
}