<?php

namespace App\Services\Plugin\Contracts;

interface PluginInterface
{
    /**
     * Get the unique identifier slug for the plugin.
     */
    public function getSlug(): string;

    /**
     * Get the display name of the plugin.
     */
    public function getName(): string;

    /**
     * Get the description of the plugin.
     */
    public function getDescription(): string;

    /**
     * Get the version of the plugin.
     */
    public function getVersion(): string;

    /**
     * Get the author of the plugin.
     */
    public function getAuthor(): string;

    /**
     * Get the icon identifier for the plugin.
     */
    public function getIcon(): string;

    /**
     * Called when the plugin is installed system-wide.
     */
    public function install(): void;

    /**
     * Called when the plugin is uninstalled from the system.
     */
    public function uninstall(): void;

    /**
     * Called when a user activates the plugin.
     *
     * @param  int  $userId  The user activating the plugin
     */
    public function activate(int $userId): void;

    /**
     * Called when a user deactivates the plugin.
     *
     * @param  int  $userId  The user deactivating the plugin
     */
    public function deactivate(int $userId): void;

    /**
     * Get the configuration schema for the plugin.
     * Returns an array defining the configuration fields.
     *
     * @return array<string, array{
     *     type: string,
     *     label: string,
     *     default?: mixed,
     *     required?: bool,
     *     options?: array<string>,
     *     multiple?: bool,
     *     condition?: string,
     *     description?: string,
     *     min?: int|float,
     *     max?: int|float
     * }>
     */
    public function getConfigSchema(): array;

    /**
     * Get the default configuration values.
     *
     * @return array<string, mixed>
     */
    public function getDefaultConfig(): array;

    /**
     * Validate configuration values.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, string> Array of errors (empty if valid)
     */
    public function validateConfig(array $config): array;

    /**
     * Execute the plugin's main functionality.
     *
     * @param  int  $userId  The user to execute for
     * @param  array<string, mixed>  $config  The plugin configuration
     * @param  array<string, mixed>  $context  Additional context data
     */
    public function execute(int $userId, array $config, array $context = []): void;

    /**
     * Check if the plugin supports scheduled execution.
     */
    public function supportsScheduling(): bool;

    /**
     * Get the default schedule configuration.
     *
     * @return array{type: string, value: string}|null
     */
    public function getDefaultSchedule(): ?array;

    /**
     * Check if the plugin supports chat integration.
     */
    public function supportsChatIntegration(): bool;

    /**
     * Get chat intents supported by this plugin.
     * Returns an array of intent definitions for the AI parser.
     *
     * @return array<int, array{
     *     action: string,
     *     description: string,
     *     entities: array<string, string>,
     *     examples: array<string>
     * }>
     */
    public function getChatIntents(): array;

    /**
     * Handle chat intent execution.
     *
     * @param  int  $userId  The user executing the intent
     * @param  string  $action  The action to execute
     * @param  array<string, mixed>  $entities  Extracted entities from user message
     * @return array{success: bool, message: string, data?: mixed}
     */
    public function handleChatIntent(int $userId, string $action, array $entities): array;
}
