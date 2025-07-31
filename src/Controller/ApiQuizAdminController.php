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
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[Route('/api')]
class ApiQuizAdminController extends AbstractController
{
    #[Route('/quizzes', name: 'api_admin_quiz_create', methods: ['POST'])]
    public function createQuiz(Request $request, EntityManagerInterface $entityManager, TokenStorageInterface $tokenStorage): JsonResponse
    {
        // Vérifier que l'utilisateur est connecté et est admin
        $token = $tokenStorage->getToken();
        if (!$token || !$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['error' => 'Accès refusé'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return new JsonResponse(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        $title = $data['title'] ?? null;
        $description = $data['description'] ?? null;
        $isActive = $data['isActive'] ?? true;
        $isStarted = $data['isStarted'] ?? false;
        $passingScore = $data['passingScore'] ?? 70;
        $questions = $data['questions'] ?? [];

        if (!$title) {
            return new JsonResponse(['error' => 'Le titre est obligatoire'], Response::HTTP_BAD_REQUEST);
        }

        if (empty($questions)) {
            return new JsonResponse(['error' => 'Au moins une question est requise'], Response::HTTP_BAD_REQUEST);
        }

        try {
            // Créer le quiz
            $quiz = new Quiz();
            $quiz->setTitle($title);
            $quiz->setDescription($description);
            $quiz->setIsActive($isActive);
            $quiz->setIsStarted($isStarted);
            $quiz->setPassingScore($passingScore);
            $quiz->setCreatedBy($this->getUser());

            $entityManager->persist($quiz);

            // Créer les questions et réponses
            foreach ($questions as $questionData) {
                $questionText = $questionData['text'] ?? null;
                $orderNumber = $questionData['orderNumber'] ?? 1;
                $answers = $questionData['answers'] ?? [];

                if (!$questionText) {
                    continue;
                }

                $question = new Question();
                $question->setText($questionText);
                $question->setOrderNumber($orderNumber);
                $question->setQuiz($quiz);

                $entityManager->persist($question);

                // Créer les réponses
                foreach ($answers as $answerData) {
                    $answerText = $answerData['text'] ?? null;
                    $isCorrect = $answerData['isCorrect'] ?? false;
                    $answerOrderNumber = $answerData['orderNumber'] ?? 1;

                    if (!$answerText) {
                        continue;
                    }

                    $answer = new Answer();
                    $answer->setText($answerText);
                    $answer->setIsCorrect($isCorrect);
                    $answer->setOrderNumber($answerOrderNumber);
                    $answer->setQuestion($question);

                    $entityManager->persist($answer);
                }
            }

            // Gérer les de codes d'accès au quizz qui sont en double  lors du flush en base de données
            // En cas de conflit, on régénère le code d'accès jusqu'à 10
            // fois avant de renoncer
            $maxAttempts = 10;
            $attempts = 0;
            while ($attempts < $maxAttempts) {
                try {
                    $entityManager->flush();
                    break; // Si ça marche, on sort de la boucle
                } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
                    $attempts++;
                    if ($attempts >= $maxAttempts) {
                        throw new \Exception('Impossible de générer un code d\'accès unique après ' . $maxAttempts . ' tentatives');
                    }
                    // Régénérer le code d'accès et réessayer
                    $quiz->regenerateAccessCode();
                }
            }

            return new JsonResponse([
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
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Erreur lors de la création du quiz: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }



    #[Route('/quizzes/{id}', name: 'api_admin_quiz_update', methods: ['PUT'])]
    public function updateQuiz(Request $request, Quiz $quiz, EntityManagerInterface $entityManager, TokenStorageInterface $tokenStorage): JsonResponse
    {
        // Vérifier que l'utilisateur est connecté et est admin
        $token = $tokenStorage->getToken();
        if (!$token || !$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['error' => 'Accès refusé'], Response::HTTP_FORBIDDEN);
        }

        // Vérifier que l'utilisateur est le créateur du quiz
        if ($quiz->getCreatedBy() !== $this->getUser()) {
            return new JsonResponse(['error' => 'Vous ne pouvez modifier que vos propres quiz'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return new JsonResponse(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

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

        $entityManager->flush();

        return new JsonResponse([
            'id' => $quiz->getId(),
            'title' => $quiz->getTitle(),
            'description' => $quiz->getDescription(),
            'uniqueCode' => $quiz->getAccessCode(),
            'accessCode' => $quiz->getAccessCode(),
            'isActive' => $quiz->isActive(),
            'isStarted' => $quiz->isStarted(),
            'passingScore' => $quiz->getPassingScore(),
            'updatedAt' => $quiz->getCreatedAt()->format('Y-m-d H:i:s')
        ]);
    }

    #[Route('/quizzes/{id}/with-questions', name: 'api_admin_quiz_with_questions', methods: ['GET'])]
    public function getQuizWithQuestions(Quiz $quiz, EntityManagerInterface $entityManager, TokenStorageInterface $tokenStorage): JsonResponse
    {
        // Vérifier que l'utilisateur est connecté et est admin
        $token = $tokenStorage->getToken();
        if (!$token || !$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['error' => 'Accès refusé'], Response::HTTP_FORBIDDEN);
        }

        // Vérifier que l'utilisateur est le créateur du quiz
        if ($quiz->getCreatedBy() !== $this->getUser()) {
            return new JsonResponse(['error' => 'Vous ne pouvez voir que vos propres quiz'], Response::HTTP_FORBIDDEN);
        }

        // Récupérer les questions avec leurs réponses
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

        return new JsonResponse([
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
        ]);
    }

    #[Route('/quizzes/{id}', name: 'api_admin_quiz_delete', methods: ['DELETE'])]
    public function deleteQuiz(Quiz $quiz, EntityManagerInterface $entityManager, TokenStorageInterface $tokenStorage): JsonResponse
    {
        // Vérifier que l'utilisateur est connecté et est admin
        $token = $tokenStorage->getToken();
        if (!$token || !$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['error' => 'Accès refusé'], Response::HTTP_FORBIDDEN);
        }

        // Vérifier que l'utilisateur est le créateur du quiz
        if ($quiz->getCreatedBy() !== $this->getUser()) {
            return new JsonResponse(['error' => 'Vous ne pouvez supprimer que vos propres quiz'], Response::HTTP_FORBIDDEN);
        }

        $entityManager->remove($quiz);
        $entityManager->flush();

        return new JsonResponse(['message' => 'Quiz supprimé avec succès']);
    }

    #[Route('/quizzes/my', name: 'api_admin_my_quizzes', methods: ['GET'])]
    public function getMyQuizzes(EntityManagerInterface $entityManager, TokenStorageInterface $tokenStorage): JsonResponse
    {
        // Vérifier que l'utilisateur est connecté et est admin
        $token = $tokenStorage->getToken();
        if (!$token || !$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['error' => 'Accès refusé'], Response::HTTP_FORBIDDEN);
        }

        $quizzes = $entityManager->getRepository(Quiz::class)->findBy(['createdBy' => $this->getUser()], ['createdAt' => 'DESC']);

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

        return new JsonResponse($data);
    }

    /**
     * Ajouter une question à un quiz
     */
    #[Route('/quizzes/{id}/questions', name: 'api_quiz_add_question', methods: ['POST'])]
    public function addQuestion(
        Request $request,
        int $id,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        // Récupérer le quiz par son ID
        $quiz = $entityManager->getRepository(Quiz::class)->find($id);

        if (!$quiz) {
            return new JsonResponse(['error' => 'Quiz non trouvé'], 404);
        }
        // Vérifier que l'utilisateur est admin et propriétaire du quiz
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $user = $this->getUser();

        // Vérifier que l'utilisateur est propriétaire du quiz
        if ($quiz->getCreatedBy() === null) {
            return new JsonResponse(['error' => 'Quiz sans propriétaire'], 403);
        }

        if ($quiz->getCreatedBy() !== $user) {
            return new JsonResponse(['error' => 'Accès non autorisé - vous n\'êtes pas le propriétaire de ce quiz'], 403);
        }

        $data = json_decode($request->getContent(), true);

        // Validation des données
        if (!isset($data['text']) || empty($data['text'])) {
            return new JsonResponse(['error' => 'Le texte de la question est obligatoire'], 400);
        }

        if (!isset($data['answers']) || !is_array($data['answers']) || count($data['answers']) < 2) {
            return new JsonResponse(['error' => 'Il faut au moins 2 réponses'], 400);
        }

        $hasCorrectAnswer = false;
        foreach ($data['answers'] as $answerData) {
            if (!isset($answerData['text']) || empty($answerData['text'])) {
                return new JsonResponse(['error' => 'Toutes les réponses doivent avoir un texte'], 400);
            }
            if (isset($answerData['isCorrect']) && $answerData['isCorrect']) {
                $hasCorrectAnswer = true;
            }
        }

        if (!$hasCorrectAnswer) {
            return new JsonResponse(['error' => 'Il faut au moins une réponse correcte'], 400);
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
                $answer->setIsCorrect($answerData['isCorrect'] ?? false);
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
                            'isCorrect' => $answer->isCorrect(),
                            'orderNumber' => $answer->getOrderNumber()
                        ];
                    })->toArray()
                ]
            ]);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Erreur lors de l\'ajout de la question: ' . $e->getMessage()], 500);
        }
    }
}