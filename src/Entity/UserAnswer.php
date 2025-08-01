<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use App\Repository\UserAnswerRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: UserAnswerRepository::class)]
#[UniqueEntity(
    fields: ['quizAttempt', 'question'],
    message: 'Une réponse a déjà été donnée à cette question pour cette tentative.'
)]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(),
        new Put(),
        new Delete()
    ],
    normalizationContext: ['groups' => ['user_answer:read']],
    denormalizationContext: ['groups' => ['user_answer:write']]
)]
class UserAnswer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['user_answer:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'userAnswers')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['user_answer:read', 'user_answer:write'])]
    #[Assert\NotNull(message: 'La tentative de quiz ne peut pas être nulle.')]
    private ?QuizAttempt $quizAttempt = null;

    #[ORM\ManyToOne(inversedBy: 'userAnswers')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['user_answer:read', 'user_answer:write'])]
    #[Assert\NotNull(message: 'La question ne peut pas être nulle.')]
    private ?Question $question = null;

    #[ORM\ManyToOne(inversedBy: 'userAnswers')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['user_answer:read', 'user_answer:write'])]
    #[Assert\NotNull(message: 'La réponse ne peut pas être nulle.')]
    #[Assert\Expression(
        "this.getAnswer() === null or this.getQuestion() === null or this.getAnswer().getQuestion() === this.getQuestion()",
        message: 'La réponse sélectionnée ne correspond pas à la question posée.'
    )]
    private ?Answer $answer = null;

    #[ORM\Column]
    #[Groups(['user_answer:read', 'user_answer:write'])]
    #[Assert\NotNull(message: 'La date de réponse ne peut pas être nulle.')]
    #[Assert\Type(
        type: \DateTimeImmutable::class,
        message: 'La date de réponse doit être une date valide.'
    )]
    #[Assert\Expression(
        "this.getAnsweredAt() === null or this.getQuizAttempt() === null or this.getAnsweredAt() >= this.getQuizAttempt().getStartedAt()",
        message: 'La date de réponse ne peut pas être antérieure au début de la tentative.'
    )]
    #[Assert\Expression(
        "this.getAnsweredAt() === null or this.getQuizAttempt() === null or this.getQuizAttempt().getCompletedAt() === null or this.getAnsweredAt() <= this.getQuizAttempt().getCompletedAt()",
        message: 'La date de réponse ne peut pas être postérieure à la fin de la tentative.'
    )]
    private ?\DateTimeImmutable $answeredAt = null;

    public function __construct()
    {
        $this->answeredAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuizAttempt(): ?QuizAttempt
    {
        return $this->quizAttempt;
    }

    public function setQuizAttempt(?QuizAttempt $quizAttempt): static
    {
        $this->quizAttempt = $quizAttempt;

        return $this;
    }

    public function getQuestion(): ?Question
    {
        return $this->question;
    }

    public function setQuestion(?Question $question): static
    {
        $this->question = $question;

        return $this;
    }

    public function getAnswer(): ?Answer
    {
        return $this->answer;
    }

    public function setAnswer(?Answer $answer): static
    {
        $this->answer = $answer;

        return $this;
    }

    public function getAnsweredAt(): ?\DateTimeImmutable
    {
        return $this->answeredAt;
    }

    public function setAnsweredAt(\DateTimeImmutable $answeredAt): static
    {
        $this->answeredAt = $answeredAt;

        return $this;
    }

    /**
     * Vérifie si la réponse donnée est correcte
     */
    public function isCorrect(): bool
    {
        return $this->answer !== null && $this->answer->isCorrect();
    }

    /**
     * Vérifie la cohérence des données de cette réponse utilisateur
     */
    #[Assert\IsTrue(message: 'Les données de la réponse utilisateur sont incohérentes.')]
    public function isDataConsistent(): bool
    {
        // Vérifier que la réponse appartient bien à la question
        if ($this->answer !== null && $this->question !== null) {
            if ($this->answer->getQuestion() !== $this->question) {
                return false;
            }
        }

        // Vérifier que la question appartient bien au quiz de la tentative
        if ($this->question !== null && $this->quizAttempt !== null) {
            $quiz = $this->quizAttempt->getQuiz();
            if ($quiz !== null && !$quiz->getQuestions()->contains($this->question)) {
                return false;
            }
        }

        return true;
    }
}