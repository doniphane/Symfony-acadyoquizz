<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Quiz;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class QuizDataPersister implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Quiz
    {
        // Si c'est une création (POST)
        if ($operation instanceof \ApiPlatform\Metadata\Post) {
            $user = $this->security->getUser();
            if ($user instanceof \App\Entity\User) {
                $data->setCreatedBy($user);
            } else {
                throw new \Exception('Utilisateur non connecté');
            }
        }

        $this->entityManager->persist($data);
        $this->entityManager->flush();

        return $data;
    }
}