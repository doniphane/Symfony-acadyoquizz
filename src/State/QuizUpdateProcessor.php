<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Quiz;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;


class QuizUpdateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security
    ) {
    }

    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {

        $this->validateRequest($data, $uriVariables);

        $originalQuiz = $this->getOriginalQuiz($uriVariables['id']);


        $this->checkPermissions($originalQuiz);


        $this->updateQuizFields($originalQuiz, $data);


        $this->entityManager->flush();

        return $originalQuiz;
    }

    // Vérifier que la requête est valide
    private function validateRequest($data, array $uriVariables): void
    {
        if (!$data instanceof Quiz) {
            throw new BadRequestException('Type de données invalide');
        }

        if (!isset($uriVariables['id'])) {
            throw new BadRequestException('ID du quiz manquant');
        }
    }


    private function getOriginalQuiz(int $quizId): Quiz
    {
        $quiz = $this->entityManager->find(Quiz::class, $quizId);

        if (!$quiz) {
            throw new BadRequestException('Quiz non trouvé');
        }

        return $quiz;
    }

    private function checkPermissions(Quiz $quiz): void
    {
        // Vérifier que l'utilisateur est admin
        if (!$this->security->isGranted('ROLE_ADMIN')) {
            throw new BadRequestException('Accès refusé');
        }

        // Vérifier que c'est son quiz
        if ($quiz->getCreatedBy() !== $this->security->getUser()) {
            throw new BadRequestException('Vous ne pouvez modifier que vos propres quiz');
        }
    }


    private function updateQuizFields(Quiz $originalQuiz, Quiz $newData): void
    {
        // Liste des champs à vérifier et leurs setters
        $fieldsToUpdate = [
            'getTitle' => 'setTitle',
            'getDescription' => 'setDescription',
            'isActive' => 'setIsActive',
            'isStarted' => 'setIsStarted',
            'getPassingScore' => 'setPassingScore'
        ];

        // Mettre à jour chaque champ s'il n'est pas null
        foreach ($fieldsToUpdate as $getter => $setter) {
            $newValue = $newData->$getter();

            if ($newValue !== null) {
                $originalQuiz->$setter($newValue);
            }
        }
    }
}