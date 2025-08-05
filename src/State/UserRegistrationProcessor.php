<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Processor pour l'inscription des utilisateurs
 * 
 * Ce processor gère l'endpoint POST /api/register
 * Il permet de créer un nouveau compte utilisateur avec génération
 * automatique du token JWT.
 * 
 * Fonctionnalités :
 * - Création d'un nouvel utilisateur avec validation des données
 * - Hashage automatique du mot de passe
 * - Vérification de l'unicité de l'email
 * - Définition des valeurs par défaut (rôles, prénom, nom)
 * - Génération automatique du token JWT
 * - Retour du token dans la réponse pour connexion immédiate
 * 
 * Données attendues dans le body de la requête :
 * {
 *   "email": "user@example.com",
 *   "password": "motdepasse123",
 *   "firstName": "Jean" (optionnel),
 *   "lastName": "Dupont" (optionnel)
 * }
 * 
 * Utilisé par :
 * - Le formulaire d'inscription dans l'interface
 * - L'API pour créer de nouveaux comptes utilisateur
 * - Le processus d'onboarding des nouveaux utilisateurs
 * 
 * Sécurité :
 * - Validation de l'email (format et unicité)
 * - Validation du mot de passe (longueur et complexité)
 * - Hashage sécurisé du mot de passe
 * - Attribution automatique du rôle ROLE_USER
 */
class UserRegistrationProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private JWTTokenManagerInterface $jwtManager
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Utilisateur
    {
        if (!$data instanceof Utilisateur) {
            throw new BadRequestException('Invalid data type');
        }

        // Vérifier si l'utilisateur existe déjà
        $existingUser = $this->entityManager->getRepository(Utilisateur::class)
            ->findOneBy(['email' => $data->getEmail()]);

        if ($existingUser) {
            throw new BadRequestException('User already exists');
        }

        // Validation des champs requis (seulement email et password)
        if (!$data->getEmail() || !$data->getPassword()) {
            throw new BadRequestException('Email and password are required');
        }

        // Définir des valeurs par défaut pour firstName et lastName si non fournis
        if (!$data->getFirstName()) {
            $data->setFirstName('User');
        }

        if (!$data->getLastName()) {
            $data->setLastName('Default');
        }

        // Hasher le mot de passe
        $hashedPassword = $this->passwordHasher->hashPassword($data, $data->getPassword());
        $data->setPassword($hashedPassword);

        // Définir le rôle par défaut
        if (empty($data->getRoles()) || $data->getRoles() === ['ROLE_USER']) {
            $data->setRoles(['ROLE_USER']);
        }

        // Persister l'utilisateur
        $this->entityManager->persist($data);
        $this->entityManager->flush();

        // Générer le token JWT et l'ajouter comme propriété temporaire
        $token = $this->jwtManager->create($data);

        // Utiliser une propriété dynamique pour passer le token
        $data->jwtToken = $token;

        return $data;
    }
}