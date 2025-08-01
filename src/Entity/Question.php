<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\ApiProperty;
use App\Repository\QuestionRepository;
use App\Validator\HasCorrectAnswer;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: QuestionRepository::class)]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(),
        new Put(),
        new Delete()
    ],
    normalizationContext: ['groups' => ['question:read']],
    denormalizationContext: ['groups' => ['question:write']]
)]
class Question
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['question:read', 'quiz:read'])]
    private ?int $id = null;

    #[ORM\Column(type: 'text')]
    #[Groups(['question:read', 'question:write', 'quiz:read'])]
    #[Assert\NotBlank(message: 'Le texte de la question est obligatoire.')]
    #[Assert\Length(
        min: 5,
        max: 2000,
        minMessage: 'La question doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'La question ne peut pas dépasser {{ limit }} caractères.'
    )]
    #[Assert\NotRegex(
        pattern: '/[<>{}"\\\\\[\]`]/',
        message: 'La question contient des caractères non autorisés.'
    )]
    #[Assert\NotRegex(
        pattern: '/(javascript:|data:|vbscript:|onload=|onerror=|onclick=|onmouseover=)/i',
        message: 'La question contient du contenu potentiellement dangereux.'
    )]
    #[Assert\Regex(
        pattern: '/^.+\?$/',
        message: 'Une question doit se terminer par un point d\'interrogation.'
    )]
    private ?string $text = null;

    #[ORM\Column]
    #[Groups(['question:read', 'question:write', 'quiz:read'])]
    #[Assert\NotNull(message: 'Le numéro d\'ordre est obligatoire.')]
    #[Assert\Type(
        type: 'integer',
        message: 'Le numéro d\'ordre doit être un nombre entier.'
    )]
    #[Assert\Positive(
        message: 'Le numéro d\'ordre doit être un nombre positif.'
    )]
    #[Assert\Range(
        min: 1,
        max: 100,
        notInRangeMessage: 'Le numéro d\'ordre doit être entre {{ min }} et {{ max }}.'
    )]
    private ?int $orderNumber = null;

    #[ORM\ManyToOne(inversedBy: 'questions')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['question:read', 'question:write'])]
    #[Assert\NotNull(message: 'Le quiz associé est obligatoire.')]
    private ?Quiz $quiz = null;

    #[ORM\OneToMany(mappedBy: 'question', targetEntity: Answer::class, orphanRemoval: true)]
    #[Groups(['question:read', 'quiz:read'])]
    #[ApiProperty(readableLink: true, writableLink: false)]
    #[Assert\Count(
        min: 2,
        max: 10,
        minMessage: 'Une question doit avoir au moins {{ limit }} réponses.',
        maxMessage: 'Une question ne peut pas avoir plus de {{ limit }} réponses.'
    )]
    #[Assert\Valid]
    private Collection $answers;

    #[ORM\OneToMany(mappedBy: 'question', targetEntity: UserAnswer::class, orphanRemoval: true)]
    private Collection $userAnswers;

    public function __construct()
    {
        $this->answers = new ArrayCollection();
        $this->userAnswers = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    public function setText(string $text): static
    {
        // Nettoyer et sécuriser le texte
        $cleanText = trim($text);

        // Supprimer les caractères de contrôle dangereux
        $cleanText = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $cleanText);

        // Encoder les entités HTML pour éviter les injections XSS
        $cleanText = htmlspecialchars($cleanText, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // S'assurer que la question se termine par un point d'interrogation
        if (!empty($cleanText) && !str_ends_with($cleanText, '?')) {
            $cleanText .= ' ?';
        }

        $this->text = $cleanText;

        return $this;
    }

    public function getOrderNumber(): ?int
    {
        return $this->orderNumber;
    }

    public function setOrderNumber(int $orderNumber): static
    {
        // Valider et sécuriser le numéro d'ordre
        if ($orderNumber < 1) {
            $orderNumber = 1;
        } elseif ($orderNumber > 100) {
            $orderNumber = 100;
        }

        $this->orderNumber = $orderNumber;

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

    /**
     * @return Collection<int, Answer>
     */
    public function getAnswers(): Collection
    {
        return $this->answers;
    }

    public function addAnswer(Answer $answer): static
    {
        if (!$this->answers->contains($answer)) {
            // Vérifier qu'on ne dépasse pas la limite de réponses
            if ($this->answers->count() >= 10) {
                throw new \InvalidArgumentException('Une question ne peut pas avoir plus de 10 réponses.');
            }

            $this->answers->add($answer);
            $answer->setQuestion($this);
        }

        return $this;
    }

    public function removeAnswer(Answer $answer): static
    {
        if ($this->answers->removeElement($answer)) {
            // Vérifier qu'on garde au moins 2 réponses
            if ($this->answers->count() < 2) {
                throw new \InvalidArgumentException('Une question doit avoir au moins 2 réponses.');
            }

            // set the owning side to null (unless already changed)
            if ($answer->getQuestion() === $this) {
                $answer->setQuestion(null);
            }
        }

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
            $userAnswer->setQuestion($this);
        }

        return $this;
    }

    public function removeUserAnswer(UserAnswer $userAnswer): static
    {
        if ($this->userAnswers->removeElement($userAnswer)) {
            // set the owning side to null (unless already changed)
            if ($userAnswer->getQuestion() === $this) {
                $userAnswer->setQuestion(null);
            }
        }

        return $this;
    }

    // === MÉTHODES UTILITAIRES POUR LA VALIDATION ===

    /**
     * Vérifier qu'il y a au moins une réponse correcte
     */
    public function hasCorrectAnswer(): bool
    {
        foreach ($this->answers as $answer) {
            if ($answer->isCorrect()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Compter le nombre de réponses correctes
     */
    public function getCorrectAnswersCount(): int
    {
        $count = 0;
        foreach ($this->answers as $answer) {
            if ($answer->isCorrect()) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Compter le nombre total de réponses
     */
    public function getAnswersCount(): int
    {
        return $this->answers->count();
    }

    /**
     * Vérifier que la question est valide (au moins 2 réponses, au moins 1 correcte)
     */
    public function isValid(): bool
    {
        return $this->getAnswersCount() >= 2 && $this->hasCorrectAnswer();
    }

    /**
     * Obtenir toutes les réponses correctes
     */
    public function getCorrectAnswers(): array
    {
        $correctAnswers = [];
        foreach ($this->answers as $answer) {
            if ($answer->isCorrect()) {
                $correctAnswers[] = $answer;
            }
        }
        return $correctAnswers;
    }

    /**
     * Obtenir les réponses triées par ordre
     */
    public function getAnswersOrderedByNumber(): array
    {
        $answers = $this->answers->toArray();
        usort($answers, function ($a, $b) {
            return $a->getOrderNumber() <=> $b->getOrderNumber();
        });
        return $answers;
    }

    public function reorderAnswers(): void
    {
        $answers = $this->getAnswersOrderedByNumber();
        foreach ($answers as $index => $answer) {
            $answer->setOrderNumber($index + 1);
        }
    }
}