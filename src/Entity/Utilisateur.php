<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use App\Repository\UtilisateurRepository;
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
use App\State\MeProvider;

#[ORM\Entity(repositoryClass: UtilisateurRepository::class)]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(),
        new Put(),
        new Delete()
    ],
    normalizationContext: ['groups' => ['utilisateur:read']],
    denormalizationContext: ['groups' => ['utilisateur:write']],
    formats: ['jsonld', 'json']
)]
#[ApiResource(
    uriTemplate: '/register',
    operations: [
        new Post(processor: UserRegistrationProcessor::class)
    ],
    normalizationContext: ['groups' => ['utilisateur:read', 'utilisateur:register']],
    denormalizationContext: ['groups' => ['utilisateur:register']],
    formats: ['jsonld', 'json']
)]
#[ApiResource(
    uriTemplate: '/me',
    operations: [
        new Get(provider: MeProvider::class, security: "is_granted('ROLE_USER')")
    ],
    normalizationContext: ['groups' => ['utilisateur:read']],
    formats: ['jsonld', 'json']
)]
#[UniqueEntity(
    fields: ['email'],
    message: 'Cette adresse email est déjà utilisée.'
)]
class Utilisateur implements UserInterface, PasswordAuthenticatedUserInterface, JWTUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['utilisateur:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Groups(['utilisateur:read', 'utilisateur:write', 'utilisateur:register'])]
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
    #[Groups(['utilisateur:read'])]
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
    #[Groups(['utilisateur:write', 'utilisateur:register'])]
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
    // #[Assert\NotCompromisedPassword(
    //     message: 'Ce mot de passe a été compromis dans une fuite de données. Veuillez en choisir un autre.'
    // )]
    private ?string $password = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['utilisateur:read', 'utilisateur:write', 'utilisateur:register'])]
    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: 'Le prénom doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le prénom ne peut pas dépasser {{ limit }} caractères.',
        groups: ['utilisateur:write']
    )]
    #[Assert\Regex(
        pattern: '/^[a-zA-ZÀ-ÿ\s\'-]+$/u',
        message: 'Le prénom ne peut contenir que des lettres, espaces, apostrophes et tirets.',
        groups: ['utilisateur:write']
    )]
    private ?string $firstName = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['utilisateur:read', 'utilisateur:write', 'utilisateur:register'])]
    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: 'Le nom de famille doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le nom de famille ne peut pas dépasser {{ limit }} caractères.',
        groups: ['utilisateur:write']
    )]
    #[Assert\Regex(
        pattern: '/^[a-zA-ZÀ-ÿ\s\'-]+$/u',
        message: 'Le nom de famille ne peut contenir que des lettres, espaces, apostrophes et tirets.',
        groups: ['utilisateur:write']
    )]
    private ?string $lastName = null;

    #[ORM\OneToMany(mappedBy: 'utilisateur', targetEntity: TentativeQuestionnaire::class, orphanRemoval: true)]
    #[Groups(['utilisateur:read'])]
    private Collection $tentativesQuestionnaire;

    #[ORM\OneToMany(mappedBy: 'creePar', targetEntity: Questionnaire::class, orphanRemoval: true)]
    #[Groups(['utilisateur:read'])]
    private Collection $questionnaires;

    public function __construct()
    {
        $this->tentativesQuestionnaire = new ArrayCollection();
        $this->questionnaires = new ArrayCollection();
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
     * @return Collection<int, TentativeQuestionnaire>
     */
    public function getTentativesQuestionnaire(): Collection
    {
        return $this->tentativesQuestionnaire;
    }

    public function addTentativeQuestionnaire(TentativeQuestionnaire $tentativeQuestionnaire): static
    {
        if (!$this->tentativesQuestionnaire->contains($tentativeQuestionnaire)) {
            $this->tentativesQuestionnaire->add($tentativeQuestionnaire);
            $tentativeQuestionnaire->setUtilisateur($this);
        }

        return $this;
    }

    public function removeTentativeQuestionnaire(TentativeQuestionnaire $tentativeQuestionnaire): static
    {
        if ($this->tentativesQuestionnaire->removeElement($tentativeQuestionnaire)) {
            // set the owning side to null (unless already changed)
            if ($tentativeQuestionnaire->getUtilisateur() === $this) {
                $tentativeQuestionnaire->setUtilisateur(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Questionnaire>
     */
    public function getQuestionnaires(): Collection
    {
        return $this->questionnaires;
    }

    public function addQuestionnaire(Questionnaire $questionnaire): static
    {
        if (!$this->questionnaires->contains($questionnaire)) {
            $this->questionnaires->add($questionnaire);
            $questionnaire->setCreePar($this);
        }

        return $this;
    }

    public function removeQuestionnaire(Questionnaire $questionnaire): static
    {
        if ($this->questionnaires->removeElement($questionnaire)) {
            // set the owning side to null (unless already changed)
            if ($questionnaire->getCreePar() === $this) {
                $questionnaire->setCreePar(null);
            }
        }

        return $this;
    }

    /**
     * Token JWT temporaire pour l'inscription
     * Utilisé uniquement pour passer le token depuis le UserRegistrationProcessor
     */
    #[Groups(['utilisateur:register'])]
    public ?string $jwtToken = null;

    /**
     * Créer un utilisateur à partir du payload JWT
     */
    public static function createFromPayload($username, array $payload): self
    {
        $utilisateur = new self();
        $utilisateur->setEmail($username);
        $utilisateur->setFirstName($payload['firstName'] ?? 'User');
        $utilisateur->setLastName($payload['lastName'] ?? 'Default');

        // Validation des rôles du payload
        $roles = $payload['roles'] ?? ['ROLE_USER'];
        $allowedRoles = ['ROLE_USER', 'ROLE_ADMIN', 'ROLE_MODERATOR'];
        $validRoles = array_intersect($roles, $allowedRoles);
        $utilisateur->setRoles($validRoles ?: ['ROLE_USER']);

        return $utilisateur;
    }
}