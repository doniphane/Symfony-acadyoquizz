<?php

namespace App\Controller;

use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ApiAuthController extends AbstractController
{
    #[Route('/api/verify-token', name: 'api_verify_token', methods: ['GET'])]
    public function verifyToken(TokenStorageInterface $tokenStorage, EntityManagerInterface $entityManager): JsonResponse
    {
        $token = $tokenStorage->getToken();

        if (!$token) {
            return new JsonResponse(['error' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        $user = $token->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Invalid user'], Response::HTTP_UNAUTHORIZED);
        }

        // Récupérer l'utilisateur à jour depuis la base de données
        $freshUser = $entityManager->getRepository(User::class)->find($user->getId());

        if (!$freshUser) {
            return new JsonResponse(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
            'valid' => true,
            'user' => [
                'id' => $freshUser->getId(),
                'email' => $freshUser->getEmail(),
                'firstName' => $freshUser->getFirstName(),
                'lastName' => $freshUser->getLastName(),
                'roles' => $freshUser->getRoles(),
            ]
        ]);
    }

    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function register(Request $request, JWTTokenManagerInterface $jwtManager, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return new JsonResponse(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;
        $firstName = $data['firstName'] ?? null;
        $lastName = $data['lastName'] ?? null;

        if (!$email || !$password || !$firstName || !$lastName) {
            return new JsonResponse(['error' => 'Missing required fields'], Response::HTTP_BAD_REQUEST);
        }

        // Vérifier si l'utilisateur existe déjà
        $existingUser = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

        if ($existingUser) {
            return new JsonResponse(['error' => 'User already exists'], Response::HTTP_CONFLICT);
        }

        // Créer le nouvel utilisateur
        $user = new User();
        $user->setEmail($email);
        $user->setFirstName($firstName);
        $user->setLastName($lastName);
        $user->setRoles(['ROLE_USER']);

        $hashedPassword = $passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        $entityManager->persist($user);
        $entityManager->flush();

        // Générer le token JWT
        $token = $jwtManager->create($user);

        return new JsonResponse([
            'token' => $token,
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'roles' => $user->getRoles(),
            ]
        ], Response::HTTP_CREATED);
    }
}
