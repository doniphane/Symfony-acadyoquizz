<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

/**
 * Provider pour l'endpoint /api/me
 * Récupère l'utilisateur connecté via le token JWT
 */
class MeProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): User|null
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new BadRequestException('Not authenticated or invalid user');
        }

        // Récupérer l'utilisateur à jour depuis la base de données
        $freshUser = $this->entityManager->getRepository(User::class)->find($user->getId());

        if (!$freshUser) {
            throw new BadRequestException('User not found');
        }

        return $freshUser;
    }
}