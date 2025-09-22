<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'webauthn_credentials')]
class WebAuthnCredential
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'webauthnCredentials')]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private string $credentialId;

    #[ORM\Column(type: 'text')]
    private string $publicKey;

    #[ORM\Column(type: 'integer')]
    private int $signCount = 0;

    #[ORM\Column(type: 'string', length: 255)]
    private string $attestationType;

    #[ORM\Column(type: 'string', length: 255)]
    private string $attachmentType;

    #[ORM\Column(type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $lastUsed = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isActive = true;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    // Getter und Setter...
}
