<?php

declare(strict_types=1);

namespace TuskPress\Infrastructure\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use Tusk\Contracts\Attributes\Service;
use TuskPress\Domain\Option\Option;

/**
 * MySqlOptionRepository — Manages wp_options (site settings).
 * Includes in-memory cache to reduce repeated queries.
 */
#[Service(scope: 'request')]
class MySqlOptionRepository
{
    /** @var array<string, mixed> */
    private array $cache = [];

    public function __construct(private readonly EntityManagerInterface $em) {}

    public function get(string $name, mixed $default = false): mixed
    {
        if (array_key_exists($name, $this->cache)) {
            return $this->cache[$name];
        }

        $option = $this->em->getRepository(Option::class)->findOneBy(['name' => $name]);

        if (!$option) {
            return $default;
        }

        $value = $option->getValue();
        $unserialized = @unserialize($value);
        $value = ($unserialized !== false || $value === 'b:0;') ? $unserialized : $value;

        $this->cache[$name] = $value;
        return $value;
    }

    public function set(string $name, mixed $value, bool $autoload = true): void
    {
        $serialized = is_array($value) || is_object($value) ? serialize($value) : (string) $value;
        $autoloadStr = $autoload ? 'yes' : 'no';

        $option = $this->em->getRepository(Option::class)->findOneBy(['name' => $name]);

        if ($option) {
            $option->setValue($serialized);
            $option->setAutoload($autoloadStr);
        } else {
            $option = new Option($name, $serialized, $autoloadStr);
            $this->em->persist($option);
        }

        $this->em->flush();
        $this->cache[$name] = $value;
    }

    public function exists(string $name): bool
    {
        if (array_key_exists($name, $this->cache)) {
            return true;
        }

        $count = $this->em->getRepository(Option::class)->count(['name' => $name]);
        return $count > 0;
    }

    public function delete(string $name): void
    {
        $option = $this->em->getRepository(Option::class)->findOneBy(['name' => $name]);
        if ($option) {
            $this->em->remove($option);
            $this->em->flush();
        }
        unset($this->cache[$name]);
    }

    /**
     * Preloads all options with autoload=yes (executed on bootstrap).
     */
    public function preloadAutoload(): void
    {
        /** @var Option[] $options */
        $options = $this->em->getRepository(Option::class)->findBy(['autoload' => 'yes']);

        foreach ($options as $option) {
            $value = $option->getValue();
            $unserialized = @unserialize($value);
            $this->cache[$option->getName()] = ($unserialized !== false || $value === 'b:0;')
                ? $unserialized
                : $value;
        }
    }
}
