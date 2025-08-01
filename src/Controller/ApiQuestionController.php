<?php

namespace App\Controller;

use App\Entity\Quiz;
use App\Entity\Question;
use App\Entity\Answer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api')]
class ApiQuestionController extends AbstractController
{

    #[Route('/quizzes/{id}/questions', name: 'api_quiz_add_question', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function addQuestion(
        Request $request,
        int $id,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        // Récupérer le quiz par son ID
        $quiz = $entityManager->getRepository(Quiz::class)->find($id);

        if (!$quiz) {
            return new JsonResponse(['error' => 'Quiz non trouvé'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier que l'utilisateur est propriétaire du quiz
        if ($quiz->getCreatedBy() !== $this->getUser()) {
            return new JsonResponse(['error' => 'Accès non autorisé - vous n\'êtes pas le propriétaire de ce quiz'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);

        // Validation des données
        if (!isset($data['text']) || empty($data['text'])) {
            return new JsonResponse(['error' => 'Le texte de la question est obligatoire'], Response::HTTP_BAD_REQUEST);
        }

        if (!isset($data['answers']) || !is_array($data['answers']) || count($data['answers']) < 2) {
            return new JsonResponse(['error' => 'Il faut au moins 2 réponses'], Response::HTTP_BAD_REQUEST);
        }

        $hasCorrectAnswer = false;
        foreach ($data['answers'] as $answerData) {
            if (!isset($answerData['text']) || empty($answerData['text'])) {
                return new JsonResponse(['error' => 'Toutes les réponses doivent avoir un texte'], Response::HTTP_BAD_REQUEST);
            }
            if (isset($answerData['correct']) && $answerData['correct']) {
                $hasCorrectAnswer = true;
            }
        }

        if (!$hasCorrectAnswer) {
            return new JsonResponse(['error' => 'Il faut au moins une réponse correcte'], Response::HTTP_BAD_REQUEST);
        }

        try {
            // Créer la question
            $question = new Question();
            $question->setText($data['text']);
            $question->setQuiz($quiz);
            $question->setOrderNumber(count($quiz->getQuestions()) + 1);

            $entityManager->persist($question);

            // Créer les réponses
            foreach ($data['answers'] as $index => $answerData) {
                $answer = new Answer();
                $answer->setText($answerData['text']);
                $answer->setIsCorrect($answerData['correct'] ?? false);
                $answer->setQuestion($question);
                $answer->setOrderNumber($index + 1);

                $entityManager->persist($answer);
            }

            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Question ajoutée avec succès',
                'question' => [
                    'id' => $question->getId(),
                    'text' => $question->getText(),
                    'orderNumber' => $question->getOrderNumber(),
                    'answers' => $question->getAnswers()->map(function ($answer) {
                        return [
                            'id' => $answer->getId(),
                            'text' => $answer->getText(),
                            'correct' => $answer->isCorrect(),
                            'orderNumber' => $answer->getOrderNumber()
                        ];
                    })->toArray()
                ]
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Erreur lors de l\'ajout de la question: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}