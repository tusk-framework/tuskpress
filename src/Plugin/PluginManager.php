<?php

declare(strict_types=1);

namespace TuskPress\Plugin;

use Tusk\Contracts\Attributes\Service;

/**
 * PluginManager — TuskPress plugin manager.
 *
 * Loads active plugins from the wp-content/plugins/ directory,
 * maintaining compatibility with the WordPress plugin system.
 *
 * Each plugin is a PHP file with a comment header:
 *   Plugin Name: My Plugin
 *   Version: 1.0.0
 *   Author: Author
 *
 * The main plugin file registers hooks via global $hookBus
 * (compatibility with WordPress add_action / add_filter).
 */
#[Service]
class PluginManager
{
    /** @var string[] Loaded plugins slugs */
    private array $loaded = [];

    private readonly string $pluginsDir;

    public function __construct(
        private readonly HookBus $hookBus,
    ) {
        $this->pluginsDir = defined('WP_PLUGIN_DIR') ? WP_PLUGIN_DIR : __DIR__;
    }

    /**
     * Loads a list of active plugins.
     * @param string[] $activePlugins List of slugs (e.g.: ['hello-dolly/hello.php'])
     */
    public function loadPlugins(array $activePlugins): void
    {
        foreach ($activePlugins as $pluginFile) {
            $this->loadPlugin($pluginFile);
        }
    }

    /**
     * Loads a specific plugin by relative path to the plugins directory.
     */
    public function loadPlugin(string $pluginFile): void
    {
        $fullPath = $this->pluginsDir . DIRECTORY_SEPARATOR . ltrim($pluginFile, '/\\');

        if (!file_exists($fullPath)) {
            error_log("[TuskPress] Plugin not found: {$fullPath}");
            return;
        }

        if (in_array($fullPath, $this->loaded, true)) {
            return; // Already loaded
        }

        try {
            require_once $fullPath;
            $this->loaded[] = $fullPath;
        } catch (\Throwable $e) {
            error_log("[TuskPress] Error loading plugin {$pluginFile}: " . $e->getMessage());
        }
    }

    /**
     * Returns plugin metadata from the file header.
     * @return array<string, string>
     */
    public function getPluginData(string $pluginFile): array
    {
        $fullPath = $this->pluginsDir . DIRECTORY_SEPARATOR . $pluginFile;

        if (!file_exists($fullPath)) {
            return [];
        }

        $content = file_get_contents($fullPath, false, null, 0, 8192);
        if ($content === false) {
            return [];
        }

        $headers = [
            'Name'        => 'Plugin Name',
            'Version'     => 'Version',
            'Description' => 'Description',
            'Author'      => 'Author',
            'AuthorURI'   => 'Author URI',
            'PluginURI'   => 'Plugin URI',
            'TextDomain'  => 'Text Domain',
            'RequiresWP'  => 'Requires at least',
            'RequiresPHP' => 'Requires PHP',
        ];

        $data = [];
        foreach ($headers as $field => $regex) {
            preg_match('/^[ \t\/*#@]*' . preg_quote($regex, '/') . ':\s*(.+)$/mi', $content, $match);
            $data[$field] = isset($match[1]) ? trim($match[1]) : '';
        }

        return $data;
    }

    /**
     * Scans the plugins directory and returns all installed plugins.
     * @return array<string, array<string, string>>
     */
    public function getInstalledPlugins(): array
    {
        $plugins = [];

        if (!is_dir($this->pluginsDir)) {
            return $plugins;
        }

        foreach (glob($this->pluginsDir . '/*/*.php') as $file) {
            $relPath = str_replace($this->pluginsDir . DIRECTORY_SEPARATOR, '', $file);
            $relPath = str_replace('\\', '/', $relPath);
            $data    = $this->getPluginData($relPath);

            if (!empty($data['Name'])) {
                $plugins[$relPath] = $data;
            }
        }

        // Single file plugins (e.g., hello.php in plugins root)
        foreach (glob($this->pluginsDir . '/*.php') as $file) {
            $relPath = basename($file);
            $data    = $this->getPluginData($relPath);

            if (!empty($data['Name'])) {
                $plugins[$relPath] = $data;
            }
        }

        return $plugins;
    }

    /**
     * @return string[]
     */
    public function getLoadedPlugins(): array
    {
        return $this->loaded;
    }
}
