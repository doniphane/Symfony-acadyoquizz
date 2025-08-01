<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use App\Repository\AnswerRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AnswerRepository::class)]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(),
        new Put(),
        new Delete()
    ],
    normalizationContext: ['groups' => ['answer:read']],
    denormalizationContext: ['groups' => ['answer:write']]
)]
class Answer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['answer:read', 'quiz:read'])]
    private ?int $id = null;

    #[ORM\Column(type: 'text')]
    #[Groups(['answer:read', 'answer:write', 'quiz:read'])]
    #[Assert\NotBlank(message: 'Le texte de la réponse est obligatoire.')]
    #[Assert\Length(
        min: 1,
        max: 1000,
        minMessage: 'La réponse doit contenir au moins {{ limit }} caractère.',
        maxMessage: 'La réponse ne peut pas dépasser {{ limit }} caractères.'
    )]
    #[Assert\NotRegex(
        pattern: '/[<>{}"\\\\\[\]`]/',
        message: 'La réponse contient des caractères non autorisés.'
    )]
    #[Assert\NotRegex(
        pattern: '/(javascript:|data:|vbscript:|onload=|onerror=)/i',
        message: 'La réponse contient du contenu potentiellement dangereux.'
    )]
    private ?string $text = null;

    #[ORM\Column]
    #[Groups(['answer:read', 'answer:write', 'quiz:read'])]
    #[Assert\NotNull(message: 'Le statut de correction est obligatoire.')]
    #[Assert\Type(
        type: 'bool',
        message: 'Le statut de correction doit être un booléen (true/false).'
    )]
    private ?bool $isCorrect = false;

    #[ORM\Column]
    #[Groups(['answer:read', 'answer:write', 'quiz:read'])]
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
        max: 50,
        notInRangeMessage: 'Le numéro d\'ordre doit être entre {{ min }} et {{ max }}.'
    )]
    private ?int $orderNumber = null;

    #[ORM\ManyToOne(inversedBy: 'answers')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['answer:read', 'answer:write'])]
    #[Assert\NotNull(message: 'La question associée est obligatoire.')]
    private ?Question $question = null;

    #[ORM\OneToMany(mappedBy: 'answer', targetEntity: UserAnswer::class, orphanRemoval: true)]
    private Collection $userAnswers;

    public function __construct()
    {
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

        $this->text = $cleanText;

        return $this;
    }

    #[Groups(['answer:read', 'quiz:read'])]
    public function isCorrect(): ?bool
    {
        return $this->isCorrect;
    }

    public function setIsCorrect(bool $isCorrect): static
    {
        $this->isCorrect = $isCorrect;

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
        } elseif ($orderNumber > 50) {
            $orderNumber = 50;
        }

        $this->orderNumber = $orderNumber;

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
            $userAnswer->setAnswer($this);
        }

        return $this;
    }

    public function removeUserAnswer(UserAnswer $userAnswer): static
    {
        if ($this->userAnswers->removeElement($userAnswer)) {
            // set the owning side to null (unless already changed)
            if ($userAnswer->getAnswer() === $this) {
                $userAnswer->setAnswer(null);
            }
        }

        return $this;
    }

    /**
     * Méthode utilitaire pour valider qu'une question a au moins une réponse correcte
     */
    public function validateQuestionHasCorrectAnswer(): bool
    {
        if (!$this->question) {
            return false;
        }

        foreach ($this->question->getAnswers() as $answer) {
            if ($answer->isCorrect()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Méthode utilitaire pour compter les réponses correctes d'une question
     */
    public function countCorrectAnswersInQuestion(): int
    {
        if (!$this->question) {
            return 0;
        }

        $count = 0;
        foreach ($this->question->getAnswers() as $answer) {
            if ($answer->isCorrect()) {
                $count++;
            }
        }

        return $count;
    }
}