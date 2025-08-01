<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\ApiProperty;
use App\Repository\QuizRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use App\State\QuizDataPersister;
use App\State\QuizDataProvider;
use App\State\QuizUpdateProcessor;
use App\State\QuizPublicDataProvider;

#[ORM\Entity(repositoryClass: QuizRepository::class)]
#[UniqueEntity(
    fields: ['accessCode'],
    message: 'Ce code d\'accès est déjà utilisé.'
)]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(processor: QuizDataPersister::class),
        new Put(
            processor: QuizUpdateProcessor::class,
            denormalizationContext: ['groups' => ['quiz:update']]
        ),
        new Delete(
            security: "is_granted('ROLE_ADMIN') and object.getCreatedBy() == user"
        )
    ],
    normalizationContext: ['groups' => ['quiz:read']],
    denormalizationContext: ['groups' => ['quiz:write']],
    formats: ['jsonld', 'json']
)]
#[ApiResource(
    uriTemplate: '/public/quizzes',
    operations: [
        new GetCollection(provider: QuizPublicDataProvider::class),
    ],
    normalizationContext: ['groups' => ['quiz:read']],
    formats: ['jsonld', 'json']
)]
#[ApiResource(
    uriTemplate: '/public/quizzes/{id}',
    operations: [
        new Get(provider: QuizPublicDataProvider::class),
    ],
    normalizationContext: ['groups' => ['quiz:read']],
    formats: ['jsonld', 'json']
)]

class Quiz
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['quiz:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['quiz:read', 'quiz:write', 'quiz:update'])]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: 'Le titre doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères.'
    )]
    #[Assert\Regex(
        pattern: '/^[a-zA-Z0-9\sÀ-ÿ\-_\.\!\?]+$/u',
        message: 'Le titre contient des caractères non autorisés.'
    )]
    private ?string $title = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['quiz:read', 'quiz:write', 'quiz:update'])]
    #[Assert\Length(
        max: 2000,
        maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères.'
    )]
    #[Assert\Regex(
        pattern: '/^[a-zA-Z0-9\s\n\rÀ-ÿ\-_\.\!\?\,\;\:\(\)\"\']+$/u',
        message: 'La description contient des caractères non autorisés.'
    )]
    private ?string $description = null;

    #[ORM\Column(length: 10, unique: true)]
    #[Groups(['quiz:read'])]
    #[Assert\NotBlank(message: 'Le code d\'accès ne peut pas être vide.')]
    #[Assert\Length(
        min: 6,
        max: 6,
        exactMessage: 'Le code d\'accès doit contenir exactement {{ limit }} caractères.'
    )]
    #[Assert\Regex(
        pattern: '/^[A-Z0-9]+$/',
        message: 'Le code d\'accès ne peut contenir que des lettres majuscules et des chiffres.'
    )]
    private ?string $accessCode = null;

    #[ORM\Column]
    #[Groups(['quiz:read', 'quiz:write', 'quiz:update'])]
    #[Assert\Type(
        type: 'bool',
        message: 'La valeur doit être un booléen.'
    )]
    private ?bool $isActive = true;

    #[ORM\Column]
    #[Groups(['quiz:read', 'quiz:write', 'quiz:update'])]
    #[Assert\Type(
        type: 'bool',
        message: 'La valeur doit être un booléen.'
    )]
    private ?bool $isStarted = false;

    #[ORM\Column]
    #[Groups(['quiz:read', 'quiz:write', 'quiz:update'])]
    #[Assert\Type(
        type: 'integer',
        message: 'Le score de passage doit être un nombre entier.'
    )]
    #[Assert\Range(
        min: 0,
        max: 100,
        notInRangeMessage: 'Le score de passage doit être entre {{ min }}% et {{ max }}%.'
    )]
    private ?int $passingScore = 70;

    #[ORM\Column]
    #[Groups(['quiz:read', 'quiz:write'])]
    #[Assert\NotNull(message: 'La date de création ne peut pas être nulle.')]
    #[Assert\Type(
        type: \DateTimeImmutable::class,
        message: 'La date de création doit être une date valide.'
    )]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne(inversedBy: 'quizzes')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['quiz:read'])]
    private ?User $createdBy = null;

    #[ORM\OneToMany(mappedBy: 'quiz', targetEntity: Question::class, orphanRemoval: true)]
    #[Groups(['quiz:read'])]
    #[ApiProperty(readableLink: true, writableLink: false)]
    #[Assert\Valid]
    private Collection $questions;

    #[ORM\OneToMany(mappedBy: 'quiz', targetEntity: QuizAttempt::class, orphanRemoval: true)]
    #[Groups(['quiz:read'])]
    #[ApiProperty(readableLink: true, writableLink: false)]
    #[Assert\Valid]
    private Collection $quizAttempts;

    public function __construct()
    {
        $this->questions = new ArrayCollection();
        $this->quizAttempts = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->accessCode = $this->generateAccessCode();
    }

    /**
     * Génère un nouveau code d'accès pour ce quiz
     * Utilisé si le code actuel entre en conflit
     */
    public function regenerateAccessCode(): void
    {
        $this->accessCode = $this->generateAccessCode();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = trim($title);

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description ? trim($description) : null;

        return $this;
    }

    public function getAccessCode(): ?string
    {
        return $this->accessCode;
    }

    public function setAccessCode(string $accessCode): static
    {
        $this->accessCode = strtoupper(trim($accessCode));

        return $this;
    }

    /**
     * Getter pour uniqueCode (alias de accessCode pour compatibilité frontend)
     */
    #[Groups(['quiz:read'])]
    public function getUniqueCode(): ?string
    {
        return $this->accessCode;
    }

    #[Groups(['quiz:read'])]
    #[SerializedName('isActive')]
    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    #[Groups(['quiz:read'])]
    #[SerializedName('isStarted')]
    public function isStarted(): ?bool
    {
        return $this->isStarted;
    }

    public function setIsStarted(bool $isStarted): static
    {
        $this->isStarted = $isStarted;

        return $this;
    }

    public function getPassingScore(): ?int
    {
        return $this->passingScore;
    }

    public function setPassingScore(int $passingScore): static
    {
        $this->passingScore = max(0, min(100, $passingScore));

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    /**
     * @return Collection<int, Question>
     */
    public function getQuestions(): Collection
    {
        return $this->questions;
    }

    public function addQuestion(Question $question): static
    {
        if (!$this->questions->contains($question)) {
            $this->questions->add($question);
            $question->setQuiz($this);
        }

        return $this;
    }

    public function removeQuestion(Question $question): static
    {
        if ($this->questions->removeElement($question)) {
            // set the owning side to null (unless already changed)
            if ($question->getQuiz() === $this) {
                $question->setQuiz(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, QuizAttempt>
     */
    public function getQuizAttempts(): Collection
    {
        return $this->quizAttempts;
    }

    public function addQuizAttempt(QuizAttempt $quizAttempt): static
    {
        if (!$this->quizAttempts->contains($quizAttempt)) {
            $this->quizAttempts->add($quizAttempt);
            $quizAttempt->setQuiz($this);
        }

        return $this;
    }

    public function removeQuizAttempt(QuizAttempt $quizAttempt): static
    {
        if ($this->quizAttempts->removeElement($quizAttempt)) {
            // set the owning side to null (unless already changed)
            if ($quizAttempt->getQuiz() === $this) {
                $quizAttempt->setQuiz(null);
            }
        }

        return $this;
    }

    private function generateAccessCode(): string
    {
        // Génère un code d'accès de 6 caractères aléatoires

        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= $characters[random_int(0, strlen($characters) - 1)];
        }
        return $code;
    }
}