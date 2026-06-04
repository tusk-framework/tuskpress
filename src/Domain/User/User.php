<?php

declare(strict_types=1);

namespace TuskPress\Domain\User;

use Doctrine\ORM\Mapping as ORM;
use Tusk\Security\Contract\UserInterface;

/**
 * User — Aggregate Root of the users domain.
 */
#[ORM\Entity]
#[ORM\Table(name: 'wp_users')]
#[ORM\HasLifecycleCallbacks]
class User implements UserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "IDENTITY")]
    #[ORM\Column(name: 'ID', type: 'integer')]
    public ?int $idValue = null;

    public ?UserId $id = null;

    #[ORM\Column(name: 'user_login', type: 'string', length: 60)]
    public string $login;

    #[ORM\Column(name: 'user_pass', type: 'string', length: 255)]
    public string $passwordHash;

    #[ORM\Column(name: 'user_nicename', type: 'string', length: 50)]
    public string $nicename;

    #[ORM\Column(name: 'user_email', type: 'string', length: 100)]
    public string $email;

    #[ORM\Column(name: 'user_url', type: 'string', length: 100)]
    public string $url;

    #[ORM\Column(name: 'user_registered', type: 'datetime_immutable')]
    public \DateTimeImmutable $registeredAt;

    #[ORM\Column(name: 'user_activation_key', type: 'string', length: 255)]
    public string $activationKey = '';

    #[ORM\Column(name: 'user_status', type: 'integer')]
    public int $status = 0;

    #[ORM\Column(name: 'display_name', type: 'string', length: 250)]
    public string $displayName;

    public UserRole $role = UserRole::Subscriber;

    public array $meta = [];

    #[ORM\PostLoad]
    public function onPostLoad(): void
    {
        $this->id = new UserId($this->idValue);
        // In a real system, the role would come from user_meta (wp_capabilities).
        // Here we assume admin if ID == 1 just for MVP.
        $this->role = ($this->idValue === 1) ? UserRole::Administrator : UserRole::Subscriber;
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function onPrePersist(): void
    {
        if ($this->id !== null && $this->id->value !== 0) {
            $this->idValue = $this->id->value;
        }
    }

    public function __construct(
        ?UserId $id = null,
        string $login = '',
        string $email = '',
        string $displayName = '',
        string $nicename = '',
        string $url = '',
        UserRole $role = UserRole::Subscriber,
        string $passwordHash = ''
    ) {
        $this->id = $id;
        if ($id !== null) {
            $this->idValue = $id->value;
        }
        $this->login = $login;
        $this->email = $email;
        $this->displayName = $displayName;
        $this->nicename = $nicename;
        $this->url = $url;
        $this->role = $role;
        $this->passwordHash = $passwordHash;
        $this->registeredAt = new \DateTimeImmutable();
    }

    public function verifyPassword(string $plainPassword): bool
    {
        if (str_starts_with($this->passwordHash, '$P$') || str_starts_with($this->passwordHash, '$H$')) {
            return $this->verifyPhpass($plainPassword);
        }
        return password_verify($plainPassword, $this->passwordHash);
    }

    private function verifyPhpass(string $plain): bool
    {
        $itoa64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

        if (strlen($this->passwordHash) !== 34) {
            return password_verify($plain, $this->passwordHash);
        }

        $count_log2 = strpos($itoa64, $this->passwordHash[3]);
        if ($count_log2 < 7 || $count_log2 > 30) {
            return false;
        }

        $count = 1 << $count_log2;
        $salt  = substr($this->passwordHash, 4, 8);

        if (strlen($salt) !== 8) {
            return false;
        }

        $hash = md5($salt . $plain, true);
        do {
            $hash = md5($hash . $plain, true);
        } while (--$count);

        $output = substr($this->passwordHash, 0, 12);
        $output .= $this->hashEncode64($hash, 16, $itoa64);

        return hash_equals($this->passwordHash, $output);
    }

    private function hashEncode64(string $input, int $count, string $itoa64): string
    {
        $output = '';
        $i = 0;

        do {
            $value = ord($input[$i++]);
            $output .= $itoa64[$value & 0x3f];
            if ($i < $count) {
                $value |= ord($input[$i]) << 8;
            }
            $output .= $itoa64[($value >> 6) & 0x3f];
            if ($i++ >= $count) {
                break;
            }
            if ($i < $count) {
                $value |= ord($input[$i]) << 16;
            }
            $output .= $itoa64[($value >> 12) & 0x3f];
            if ($i++ >= $count) {
                break;
            }
            $output .= $itoa64[($value >> 18) & 0x3f];
        } while ($i < $count);

        return $output;
    }

    public function can(string $capability): bool
    {
        $caps = $this->meta['wp_capabilities'] ?? null;
        if (is_array($caps) && array_key_exists($capability, $caps)) {
            return (bool) $caps[$capability];
        }
        return $this->role->can($capability);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Administrator;
    }

    public function isAtLeast(UserRole $role): bool
    {
        return $this->role->isAtLeast($role);
    }

    public function getIdentifier(): string|int
    {
        return $this->idValue ?? 0;
    }

    public function getRoles(): array
    {
        return [$this->role->value];
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function getMeta(string $key, mixed $default = null): mixed
    {
        return $this->meta[$key] ?? $default;
    }

    public function getAvatar(int $size = 96): string
    {
        $hash = md5(strtolower(trim($this->email)));
        return "https://www.gravatar.com/avatar/{$hash}?s={$size}&d=mp&r=g";
    }

    public static function create(
        UserId   $id,
        string   $login,
        string   $email,
        string   $displayName,
        UserRole $role,
        string   $plainPassword,
    ): self {
        return new self(
            id:           $id,
            login:        $login,
            email:        $email,
            displayName:  $displayName,
            nicename:     sanitize_title($login),
            url:          '',
            role:         $role,
            passwordHash: password_hash($plainPassword, PASSWORD_BCRYPT, ['cost' => 12]),
        );
    }

    public function withRole(UserRole $role): self
    {
        $this->role = $role;
        return $this;
    }
}
