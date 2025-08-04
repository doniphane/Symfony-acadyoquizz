<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Quiz;
use App\Repository\QuizRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Provider pour récupérer tous les quiz (admin uniquement)
 */
class AllQuizzesProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private QuizRepository $quizRepository
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        // Récupérer tous les quiz avec leurs relations
        $quizzes = $this->quizRepository->createQueryBuilder('q')
            ->leftJoin('q.questions', 'questions')
            ->leftJoin('questions.answers', 'answers')
            ->leftJoin('q.quizAttempts', 'attempts')
            ->leftJoin('q.createdBy', 'creator')
            ->addSelect('questions', 'answers', 'attempts', 'creator')
            ->orderBy('q.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $quizzes;
    }
}