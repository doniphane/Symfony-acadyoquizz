<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

/**
 * Provider pour l'endpoint /api/me
 * 
 * Ce provider gère l'endpoint /api/me qui permet à un utilisateur
 * connecté de récupérer ses propres informations.
 * 
 * Fonctionnalités :
 * - Récupération de l'utilisateur connecté via le token JWT
 * - Vérification de l'authentification
 * - Retour des données utilisateur à jour depuis la base de données
 * - Utilisé pour vérifier l'état de connexion et récupérer les infos utilisateur
 * 
 * Utilisé par le frontend pour :
 * - Vérifier si l'utilisateur est connecté
 * - Récupérer les informations du profil utilisateur
 * - Afficher le nom/prénom dans l'interface
 */
class MeProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Utilisateur|null
    {
        $user = $this->security->getUser();

        if (!$user instanceof Utilisateur) {
            throw new BadRequestException('Not authenticated or invalid user');
        }

        // Récupérer l'utilisateur à jour depuis la base de données
        $freshUser = $this->entityManager->getRepository(Utilisateur::class)->find($user->getId());

        if (!$freshUser) {
            throw new BadRequestException('User not found');
        }

        return $freshUser;
    }
}