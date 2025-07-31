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

        $user = $this->getConnectedUser();
        if (!$user) {
            return null;
        }


        if ($this->isRequestingSpecificQuiz($uriVariables)) {
            return $this->getQuizWithDetails($uriVariables['id'], $user);
        }


        return $this->getAllUserQuizzes($user);
    }


    private function getConnectedUser()
    {
        return $this->security->getUser();
    }


    private function isRequestingSpecificQuiz(array $uriVariables): bool
    {
        return isset($uriVariables['id']);
    }


    private function getQuizWithDetails(int $quizId, $user): ?Quiz
    {

        $quiz = $this->findQuiz($quizId);

        if (!$quiz) {
            return null;
        }

        // Vérifier que l'utilisateur est propriétaire
        if (!$this->isQuizOwner($quiz, $user)) {
            return null; // Pas autorisé
        }


        $this->loadQuizQuestions($quiz);

        return $quiz;
    }


    private function findQuiz(int $quizId): ?Quiz
    {
        return $this->entityManager
            ->getRepository(Quiz::class)
            ->find($quizId);
    }

    // Vérifier si l'utilisateur est propriétaire du quiz
    private function isQuizOwner(Quiz $quiz, $user): bool
    {
        return $quiz->getCreatedBy() === $user;
    }


    private function loadQuizQuestions(Quiz $quiz): void
    {
        // Requête pour charger les questions avec leurs réponses
        $this->entityManager
            ->getRepository(Quiz::class)
            ->createQueryBuilder('q')
            ->leftJoin('q.questions', 'questions')
            ->leftJoin('questions.answers', 'answers')
            ->where('q.id = :id')
            ->setParameter('id', $quiz->getId())
            ->getQuery()
            ->getResult();
    }


    private function getAllUserQuizzes($user): array
    {
        return $this->entityManager
            ->getRepository(Quiz::class)
            ->findBy(['createdBy' => $user]);
    }
}