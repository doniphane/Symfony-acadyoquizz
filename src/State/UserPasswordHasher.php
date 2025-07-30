<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * @implements ProcessorInterface<User, User|void>
 */
final readonly class UserPasswordHasher implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $processor,
        private UserPasswordHasherInterface $passwordHasher
    ) {
    }

    /**

     *
     * @param User $data L'utilisateur à traiter
     * @param Operation $operation L'opération API Platform (POST, PUT, etc.)
     * @param array $uriVariables Les variables d'URI
     * @param array $context Le contexte de l'opération
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): User
    {

        $this->setDefaultValues($data, $operation);


        if ($data->getPassword() && !$this->isPasswordAlreadyHashed($data->getPassword())) {
            // Le mot de passe est en clair, on le hache
            $hashedPassword = $this->passwordHasher->hashPassword(
                $data,
                $data->getPassword()
            );
            $data->setPassword($hashedPassword);
        }

        return $this->processor->process($data, $operation, $uriVariables, $context);
    }

    /**

     */
    private function setDefaultValues(User $user, Operation $operation): void
    {

        $isCreation = $user->getId() === null;

        if ($isCreation) {

            if ($user->getCreatedAt() === null) {
                $user->setCreatedAt(new \DateTimeImmutable());
            }


            if (empty($user->getRoles()) || $user->getRoles() === ['ROLE_USER']) {
                $user->setRoles(['ROLE_USER']);
            }
        } else {

            $user->setUpdatedAt(new \DateTimeImmutable());
        }
    }

    /**

     */
    private function isPasswordAlreadyHashed(string $password): bool
    {

        return strlen($password) > 50 && (
            str_starts_with($password, '$2y$') ||  // bcrypt
            str_starts_with($password, '$argon2') || // argon2
            str_starts_with($password, '$2a$') ||  // bcrypt variant
            str_starts_with($password, '$2b$')     // bcrypt variant
        );
    }
}