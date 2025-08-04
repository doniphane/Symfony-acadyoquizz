<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Lexik\Bundle\JWTAuthenticationBundle\Security\User\JWTUserInterface;
use App\State\UserRegistrationProcessor;
use App\State\TokenVerificationProvider;
use App\State\MeProvider;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(),
        new Put(),
        new Delete()
    ],
    normalizationContext: ['groups' => ['user:read']],
    denormalizationContext: ['groups' => ['user:write']],
    formats: ['jsonld', 'json']
)]
#[ApiResource(
    uriTemplate: '/register',
    operations: [
        new Post(processor: UserRegistrationProcessor::class)
    ],
    normalizationContext: ['groups' => ['user:read', 'user:register']],
    denormalizationContext: ['groups' => ['user:write']],
    formats: ['jsonld', 'json']
)]
#[ApiResource(
    uriTemplate: '/me',
    operations: [
        new Get(provider: MeProvider::class, security: "is_granted('ROLE_USER')")
    ],
    normalizationContext: ['groups' => ['user:read']],
    formats: ['jsonld', 'json']
)]
#[ApiResource(
    uriTemplate: '/verify-token',
    operations: [
        new Get(provider: TokenVerificationProvider::class, security: "is_granted('ROLE_USER')")
    ],
    normalizationContext: ['groups' => ['user:read']],
    formats: ['jsonld', 'json']
)]
#[UniqueEntity(
    fields: ['email'],
    message: 'Cette adresse email est déjà utilisée.'
)]
class User implements UserInterface, PasswordAuthenticatedUserInterface, JWTUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['user:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Groups(['user:read', 'user:write'])]
    #[Assert\NotBlank(message: 'L\'adresse email est obligatoire.')]
    #[Assert\Email(message: 'L\'adresse email {{ value }} n\'est pas valide.')]
    #[Assert\Length(
        max: 180,
        maxMessage: 'L\'adresse email ne peut pas dépasser {{ limit }} caractères.'
    )]
    #[Assert\Regex(
        pattern: '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
        message: 'L\'adresse email contient des caractères non autorisés.'
    )]
    private ?string $email = null;

    #[ORM\Column]
    #[Groups(['user:read'])]
    #[Assert\Type(
        type: 'array',
        message: 'Les rôles doivent être un tableau.'
    )]
    #[Assert\All([
        new Assert\Choice(
            choices: ['ROLE_USER', 'ROLE_ADMIN', 'ROLE_MODERATOR'],
            message: 'Le rôle {{ value }} n\'est pas autorisé.'
        )
    ])]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    #[Groups(['user:write'])]
    #[Assert\NotBlank(message: 'Le mot de passe est obligatoire.')]
    #[Assert\Length(
        min: 6,
        max: 255,
        minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le mot de passe ne peut pas dépasser {{ limit }} caractères.'
    )]
    #[Assert\Regex(
        pattern: '/^(?=.*[a-zA-Z])(?=.*\d)/',
        message: 'Le mot de passe doit contenir au moins une lettre et un chiffre.'
    )]
    #[Assert\NotCompromisedPassword(
        message: 'Ce mot de passe a été compromis dans une fuite de données. Veuillez en choisir un autre.'
    )]
    private ?string $password = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['user:read', 'user:write'])]
    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: 'Le prénom doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le prénom ne peut pas dépasser {{ limit }} caractères.'
    )]
    #[Assert\Regex(
        pattern: '/^[a-zA-ZÀ-ÿ\s\'-]+$/u',
        message: 'Le prénom ne peut contenir que des lettres, espaces, apostrophes et tirets.'
    )]
    private ?string $firstName = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['user:read', 'user:write'])]
    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: 'Le nom de famille doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le nom de famille ne peut pas dépasser {{ limit }} caractères.'
    )]
    #[Assert\Regex(
        pattern: '/^[a-zA-ZÀ-ÿ\s\'-]+$/u',
        message: 'Le nom de famille ne peut contenir que des lettres, espaces, apostrophes et tirets.'
    )]
    private ?string $lastName = null;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: QuizAttempt::class, orphanRemoval: true)]
    #[Groups(['user:read'])]
    private Collection $quizAttempts;

    #[ORM\OneToMany(mappedBy: 'createdBy', targetEntity: Quiz::class, orphanRemoval: true)]
    #[Groups(['user:read'])]
    private Collection $quizzes;

    public function __construct()
    {
        $this->quizAttempts = new ArrayCollection();
        $this->quizzes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        // Nettoyer et sécuriser l'email
        $this->email = filter_var(trim(strtolower($email)), FILTER_SANITIZE_EMAIL);

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        // Filtrer les rôles autorisés uniquement
        $allowedRoles = ['ROLE_USER', 'ROLE_ADMIN', 'ROLE_MODERATOR'];
        $this->roles = array_intersect($roles, $allowedRoles);

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        // Nettoyer le prénom
        $this->firstName = trim(ucfirst(strtolower($firstName)));

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        // Nettoyer le nom de famille
        $this->lastName = trim(strtoupper($lastName));

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
            $quizAttempt->setUser($this);
        }

        return $this;
    }

    public function removeQuizAttempt(QuizAttempt $quizAttempt): static
    {
        if ($this->quizAttempts->removeElement($quizAttempt)) {
            // set the owning side to null (unless already changed)
            if ($quizAttempt->getUser() === $this) {
                $quizAttempt->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Quiz>
     */
    public function getQuizzes(): Collection
    {
        return $this->quizzes;
    }

    public function addQuiz(Quiz $quiz): static
    {
        if (!$this->quizzes->contains($quiz)) {
            $this->quizzes->add($quiz);
            $quiz->setCreatedBy($this);
        }

        return $this;
    }

    public function removeQuiz(Quiz $quiz): static
    {
        if ($this->quizzes->removeElement($quiz)) {
            // set the owning side to null (unless already changed)
            if ($quiz->getCreatedBy() === $this) {
                $quiz->setCreatedBy(null);
            }
        }

        return $this;
    }

    /**
     * Token JWT temporaire pour l'inscription
     * Utilisé uniquement pour passer le token depuis le UserRegistrationProcessor
     */
    #[Groups(['user:register'])]
    public ?string $jwtToken = null;

    /**
     * Créer un utilisateur à partir du payload JWT
     */
    public static function createFromPayload($username, array $payload): self
    {
        $user = new self();
        $user->setEmail($username);
        $user->setFirstName($payload['firstName'] ?? 'User');
        $user->setLastName($payload['lastName'] ?? 'Default');

        // Validation des rôles du payload
        $roles = $payload['roles'] ?? ['ROLE_USER'];
        $allowedRoles = ['ROLE_USER', 'ROLE_ADMIN', 'ROLE_MODERATOR'];
        $validRoles = array_intersect($roles, $allowedRoles);
        $user->setRoles($validRoles ?: ['ROLE_USER']);

        return $user;
    }
}