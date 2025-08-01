<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use App\Repository\QuizAttemptRepository;
use App\State\QuizAttemptProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

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
#[ApiResource(
    uriTemplate: '/quizzes/{id}/participate',
    operations: [
        new Post(processor: QuizAttemptProcessor::class),
    ],
    normalizationContext: ['groups' => ['quiz_attempt:read']],
    denormalizationContext: ['groups' => ['quiz_attempt:write']],
    formats: ['jsonld', 'json']
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
    #[Assert\NotBlank(message: 'Le prénom ne peut pas être vide.')]
    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: 'Le prénom doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le prénom ne peut pas dépasser {{ limit }} caractères.'
    )]
    #[Assert\Regex(
        pattern: '/^[a-zA-ZÀ-ÿ\s\-\'\.]+$/u',
        message: 'Le prénom ne peut contenir que des lettres, espaces, tirets et apostrophes.'
    )]
    private ?string $participantFirstName = null;

    #[ORM\Column(length: 255)]
    #[Groups(['quiz_attempt:read', 'quiz_attempt:write'])]
    #[Assert\NotBlank(message: 'Le nom de famille ne peut pas être vide.')]
    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: 'Le nom de famille doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le nom de famille ne peut pas dépasser {{ limit }} caractères.'
    )]
    #[Assert\Regex(
        pattern: '/^[a-zA-ZÀ-ÿ\s\-\'\.]+$/u',
        message: 'Le nom de famille ne peut contenir que des lettres, espaces, tirets et apostrophes.'
    )]
    private ?string $participantLastName = null;

    #[ORM\Column]
    #[Groups(['quiz_attempt:read', 'quiz_attempt:write'])]
    #[Assert\NotNull(message: 'La date de début ne peut pas être nulle.')]
    #[Assert\Type(
        type: \DateTimeImmutable::class,
        message: 'La date de début doit être une date valide.'
    )]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['quiz_attempt:read', 'quiz_attempt:write'])]
    #[Assert\Type(
        type: \DateTimeImmutable::class,
        message: 'La date de fin doit être une date valide.'
    )]
    #[Assert\Expression(
        "this.getCompletedAt() === null or this.getCompletedAt() >= this.getStartedAt()",
        message: 'La date de fin doit être postérieure à la date de début.'
    )]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['quiz_attempt:read'])]
    #[Assert\Type(
        type: 'integer',
        message: 'Le score doit être un nombre entier.'
    )]
    #[Assert\Range(
        min: 0,
        minMessage: 'Le score ne peut pas être négatif.'
    )]
    private ?int $score = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['quiz_attempt:read'])]
    #[Assert\Type(
        type: 'integer',
        message: 'Le nombre total de questions doit être un nombre entier.'
    )]
    #[Assert\Range(
        min: 0,
        minMessage: 'Le nombre total de questions ne peut pas être négatif.'
    )]
    #[Assert\Expression(
        "this.getScore() === null or this.getTotalQuestions() === null or this.getScore() <= this.getTotalQuestions()",
        message: 'Le score ne peut pas être supérieur au nombre total de questions.'
    )]
    private ?int $totalQuestions = null;

    #[ORM\ManyToOne(inversedBy: 'quizAttempts')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['quiz_attempt:read', 'quiz_attempt:write'])]
    #[Assert\NotNull(message: 'Le quiz associé ne peut pas être nul.')]
    private ?Quiz $quiz = null;

    #[ORM\ManyToOne(inversedBy: 'quizAttempts')]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['quiz_attempt:read', 'quiz_attempt:write'])]
    private ?User $user = null;

    #[ORM\OneToMany(mappedBy: 'quizAttempt', targetEntity: UserAnswer::class, orphanRemoval: true)]
    #[Groups(['quiz_attempt:read'])]
    #[Assert\Valid]
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
        $this->participantFirstName = trim($participantFirstName);

        return $this;
    }

    public function getParticipantLastName(): ?string
    {
        return $this->participantLastName;
    }

    public function setParticipantLastName(string $participantLastName): static
    {
        $this->participantLastName = trim($participantLastName);

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
        if ($score !== null) {
            $this->score = max(0, $score);
        } else {
            $this->score = null;
        }

        return $this;
    }

    public function getTotalQuestions(): ?int
    {
        return $this->totalQuestions;
    }

    public function setTotalQuestions(?int $totalQuestions): static
    {
        if ($totalQuestions !== null) {
            $this->totalQuestions = max(0, $totalQuestions);
        } else {
            $this->totalQuestions = null;
        }

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

    #[Assert\Range(
        min: 0,
        max: 100,
        notInRangeMessage: 'Le pourcentage doit être entre {{ min }}% et {{ max }}%.'
    )]
    public function getPercentage(): float
    {
        if ($this->totalQuestions === 0) {
            return 0.0;
        }

        return ($this->score / $this->totalQuestions) * 100;
    }
}