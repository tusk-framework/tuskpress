<?php

declare(strict_types=1);

namespace TuskPress\Domain\Option;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "options")]
class Option
{
    #[ORM\Id]
    #[ORM\Column(name: "option_id", type: "integer", options: ["unsigned" => true])]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Column(name: "option_name", type: "string", length: 191, unique: true)]
    private string $name;

    #[ORM\Column(name: "option_value", type: "text")]
    private string $value;

    #[ORM\Column(name: "autoload", type: "string", length: 20, options: ["default" => "yes"])]
    private string $autoload = 'yes';

    public function __construct(string $name, string $value, string $autoload = 'yes')
    {
        $this->name = $name;
        $this->value = $value;
        $this->autoload = $autoload;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): void
    {
        $this->value = $value;
    }

    public function getAutoload(): string
    {
        return $this->autoload;
    }

    public function setAutoload(string $autoload): void
    {
        $this->autoload = $autoload;
    }
}
