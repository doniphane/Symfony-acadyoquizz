<?php

namespace App\Controller;

use App\Entity\Questionnaire;
use App\Entity\Question;
use App\Entity\Reponse;
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
        // Récupérer le questionnaire par son ID
        $questionnaire = $entityManager->getRepository(Questionnaire::class)->find($id);

        if (!$questionnaire) {
            return new JsonResponse(['error' => 'Questionnaire non trouvé'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier que l'utilisateur est propriétaire du questionnaire
        if ($questionnaire->getCreePar() !== $this->getUser()) {
            return new JsonResponse(['error' => 'Accès non autorisé - vous n\'êtes pas le propriétaire de ce questionnaire'], Response::HTTP_FORBIDDEN);
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
            $question->setTexte($data['text']);
            $question->setQuestionnaire($questionnaire);
            $question->setNumeroOrdre(count($questionnaire->getQuestions()) + 1);

            $entityManager->persist($question);

            // Créer les réponses
            foreach ($data['answers'] as $index => $answerData) {
                $reponse = new Reponse();
                $reponse->setTexte($answerData['text']);
                $reponse->setEstCorrecte($answerData['correct'] ?? false);
                $reponse->setQuestion($question);
                $reponse->setNumeroOrdre($index + 1);

                $entityManager->persist($reponse);
            }

            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Question ajoutée avec succès',
                'question' => [
                    'id' => $question->getId(),
                    'text' => $question->getTexte(),
                    'orderNumber' => $question->getNumeroOrdre(),
                    'answers' => $question->getReponses()->map(function ($reponse) {
                        return [
                            'id' => $reponse->getId(),
                            'text' => $reponse->getTexte(),
                            'correct' => $reponse->isCorrect(),
                            'orderNumber' => $reponse->getNumeroOrdre()
                        ];
                    })->toArray()
                ]
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Erreur lors de l\'ajout de la question: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}