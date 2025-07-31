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

#[Route('/api')]
class ApiQuizAdminController extends AbstractController
{
    // Créer un nouveau quiz
    #[Route('/quizzes', name: 'api_admin_quiz_create', methods: ['POST'])]
    public function createQuiz(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        // Vérifier les permissions
        if (!$this->checkAdminAccess()) {
            return $this->errorResponse('Accès refusé', Response::HTTP_FORBIDDEN);
        }

        // Récupérer et valider les données
        $data = $this->getJsonData($request);
        if (!$data) {
            return $this->errorResponse('Données JSON invalides', Response::HTTP_BAD_REQUEST);
        }

        $validationError = $this->validateQuizData($data);
        if ($validationError) {
            return $this->errorResponse($validationError, Response::HTTP_BAD_REQUEST);
        }

        try {
            // Créer le quiz avec ses questions
            $quiz = $this->createQuizWithQuestions($data, $entityManager);

            // Sauvegarder avec gestion des codes uniques
            $this->saveQuizWithUniqueCode($quiz, $entityManager);

            return new JsonResponse($this->formatQuizResponse($quiz), Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la création: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // Mettre à jour un quiz
    #[Route('/quizzes/{id}', name: 'api_admin_quiz_update', methods: ['PUT'])]
    public function updateQuiz(Request $request, Quiz $quiz, EntityManagerInterface $entityManager): JsonResponse
    {
        // Vérifier les permissions
        $accessError = $this->checkQuizAccess($quiz);
        if ($accessError) {
            return $accessError;
        }

        // Récupérer et valider les données
        $data = $this->getJsonData($request);
        if (!$data) {
            return $this->errorResponse('Données JSON invalides', Response::HTTP_BAD_REQUEST);
        }

        // Mettre à jour les champs
        $this->updateQuizFields($quiz, $data);
        $entityManager->flush();

        return new JsonResponse($this->formatQuizResponse($quiz));
    }

    // Récupérer un quiz avec ses questions
    #[Route('/quizzes/{id}/with-questions', name: 'api_admin_quiz_with_questions', methods: ['GET'])]
    public function getQuizWithQuestions(Quiz $quiz): JsonResponse
    {
        // Vérifier les permissions
        $accessError = $this->checkQuizAccess($quiz);
        if ($accessError) {
            return $accessError;
        }

        $data = $this->formatQuizWithQuestions($quiz);
        return new JsonResponse($data);
    }

    // Supprimer un quiz
    #[Route('/quizzes/{id}', name: 'api_admin_quiz_delete', methods: ['DELETE'])]
    public function deleteQuiz(Quiz $quiz, EntityManagerInterface $entityManager): JsonResponse
    {
        // Vérifier les permissions
        $accessError = $this->checkQuizAccess($quiz);
        if ($accessError) {
            return $accessError;
        }

        $entityManager->remove($quiz);
        $entityManager->flush();

        return new JsonResponse(['message' => 'Quiz supprimé avec succès']);
    }

    // Récupérer mes quiz
    #[Route('/quizzes/my', name: 'api_admin_my_quizzes', methods: ['GET'])]
    public function getMyQuizzes(EntityManagerInterface $entityManager): JsonResponse
    {
        // Vérifier les permissions
        if (!$this->checkAdminAccess()) {
            return $this->errorResponse('Accès refusé', Response::HTTP_FORBIDDEN);
        }

        $quizzes = $this->getUserQuizzes($entityManager);
        $data = $this->formatQuizzesList($quizzes);

        return new JsonResponse($data);
    }

    // Ajouter une question à un quiz
    #[Route('/quizzes/{id}/questions', name: 'api_quiz_add_question', methods: ['POST'])]
    public function addQuestion(Request $request, int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        // Trouver le quiz
        $quiz = $entityManager->getRepository(Quiz::class)->find($id);
        if (!$quiz) {
            return $this->errorResponse('Quiz non trouvé', Response::HTTP_NOT_FOUND);
        }

        // Vérifier les permissions
        $accessError = $this->checkQuizAccess($quiz);
        if ($accessError) {
            return $accessError;
        }

        // Récupérer et valider les données
        $data = $this->getJsonData($request);
        if (!$data) {
            return $this->errorResponse('Données JSON invalides', Response::HTTP_BAD_REQUEST);
        }

        $validationError = $this->validateQuestionData($data);
        if ($validationError) {
            return $this->errorResponse($validationError, Response::HTTP_BAD_REQUEST);
        }

        try {
            $question = $this->createQuestionWithAnswers($quiz, $data, $entityManager);

            return new JsonResponse([
                'success' => true,
                'message' => 'Question ajoutée avec succès',
                'question' => $this->formatQuestionResponse($question)
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de l\'ajout: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // === MÉTHODES HELPER PRIVÉES ===

    // Vérifier l'accès admin
    private function checkAdminAccess(): bool
    {
        return $this->isGranted('ROLE_ADMIN');
    }

    // Vérifier l'accès à un quiz spécifique
    private function checkQuizAccess(Quiz $quiz): ?JsonResponse
    {
        if (!$this->checkAdminAccess()) {
            return $this->errorResponse('Accès refusé', Response::HTTP_FORBIDDEN);
        }

        if ($quiz->getCreatedBy() !== $this->getUser()) {
            return $this->errorResponse('Vous ne pouvez accéder qu\'à vos propres quiz', Response::HTTP_FORBIDDEN);
        }

        return null; // Pas d'erreur
    }

    // Récupérer les données JSON
    private function getJsonData(Request $request): ?array
    {
        return json_decode($request->getContent(), true);
    }

    // Valider les données du quiz
    private function validateQuizData(array $data): ?string
    {
        if (!isset($data['title']) || empty($data['title'])) {
            return 'Le titre est obligatoire';
        }

        if (!isset($data['questions']) || empty($data['questions'])) {
            return 'Au moins une question est requise';
        }

        return null; // Pas d'erreur
    }

    // Valider les données d'une question
    private function validateQuestionData(array $data): ?string
    {
        if (!isset($data['text']) || empty($data['text'])) {
            return 'Le texte de la question est obligatoire';
        }

        if (!isset($data['answers']) || !is_array($data['answers']) || count($data['answers']) < 2) {
            return 'Il faut au moins 2 réponses';
        }

        // Vérifier qu'il y a au moins une réponse correcte
        $hasCorrectAnswer = false;
        foreach ($data['answers'] as $answer) {
            if (!isset($answer['text']) || empty($answer['text'])) {
                return 'Toutes les réponses doivent avoir un texte';
            }
            if (isset($answer['isCorrect']) && $answer['isCorrect']) {
                $hasCorrectAnswer = true;
            }
        }

        if (!$hasCorrectAnswer) {
            return 'Il faut au moins une réponse correcte';
        }

        return null; // Pas d'erreur
    }

    // Créer un quiz avec ses questions
    private function createQuizWithQuestions(array $data, EntityManagerInterface $entityManager): Quiz
    {
        // Créer le quiz
        $quiz = new Quiz();
        $quiz->setTitle($data['title']);
        $quiz->setDescription($data['description'] ?? null);
        $quiz->setIsActive($data['isActive'] ?? true);
        $quiz->setIsStarted($data['isStarted'] ?? false);
        $quiz->setPassingScore($data['passingScore'] ?? 70);
        $quiz->setCreatedBy($this->getUser());

        $entityManager->persist($quiz);

        // Créer les questions
        foreach ($data['questions'] as $questionData) {
            $this->createQuestionForQuiz($quiz, $questionData, $entityManager);
        }

        return $quiz;
    }

    // Créer une question pour un quiz
    private function createQuestionForQuiz(Quiz $quiz, array $questionData, EntityManagerInterface $entityManager): void
    {
        if (empty($questionData['text'])) {
            return; // Ignorer les questions vides
        }

        $question = new Question();
        $question->setText($questionData['text']);
        $question->setOrderNumber($questionData['orderNumber'] ?? 1);
        $question->setQuiz($quiz);

        $entityManager->persist($question);

        // Créer les réponses
        foreach ($questionData['answers'] ?? [] as $answerData) {
            $this->createAnswerForQuestion($question, $answerData, $entityManager);
        }
    }

    // Créer une réponse pour une question
    private function createAnswerForQuestion(Question $question, array $answerData, EntityManagerInterface $entityManager): void
    {
        if (empty($answerData['text'])) {
            return; // Ignorer les réponses vides
        }

        $answer = new Answer();
        $answer->setText($answerData['text']);
        $answer->setIsCorrect($answerData['isCorrect'] ?? false);
        $answer->setOrderNumber($answerData['orderNumber'] ?? 1);
        $answer->setQuestion($question);

        $entityManager->persist($answer);
    }

    // Sauvegarder avec gestion des codes uniques
    private function saveQuizWithUniqueCode(Quiz $quiz, EntityManagerInterface $entityManager): void
    {
        $maxAttempts = 10;
        $attempts = 0;

        while ($attempts < $maxAttempts) {
            try {
                $entityManager->flush();
                break; // Succès
            } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
                $attempts++;
                if ($attempts >= $maxAttempts) {
                    throw new \Exception('Impossible de générer un code unique après ' . $maxAttempts . ' tentatives');
                }
                $quiz->regenerateAccessCode();
            }
        }
    }

    // Mettre à jour les champs du quiz
    private function updateQuizFields(Quiz $quiz, array $data): void
    {
        if (isset($data['title'])) {
            $quiz->setTitle($data['title']);
        }
        if (isset($data['description'])) {
            $quiz->setDescription($data['description']);
        }
        if (isset($data['isActive'])) {
            $quiz->setIsActive($data['isActive']);
        }
        if (isset($data['isStarted'])) {
            $quiz->setIsStarted($data['isStarted']);
        }
        if (isset($data['passingScore'])) {
            $quiz->setPassingScore($data['passingScore']);
        }
    }

    // Récupérer les quiz de l'utilisateur
    private function getUserQuizzes(EntityManagerInterface $entityManager): array
    {
        return $entityManager->getRepository(Quiz::class)
            ->findBy(['createdBy' => $this->getUser()], ['createdAt' => 'DESC']);
    }

    // Créer une question avec ses réponses
    private function createQuestionWithAnswers(Quiz $quiz, array $data, EntityManagerInterface $entityManager): Question
    {
        $question = new Question();
        $question->setText($data['text']);
        $question->setQuiz($quiz);
        $question->setOrderNumber(count($quiz->getQuestions()) + 1);

        $entityManager->persist($question);

        // Créer les réponses
        foreach ($data['answers'] as $index => $answerData) {
            $answer = new Answer();
            $answer->setText($answerData['text']);
            $answer->setIsCorrect($answerData['isCorrect'] ?? false);
            $answer->setQuestion($question);
            $answer->setOrderNumber($index + 1);

            $entityManager->persist($answer);
        }

        $entityManager->flush();
        return $question;
    }

    // === MÉTHODES DE FORMATAGE ===

    // Formater la réponse d'un quiz
    private function formatQuizResponse(Quiz $quiz): array
    {
        return [
            'id' => $quiz->getId(),
            'title' => $quiz->getTitle(),
            'description' => $quiz->getDescription(),
            'uniqueCode' => $quiz->getAccessCode(),
            'accessCode' => $quiz->getAccessCode(),
            'isActive' => $quiz->isActive(),
            'isStarted' => $quiz->isStarted(),
            'passingScore' => $quiz->getPassingScore(),
            'createdAt' => $quiz->getCreatedAt()->format('Y-m-d H:i:s'),
            'questions' => $quiz->getQuestions()->count()
        ];
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
                    'isCorrect' => $answer->isCorrect(),
                    'orderNumber' => $answer->getOrderNumber()
                ];
            }

            $questions[] = [
                'id' => $question->getId(),
                'text' => $question->getText(),
                'orderNumber' => $question->getOrderNumber(),
                'answers' => $answers
            ];
        }

        return [
            'id' => $quiz->getId(),
            'title' => $quiz->getTitle(),
            'description' => $quiz->getDescription(),
            'uniqueCode' => $quiz->getAccessCode(),
            'accessCode' => $quiz->getAccessCode(),
            'isActive' => $quiz->isActive(),
            'isStarted' => $quiz->isStarted(),
            'passingScore' => $quiz->getPassingScore(),
            'createdAt' => $quiz->getCreatedAt()->format('Y-m-d H:i:s'),
            'questions' => $questions
        ];
    }

    // Formater la liste des quiz
    private function formatQuizzesList(array $quizzes): array
    {
        $data = [];
        foreach ($quizzes as $quiz) {
            $data[] = [
                'id' => $quiz->getId(),
                'title' => $quiz->getTitle(),
                'description' => $quiz->getDescription(),
                'uniqueCode' => $quiz->getAccessCode(),
                'accessCode' => $quiz->getAccessCode(),
                'isActive' => $quiz->isActive(),
                'isStarted' => $quiz->isStarted(),
                'passingScore' => $quiz->getPassingScore(),
                'createdAt' => $quiz->getCreatedAt()->format('Y-m-d H:i:s'),
                'questionsCount' => $quiz->getQuestions()->count(),
                'attemptsCount' => $quiz->getQuizAttempts()->count()
            ];
        }
        return $data;
    }

    // Formater la réponse d'une question
    private function formatQuestionResponse(Question $question): array
    {
        return [
            'id' => $question->getId(),
            'text' => $question->getText(),
            'orderNumber' => $question->getOrderNumber(),
            'answers' => $question->getAnswers()->map(function ($answer) {
                return [
                    'id' => $answer->getId(),
                    'text' => $answer->getText(),
                    'isCorrect' => $answer->isCorrect(),
                    'orderNumber' => $answer->getOrderNumber()
                ];
            })->toArray()
        ];
    }

    // Réponse d'erreur standardisée
    private function errorResponse(string $message, int $statusCode): JsonResponse
    {
        return new JsonResponse(['error' => $message], $statusCode);
    }
}