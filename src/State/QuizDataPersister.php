<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Quiz;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class QuizDataPersister implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private JWTTokenManagerInterface $jwtManager,
        private RequestStack $requestStack
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Quiz
    {
        // Si c'est une création (POST)
        if ($operation instanceof \ApiPlatform\Metadata\Post) {
            // Récupérer le token JWT depuis les headers
            $request = $this->requestStack->getCurrentRequest();
            $authorizationHeader = $request->headers->get('Authorization');

            error_log("Authorization header: " . $authorizationHeader);

            if (!$authorizationHeader || !str_starts_with($authorizationHeader, 'Bearer ')) {
                throw new \Exception('Token d\'authentification manquant');
            }

            $token = substr($authorizationHeader, 7); // Enlever "Bearer "
            error_log("Token: " . $token);

            try {
                // Décoder le token JWT pour obtenir les informations utilisateur
                $payload = $this->jwtManager->parse($token);
                error_log("Payload: " . json_encode($payload));

                $username = $payload['username'] ?? null;
                error_log("Username: " . $username);

                if (!$username) {
                    throw new \Exception('Token invalide');
                }

                // Récupérer l'utilisateur depuis la base de données
                $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $username]);
                error_log("User found: " . ($user ? 'yes' : 'no'));

                if (!$user) {
                    throw new \Exception('Utilisateur non trouvé');
                }

                $data->setCreatedBy($user);
                error_log("CreatedBy set successfully");

            } catch (\Exception $e) {
                error_log("Error in QuizDataPersister: " . $e->getMessage());
                throw new \Exception('Erreur d\'authentification: ' . $e->getMessage());
            }
        }

        $this->entityManager->persist($data);
        $this->entityManager->flush();

        return $data;
    }
}