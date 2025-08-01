<?php

namespace App\Controller;

use App\Entity\Quiz;
use App\Entity\QuizAttempt;
use App\Repository\QuizAttemptRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api')]
class ApiQuizController extends AbstractController
{
    /**
     * Chercher un quiz par son code d'accès (pour les étudiants)
     */
    #[Route('/public/quizzes/by-code/{code}', name: 'api_quiz_by_code', methods: ['GET'])]
    public function findByCode(string $code, EntityManagerInterface $entityManager): JsonResponse
    {
        $quiz = $entityManager->getRepository(Quiz::class)->findOneBy([
            'accessCode' => $code,
            'isActive' => true
        ]);

        if (!$quiz) {
            return new JsonResponse(['error' => 'Quiz non trouvé'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
            'id' => $quiz->getId(),
            'title' => $quiz->getTitle(),
            'description' => $quiz->getDescription(),
            'accessCode' => $quiz->getAccessCode(),
            'isActive' => $quiz->isActive()
        ]);
    }

    /**
     * Soumettre les réponses d'un quiz et calculer le score
     * Cette méthode contient de la logique métier complexe qui ne peut pas être facilement
     * gérée par un State Processor standard
     */
    #[Route('/quizzes/{id}/submit', name: 'api_quiz_submit', methods: ['POST'])]
    public function submit(Request $request, Quiz $quiz, EntityManagerInterface $entityManager, QuizAttemptRepository $quizAttemptRepository): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return new JsonResponse(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        $attemptId = $data['attemptId'] ?? null;
        $answers = $data['answers'] ?? null;

        if (!$attemptId || !$answers) {
            return new JsonResponse(['error' => 'Missing required fields'], Response::HTTP_BAD_REQUEST);
        }

        $quizAttempt = $quizAttemptRepository->find($attemptId);

        if (!$quizAttempt || $quizAttempt->getQuiz()->getId() !== $quiz->getId()) {
            return new JsonResponse(['error' => 'Invalid attempt'], Response::HTTP_BAD_REQUEST);
        }

        // Supprimer les anciennes réponses si elles existent
        $existingAnswers = $entityManager->getRepository('App\Entity\UserAnswer')
            ->findBy(['quizAttempt' => $quizAttempt]);
        foreach ($existingAnswers as $existingAnswer) {
            $entityManager->remove($existingAnswer);
        }

        // Enregistrer les nouvelles réponses
        foreach ($answers as $questionId => $answerId) {
            if (!empty($answerId)) {
                $question = $entityManager->getRepository('App\Entity\Question')->find($questionId);
                $answer = $entityManager->getRepository('App\Entity\Answer')->find($answerId);

                if ($question && $answer) {
                    $userAnswer = new \App\Entity\UserAnswer();
                    $userAnswer->setQuizAttempt($quizAttempt);
                    $userAnswer->setQuestion($question);
                    $userAnswer->setAnswer($answer);

                    $entityManager->persist($userAnswer);
                }
            }
        }

        // Persister d'abord les réponses
        $entityManager->flush();

        // Recharger les réponses pour le calcul du score
        $entityManager->refresh($quizAttempt);

        $quizAttempt->setCompletedAt(new \DateTimeImmutable());
        $quizAttempt->calculateScore();

        // Persister le score mis à jour
        $entityManager->flush();

        // Préparer les détails des réponses pour le frontend
        $responseDetails = [];
        foreach ($answers as $questionId => $answerId) {
            $question = $entityManager->getRepository('App\Entity\Question')->find($questionId);
            $userAnswer = $entityManager->getRepository('App\Entity\Answer')->find($answerId);

            if ($question && $userAnswer) {
                // Trouver la bonne réponse
                $correctAnswer = $entityManager->getRepository('App\Entity\Answer')
                    ->findOneBy(['question' => $question, 'isCorrect' => true]);

                $responseDetails[] = [
                    'questionId' => $questionId,
                    'questionText' => $question->getText(),
                    'userAnswer' => [
                        'id' => $userAnswer->getId(),
                        'text' => $userAnswer->getText(),
                        'isCorrect' => $userAnswer->isCorrect()
                    ],
                    'correctAnswer' => $correctAnswer ? [
                        'id' => $correctAnswer->getId(),
                        'text' => $correctAnswer->getText(),
                        'isCorrect' => $correctAnswer->isCorrect()
                    ] : null,
                    'isCorrect' => $userAnswer->isCorrect()
                ];
            }
        }

        return new JsonResponse([
            'score' => $quizAttempt->getScore(),
            'totalQuestions' => $quizAttempt->getTotalQuestions(),
            'percentage' => $quizAttempt->getPercentage(),
            'responseDetails' => $responseDetails
        ]);
    }
}