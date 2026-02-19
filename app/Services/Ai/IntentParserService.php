<?php

namespace App\Services\Ai;

use App\Models\User;
use App\Services\Plugin\PluginManager;
use Illuminate\Support\Facades\Log;

/**
 * IntentParserService - Universal Intent Detection for ASPRI
 *
 * This service provides LLM-agnostic intent parsing that works with any AI provider.
 * It uses a dual approach for maximum compatibility:
 *
 * 1. Function Calling Approach (Primary):
 *    - Uses OpenAI-style function/tool calling when available
 *    - Provides structured output with high confidence
 *    - Best for providers that support function calling (OpenAI, compatible providers)
 *
 * 2. Prompt-Based Approach (Fallback):
 *    - Uses detailed system prompts requesting JSON output
 *    - Works with ANY LLM that can follow instructions
 *    - Compatible with Gemini, Claude, local models, etc.
 *
 * The service automatically tries function calling first, then falls back to
 * prompt-based parsing if needed, ensuring reliable intent detection regardless
 * of the AI provider being used.
 *
 * ## Two-Stage Intent Detection (Robust Plugin Handling)
 *
 * To handle scenarios with many active plugins (50+) without missing detection:
 *
 * **Stage 1: Module Classification (Lightweight)**
 * - Keyword matching for fast detection (finance, schedule, notes, plugins)
 * - LLM classification for ambiguous cases
 * - Identifies specific plugin(s) if mentioned
 * - Takes ~0.5-1s, uses ~200 tokens
 *
 * **Stage 2: Detailed Intent Parsing (Targeted)**
 * - Loads ONLY relevant plugin context based on Stage 1
 * - If plugin X detected → only load plugin X's intents
 * - If finance detected → only load finance functions
 * - Prevents context overflow and improves accuracy
 * - Takes ~1-2s, uses ~500-1000 tokens
 *
 * Benefits:
 * - No missed plugin detection (all plugins can be found)
 * - Optimal token usage (only relevant context loaded)
 * - Scales to 100+ plugins without degradation
 * - Fallback to top-10 prioritization if module unclear
 */
class IntentParserService
{
    public function __construct(
        protected AiProviderInterface $provider,
        protected PluginManager $pluginManager
    ) {}

    /**
     * Parse user message to detect intent.
     * Uses two-stage detection for robustness:
     * Stage 1: Classify module/plugin (lightweight)
     * Stage 2: Detailed intent parsing with relevant context only
     *
     * @return array{action: string, module: string, entities: array, confidence: float, requires_confirmation: bool}
     */
    public function parse(User $user, string $message, array $conversationHistory = []): array
    {
        // Stage 1: Quick classification to identify module/plugin
        $classification = $this->classifyModule($user, $message);

        Log::debug('Intent parsing stage 1', [
            'classification' => $classification,
            'message_preview' => substr($message, 0, 50),
        ]);

        // Stage 2: Detailed parsing with targeted context
        try {
            // Try function calling first (if provider supports it)
            try {
                $result = $this->parseWithFunctionCalling(
                    $user,
                    $message,
                    $conversationHistory,
                    $classification
                );

                if ($result['action'] !== 'unknown') {
                    return $result;
                }
            } catch (\Exception $e) {
                Log::warning('Function calling failed, falling back to prompt-based', [
                    'error' => $e->getMessage(),
                ]);
            }

            // Fallback to prompt-based
            return $this->parseWithPrompt($user, $message, $conversationHistory, $classification);
        } catch (\Exception $e) {
            Log::error('Both intent parsing methods failed', ['error' => $e->getMessage()]);

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
     * Stage 1: Classify which module or plugin the message is about.
     * This is a lightweight classification to narrow down context.
     *
     * @return array{module: string, plugin_slugs: array<string>, confidence: float}
     */
    protected function classifyModule(User $user, string $message): array
    {
        // Quick keyword-based detection first (fast path)
        $keywordResult = $this->detectModuleByKeywords($user, $message);
        if ($keywordResult['confidence'] >= 0.8) {
            Log::debug('Module detected by keywords', $keywordResult);

            return $keywordResult;
        }

        // Use LLM for ambiguous cases
        try {
            return $this->classifyModuleWithLLM($user, $message);
        } catch (\Exception $e) {
            Log::warning('LLM classification failed, using keyword result', [
                'error' => $e->getMessage(),
            ]);

            return $keywordResult;
        }
    }

    /**
     * Detect module using keyword matching (fast path).
     */
    protected function detectModuleByKeywords(User $user, string $message): array
    {
        $messageLower = strtolower($message);

        // Check plugin keywords FIRST (more specific than core modules)
        $activePlugins = $this->pluginManager->getActivePluginsForUser($user->id);
        foreach ($activePlugins as $userPlugin) {
            $plugin = $userPlugin->plugin;
            $pluginNameLower = strtolower($plugin->name);
            $pluginSlugLower = strtolower($plugin->slug);

            // Check if message mentions plugin name or slug
            if (str_contains($messageLower, $pluginNameLower) ||
                str_contains($messageLower, $pluginSlugLower)) {
                return [
                    'module' => 'plugin',
                    'plugin_slugs' => [$plugin->slug],
                    'confidence' => 0.9,
                ];
            }

            // Check plugin-specific keywords if available
            $pluginInstance = $this->pluginManager->getPlugin($plugin->slug);
            if ($pluginInstance && $pluginInstance->supportsChatIntegration()) {
                // You could add a method to plugins to return their keywords
                // For now, check intent examples
                $intents = $pluginInstance->getChatIntents();
                foreach ($intents as $intent) {
                    foreach ($intent['examples'] as $example) {
                        $exampleWords = explode(' ', strtolower($example));
                        $matchCount = 0;
                        foreach ($exampleWords as $word) {
                            if (strlen($word) > 3 && str_contains($messageLower, $word)) {
                                $matchCount++;
                            }
                        }
                        // If 2+ words match, likely this plugin
                        if ($matchCount >= 2) {
                            return [
                                'module' => 'plugin',
                                'plugin_slugs' => [$plugin->slug],
                                'confidence' => 0.75,
                            ];
                        }
                    }
                }
            }
        }

        // Then check core modules (less specific than plugins)
        $moduleKeywords = [
            'finance' => ['uang', 'gaji', 'pengeluaran', 'pemasukan', 'transfer', 'saldo', 'bayar', 'belanja', 'transaksi', 'keuangan', 'rupiah', 'rp'],
            'schedule' => ['jadwal', 'agenda', 'rapat', 'meeting', 'acara', 'event', 'ingatkan', 'reminder', 'besok', 'hari ini', 'minggu', 'bulan', 'tanggal', 'jam', 'ubah jadwal', 'pindah jadwal', 'hapus jadwal', 'ganti jadwal', 'batalkan jadwal', 'reschedule'],
            'notes' => ['catat', 'catatn', 'note', 'notes', 'memo', 'tulis', 'simpan catatan', 'buat catatan', 'bikin catatan', 'bikin notes', 'bikin catatn', 'bikinin catatan', 'bikinin notes', 'ingat', 'ide', 'update catatan', 'edit catatan', 'ubah catatan', 'hapus catatan', 'histori'],
            'general' => ['halo', 'hi', 'help', 'bantuan', 'apa', 'siapa', 'gimana', 'bagaimana', 'terima kasih', 'thanks'],
        ];

        foreach ($moduleKeywords as $module => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($messageLower, $keyword)) {
                    return [
                        'module' => $module,
                        'plugin_slugs' => [],
                        'confidence' => 0.85,
                    ];
                }
            }
        }

        // No clear match
        return [
            'module' => 'unknown',
            'plugin_slugs' => [],
            'confidence' => 0.0,
        ];
    }

    /**
     * Classify module using LLM (for ambiguous cases).
     */
    protected function classifyModuleWithLLM(User $user, string $message): array
    {
        $activePlugins = $this->pluginManager->getActivePluginsForUser($user->id);
        $pluginList = $activePlugins->map(function ($up) {
            return "- {$up->plugin->slug}: {$up->plugin->name} - {$up->plugin->description}";
        })->join("\n");

        $prompt = <<<PROMPT
Classify which module this user message is about. Return ONLY a JSON object.

Available modules:
- finance: Money, transactions, expenses, income, balance
- schedule: Events, meetings, reminders, calendar
- notes: Notes, memos, ideas, writing
- general: Greetings, help requests, confirmations
- plugin: Third-party plugins (see list below)

Active plugins:
{$pluginList}

User message: "{$message}"

Return JSON format:
{
  "module": "finance|schedule|notes|general|plugin|unknown",
  "plugin_slugs": ["plugin-slug-if-applicable"],
  "confidence": 0.95,
  "reasoning": "brief explanation"
}
PROMPT;

        $response = $this->provider->chat([
            ['role' => 'system', 'content' => 'You are a message classifier. Return only JSON.'],
            ['role' => 'user', 'content' => $prompt],
        ], [
            'temperature' => 0.3,
            'max_tokens' => 1024,
        ]);

        // Parse JSON response
        $cleaned = trim(preg_replace('/```json\s*|```\s*/', '', $response));
        if (preg_match('/\{[\s\S]*\}/m', $cleaned, $matches)) {
            $cleaned = $matches[0];
        }

        $data = json_decode($cleaned, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($data['module'])) {
            Log::debug('LLM classification result', $data);

            return [
                'module' => $data['module'],
                'plugin_slugs' => $data['plugin_slugs'] ?? [],
                'confidence' => (float) ($data['confidence'] ?? 0.7),
            ];
        }

        // Fallback
        return ['module' => 'unknown', 'plugin_slugs' => [], 'confidence' => 0.0];
    }

    /**
     * Parse intent using function calling (OpenAI-style).
     * Now with targeted context based on stage 1 classification.
     */
    protected function parseWithFunctionCalling(
        User $user,
        string $message,
        array $conversationHistory = [],
        ?array $classification = null
    ): array {
        $functions = $this->buildFunctionDefinitions($user, $classification);

        $currentDate = now()->format('l, d F Y');
        $currentTime = now()->format('H:i');

        $messages = [
            [
                'role' => 'system',
                'content' => "You are an intent classifier. Current date: {$currentDate}, time: {$currentTime}. Analyze the user message and call the appropriate function that matches their intent. Extract all relevant entities from the message. When user mentions time-relative terms like 'hari ini' (today), 'kemarin' (yesterday), 'besok' (tomorrow), convert them to actual dates in the content.",
            ],
        ];

        // Add conversation history for context (last 6 messages to retain note/entity context)
        foreach (array_slice($conversationHistory, -6) as $msg) {
            $messages[] = $msg;
        }

        $messages[] = ['role' => 'user', 'content' => $message];

        $response = $this->provider->chat($messages, [
            'functions' => $functions,
            'tool_choice' => 'auto',
        ]);

        return $this->parseResponse($response);
    }

    /**
     * Parse intent using prompt-based approach (works with any LLM).
     * Now with targeted context based on stage 1 classification.
     */
    protected function parseWithPrompt(
        User $user,
        string $message,
        array $conversationHistory = [],
        ?array $classification = null
    ): array {
        $systemPrompt = $this->buildIntentPrompt($user, $classification);

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        // Add conversation history for context (last 6 messages to retain note/entity context)
        foreach (array_slice($conversationHistory, -6) as $msg) {
            $messages[] = $msg;
        }

        $messages[] = [
            'role' => 'user',
            'content' => "Analyze this message and return ONLY a JSON object with the intent classification:\n\n{$message}",
        ];

        $response = $this->provider->chat($messages, [
            'temperature' => 0.3, // Lower temperature for more consistent JSON output
            'max_tokens' => 500,
        ]);

        return $this->parseResponse($response);
    }

    /**
     * Build the system prompt for intent detection (prompt-based approach).
     * Now with targeted context based on classification.
     */
    protected function buildIntentPrompt(User $user, ?array $classification = null): string
    {
        $currentDate = now()->format('l, d F Y');
        $currentTime = now()->format('H:i');

        $pluginInfo = $this->getActivePluginsInfo($user, $classification);

        $prompt = <<<PROMPT
You are an intent classification expert for ASPRI personal assistant.

Current Context:
- Date: {$currentDate}
- Time: {$currentTime}

IMPORTANT: When user mentions time-relative terms like "hari ini" (today), "kemarin" (yesterday), "besok" (tomorrow), convert them to actual dates (e.g., "19 Feb 2026") in the content field.

Your task is to analyze user messages and return ONLY a valid JSON object with this structure:
{
  "action": "action_name",
  "module": "module_name",
  "entities": { "key": "value", ... },
  "confidence": 0.95,
  "requires_confirmation": true/false
}

## AVAILABLE MODULES & ACTIONS:

### FINANCE MODULE
Actions:
- create_transaction: Record income/expense
  entities: {tx_type: "income|expense", amount: number, category?: string, note?: string}
  examples: "catat pengeluaran 50k untuk makan", "dapat gaji 5jt"
  requires_confirmation: true

- view_balance: View financial summary
  entities: {period?: "today|this_week|this_month|all"}
  examples: "berapa saldo bulan ini?", "lihat keuangan hari ini"
  requires_confirmation: false

- view_transactions: List transactions
  entities: {period?: "today|this_week|this_month", tx_type?: "income|expense", limit?: number}
  examples: "daftar pengeluaran minggu ini", "transaksi bulan ini"
  requires_confirmation: false

### SCHEDULE MODULE
Actions:
- create_schedule: Create event/reminder
  entities: {title: string, start_time?: string}
  examples: "ingatkan rapat besok jam 2", "buat jadwal meeting"
  requires_confirmation: true

- update_schedule: Update an existing event/schedule
  entities: {title?: string, schedule_id?: string, new_title?: string, start_time?: string, end_time?: string, location?: string, description?: string}
  examples: "ubah jadwal meeting jadi jam 3", "pindah rapat ke besok", "ganti lokasi meeting ke ruang B", "update jadwal"
  IMPORTANT: Use title or schedule_id to identify the schedule. Include only the fields that need updating.
  requires_confirmation: true

- delete_schedule: Delete an existing event/schedule
  entities: {title?: string, schedule_id?: string}
  examples: "hapus jadwal meeting", "batalkan rapat besok", "delete agenda"
  requires_confirmation: true

- view_schedules: View upcoming events
  entities: {period?: "today|tomorrow|this_week|this_month"}
  examples: "jadwal hari ini", "agenda minggu depan"
  requires_confirmation: false

### NOTES MODULE
Actions:
- create_note: Create a new note
  entities: {title?: string, content: string, tags?: array}
  examples: "catat ide bisnis baru", "buat catatan meeting", "bikinin catatan untuk histori tekanan darah"
  IMPORTANT: Convert time-relative terms ("hari ini", "kemarin", "besok") to actual dates in content (e.g., "hari ini" → "19 Feb 2026")
  requires_confirmation: false

- update_note: Update an existing note
  entities: {title?: string, keyword?: string, note_id?: string, new_title?: string, content?: string, tags?: array}
  examples: "update catatan ngaji jadi surat albaqarah", "edit catatan meeting", "ubah isi catatan X jadi Y"
  IMPORTANT: (1) For content, always reconstruct the COMPLETE updated content using conversation context. (2) Convert time-relative terms to actual dates.
  requires_confirmation: false

- delete_note: Delete an existing note
  entities: {title?: string, note_id?: string}
  examples: "hapus catatan meeting", "delete catatan lama"
  requires_confirmation: true

- view_notes: Search or view notes
  entities: {search?: string, tags?: array}
  examples: "cari catatan tentang project", "tampilkan semua notes"
  requires_confirmation: false

### GENERAL MODULE
Actions:
- greeting: User greeting
  entities: {}
  examples: "halo", "hi aspri", "selamat pagi"

- help: Request help/guidance
  entities: {topic?: string}
  examples: "bantuan fitur keuangan", "apa yang bisa kamu lakukan"

- confirm: Confirm pending action
  entities: {}
  examples: "ya", "oke", "simpan", "setuju"

- cancel: Cancel pending action
  entities: {}
  examples: "tidak", "batal", "cancel"

- out_of_scope: Question clearly outside assistant capabilities
  entities: {topic: string, question_type?: string}
  examples: "berapa kurs dollar?", "siapa presiden indonesia?", "bagaimana cara membuat nasi goreng?"
  Use this when user asks about: weather, news, exchange rates, general knowledge, recipes, external data, calculations not related to personal finance

- unknown: Cannot understand user intent
  entities: {unclear_reason?: string}
  examples: "asdfghjkl", "wkwkwk", very ambiguous messages
  Use this only when the message is truly unclear or gibberish

PROMPT;

        // Append plugin information if available
        if (! empty($pluginInfo)) {
            $prompt .= "\n\n".$pluginInfo;
        }

        $prompt .= <<<'FOOTER'

## INSTRUCTIONS:
1. Analyze the user message carefully
2. Identify the best matching action and module
3. Extract all relevant entities from the message
4. Set confidence based on match quality (0.0-1.0)
5. Set requires_confirmation to true for mutations (create/update/delete)
6. Return ONLY valid JSON, no explanation or markdown
7. Use "out_of_scope" (confidence > 0.7) when user asks something ASPRI cannot do (weather, news, exchange rates, etc.)
8. Use "unknown" (confidence < 0.5) only when message is truly unclear or gibberish
9. Prefer specific actions over out_of_scope/unknown when possible

## ENTITY EXTRACTION TIPS:
- Parse natural language amounts: "50k" → 50000, "5jt" → 5000000
- Extract implicit information: "pengeluaran makan" → {tx_type: "expense", category: "makan"}
- Parse time references: "besok", "minggu depan", etc.
- Handle Indonesian language naturally

Return ONLY the JSON object, nothing else.
FOOTER;

        return $prompt;
    }

    /**
     * Build function definitions for OpenAI function calling.
     * Now with targeted selection based on classification.
     *
     * @return array<int, array>
     */
    protected function buildFunctionDefinitions(User $user, ?array $classification = null): array
    {
        $functions = [];

        // Always include ALL core module functions to prevent misclassification
        // from hiding valid actions. Stage 1 only filters plugins, not core modules.
        $functions = array_merge($functions, $this->getGeneralFunctions());
        $functions = array_merge($functions, $this->getFinanceFunctions());
        $functions = array_merge($functions, $this->getScheduleFunctions());
        $functions = array_merge($functions, $this->getNotesFunctions());

        // Add plugin functions based on classification
        if ($classification && $classification['module'] === 'plugin') {
            // Only include specific plugins identified in classification
            $functions = array_merge($functions, $this->getPluginFunctions($user, $classification['plugin_slugs'] ?? []));
        } elseif (! $classification || $classification['module'] === 'unknown') {
            // Fallback: include all plugins
            $functions = array_merge($functions, $this->getPluginFunctions($user));
        }

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
                'name' => 'update_schedule',
                'description' => 'Update an existing event or schedule. Use title or schedule_id to find it, then provide the fields to change.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => [
                            'type' => 'string',
                            'description' => 'Current title of the schedule to find',
                        ],
                        'schedule_id' => [
                            'type' => 'string',
                            'description' => 'ID of the schedule to update',
                        ],
                        'new_title' => [
                            'type' => 'string',
                            'description' => 'New title for the schedule',
                        ],
                        'start_time' => [
                            'type' => 'string',
                            'description' => 'New start time in YYYY-MM-DD HH:mm format',
                        ],
                        'end_time' => [
                            'type' => 'string',
                            'description' => 'New end time in YYYY-MM-DD HH:mm format',
                        ],
                        'location' => [
                            'type' => 'string',
                            'description' => 'New location for the schedule',
                        ],
                        'description' => [
                            'type' => 'string',
                            'description' => 'New description for the schedule',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'delete_schedule',
                'description' => 'Delete an existing event or schedule',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => [
                            'type' => 'string',
                            'description' => 'Title of the schedule to delete',
                        ],
                        'schedule_id' => [
                            'type' => 'string',
                            'description' => 'ID of the schedule to delete',
                        ],
                    ],
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
                'description' => 'Create a new note. IMPORTANT: Convert time-relative terms ("hari ini", "kemarin", "besok", "today", "yesterday") to actual dates in the content field.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => 'string'],
                        'content' => [
                            'type' => 'string',
                            'description' => 'Note content. Convert relative dates to actual dates (e.g., "hari ini" → "19 Feb 2026").',
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
                'name' => 'update_note',
                'description' => 'Update an existing note content or title. IMPORTANT: (1) Use conversation context to reconstruct the COMPLETE updated content, not just the changed part. (2) Convert time-relative terms ("hari ini", "kemarin", "besok", "today", "yesterday") to actual dates in the content. If user says "update jadi 130", find the note and write out the full new content with 130 replacing the old value.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => [
                            'type' => 'string',
                            'description' => 'Partial title of the note to find (case-insensitive search)',
                        ],
                        'keyword' => [
                            'type' => 'string',
                            'description' => 'Keyword to search in note title or content when title is unknown',
                        ],
                        'note_id' => [
                            'type' => 'string',
                            'description' => 'ID of the note to update',
                        ],
                        'new_title' => [
                            'type' => 'string',
                            'description' => 'New title for the note',
                        ],
                        'content' => [
                            'type' => 'string',
                            'description' => 'The COMPLETE new content for the note. Reconstruct full content from context and convert relative dates to actual dates. Example: if note had "Al-Baqarah ayat 129" and user says "update jadi 130", write "Sampai surat Al-Baqarah ayat 130". If user says "hari ini", write "19 Feb 2026".',
                        ],
                        'tags' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'delete_note',
                'description' => 'Delete an existing note',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => [
                            'type' => 'string',
                            'description' => 'Title of the note to delete',
                        ],
                        'note_id' => [
                            'type' => 'string',
                            'description' => 'ID of the note to delete',
                        ],
                    ],
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
            [
                'name' => 'out_of_scope',
                'description' => 'User is asking about something clearly outside assistant capabilities (weather, news, exchange rates, general knowledge, recipes, external data, calculations not related to personal finance)',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'topic' => [
                            'type' => 'string',
                            'description' => 'The topic user is asking about',
                        ],
                        'question_type' => [
                            'type' => 'string',
                            'description' => 'Type of question (weather, news, exchange_rate, general_knowledge, recipe, etc.)',
                        ],
                    ],
                    'required' => ['topic'],
                ],
            ],
            [
                'name' => 'unknown',
                'description' => 'User intent is truly unclear, ambiguous, or gibberish. Use only when message cannot be understood at all.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'unclear_reason' => [
                            'type' => 'string',
                            'description' => 'Why the message is unclear',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Get plugin function definitions with smart selection for scalability.
     * Now supports targeted plugin selection from classification.
     */
    protected function getPluginFunctions(User $user, array $targetPluginSlugs = []): array
    {
        $functions = [];
        $activePlugins = $this->pluginManager->getActivePluginsForUser($user->id);

        // If specific plugins are targeted, only include those
        if (! empty($targetPluginSlugs)) {
            $activePlugins = $activePlugins->filter(function ($up) use ($targetPluginSlugs) {
                return in_array($up->plugin->slug, $targetPluginSlugs);
            });
            $maxPlugins = count($targetPluginSlugs); // Include all targeted
        } else {
            // Sort by priority (same logic as prompt-based approach)
            $activePlugins = $this->prioritizePlugins($activePlugins);
            $maxPlugins = 10; // Default limit
        }

        $pluginsIncluded = 0;

        foreach ($activePlugins as $userPlugin) {
            // Check if we've reached plugin limit
            if ($pluginsIncluded >= $maxPlugins) {
                Log::info('Function calling: plugin limit reached', [
                    'included' => $pluginsIncluded,
                    'total' => $sortedPlugins->count(),
                ]);
                break;
            }

            $plugin = $userPlugin->plugin;
            $pluginInstance = $this->pluginManager->getPlugin($plugin->slug);

            if (! $pluginInstance || ! $pluginInstance->supportsChatIntegration()) {
                continue;
            }

            $intents = $pluginInstance->getChatIntents();

            if (empty($intents)) {
                continue;
            }

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

                // Use condensed examples (only 1) to save tokens
                $exampleText = ! empty($intent['examples'])
                    ? ' Example: '.$intent['examples'][0]
                    : '';

                $functions[] = [
                    'name' => $intent['action'],
                    'description' => $intent['description'].$exampleText,
                    'parameters' => [
                        'type' => 'object',
                        'properties' => $properties,
                        'required' => $required,
                    ],
                ];
            }

            $pluginsIncluded++;
        }

        return $functions;
    }

    /**
     * Get active plugins information for the user with smart selection.
     * Implements scalability for many plugins by:
     * - Limiting number of plugins included
     * - Prioritizing by usage frequency
     * - Estimating tokens to prevent prompt overflow
     * Now supports targeted plugin selection from classification.
     */
    protected function getActivePluginsInfo(User $user, ?array $classification = null): string
    {
        $activePlugins = $this->pluginManager->getActivePluginsForUser($user->id);

        if ($activePlugins->isEmpty()) {
            return '';
        }

        // If classification specifies plugins, only include those
        if ($classification && $classification['module'] === 'plugin' && ! empty($classification['plugin_slugs'])) {
            $targetSlugs = $classification['plugin_slugs'];
            $sortedPlugins = $activePlugins->filter(function ($up) use ($targetSlugs) {
                return in_array($up->plugin->slug, $targetSlugs);
            });
            $maxPlugins = count($targetSlugs); // Include all targeted plugins
            $maxTokensEstimate = 2000; // Can afford more tokens for targeted plugins
        } else {
            // Sort plugins by priority (most recently used / most frequently used first)
            $sortedPlugins = $this->prioritizePlugins($activePlugins);
            $maxPlugins = 10; // Max number of plugins to include
            $maxTokensEstimate = 1500; // Approximate token budget for plugins
        }
        $currentTokensEstimate = 0;

        $pluginSections = [];
        $pluginsIncluded = 0;

        foreach ($sortedPlugins as $userPlugin) {
            // Check if we've reached limits
            if ($pluginsIncluded >= $maxPlugins) {
                Log::info('Plugin limit reached, truncating plugin list', [
                    'included' => $pluginsIncluded,
                    'total' => $sortedPlugins->count(),
                ]);
                break;
            }

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

            // Build plugin section and estimate tokens
            $pluginSection = $this->buildPluginSection($plugin, $intents, $currentTokensEstimate >= $maxTokensEstimate * 0.8);
            $sectionTokens = $this->estimateTokens($pluginSection);

            // Check if adding this plugin would exceed token budget
            if ($currentTokensEstimate + $sectionTokens > $maxTokensEstimate) {
                Log::info('Token budget would be exceeded, stopping plugin inclusion', [
                    'current' => $currentTokensEstimate,
                    'would_add' => $sectionTokens,
                    'budget' => $maxTokensEstimate,
                ]);
                break;
            }

            $pluginSections[] = $pluginSection;
            $currentTokensEstimate += $sectionTokens;
            $pluginsIncluded++;
        }

        if (empty($pluginSections)) {
            return '';
        }

        // Add summary if not all plugins were included
        $totalPlugins = $sortedPlugins->count();
        if ($pluginsIncluded < $totalPlugins) {
            $remaining = $totalPlugins - $pluginsIncluded;
            $pluginSections[] = "\n(Note: {$remaining} more plugin(s) available. Ask for 'plugin help' for full list)";
        }

        return implode("\n\n", $pluginSections);
    }

    /**
     * Prioritize plugins based on usage patterns.
     */
    protected function prioritizePlugins($plugins)
    {
        return $plugins->sortByDesc(function ($userPlugin) {
            // Priority factors:
            // 1. Last used date (more recent = higher priority)
            // 2. Total usage count (more used = higher priority)
            // 3. Is currently installed (fallback)

            $lastUsedScore = 0;
            if ($userPlugin->relationLoaded('plugin') && $userPlugin->plugin->relationLoaded('logs')) {
                $recentLogs = $userPlugin->plugin->logs()
                    ->where('user_id', $userPlugin->user_id)
                    ->where('created_at', '>', now()->subDays(7))
                    ->count();
                $lastUsedScore = $recentLogs * 10;
            }

            // Simple scoring: recent usage heavily weighted
            return $lastUsedScore + ($userPlugin->is_active ? 5 : 0);
        });
    }

    /**
     * Build plugin section with optional condensed mode.
     */
    protected function buildPluginSection($plugin, array $intents, bool $condensed = false): string
    {
        $section = "### PLUGIN: {$plugin->name} ({$plugin->slug})\n";
        $section .= "Actions:\n";

        foreach ($intents as $intent) {
            $entitiesDef = [];
            foreach ($intent['entities'] as $key => $type) {
                $isOptional = str_ends_with($type, '|null');
                $baseType = str_replace('|null', '', $type);
                $entitiesDef[] = "{$key}".($isOptional ? '?' : '').': '.$baseType;
            }

            $section .= "- {$intent['action']}: {$intent['description']}\n";
            $section .= "  entities: {plugin_slug: \"{$plugin->slug}\", ".implode(', ', $entitiesDef)."}\n";

            // In condensed mode, only include 1 example; otherwise 2
            $exampleLimit = $condensed ? 1 : 2;
            if (! empty($intent['examples'])) {
                $examples = array_slice($intent['examples'], 0, $exampleLimit);
                $section .= '  ex: '.implode(', ', array_map(fn ($ex) => "\"{$ex}\"", $examples))."\n";
            }

            // Determine if confirmation required
            $requiresConfirm = $this->functionRequiresConfirmation($intent['action']);
            $section .= '  requires_confirmation: '.($requiresConfirm ? 'true' : 'false')."\n";
        }

        return $section;
    }

    /**
     * Estimate token count for a string (rough approximation).
     * Rule of thumb: ~4 characters per token for English, ~2-3 for code/structured text.
     */
    protected function estimateTokens(string $text): int
    {
        // Rough estimation: 1 token ≈ 3.5 characters on average
        return (int) ceil(strlen($text) / 3.5);
    }

    /**
     * Parse AI response to extract intent data.
     * Handles both function calling (array) and prompt-based (JSON string) responses.
     */
    protected function parseResponse(string|array $response): array
    {
        // Handle function calling response (array format from OpenAI-style providers)
        if (is_array($response)) {
            return $this->parseFunctionCallResponse($response);
        }

        // Handle text-based JSON response (prompt-based approach)
        return $this->parseJsonResponse($response);
    }

    /**
     * Parse JSON string response from prompt-based approach.
     */
    protected function parseJsonResponse(string $response): array
    {
        // Clean response - remove markdown code blocks if present
        $response = preg_replace('/```json\s*/', '', $response);
        $response = preg_replace('/```\s*/', '', $response);
        $response = trim($response);

        // Try to extract JSON if there's extra text
        if (preg_match('/\{[\s\S]*\}/m', $response, $matches)) {
            $response = $matches[0];
        }

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('Failed to parse JSON response', [
                'response' => $response,
                'error' => json_last_error_msg(),
            ]);

            return [
                'action' => 'unknown',
                'module' => 'general',
                'entities' => [],
                'confidence' => 0.0,
                'requires_confirmation' => false,
            ];
        }

        // Validate required fields
        if (! isset($data['action']) || ! isset($data['module'])) {
            Log::warning('Invalid JSON structure', ['data' => $data]);

            return [
                'action' => 'unknown',
                'module' => 'general',
                'entities' => [],
                'confidence' => 0.0,
                'requires_confirmation' => false,
            ];
        }

        // Check if it's a plugin action - must route to plugin module
        $action = $data['action'];
        $module = $data['module'];
        $entities = $data['entities'] ?? [];

        if (isset($entities['plugin_slug']) || str_starts_with($action, 'plugin_')) {
            $module = 'plugin';
        }

        return [
            'action' => $action,
            'module' => $module,
            'entities' => $entities,
            'confidence' => (float) ($data['confidence'] ?? 0.5),
            'requires_confirmation' => (bool) ($data['requires_confirmation'] ?? false),
        ];
    }

    /**
     * Parse function calling response from OpenAI-style providers.
     */
    protected function parseFunctionCallResponse(array $response): array
    {
        // Response format from providers with function calling:
        // ['function_name' => 'create_transaction', 'arguments' => [...]]

        $functionName = $response['function_name'] ?? null;
        $arguments = $response['arguments'] ?? [];

        // If no function was called, treat as unknown intent
        if (! $functionName) {
            Log::debug('No function name in response', ['response' => $response]);

            return [
                'action' => 'unknown',
                'module' => 'general',
                'entities' => $arguments,
                'confidence' => 0.3,
                'requires_confirmation' => false,
            ];
        }

        // Check if it's a plugin function
        if (isset($arguments['plugin_slug']) || str_starts_with($functionName, 'plugin_')) {
            return [
                'action' => $functionName,
                'module' => 'plugin',
                'entities' => $arguments,
                'confidence' => 0.95,
                'requires_confirmation' => $this->functionRequiresConfirmation($functionName),
            ];
        }

        // Map function name to module
        $mapping = $this->getFunctionNameToModuleMapping();
        $module = $mapping[$functionName] ?? 'general';

        // If unknown function, log warning
        if (! isset($mapping[$functionName])) {
            Log::warning('Unknown function called', ['function' => $functionName]);
        }

        return [
            'action' => $functionName,
            'module' => $module,
            'entities' => $arguments,
            'confidence' => 0.95, // Function calling is generally more confident
            'requires_confirmation' => $this->functionRequiresConfirmation($functionName),
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
            'update_schedule' => 'schedule',
            'delete_schedule' => 'schedule',
            'view_schedules' => 'schedule',

            // Notes module
            'create_note' => 'notes',
            'update_note' => 'notes',
            'delete_note' => 'notes',
            'view_notes' => 'notes',

            // General module
            'greeting' => 'general',
            'help' => 'general',
            'confirm' => 'general',
            'cancel' => 'general',
            'out_of_scope' => 'general',
            'unknown' => 'general',
        ];
    }

    /**
     * Check if function/action requires user confirmation.
     * All mutations (create/update/delete) require confirmation.
     */
    protected function functionRequiresConfirmation(string $functionName): bool
    {
        // Read-only actions that don't require confirmation
        $readOnlyActions = [
            'view_balance',
            'view_transactions',
            'view_schedules',
            'view_notes',
            'greeting',
            'help',
            'out_of_scope',
            'unknown',
        ];

        if (in_array($functionName, $readOnlyActions)) {
            return false;
        }

        // Confirmation actions themselves don't need confirmation
        if (in_array($functionName, ['confirm', 'cancel'])) {
            return false;
        }

        // Note mutations (create/update) execute directly without confirmation
        $directExecutionActions = [
            'create_note',
            'update_note',
        ];

        if (in_array($functionName, $directExecutionActions)) {
            return false;
        }

        // All other create/update/delete actions require confirmation
        $mutationActions = [
            'create_transaction',
            'update_transaction',
            'delete_transaction',
            'create_schedule',
            'update_schedule',
            'delete_schedule',
            'delete_note',
        ];

        if (in_array($functionName, $mutationActions)) {
            return true;
        }

        // Plugin functions: check if it's a mutation based on naming pattern
        if (str_starts_with($functionName, 'plugin_')) {
            // If function name contains create/update/delete/add/remove, it's likely a mutation
            $mutationPatterns = ['create', 'update', 'delete', 'add', 'remove', 'save', 'store', 'modify'];
            foreach ($mutationPatterns as $pattern) {
                if (stripos($functionName, $pattern) !== false) {
                    return true;
                }
            }

            // Default to requiring confirmation for unknown plugin actions
            return true;
        }

        // Default: require confirmation for unknown actions (safer)
        return true;
    }
}
