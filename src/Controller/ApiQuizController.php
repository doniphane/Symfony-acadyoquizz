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

#[Route('/api')]
class ApiQuizController extends AbstractController
{
    // Récupérer la liste des quiz actifs
    #[Route('/quizzes', name: 'api_quizzes_list', methods: ['GET'])]
    public function list(QuizRepository $quizRepository): JsonResponse
    {
        $quizzes = $quizRepository->findBy(['isActive' => true]);
        $data = $this->formatQuizzesList($quizzes);

        return new JsonResponse($data);
    }

    // Récupérer un quiz avec ses questions
    #[Route('/quizzes/{id}', name: 'api_quiz_show', methods: ['GET'])]
    public function show(Quiz $quiz): JsonResponse
    {
        // Vérifier que le quiz est actif
        if (!$quiz->isActive()) {
            return $this->errorResponse('Quiz non trouvé', Response::HTTP_NOT_FOUND);
        }

        $data = $this->formatQuizWithQuestions($quiz);
        return new JsonResponse($data);
    }

    // Trouver un quiz par son code d'accès
    #[Route('/quizzes/by-code/{code}', name: 'api_quiz_by_code', methods: ['GET'])]
    public function findByCode(string $code, QuizRepository $quizRepository): JsonResponse
    {
        $quiz = $quizRepository->findOneBy(['accessCode' => $code, 'isActive' => true]);

        if (!$quiz) {
            return $this->errorResponse('Quiz non trouvé', Response::HTTP_NOT_FOUND);
        }

        return $this->show($quiz);
    }

    // Participer à un quiz (créer une tentative)
    #[Route('/quizzes/{id}/participate', name: 'api_quiz_participate', methods: ['POST'])]
    public function participate(Request $request, Quiz $quiz, EntityManagerInterface $entityManager): JsonResponse
    {
        // Vérifier que le quiz est actif
        if (!$quiz->isActive()) {
            return $this->errorResponse('Quiz non trouvé', Response::HTTP_NOT_FOUND);
        }

        // Récupérer et valider les données
        $data = $this->getJsonData($request);
        if (!$data) {
            return $this->errorResponse('Données JSON invalides', Response::HTTP_BAD_REQUEST);
        }

        $participantData = $this->validateParticipantData($data);
        if (!$participantData) {
            return $this->errorResponse('Prénom et nom requis', Response::HTTP_BAD_REQUEST);
        }

        // Créer la tentative
        $quizAttempt = $this->createQuizAttempt($quiz, $participantData, $entityManager);

        return new JsonResponse([
            'attemptId' => $quizAttempt->getId(),
            'quizId' => $quiz->getId(),
        ], Response::HTTP_CREATED);
    }

    // Soumettre les réponses d'un quiz
    #[Route('/quizzes/{id}/submit', name: 'api_quiz_submit', methods: ['POST'])]
    public function submit(Request $request, Quiz $quiz, EntityManagerInterface $entityManager, QuizAttemptRepository $quizAttemptRepository): JsonResponse
    {
        // Récupérer et valider les données
        $data = $this->getJsonData($request);
        if (!$data) {
            return $this->errorResponse('Données JSON invalides', Response::HTTP_BAD_REQUEST);
        }

        $submissionData = $this->validateSubmissionData($data);
        if (!$submissionData) {
            return $this->errorResponse('ID de tentative et réponses requis', Response::HTTP_BAD_REQUEST);
        }

        // Vérifier la tentative
        $quizAttempt = $this->getValidQuizAttempt($submissionData['attemptId'], $quiz, $quizAttemptRepository);
        if (!$quizAttempt) {
            return $this->errorResponse('Tentative invalide', Response::HTTP_BAD_REQUEST);
        }

        // Traiter les réponses
        $this->processQuizAnswers($quizAttempt, $submissionData['answers'], $entityManager);

        // Calculer le score
        $results = $this->calculateQuizResults($quizAttempt, $entityManager);

        return new JsonResponse($results);
    }

    // === MÉTHODES HELPER PRIVÉES ===

    // Formater la liste des quiz
    private function formatQuizzesList(array $quizzes): array
    {
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
        return $data;
    }

    // Formater un quiz avec ses questions
    private function formatQuizWithQuestions(Quiz $quiz): array
    {
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

        return [
            'id' => $quiz->getId(),
            'title' => $quiz->getTitle(),
            'description' => $quiz->getDescription(),
            'questions' => $questions,
        ];
    }

    // Décoder les données JSON de la requête
    private function getJsonData(Request $request): ?array
    {
        return json_decode($request->getContent(), true);
    }

    // Valider les données du participant
    private function validateParticipantData(array $data): ?array
    {
        $firstName = $data['firstName'] ?? null;
        $lastName = $data['lastName'] ?? null;

        if (!$firstName || !$lastName) {
            return null;
        }

        return ['firstName' => $firstName, 'lastName' => $lastName];
    }

    // Valider les données de soumission
    private function validateSubmissionData(array $data): ?array
    {
        $attemptId = $data['attemptId'] ?? null;
        $answers = $data['answers'] ?? null;

        if (!$attemptId || !$answers) {
            return null;
        }

        return ['attemptId' => $attemptId, 'answers' => $answers];
    }

    // Créer une tentative de quiz
    private function createQuizAttempt(Quiz $quiz, array $participantData, EntityManagerInterface $entityManager): QuizAttempt
    {
        $quizAttempt = new QuizAttempt();
        $quizAttempt->setQuiz($quiz);
        $quizAttempt->setParticipantFirstName($participantData['firstName']);
        $quizAttempt->setParticipantLastName($participantData['lastName']);

        $entityManager->persist($quizAttempt);
        $entityManager->flush();

        return $quizAttempt;
    }

    // Récupérer et valider une tentative de quiz
    private function getValidQuizAttempt(int $attemptId, Quiz $quiz, QuizAttemptRepository $repository): ?QuizAttempt
    {
        $quizAttempt = $repository->find($attemptId);

        if (!$quizAttempt || $quizAttempt->getQuiz()->getId() !== $quiz->getId()) {
            return null;
        }

        return $quizAttempt;
    }

    // Traiter les réponses du quiz
    private function processQuizAnswers(QuizAttempt $quizAttempt, array $answers, EntityManagerInterface $entityManager): void
    {
        // Supprimer les anciennes réponses
        $this->removeOldAnswers($quizAttempt, $entityManager);

        // Enregistrer les nouvelles réponses
        $this->saveNewAnswers($quizAttempt, $answers, $entityManager);

        $entityManager->flush();
    }

    // Supprimer les anciennes réponses
    private function removeOldAnswers(QuizAttempt $quizAttempt, EntityManagerInterface $entityManager): void
    {
        $existingAnswers = $entityManager->getRepository('App\Entity\UserAnswer')
            ->findBy(['quizAttempt' => $quizAttempt]);

        foreach ($existingAnswers as $existingAnswer) {
            $entityManager->remove($existingAnswer);
        }
    }

    // Enregistrer les nouvelles réponses
    private function saveNewAnswers(QuizAttempt $quizAttempt, array $answers, EntityManagerInterface $entityManager): void
    {
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
    }

    // Calculer les résultats du quiz
    private function calculateQuizResults(QuizAttempt $quizAttempt, EntityManagerInterface $entityManager): array
    {
        // Recharger pour avoir les nouvelles réponses
        $entityManager->refresh($quizAttempt);

        $quizAttempt->setCompletedAt(new \DateTimeImmutable());
        $quizAttempt->calculateScore();

        $entityManager->flush();

        return [
            'score' => $quizAttempt->getScore(),
            'totalQuestions' => $quizAttempt->getTotalQuestions(),
            'percentage' => $quizAttempt->getPercentage(),
        ];
    }


    private function errorResponse(string $message, int $statusCode): JsonResponse
    {
        return new JsonResponse(['error' => $message], $statusCode);
    }
}