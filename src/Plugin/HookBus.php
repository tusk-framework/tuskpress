<?php

declare(strict_types=1);

namespace TuskPress\Plugin;

use Tusk\Contracts\Attributes\Service;

/**
 * HookBus — TuskPress event/hook system.
 *
 * Reimplements WordPress hook system semantics
 * (add_action / apply_filters / do_action) using a modern
 * object-oriented event bus, PSR-14 compatible.
 *
 * WordPress compatibility:
 *   add_action($tag, $callback, $priority)  → HookBus::on($tag, $callback, $priority)
 *   add_filter($tag, $callback, $priority)  → HookBus::on($tag, $callback, $priority)
 *   do_action($tag, ...$args)               → HookBus::dispatch($tag, ...$args)
 *   apply_filters($tag, $value, ...$args)   → HookBus::filter($tag, $value, ...$args)
 *   remove_action($tag, $callback)          → HookBus::off($tag, $callback)
 */
#[Service]
class HookBus
{
    /**
     * @var array<string, array<int, array<callable>>>
     * Structure: [tag => [priority => [callbacks]]]
     */
    private array $hooks = [];

    // =========================================================
    // Hook registration
    // =========================================================

    /**
     * Registers a listener for a hook/action/filter.
     * Equivalent to WordPress add_action() and add_filter().
     */
    public function on(string $tag, callable $callback, int $priority = 10): void
    {
        $this->hooks[$tag][$priority][] = $callback;
        ksort($this->hooks[$tag]);
    }

    /**
     * Removes a listener.
     * Equivalent to WordPress remove_action() and remove_filter().
     */
    public function off(string $tag, callable $callback, int $priority = 10): void
    {
        if (!isset($this->hooks[$tag][$priority])) {
            return;
        }

        $this->hooks[$tag][$priority] = array_filter(
            $this->hooks[$tag][$priority],
            fn($cb) => $cb !== $callback
        );
    }

    /**
     * Removes all listeners for a hook.
     */
    public function removeAll(string $tag, ?int $priority = null): void
    {
        if ($priority !== null) {
            unset($this->hooks[$tag][$priority]);
        } else {
            unset($this->hooks[$tag]);
        }
    }

    // =========================================================
    // Dispatch
    // =========================================================

    /**
     * Triggers an action hook.
     * Equivalent to WordPress do_action().
     */
    public function dispatch(string $tag, mixed ...$args): void
    {
        if (!isset($this->hooks[$tag])) {
            return;
        }

        foreach ($this->hooks[$tag] as $callbacks) {
            foreach ($callbacks as $callback) {
                $callback(...$args);
            }
        }
    }

    /**
     * Applies filters to a value and returns the modified value.
     * Equivalent to WordPress apply_filters().
     */
    public function filter(string $tag, mixed $value, mixed ...$args): mixed
    {
        if (!isset($this->hooks[$tag])) {
            return $value;
        }

        foreach ($this->hooks[$tag] as $callbacks) {
            foreach ($callbacks as $callback) {
                $value = $callback($value, ...$args);
            }
        }

        return $value;
    }

    /**
     * Checks if there is any listener for a hook.
     * Equivalent to WordPress has_action() / has_filter().
     */
    public function has(string $tag, ?callable $callback = null): bool
    {
        if (!isset($this->hooks[$tag])) {
            return false;
        }

        if ($callback === null) {
            return true;
        }

        foreach ($this->hooks[$tag] as $callbacks) {
            if (in_array($callback, $callbacks, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns all registered hooks (useful for debugging).
     * @return array<string, array<int, array<callable>>>
     */
    public function all(): array
    {
        return $this->hooks;
    }
}
