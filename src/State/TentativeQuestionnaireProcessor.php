<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\TentativeQuestionnaire;
use App\Entity\Questionnaire;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

/**
 * Processor pour la création de tentatives de questionnaire
 * 
 * Ce processor gère l'endpoint POST /api/questionnaires/{id}/participate
 * Il permet de créer une tentative de questionnaire pour un étudiant.
 * 
 * Fonctionnalités :
 * - Création d'une tentative avec validation des données
 * - Association automatique à l'utilisateur connecté (si connecté)
 * - Vérification que le questionnaire est actif et démarré
 * - Validation des données obligatoires (prénom, nom)
 * - Définition automatique de la date de début
 * - Persistance en base de données
 * 
 * Utilisé par :
 * - L'interface étudiant pour commencer un quiz
 * - L'API pour créer une tentative de participation
 * - Le processus de participation aux quiz
 * 
 * Données attendues dans le body de la requête :
 * {
 *   "prenomParticipant": "Jean",
 *   "nomParticipant": "Dupont"
 * }
 * 
 * IMPORTANT : Ce processor associe automatiquement la tentative
 * à l'utilisateur connecté, ce qui permet ensuite de filtrer
 * l'historique par utilisateur dans /api/user/my-attempts
 */
class TentativeQuestionnaireProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TentativeQuestionnaire
    {
        if (!$data instanceof TentativeQuestionnaire) {
            throw new BadRequestException('Invalid data type');
        }

        // Récupérer le questionnaire depuis l'URI
        $questionnaireId = $uriVariables['id'] ?? null;
        if (!$questionnaireId) {
            throw new BadRequestException('Questionnaire ID is required');
        }

        $questionnaire = $this->entityManager->getRepository(Questionnaire::class)->find($questionnaireId);
        if (!$questionnaire) {
            throw new BadRequestException('Questionnaire not found');
        }

        // Vérifier que le questionnaire est actif
        if (!$questionnaire->isActive()) {
            throw new BadRequestException('This questionnaire is not active');
        }

        // Vérifier que le questionnaire est démarré
        if (!$questionnaire->isStarted()) {
            throw new BadRequestException('This questionnaire has not started yet');
        }

        // Vérifier les données de la tentative
        if (!$data->getPrenomParticipant() || !$data->getNomParticipant()) {
            throw new BadRequestException('First name and last name are required');
        }

        // Associer le questionnaire à la tentative
        $data->setQuestionnaire($questionnaire);

        // Récupérer l'utilisateur connecté depuis le service Security
        $utilisateur = $this->security->getUser();
        if ($utilisateur instanceof Utilisateur) {
            // Associer automatiquement l'utilisateur connecté à la tentative
            $data->setUtilisateur($utilisateur);
        }

        // Définir la date de début si pas déjà définie
        if (!$data->getDateDebut()) {
            $data->setDateDebut(new \DateTimeImmutable());
        }

        // Persister la tentative
        $this->entityManager->persist($data);
        $this->entityManager->flush();

        return $data;
    }
}