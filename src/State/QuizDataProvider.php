<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Quiz;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class QuizDataProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $user = $this->security->getUser();

        if (!$user) {
            return null;
        }

        // Pour les opérations GET sur un quiz spécifique
        if (isset($uriVariables['id'])) {
            $quiz = $this->entityManager->getRepository(Quiz::class)->find($uriVariables['id']);

            if (!$quiz || $quiz->getCreatedBy() !== $user) {
                return null;
            }

            // Charger les questions avec leurs réponses
            $this->entityManager->getRepository(Quiz::class)->createQueryBuilder('q')
                ->leftJoin('q.questions', 'questions')
                ->leftJoin('questions.answers', 'answers')
                ->where('q.id = :id')
                ->setParameter('id', $quiz->getId())
                ->getQuery()
                ->getResult();

            return $quiz;
        }

        // Pour les opérations GET sur la collection
        return $this->entityManager->getRepository(Quiz::class)->findBy(['createdBy' => $user]);
    }
}