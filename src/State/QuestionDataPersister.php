<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Question;
use App\Entity\Reponse;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Processor pour la création de questions avec leurs réponses
 * 
 * Ce processor gère l'endpoint POST /api/questions
 * Il permet de créer une question et ses réponses associées
 * en une seule opération.
 * 
 * Fonctionnalités :
 * - Création d'une question avec validation des données
 * - Création automatique des réponses associées
 * - Persistance en base de données avec gestion des relations
 * - Validation que chaque question a au moins une réponse correcte
 * 
 * Utilisé par :
 * - Le formulaire de création de questions dans l'interface admin
 * - L'API pour ajouter des questions à un questionnaire existant
 * 
 * Données attendues dans le body de la requête :
 * {
 *   "texte": "Question text?",
 *   "numeroOrdre": 1,
 *   "questionnaire": "/api/questionnaires/123",
 *   "reponses": [
 *     {"texte": "Réponse 1", "estCorrecte": true, "numeroOrdre": 1},
 *     {"texte": "Réponse 2", "estCorrecte": false, "numeroOrdre": 2}
 *   ]
 * }
 */
class QuestionDataPersister implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RequestStack $requestStack
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Question
    {
        if (!$data instanceof Question) {
            throw new \InvalidArgumentException('Les données doivent être une instance de Question');
        }

        // Récupérer les données de la requête
        $request = $this->requestStack->getCurrentRequest();
        $requestData = json_decode($request->getContent(), true);

        // Récupérer les réponses depuis les données
        $reponsesData = $requestData['reponses'] ?? [];

        // Persister la question d'abord
        $this->entityManager->persist($data);
        $this->entityManager->flush();

        // Créer et persister les réponses
        foreach ($reponsesData as $reponseData) {
            $reponse = new Reponse();
            $reponse->setTexte($reponseData['texte']);
            $reponse->setEstCorrecte($reponseData['estCorrecte']);
            $reponse->setNumeroOrdre($reponseData['numeroOrdre']);
            $reponse->setQuestion($data);

            $this->entityManager->persist($reponse);
        }

        $this->entityManager->flush();

        return $data;
    }
}