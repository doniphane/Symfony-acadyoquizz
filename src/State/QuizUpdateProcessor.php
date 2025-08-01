<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Quiz;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Processeur personnalisé pour la mise à jour des quiz
 * Gère uniquement les champs envoyés dans la requête
 */
class QuizUpdateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private JWTTokenManagerInterface $jwtManager,
        private RequestStack $requestStack
    ) {
    }

    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        // Vérifier que c'est bien un Quiz
        if (!$data instanceof Quiz) {
            throw new BadRequestException('Invalid data type');
        }

        // Récupérer le token JWT depuis les headers
        $request = $this->requestStack->getCurrentRequest();
        $authorizationHeader = $request->headers->get('Authorization');

        if (!$authorizationHeader || !str_starts_with($authorizationHeader, 'Bearer ')) {
            throw new BadRequestException('Token d\'authentification manquant');
        }

        $token = substr($authorizationHeader, 7); // Enlever "Bearer "

        try {
            // Décoder le token JWT pour obtenir les informations utilisateur
            $payload = $this->jwtManager->parse($token);
            $username = $payload['username'] ?? null;

            if (!$username) {
                throw new BadRequestException('Token invalide');
            }

            // Récupérer l'utilisateur depuis la base de données
            $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $username]);

            if (!$user) {
                throw new BadRequestException('Utilisateur non trouvé');
            }

            // Vérifier que l'utilisateur est admin
            if (!in_array('ROLE_ADMIN', $user->getRoles())) {
                throw new BadRequestException('Access denied - Admin role required');
            }

        } catch (\Exception $e) {
            throw new BadRequestException('Erreur d\'authentification: ' . $e->getMessage());
        }

        // Récupérer le quiz original depuis la base de données
        $quizId = $uriVariables['id'] ?? null;
        if (!$quizId) {
            throw new BadRequestException('Quiz ID is required');
        }

        $originalQuiz = $this->entityManager->find(Quiz::class, $quizId);
        if (!$originalQuiz) {
            throw new BadRequestException('Quiz not found');
        }

        // Vérifier que l'utilisateur est le propriétaire du quiz
        if ($originalQuiz->getCreatedBy() !== $user) {
            throw new BadRequestException('You can only update your own quizzes');
        }

        // Mettre à jour seulement les champs qui ne sont pas null dans $data
        if ($data->getTitle() !== null) {
            $originalQuiz->setTitle($data->getTitle());
        }

        if ($data->getDescription() !== null) {
            $originalQuiz->setDescription($data->getDescription());
        }

        if ($data->isActive() !== null) {
            $originalQuiz->setIsActive($data->isActive());
        }

        if ($data->isStarted() !== null) {
            $originalQuiz->setIsStarted($data->isStarted());
        }

        if ($data->getPassingScore() !== null) {
            $originalQuiz->setPassingScore($data->getPassingScore());
        }

        // Sauvegarder les modifications
        $this->entityManager->flush();

        // Retourner le quiz mis à jour
        return $originalQuiz;
    }
}