<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**

 * 
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

     * @param User $data 
     * @param Operation $operation 
     * @param array $uriVariables 
     * @param array $context 
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): User
    {
        // Étape 1 : Définir les valeurs par défaut pour une création
        $this->setDefaultValues($data, $operation);

        // Étape 2 : Gestion du mot de passe
        if ($data->getPlainPassword()) {
            $hashedPassword = $this->passwordHasher->hashPassword(
                $data,
                $data->getPlainPassword()
            );
            $data->setPassword($hashedPassword);

            $data->eraseCredentials();
        }

        return $this->processor->process($data, $operation, $uriVariables, $context);
    }


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
}