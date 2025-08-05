<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Questionnaire;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

/**
 * Processor pour la persistance des données de questionnaire
 * 
 * Ce processor gère les opérations POST et PUT sur /api/questionnaires
 * Il permet de créer et mettre à jour des questionnaires simples
 * (sans questions/réponses automatiques).
 * 
 * Fonctionnalités :
 * - Création d'un questionnaire avec validation des données
 * - Mise à jour d'un questionnaire existant
 * - Association automatique à l'utilisateur connecté
 * - Génération automatique du code d'accès unique
 * - Définition des valeurs par défaut (actif, démarré, score passage)
 * - Vérification que l'utilisateur est le créateur du questionnaire
 * 
 * Utilisé par :
 * - L'API pour créer des questionnaires vides
 * - L'API pour mettre à jour les propriétés d'un questionnaire
 * - Le formulaire de création de quiz simple
 * 
 * Différence avec QuestionnaireAvecQuestionsPersister :
 * - Ce processor ne gère QUE le questionnaire (pas les questions)
 * - L'autre processor gère questionnaire + questions + réponses en une fois
 */
class QuestionnaireDataPersister implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Questionnaire
    {
        if (!$data instanceof Questionnaire) {
            throw new BadRequestException('Invalid data type');
        }

        // Récupérer l'utilisateur connecté
        $utilisateur = $this->security->getUser();
        if (!$utilisateur instanceof Utilisateur) {
            throw new BadRequestException('User not authenticated');
        }

        // Vérifier les données obligatoires
        if (!$data->getTitre()) {
            throw new BadRequestException('Title is required');
        }

        // Définir l'utilisateur créateur si pas déjà défini
        if (!$data->getCreePar()) {
            $data->setCreePar($utilisateur);
        }

        // Vérifier que l'utilisateur est bien le créateur
        if ($data->getCreePar() !== $utilisateur) {
            throw new BadRequestException('You can only create questionnaires for yourself');
        }

        // Définir la date de création si pas déjà définie
        if (!$data->getDateCreation()) {
            $data->setDateCreation(new \DateTimeImmutable());
        }

        // Définir les valeurs par défaut
        if ($data->isActive() === null) {
            $data->setEstActif(true);
        }

        if ($data->isStarted() === null) {
            $data->setEstDemarre(false);
        }

        if (!$data->getScorePassage()) {
            $data->setScorePassage(70);
        }

        // Persister le questionnaire
        $this->entityManager->persist($data);
        $this->entityManager->flush();

        return $data;
    }
}