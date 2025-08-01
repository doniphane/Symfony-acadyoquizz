<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\QuizAttempt;
use App\Entity\Quiz;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

/**
 * Processor pour la création des tentatives de quiz
 * Remplace la méthode participate du ApiQuizController
 */
class QuizAttemptProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): QuizAttempt
    {
        // Vérifier que c'est bien une tentative de quiz
        if (!$data instanceof QuizAttempt) {
            throw new BadRequestException('Invalid data type');
        }

        // Récupérer le quiz depuis les variables d'URI
        $quizId = $uriVariables['id'] ?? null;
        if (!$quizId) {
            throw new BadRequestException('Quiz ID is required');
        }

        $quiz = $this->entityManager->find(Quiz::class, $quizId);
        if (!$quiz || !$quiz->isActive()) {
            throw new BadRequestException('Quiz not found or not active');
        }

        // Vérifier que les données requises sont présentes
        if (!$data->getParticipantFirstName() || !$data->getParticipantLastName()) {
            throw new BadRequestException('First name and last name are required');
        }

        // Associer la tentative au quiz
        $data->setQuiz($quiz);

        // Persister la tentative
        $this->entityManager->persist($data);
        $this->entityManager->flush();

        return $data;
    }
}