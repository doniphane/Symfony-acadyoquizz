<?php

namespace App\Controller;

use App\Entity\TentativeQuestionnaire;
use App\Entity\Questionnaire;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Contrôleur pour les endpoints spécifiques à l'utilisateur connecté
 */
#[Route('/api/user')]
class ApiUtilisateurController extends AbstractController
{
    /**
     * Récupérer les tentatives de questionnaire de l'utilisateur connecté
     */
    #[Route('/my-attempts', name: 'api_utilisateur_my_attempts', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getMyQuizAttempts(EntityManagerInterface $entityManager): JsonResponse
    {
        // Récupérer l'utilisateur connecté
        $utilisateur = $this->getUser();
        if (!$utilisateur) {
            return new JsonResponse(['error' => 'Utilisateur non connecté'], Response::HTTP_UNAUTHORIZED);
        }

        // Récupérer les tentatives de l'utilisateur connecté
        $tentatives = $entityManager->getRepository(TentativeQuestionnaire::class)
            ->createQueryBuilder('tq')
            ->leftJoin('tq.questionnaire', 'q')
            ->addSelect('q')
            ->where('tq.utilisateur = :utilisateur')
            ->setParameter('utilisateur', $utilisateur)
            ->orderBy('tq.dateDebut', 'DESC')
            ->getQuery()
            ->getResult();

        // Transformer les données pour le frontend
        $tentativesData = [];
        foreach ($tentatives as $tentative) {
            $pourcentage = $tentative->getNombreTotalQuestions() > 0
                ? round(($tentative->getScore() / $tentative->getNombreTotalQuestions()) * 100)
                : 0;

            $tentativesData[] = [
                'id' => $tentative->getId(),
                'questionnaireTitre' => $tentative->getQuestionnaire()->getTitre(),
                'questionnaireCode' => $tentative->getQuestionnaire()->getCodeAcces(),
                'date' => $tentative->getDateDebut()->format('d/m/Y'),
                'heure' => $tentative->getDateDebut()->format('H:i'),
                'score' => $tentative->getScore(),
                'nombreTotalQuestions' => $tentative->getNombreTotalQuestions(),
                'pourcentage' => $pourcentage,
                'estReussi' => $pourcentage >= 70
            ];
        }

        return new JsonResponse($tentativesData);
    }

    /**
     * Récupérer les détails d'une tentative spécifique
     */
    #[Route('/my-attempts/{id}', name: 'api_utilisateur_attempt_detail', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getAttemptDetail(int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $utilisateur = $this->getUser();
        if (!$utilisateur) {
            return new JsonResponse(['error' => 'Utilisateur non connecté'], Response::HTTP_UNAUTHORIZED);
        }

        // Récupérer la tentative avec ses détails
        $tentative = $entityManager->getRepository(TentativeQuestionnaire::class)
            ->createQueryBuilder('tq')
            ->leftJoin('tq.questionnaire', 'q')
            ->leftJoin('tq.reponsesUtilisateur', 'ru')
            ->leftJoin('ru.question', 'question')
            ->leftJoin('ru.reponse', 'reponse')
            ->addSelect('q')
            ->addSelect('ru')
            ->addSelect('question')
            ->addSelect('reponse')
            ->where('tq.id = :id')
            ->andWhere('tq.utilisateur = :utilisateur')
            ->setParameter('id', $id)
            ->setParameter('utilisateur', $utilisateur)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$tentative) {
            return new JsonResponse(['error' => 'Tentative non trouvée'], Response::HTTP_NOT_FOUND);
        }

        // Préparer les détails des réponses
        $reponsesDetails = [];
        foreach ($tentative->getReponsesUtilisateur() as $reponseUtilisateur) {
            $question = $reponseUtilisateur->getQuestion();
            $reponse = $reponseUtilisateur->getReponse();

            // Trouver la réponse correcte pour cette question
            $reponseCorrecte = null;
            foreach ($question->getReponses() as $qReponse) {
                if ($qReponse->isCorrect()) {
                    $reponseCorrecte = $qReponse;
                    break;
                }
            }

            $reponsesDetails[] = [
                'questionId' => $question->getId(),
                'questionTexte' => $question->getTexte(),
                'reponseUtilisateurId' => $reponse->getId(),
                'reponseUtilisateurTexte' => $reponse->getTexte(),
                'estCorrecte' => $reponseUtilisateur->isCorrect(),
                'reponseCorrecteId' => $reponseCorrecte ? $reponseCorrecte->getId() : null,
                'reponseCorrecteTexte' => $reponseCorrecte ? $reponseCorrecte->getTexte() : null
            ];
        }

        $pourcentage = $tentative->getNombreTotalQuestions() > 0
            ? round(($tentative->getScore() / $tentative->getNombreTotalQuestions()) * 100)
            : 0;

        return new JsonResponse([
            'id' => $tentative->getId(),
            'questionnaire' => [
                'id' => $tentative->getQuestionnaire()->getId(),
                'titre' => $tentative->getQuestionnaire()->getTitre(),
                'codeAcces' => $tentative->getQuestionnaire()->getCodeAcces(),
                'scorePassage' => $tentative->getQuestionnaire()->getScorePassage()
            ],
            'dateDebut' => $tentative->getDateDebut()->format('d/m/Y H:i'),
            'dateFin' => $tentative->getDateFin() ? $tentative->getDateFin()->format('d/m/Y H:i') : null,
            'score' => $tentative->getScore(),
            'nombreTotalQuestions' => $tentative->getNombreTotalQuestions(),
            'pourcentage' => $pourcentage,
            'estReussi' => $pourcentage >= $tentative->getQuestionnaire()->getScorePassage(),
            'reponsesDetails' => $reponsesDetails
        ]);
    }

    /**
     * Récupérer les statistiques de l'utilisateur
     */
    #[Route('/stats', name: 'api_utilisateur_stats', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getUserStats(EntityManagerInterface $entityManager): JsonResponse
    {
        $utilisateur = $this->getUser();
        if (!$utilisateur) {
            return new JsonResponse(['error' => 'Utilisateur non connecté'], Response::HTTP_UNAUTHORIZED);
        }

        // Récupérer toutes les tentatives de l'utilisateur
        $tentatives = $entityManager->getRepository(TentativeQuestionnaire::class)
            ->createQueryBuilder('tq')
            ->leftJoin('tq.questionnaire', 'q')
            ->addSelect('q')
            ->where('tq.utilisateur = :utilisateur')
            ->setParameter('utilisateur', $utilisateur)
            ->getQuery()
            ->getResult();

        $totalTentatives = count($tentatives);
        $tentativesReussies = 0;
        $scoreTotal = 0;
        $questionnairesUniques = [];

        foreach ($tentatives as $tentative) {
            $pourcentage = $tentative->getNombreTotalQuestions() > 0
                ? round(($tentative->getScore() / $tentative->getNombreTotalQuestions()) * 100)
                : 0;

            if ($pourcentage >= $tentative->getQuestionnaire()->getScorePassage()) {
                $tentativesReussies++;
            }

            $scoreTotal += $pourcentage;
            $questionnairesUniques[$tentative->getQuestionnaire()->getId()] = $tentative->getQuestionnaire()->getTitre();
        }

        $moyenneScore = $totalTentatives > 0 ? round($scoreTotal / $totalTentatives, 2) : 0;
        $tauxReussite = $totalTentatives > 0 ? round(($tentativesReussies / $totalTentatives) * 100, 2) : 0;

        return new JsonResponse([
            'totalTentatives' => $totalTentatives,
            'tentativesReussies' => $tentativesReussies,
            'tauxReussite' => $tauxReussite,
            'moyenneScore' => $moyenneScore,
            'questionnairesUniques' => count($questionnairesUniques),
            'questionnaires' => array_values($questionnairesUniques)
        ]);
    }

    /**
     * Retourne les informations de l'utilisateur connecté
     */
    #[Route('/me', name: 'api_utilisateur_me', methods: ['GET'])]
    public function getCurrentUser(): JsonResponse
    {
        $utilisateur = $this->getUser();
        if (!$utilisateur) {
            return new JsonResponse(['error' => 'Utilisateur non connecté'], Response::HTTP_UNAUTHORIZED);
        }

        // Vérification de l'instance pour éviter les erreurs
        if (!method_exists($utilisateur, 'getId') || !method_exists($utilisateur, 'getEmail')) {
            return new JsonResponse(['error' => 'Utilisateur non valide'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse([
            'id' => $utilisateur->getId(),
            'email' => $utilisateur->getEmail(),
            'roles' => $utilisateur->getRoles(),
            'prenom' => method_exists($utilisateur, 'getPrenom') ? $utilisateur->getPrenom() : null,
            'nom' => method_exists($utilisateur, 'getNom') ? $utilisateur->getNom() : null
        ]);
    }
}