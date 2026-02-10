<?php

namespace App\Services\Ai;

use App\Models\User;
use App\Services\Plugin\PluginManager;
use Illuminate\Support\Facades\Log;

class IntentParserService
{
    public function __construct(
        protected AiProviderInterface $provider,
        protected PluginManager $pluginManager
    ) {}

    /**
     * Parse user message to detect intent using function calling.
     *
     * @return array{action: string, module: string, entities: array, confidence: float, requires_confirmation: bool}
     */
    public function parse(User $user, string $message, array $conversationHistory = []): array
    {
        $functions = $this->buildFunctionDefinitions($user);

        $messages = [
            [
                'role' => 'system',
                'content' => 'You are an intent classifier. Analyze the user message and call the appropriate function that matches their intent. Extract all relevant entities from the message.',
            ],
        ];

        // Add conversation history for context (last 4 messages only to save tokens)
        foreach (array_slice($conversationHistory, -4) as $msg) {
            $messages[] = $msg;
        }

        $messages[] = ['role' => 'user', 'content' => $message];

        try {
            $response = $this->provider->chat($messages, [
                'functions' => $functions,
                'tool_choice' => 'auto',
            ]);

            return $this->parseResponse($response);
        } catch (\Exception $e) {
            Log::error('Intent parsing failed', ['error' => $e->getMessage()]);

            return [
                'action' => 'unknown',
                'module' => 'general',
                'entities' => [],
                'confidence' => 0.0,
                'requires_confirmation' => false,
            ];
        }
    }

    /**
     * Build the system prompt for intent detection.
     */
    protected function buildIntentPrompt(User $user): string
    {
        // Deprecated: Kept for backward compatibility
        // Now using function calling instead
        $pluginInfo = $this->getActivePluginsInfo($user);

        return '...';
    }

    /**
     * Build function definitions for OpenAI function calling.
     *
     * @return array<int, array>
     */
    protected function buildFunctionDefinitions(User $user): array
    {
        $functions = [];

        // Add core module functions
        $functions = array_merge($functions, $this->getFinanceFunctions());
        $functions = array_merge($functions, $this->getScheduleFunctions());
        $functions = array_merge($functions, $this->getNotesFunctions());
        $functions = array_merge($functions, $this->getGeneralFunctions());

        // Add plugin functions
        $functions = array_merge($functions, $this->getPluginFunctions($user));

        return $functions;
    }

    /**
     * Get finance module function definitions.
     */
    protected function getFinanceFunctions(): array
    {
        return [
            [
                'name' => 'create_transaction',
                'description' => 'Record a new financial transaction (income or expense)',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'tx_type' => [
                            'type' => 'string',
                            'enum' => ['income', 'expense'],
                            'description' => 'Type of transaction',
                        ],
                        'amount' => [
                            'type' => 'number',
                            'description' => 'Transaction amount in IDR',
                        ],
                        'category' => [
                            'type' => 'string',
                            'description' => 'Transaction category',
                        ],
                        'note' => [
                            'type' => 'string',
                            'description' => 'Additional note or description',
                        ],
                    ],
                    'required' => ['tx_type', 'amount'],
                ],
            ],
            [
                'name' => 'view_balance',
                'description' => 'View financial summary or balance',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'period' => [
                            'type' => 'string',
                            'enum' => ['today', 'this_week', 'this_month', 'all'],
                            'description' => 'Time period for the summary',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'view_transactions',
                'description' => 'View list of transactions',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'period' => [
                            'type' => 'string',
                            'enum' => ['today', 'this_week', 'this_month'],
                        ],
                        'tx_type' => [
                            'type' => 'string',
                            'enum' => ['income', 'expense'],
                        ],
                        'limit' => [
                            'type' => 'number',
                            'description' => 'Number of transactions to show',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Get schedule module function definitions.
     */
    protected function getScheduleFunctions(): array
    {
        return [
            [
                'name' => 'create_schedule',
                'description' => 'Create a new event or reminder',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => [
                            'type' => 'string',
                            'description' => 'Event title',
                        ],
                        'start_time' => [
                            'type' => 'string',
                            'description' => 'Start time in YYYY-MM-DD HH:mm format',
                        ],
                    ],
                    'required' => ['title'],
                ],
            ],
            [
                'name' => 'view_schedules',
                'description' => 'View upcoming events or schedules',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'period' => [
                            'type' => 'string',
                            'enum' => ['today', 'tomorrow', 'this_week', 'this_month'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Get notes module function definitions.
     */
    protected function getNotesFunctions(): array
    {
        return [
            [
                'name' => 'create_note',
                'description' => 'Create a new note',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => 'string'],
                        'content' => [
                            'type' => 'string',
                            'description' => 'Note content',
                        ],
                        'tags' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                        ],
                    ],
                    'required' => ['content'],
                ],
            ],
            [
                'name' => 'view_notes',
                'description' => 'Search or view notes',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'search' => ['type' => 'string'],
                        'tags' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Get general function definitions.
     */
    protected function getGeneralFunctions(): array
    {
        return [
            [
                'name' => 'greeting',
                'description' => 'User is greeting or saying hello',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass,
                ],
            ],
            [
                'name' => 'help',
                'description' => 'User is asking for help or guidance',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'topic' => ['type' => 'string'],
                    ],
                ],
            ],
            [
                'name' => 'confirm',
                'description' => 'User is confirming a pending action (yes, ok, agree, save)',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass,
                ],
            ],
            [
                'name' => 'cancel',
                'description' => 'User is canceling a pending action (no, cancel, nevermind)',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass,
                ],
            ],
        ];
    }

    /**
     * Get plugin function definitions.
     */
    protected function getPluginFunctions(User $user): array
    {
        $functions = [];
        $activePlugins = $this->pluginManager->getActivePluginsForUser($user->id);

        foreach ($activePlugins as $userPlugin) {
            $plugin = $userPlugin->plugin;
            $pluginInstance = $this->pluginManager->getPlugin($plugin->slug);

            if (! $pluginInstance || ! $pluginInstance->supportsChatIntegration()) {
                continue;
            }

            $intents = $pluginInstance->getChatIntents();

            foreach ($intents as $intent) {
                $properties = [
                    'plugin_slug' => [
                        'type' => 'string',
                        'enum' => [$plugin->slug],
                        'description' => 'Plugin identifier',
                    ],
                ];
                $required = ['plugin_slug'];

                // Convert entity types to JSON Schema format
                foreach ($intent['entities'] as $key => $type) {
                    $isOptional = str_ends_with($type, '|null');
                    $baseType = str_replace('|null', '', $type);

                    $properties[$key] = [
                        'type' => $baseType === 'number' ? 'number' : 'string',
                        'description' => ucfirst(str_replace('_', ' ', $key)),
                    ];

                    // Add to required array if not optional
                    if (! $isOptional) {
                        $required[] = $key;
                    }
                }

                $functions[] = [
                    'name' => $intent['action'],
                    'description' => $intent['description'].' Examples: '.implode(', ', array_slice($intent['examples'], 0, 2)),
                    'parameters' => [
                        'type' => 'object',
                        'properties' => $properties,
                        'required' => $required,
                    ],
                ];
            }
        }

        return $functions;
    }

    /**
     * Get active plugins information for the user.
     */
    protected function getActivePluginsInfo(User $user): string
    {
        $activePlugins = $this->pluginManager->getActivePluginsForUser($user->id);

        if ($activePlugins->isEmpty()) {
            return '';
        }

        $pluginSections = [];

        foreach ($activePlugins as $userPlugin) {
            $plugin = $userPlugin->plugin;
            $pluginInstance = $this->pluginManager->getPlugin($plugin->slug);

            if (! $pluginInstance || ! $pluginInstance->supportsChatIntegration()) {
                continue;
            }

            // Get plugin's chat intents
            $intents = $pluginInstance->getChatIntents();

            if (empty($intents)) {
                continue;
            }

            // Build intent definitions for this plugin
            $intentDefs = [];
            foreach ($intents as $intent) {
                $entitiesDef = [];
                foreach ($intent['entities'] as $key => $type) {
                    $entitiesDef[] = "{$key}: {$type}";
                }

                $def = "- {$intent['action']}: {$intent['description']}\n";
                $def .= "  entities: {plugin_slug: \"{$plugin->slug}\", ".implode(', ', $entitiesDef).'}';

                // Only include first 2 examples to save tokens
                if (! empty($intent['examples'])) {
                    $examples = array_slice($intent['examples'], 0, 2);
                    $def .= "\n  Ex: ".implode(', ', array_map(fn ($ex) => "\"{$ex}\"", $examples));
                }

                $intentDefs[] = $def;
            }

            if (! empty($intentDefs)) {
                $pluginSections[] = "MODULE: plugin\n".implode("\n", $intentDefs);
            }
        }

        if (empty($pluginSections)) {
            return '';
        }

        return implode("\n\n", $pluginSections);
    }

    /**
     * Parse AI response to extract intent data.
     */
    protected function parseResponse(string|array $response): array
    {
        // Handle function calling response (array format)
        if (is_array($response)) {
            return $this->parseFunctionCallResponse($response);
        }

        // Legacy: Handle text-based JSON response (for backward compatibility)
        // Clean response - remove markdown code blocks if present
        $response = preg_replace('/```json\s*/', '', $response);
        $response = preg_replace('/```\s*/', '', $response);
        $response = trim($response);

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'action' => 'unknown',
                'module' => 'general',
                'entities' => [],
                'confidence' => 0.0,
                'requires_confirmation' => false,
            ];
        }

        return [
            'action' => $data['action'] ?? 'unknown',
            'module' => $data['module'] ?? 'general',
            'entities' => $data['entities'] ?? [],
            'confidence' => (float) ($data['confidence'] ?? 0.5),
            'requires_confirmation' => (bool) ($data['requires_confirmation'] ?? false),
        ];
    }

    /**
     * Parse function calling response from OpenAI.
     */
    protected function parseFunctionCallResponse(array $response): array
    {
        // Response format from OpenAiProvider when using function calling:
        // ['function_name' => 'create_transaction', 'arguments' => [...]]

        $functionName = $response['function_name'] ?? null;
        $arguments = $response['arguments'] ?? $response;

        // If function_name not in response, try to infer from arguments
        if (! $functionName) {
            // This shouldn't happen with our OpenAiProvider implementation
            // but keep as fallback
            return [
                'action' => 'unknown',
                'module' => 'general',
                'entities' => $arguments,
                'confidence' => 0.5,
                'requires_confirmation' => false,
            ];
        }

        // Check if it's a plugin function (starts with 'plugin_')
        if (str_starts_with($functionName, 'plugin_')) {
            return [
                'action' => $functionName,
                'module' => 'plugin',
                'entities' => $arguments,
                'confidence' => 0.95,
                'requires_confirmation' => $this->functionRequiresConfirmation($functionName),
            ];
        }

        // Map function name to module and action
        $mapping = $this->getFunctionNameToModuleMapping();

        if (isset($mapping[$functionName])) {
            $module = $mapping[$functionName];
            $action = $functionName;
        } else {
            // Unknown function
            $module = 'general';
            $action = 'unknown';
        }

        // Determine if confirmation is required
        $requiresConfirmation = $this->functionRequiresConfirmation($functionName);

        return [
            'action' => $action,
            'module' => $module,
            'entities' => $arguments,
            'confidence' => 0.95, // Function calling is more confident
            'requires_confirmation' => $requiresConfirmation,
        ];
    }

    /**
     * Get mapping of function names to modules.
     */
    protected function getFunctionNameToModuleMapping(): array
    {
        return [
            // Finance module
            'create_transaction' => 'finance',
            'view_balance' => 'finance',
            'view_transactions' => 'finance',

            // Schedule module
            'create_schedule' => 'schedule',
            'view_schedules' => 'schedule',

            // Notes module
            'create_note' => 'notes',
            'view_notes' => 'notes',

            // General module
            'greeting' => 'general',
            'help' => 'general',
            'confirm' => 'general',
            'cancel' => 'general',
        ];
    }

    /**
     * Check if function requires user confirmation.
     */
    protected function functionRequiresConfirmation(string $functionName): bool
    {
        $mutationFunctions = [
            'create_transaction',
            'create_schedule',
            'create_note',
            // Plugin mutations will be detected by checking with the plugin itself
        ];

        // All create/update/delete operations require confirmation
        if (in_array($functionName, $mutationFunctions)) {
            return true;
        }

        // Check if it's a plugin function
        if (str_starts_with($functionName, 'plugin_')) {
            // Plugin mutations generally require confirmation
            // unless it's explicitly a read-only function
            return true;
        }

        return false;
    }
}
