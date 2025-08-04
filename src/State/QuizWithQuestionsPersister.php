<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Quiz;
use App\Entity\Question;
use App\Entity\Answer;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class QuizWithQuestionsPersister implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private JWTTokenManagerInterface $jwtManager,
        private RequestStack $requestStack
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Quiz
    {
        // Récupérer le token JWT depuis les headers
        $request = $this->requestStack->getCurrentRequest();
        $authorizationHeader = $request->headers->get('Authorization');

        if (!$authorizationHeader || !str_starts_with($authorizationHeader, 'Bearer ')) {
            throw new \Exception('Token d\'authentification manquant');
        }

        $token = substr($authorizationHeader, 7); // Enlever "Bearer "

        try {
            // Décoder le token JWT pour obtenir les informations utilisateur
            $payload = $this->jwtManager->parse($token);
            $username = $payload['username'] ?? null;

            if (!$username) {
                throw new \Exception('Token invalide');
            }

            // Récupérer l'utilisateur depuis la base de données
            $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $username]);

            if (!$user) {
                throw new \Exception('Utilisateur non trouvé');
            }

            $data->setCreatedBy($user);

        } catch (\Exception $e) {
            throw new \Exception('Erreur d\'authentification: ' . $e->getMessage());
        }

        // Sauvegarder le quiz d'abord
        $this->entityManager->persist($data);
        $this->entityManager->flush();

        // Récupérer les données de la requête pour les questions
        $requestData = json_decode($request->getContent(), true);

        if (isset($requestData['questions']) && is_array($requestData['questions'])) {
            foreach ($requestData['questions'] as $questionData) {
                $question = new Question();
                $question->setText($questionData['text']);
                $question->setOrderNumber($questionData['orderNumber']);
                $question->setQuiz($data);

                $this->entityManager->persist($question);

                // Créer les réponses pour cette question
                if (isset($questionData['answers']) && is_array($questionData['answers'])) {
                    foreach ($questionData['answers'] as $answerData) {
                        $answer = new Answer();
                        $answer->setText($answerData['text']);
                        $answer->setIsCorrect($answerData['isCorrect']);
                        $answer->setOrderNumber($answerData['orderNumber']);
                        $answer->setQuestion($question);

                        $this->entityManager->persist($answer);
                    }
                }
            }
        }

        $this->entityManager->flush();

        return $data;
    }
}