<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Quiz;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

/**
 * Processeur personnalisé pour la mise à jour des quiz
 * Gère uniquement les champs envoyés dans la requête
 */
class QuizUpdateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security
    ) {
    }

    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        // Vérifier que c'est bien un Quiz
        if (!$data instanceof Quiz) {
            throw new BadRequestException('Invalid data type');
        }

        // Vérifier que l'utilisateur est connecté et admin
        if (!$this->security->isGranted('ROLE_ADMIN')) {
            throw new BadRequestException('Access denied');
        }

        // Récupérer le quiz original depuis la base de données
        $quizId = $uriVariables['id'] ?? null;
        if (!$quizId) {
            throw new BadRequestException('Quiz ID is required');
        }

        $originalQuiz = $this->entityManager->find(Quiz::class, $quizId);
        if (!$originalQuiz) {
            throw new BadRequestException('Quiz not found');
        }

        // Vérifier que l'utilisateur est le propriétaire du quiz
        if ($originalQuiz->getCreatedBy() !== $this->security->getUser()) {
            throw new BadRequestException('You can only update your own quizzes');
        }

        // Mettre à jour seulement les champs qui ne sont pas null dans $data
        if ($data->getTitle() !== null) {
            $originalQuiz->setTitle($data->getTitle());
        }

        if ($data->getDescription() !== null) {
            $originalQuiz->setDescription($data->getDescription());
        }

        if ($data->isActive() !== null) {
            $originalQuiz->setIsActive($data->isActive());
        }

        if ($data->isStarted() !== null) {
            $originalQuiz->setIsStarted($data->isStarted());
        }

        if ($data->getPassingScore() !== null) {
            $originalQuiz->setPassingScore($data->getPassingScore());
        }

        // Sauvegarder les modifications
        $this->entityManager->flush();

        // Retourner le quiz mis à jour
        return $originalQuiz;
    }
}