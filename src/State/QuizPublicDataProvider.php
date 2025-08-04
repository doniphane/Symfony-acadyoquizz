<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Quiz;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Provider pour les quiz publics (actifs uniquement)

 */
class QuizPublicDataProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        // Pour les opérations GET sur un quiz spécifique par ID
        if (isset($uriVariables['id'])) {
            $quiz = $this->entityManager->getRepository(Quiz::class)->find($uriVariables['id']);

            if (!$quiz || !$quiz->isActive()) {
                return null;
            }

            return $quiz;
        }

        // Pour les opérations GET sur un quiz par code d'accès
        if (isset($uriVariables['accessCode'])) {
            $quiz = $this->entityManager->getRepository(Quiz::class)->findOneBy([
                'accessCode' => $uriVariables['accessCode'],
                'isActive' => true
            ]);

            if (!$quiz) {
                return null;
            }

            return $quiz;
        }

        // Pour les opérations GET sur la collection (liste des quiz actifs)
        return $this->entityManager->getRepository(Quiz::class)->findBy(['isActive' => true]);
    }
}