<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use App\Repository\QuizAttemptRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: QuizAttemptRepository::class)]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(),
        new Put(),
        new Delete()
    ],
    normalizationContext: ['groups' => ['quiz_attempt:read']],
    denormalizationContext: ['groups' => ['quiz_attempt:write']]
)]
class QuizAttempt
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['quiz_attempt:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['quiz_attempt:read', 'quiz_attempt:write'])]
    private ?string $participantFirstName = null;

    #[ORM\Column(length: 255)]
    #[Groups(['quiz_attempt:read', 'quiz_attempt:write'])]
    private ?string $participantLastName = null;

    #[ORM\Column]
    #[Groups(['quiz_attempt:read', 'quiz_attempt:write'])]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['quiz_attempt:read', 'quiz_attempt:write'])]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['quiz_attempt:read'])]
    private ?int $score = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['quiz_attempt:read'])]
    private ?int $totalQuestions = null;

    #[ORM\ManyToOne(inversedBy: 'quizAttempts')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['quiz_attempt:read', 'quiz_attempt:write'])]
    private ?Quiz $quiz = null;

    #[ORM\ManyToOne(inversedBy: 'quizAttempts')]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['quiz_attempt:read', 'quiz_attempt:write'])]
    private ?User $user = null;

    #[ORM\OneToMany(mappedBy: 'quizAttempt', targetEntity: UserAnswer::class, orphanRemoval: true)]
    #[Groups(['quiz_attempt:read'])]
    private Collection $userAnswers;

    public function __construct()
    {
        $this->userAnswers = new ArrayCollection();
        $this->startedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getParticipantFirstName(): ?string
    {
        return $this->participantFirstName;
    }

    public function setParticipantFirstName(string $participantFirstName): static
    {
        $this->participantFirstName = $participantFirstName;

        return $this;
    }

    public function getParticipantLastName(): ?string
    {
        return $this->participantLastName;
    }

    public function setParticipantLastName(string $participantLastName): static
    {
        $this->participantLastName = $participantLastName;

        return $this;
    }

    public function getStartedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function setStartedAt(\DateTimeImmutable $startedAt): static
    {
        $this->startedAt = $startedAt;

        return $this;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeImmutable $completedAt): static
    {
        $this->completedAt = $completedAt;

        return $this;
    }

    public function getScore(): ?int
    {
        return $this->score;
    }

    public function setScore(?int $score): static
    {
        $this->score = $score;

        return $this;
    }

    public function getTotalQuestions(): ?int
    {
        return $this->totalQuestions;
    }

    public function setTotalQuestions(?int $totalQuestions): static
    {
        $this->totalQuestions = $totalQuestions;

        return $this;
    }

    public function getQuiz(): ?Quiz
    {
        return $this->quiz;
    }

    public function setQuiz(?Quiz $quiz): static
    {
        $this->quiz = $quiz;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return Collection<int, UserAnswer>
     */
    public function getUserAnswers(): Collection
    {
        return $this->userAnswers;
    }

    public function addUserAnswer(UserAnswer $userAnswer): static
    {
        if (!$this->userAnswers->contains($userAnswer)) {
            $this->userAnswers->add($userAnswer);
            $userAnswer->setQuizAttempt($this);
        }

        return $this;
    }

    public function removeUserAnswer(UserAnswer $userAnswer): static
    {
        if ($this->userAnswers->removeElement($userAnswer)) {
            // set the owning side to null (unless already changed)
            if ($userAnswer->getQuizAttempt() === $this) {
                $userAnswer->setQuizAttempt(null);
            }
        }

        return $this;
    }

    public function calculateScore(): void
    {
        $correctAnswers = 0;
        $totalAnswers = 0;

        foreach ($this->userAnswers as $userAnswer) {
            if ($userAnswer->getAnswer() && $userAnswer->getAnswer()->isCorrect()) {
                $correctAnswers++;
            }
            $totalAnswers++;
        }

        $this->score = $correctAnswers;
        $this->totalQuestions = $totalAnswers;
    }

    public function getPercentage(): float
    {
        if ($this->totalQuestions === 0) {
            return 0.0;
        }

        return ($this->score / $this->totalQuestions) * 100;
    }
}
