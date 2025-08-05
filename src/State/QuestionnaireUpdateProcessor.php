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
 * Processor pour la mise à jour des questionnaires
 * 
 * Ce processor gère l'endpoint PUT /api/questionnaires/{id}
 * Il permet de mettre à jour les propriétés d'un questionnaire existant.
 * 
 * Fonctionnalités :
 * - Mise à jour partielle des champs du questionnaire
 * - Vérification des permissions (créateur ou admin uniquement)
 * - Validation des données modifiées
 * - Logs détaillés pour le débogage
 * - Gestion des champs optionnels (seuls les champs fournis sont modifiés)
 * 
 * Champs modifiables :
 * - titre : Le titre du questionnaire
 * - description : La description du questionnaire
 * - estActif : Si le questionnaire est actif (visible publiquement)
 * - estDemarre : Si le questionnaire est démarré (peut être passé)
 * - scorePassage : Le score minimum pour réussir le questionnaire
 * 
 * Utilisé par :
 * - Le formulaire d'édition de quiz dans l'interface admin
 * - L'API pour activer/désactiver des quiz
 * - Le toggle d'activation des quiz dans le dashboard
 * 
 * Sécurité :
 * - Seul le créateur du questionnaire peut le modifier
 * - Les admins peuvent modifier tous les questionnaires
 * - Vérification de l'authentification obligatoire
 */
class QuestionnaireUpdateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Questionnaire
    {
        error_log('=== DÉBUT QuestionnaireUpdateProcessor ===');
        error_log('Type de données reçues: ' . gettype($data));

        if (!$data instanceof Questionnaire) {
            error_log('❌ Données invalides - pas une instance de Questionnaire');
            throw new BadRequestException('Invalid data type');
        }

        error_log('✅ Données valides - instance de Questionnaire');

        // Récupérer l'utilisateur connecté
        $utilisateur = $this->security->getUser();
        if (!$utilisateur instanceof Utilisateur) {
            error_log('❌ Utilisateur non authentifié');
            throw new BadRequestException('User not authenticated');
        }

        error_log('✅ Utilisateur authentifié: ' . $utilisateur->getEmail());

        // Récupérer le questionnaire original depuis la base de données
        $questionnaireId = $uriVariables['id'] ?? $data->getId();
        error_log('ID du questionnaire à mettre à jour: ' . $questionnaireId);

        $originalQuestionnaire = $this->entityManager->getRepository(Questionnaire::class)->find($questionnaireId);
        if (!$originalQuestionnaire) {
            error_log('❌ Questionnaire non trouvé avec l\'ID: ' . $questionnaireId);
            throw new BadRequestException('Questionnaire not found');
        }

        error_log('✅ Questionnaire trouvé: ' . $originalQuestionnaire->getTitre());

        // Vérifier que l'utilisateur est le créateur du questionnaire ou un admin
        $userRoles = $utilisateur->getRoles();
        $isAdmin = in_array('ROLE_ADMIN', $userRoles);
        $isCreator = $originalQuestionnaire->getCreePar() === $utilisateur;

        error_log('Rôles utilisateur: ' . implode(', ', $userRoles));
        error_log('Est admin: ' . ($isAdmin ? 'OUI' : 'NON'));
        error_log('Est créateur: ' . ($isCreator ? 'OUI' : 'NON'));

        if (!$isCreator && !$isAdmin) {
            error_log('❌ Accès refusé - pas le créateur ni admin');
            throw new BadRequestException('Access denied - you can only update your own questionnaires');
        }

        error_log('✅ Accès autorisé');

        // Mettre à jour les champs modifiables (seulement ceux fournis)
        error_log('Mise à jour des champs...');

        // Titre - seulement si fourni
        if ($data->getTitre()) {
            error_log('Titre: ' . $data->getTitre());
            $originalQuestionnaire->setTitre($data->getTitre());
        } else {
            error_log('Titre non fourni - garde l\'ancien');
        }

        // Description - seulement si fournie
        if ($data->getDescription() !== null) {
            error_log('Description: ' . $data->getDescription());
            $originalQuestionnaire->setDescription($data->getDescription());
        } else {
            error_log('Description non fournie - garde l\'ancienne');
        }

        // Est actif - seulement si fourni
        if ($data->isActive() !== null) {
            error_log('Est actif: ' . ($data->isActive() ? 'OUI' : 'NON'));
            $originalQuestionnaire->setEstActif($data->isActive());
        } else {
            error_log('Est actif non fourni - garde l\'ancien');
        }

        // Est démarré - seulement si fourni
        if ($data->isStarted() !== null) {
            error_log('Est démarré: ' . ($data->isStarted() ? 'OUI' : 'NON'));
            $originalQuestionnaire->setEstDemarre($data->isStarted());
        } else {
            error_log('Est démarré non fourni - garde l\'ancien');
        }

        // Score passage - seulement si fourni
        if ($data->getScorePassage() !== null) {
            error_log('Score passage: ' . $data->getScorePassage());
            $originalQuestionnaire->setScorePassage($data->getScorePassage());
        } else {
            error_log('Score passage non fourni - garde l\'ancien');
        }

        error_log('✅ Champs mis à jour');

        // Persister les modifications
        try {
            $this->entityManager->persist($originalQuestionnaire);
            $this->entityManager->flush();
            error_log('✅ Modifications persistées avec succès');
        } catch (\Exception $e) {
            error_log('❌ Erreur lors de la persistance: ' . $e->getMessage());
            throw $e;
        }

        error_log('=== FIN QuestionnaireUpdateProcessor ===');
        return $originalQuestionnaire;
    }
}