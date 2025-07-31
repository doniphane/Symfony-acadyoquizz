<?php

namespace App\Controller;

use App\Entity\Quiz;
use App\Entity\QuizAttempt;
use App\Repository\QuizRepository;
use App\Repository\QuizAttemptRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[Route('/api')]
class ApiQuizController extends AbstractController
{
    #[Route('/quizzes', name: 'api_quizzes_list', methods: ['GET'])]
    public function list(QuizRepository $quizRepository): JsonResponse
    {
        $quizzes = $quizRepository->findBy(['isActive' => true]);

        $data = [];
        foreach ($quizzes as $quiz) {
            $data[] = [
                'id' => $quiz->getId(),
                'title' => $quiz->getTitle(),
                'description' => $quiz->getDescription(),
                'accessCode' => $quiz->getAccessCode(),
                'createdAt' => $quiz->getCreatedAt()->format('Y-m-d H:i:s'),
                'questionsCount' => $quiz->getQuestions()->count(),
            ];
        }

        return new JsonResponse($data);
    }

    #[Route('/quizzes/{id}', name: 'api_quiz_show', methods: ['GET'])]
    public function show(Quiz $quiz): JsonResponse
    {
        if (!$quiz->isActive()) {
            return new JsonResponse(['error' => 'Quiz non trouvé'], Response::HTTP_NOT_FOUND);
        }

        $questions = [];
        foreach ($quiz->getQuestions() as $question) {
            $answers = [];
            foreach ($question->getAnswers() as $answer) {
                $answers[] = [
                    'id' => $answer->getId(),
                    'text' => $answer->getText(),
                    'orderNumber' => $answer->getOrderNumber(),
                ];
            }

            $questions[] = [
                'id' => $question->getId(),
                'text' => $question->getText(),
                'orderNumber' => $question->getOrderNumber(),
                'answers' => $answers,
            ];
        }

        return new JsonResponse([
            'id' => $quiz->getId(),
            'title' => $quiz->getTitle(),
            'description' => $quiz->getDescription(),
            'questions' => $questions,
        ]);
    }

    #[Route('/quizzes/by-code/{code}', name: 'api_quiz_by_code', methods: ['GET'])]
    public function findByCode(string $code, QuizRepository $quizRepository): JsonResponse
    {
        $quiz = $quizRepository->findOneBy(['accessCode' => $code, 'isActive' => true]);

        if (!$quiz) {
            return new JsonResponse(['error' => 'Quiz non trouvé'], Response::HTTP_NOT_FOUND);
        }

        return $this->show($quiz);
    }

    #[Route('/quizzes/{id}/participate', name: 'api_quiz_participate', methods: ['POST'])]
    public function participate(Request $request, Quiz $quiz, EntityManagerInterface $entityManager): JsonResponse
    {
        if (!$quiz->isActive()) {
            return new JsonResponse(['error' => 'Quiz non trouvé'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return new JsonResponse(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        $firstName = $data['firstName'] ?? null;
        $lastName = $data['lastName'] ?? null;

        if (!$firstName || !$lastName) {
            return new JsonResponse(['error' => 'Missing required fields'], Response::HTTP_BAD_REQUEST);
        }

        // Créer une nouvelle tentative de quiz
        $quizAttempt = new QuizAttempt();
        $quizAttempt->setQuiz($quiz);
        $quizAttempt->setParticipantFirstName($firstName);
        $quizAttempt->setParticipantLastName($lastName);

        $entityManager->persist($quizAttempt);
        $entityManager->flush();

        return new JsonResponse([
            'attemptId' => $quizAttempt->getId(),
            'quizId' => $quiz->getId(),
        ], Response::HTTP_CREATED);
    }

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

        return new JsonResponse([
            'score' => $quizAttempt->getScore(),
            'totalQuestions' => $quizAttempt->getTotalQuestions(),
            'percentage' => $quizAttempt->getPercentage(),
        ]);
    }
}