<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Questionnaire;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Provider pour les données publiques de questionnaire
 * 
 * Ce provider gère les endpoints GET /api/public/questionnaires et /api/public/questionnaires/{id}
 * Il permet de récupérer les questionnaires actifs pour le public (sans authentification).
 * 
 * Fonctionnalités :
 * - Récupération des questionnaires actifs uniquement
 * - Accès public sans authentification requise
 * - Filtrage automatique : seuls les questionnaires actifs sont visibles
 * - Tri par date de création décroissante
 * - Utilisé pour l'accès public aux quiz
 * 
 * Utilisé par :
 * - La page d'accueil publique des quiz
 * - L'accès aux quiz par code d'accès
 * - L'affichage des quiz disponibles pour les étudiants
 * 
 * Différence avec QuestionnaireDataProvider :
 * - Ce provider est PUBLIC (pas d'authentification)
 * - Il ne retourne que les questionnaires ACTIFS
 * - L'autre provider est privé et retourne tous les questionnaires de l'utilisateur
 */
class QuestionnairePublicDataProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Questionnaire|array|null
    {
        // Si on demande un questionnaire spécifique
        if (isset($uriVariables['id'])) {
            // Utiliser une requête optimisée pour charger les questions avec leurs réponses
            $qb = $this->entityManager->createQueryBuilder();
            $qb->select('q', 'questions', 'reponses')
                ->from(Questionnaire::class, 'q')
                ->leftJoin('q.questions', 'questions')
                ->leftJoin('questions.reponses', 'reponses')
                ->where('q.id = :id')
                ->andWhere('q.estActif = :estActif')
                ->setParameter('id', $uriVariables['id'])
                ->setParameter('estActif', true)
                ->orderBy('questions.numeroOrdre', 'ASC')
                ->addOrderBy('reponses.numeroOrdre', 'ASC');

            $questionnaire = $qb->getQuery()->getOneOrNullResult();

            if (!$questionnaire) {
                return null;
            }

            // Forcer le chargement des relations pour éviter les problèmes de lazy loading
            $this->entityManager->refresh($questionnaire);

            return $questionnaire;
        }

        // Si on demande la liste des questionnaires publics
        $qb = $this->entityManager->getRepository(Questionnaire::class)
            ->createQueryBuilder('q')
            ->where('q.estActif = :estActif')
            ->setParameter('estActif', true)
            ->orderBy('q.dateCreation', 'DESC');

        return $qb->getQuery()->getResult();
    }
}