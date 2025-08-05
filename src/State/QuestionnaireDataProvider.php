<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Questionnaire;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

/**
 * Provider pour les données de questionnaire
 * 
 * Ce provider gère les endpoints GET /api/questionnaires et /api/questionnaires/{id}
 * Il permet de récupérer les questionnaires avec filtrage intelligent.
 * 
 * Fonctionnalités :
 * - Utilisateurs normaux : voient leurs propres questionnaires
 * - Admins : voient tous les questionnaires de tous les utilisateurs
 * - Récupération d'un questionnaire spécifique avec vérification d'accès
 * - Tri par date de création décroissante
 * 
 * Utilisé par :
 * - La liste des quiz dans l'interface utilisateur
 * - L'affichage d'un quiz spécifique
 * - Le dashboard utilisateur et admin
 */
class QuestionnaireDataProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Questionnaire|array|null
    {
        // Récupérer l'utilisateur connecté
        $utilisateur = $this->security->getUser();
        if (!$utilisateur instanceof Utilisateur) {
            throw new BadRequestException('User not authenticated');
        }

        // Si on demande un questionnaire spécifique
        if (isset($uriVariables['id'])) {
            $questionnaire = $this->entityManager->getRepository(Questionnaire::class)->find($uriVariables['id']);

            if (!$questionnaire) {
                return null;
            }

            // Vérifier que l'utilisateur a accès à ce questionnaire
            if ($questionnaire->getCreePar() !== $utilisateur && !in_array('ROLE_ADMIN', $utilisateur->getRoles())) {
                throw new BadRequestException('Access denied');
            }

            return $questionnaire;
        }

        // Si on demande la liste des questionnaires
        $qb = $this->entityManager->getRepository(Questionnaire::class)
            ->createQueryBuilder('q')
            ->leftJoin('q.creePar', 'u')
            ->addSelect('u')
            ->orderBy('q.dateCreation', 'DESC');

        // Filtrer par utilisateur (sauf pour les admins)
        if (!in_array('ROLE_ADMIN', $utilisateur->getRoles())) {
            $qb->where('q.creePar = :utilisateur')
                ->setParameter('utilisateur', $utilisateur);
        }

        return $qb->getQuery()->getResult();
    }
}