<?php

namespace App\Services\Ai;

use App\Models\User;
use App\Services\Plugin\PluginManager;

/**
 * Builds the native tool-use (function calling) definitions sent to AI
 * providers via `provider.chat($messages, ['functions' => ...])`, and
 * resolves a tool name back to its executor target metadata.
 */
class ToolRegistry
{
    public function __construct(protected PluginManager $pluginManager) {}

    /**
     * Get all tool definitions available to the given user: the 11 core
     * tools plus tools contributed by the user's active plugins.
     *
     * @return array<int, array{name: string, description: string, parameters: array}>
     */
    public function definitions(User $user): array
    {
        return array_merge($this->coreDefinitions(), $this->pluginDefinitions($user));
    }

    /**
     * Resolve a tool name to its metadata and executor mapping.
     *
     * @return array<string, mixed>|null
     */
    public function resolve(string $name): ?array
    {
        if (isset($this->coreTools()[$name])) {
            $tool = $this->coreTools()[$name];

            $resolved = [
                'name' => $name,
                'module' => $tool['module'],
                'mutates' => $tool['mutates'],
            ];

            if ($tool['mutates']) {
                $resolved['action_type'] = $tool['action_type'];
            } else {
                $resolved['method'] = $tool['method'];
            }

            return $resolved;
        }

        return $this->resolvePluginTool($name);
    }

    /**
     * The 11 core tool definitions (schema only).
     *
     * @return array<int, array{name: string, description: string, parameters: array}>
     */
    protected function coreDefinitions(): array
    {
        $definitions = [];

        foreach ($this->coreTools() as $name => $tool) {
            $definitions[] = [
                'name' => $name,
                'description' => $tool['description'],
                'parameters' => $tool['parameters'],
            ];
        }

        return $definitions;
    }

    /**
     * Core tool metadata: schema + executor mapping.
     *
     * @return array<string, array{description: string, parameters: array, module: string, mutates: bool, action_type?: string, method?: string}>
     */
    protected function coreTools(): array
    {
        return [
            'create_transaction' => [
                'description' => 'Catat transaksi keuangan baru (pemasukan atau pengeluaran).',
                'module' => 'finance',
                'mutates' => true,
                'action_type' => 'create_transaction',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'tx_type' => [
                            'type' => 'string',
                            'enum' => ['income', 'expense'],
                            'description' => 'Jenis transaksi: pemasukan (income) atau pengeluaran (expense).',
                        ],
                        'amount' => [
                            'type' => 'number',
                            'description' => 'Jumlah nominal transaksi dalam Rupiah.',
                        ],
                        'category' => [
                            'type' => 'string',
                            'description' => 'Kategori transaksi, misal "Makanan" atau "Gaji".',
                        ],
                        'note' => [
                            'type' => 'string',
                            'description' => 'Catatan tambahan mengenai transaksi.',
                        ],
                        'occurred_at' => [
                            'type' => 'string',
                            'description' => 'Tanggal transaksi terjadi (format YYYY-MM-DD). Default hari ini jika tidak diisi.',
                        ],
                    ],
                    'required' => ['tx_type', 'amount'],
                ],
            ],

            'view_balance' => [
                'description' => 'Lihat ringkasan saldo dan keuangan (total pemasukan, pengeluaran, dan saldo) untuk periode tertentu.',
                'module' => 'finance',
                'mutates' => false,
                'method' => 'getFinanceSummary',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'period' => [
                            'type' => 'string',
                            'enum' => ['today', 'this_week', 'this_month'],
                            'description' => 'Periode ringkasan keuangan. Default this_month jika tidak diisi.',
                        ],
                    ],
                    'required' => [],
                ],
            ],

            'view_transactions' => [
                'description' => 'Lihat daftar transaksi keuangan terbaru, bisa difilter berdasarkan periode dan jenis transaksi.',
                'module' => 'finance',
                'mutates' => false,
                'method' => 'getTransactions',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'period' => [
                            'type' => 'string',
                            'enum' => ['today', 'this_week', 'this_month'],
                            'description' => 'Periode transaksi yang ingin dilihat.',
                        ],
                        'tx_type' => [
                            'type' => 'string',
                            'enum' => ['income', 'expense'],
                            'description' => 'Filter berdasarkan jenis transaksi: pemasukan atau pengeluaran.',
                        ],
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Jumlah maksimal transaksi yang ditampilkan. Default 5.',
                        ],
                    ],
                    'required' => [],
                ],
            ],

            'create_schedule' => [
                'description' => 'Buat jadwal atau pengingat baru.',
                'module' => 'schedule',
                'mutates' => true,
                'action_type' => 'create_schedule',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => [
                            'type' => 'string',
                            'description' => 'Judul atau nama kegiatan jadwal.',
                        ],
                        'start_time' => [
                            'type' => 'string',
                            'description' => 'Waktu mulai jadwal (format YYYY-MM-DD HH:MM).',
                        ],
                        'end_time' => [
                            'type' => 'string',
                            'description' => 'Waktu selesai jadwal (format YYYY-MM-DD HH:MM).',
                        ],
                        'location' => [
                            'type' => 'string',
                            'description' => 'Lokasi kegiatan.',
                        ],
                        'description' => [
                            'type' => 'string',
                            'description' => 'Deskripsi tambahan mengenai jadwal.',
                        ],
                    ],
                    'required' => ['title', 'start_time'],
                ],
            ],

            'update_schedule' => [
                'description' => 'Ubah jadwal yang sudah ada. Gunakan schedule_id jika diketahui, atau title untuk mencari jadwal yang akan diubah.',
                'module' => 'schedule',
                'mutates' => true,
                'action_type' => 'update_schedule',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'schedule_id' => [
                            'type' => 'integer',
                            'description' => 'ID jadwal yang akan diubah, jika diketahui.',
                        ],
                        'title' => [
                            'type' => 'string',
                            'description' => 'Judul jadwal saat ini, digunakan untuk mencari jadwal jika schedule_id tidak diisi.',
                        ],
                        'new_title' => [
                            'type' => 'string',
                            'description' => 'Judul baru untuk jadwal.',
                        ],
                        'start_time' => [
                            'type' => 'string',
                            'description' => 'Waktu mulai baru (format YYYY-MM-DD HH:MM).',
                        ],
                        'end_time' => [
                            'type' => 'string',
                            'description' => 'Waktu selesai baru (format YYYY-MM-DD HH:MM).',
                        ],
                        'location' => [
                            'type' => 'string',
                            'description' => 'Lokasi baru untuk jadwal.',
                        ],
                        'description' => [
                            'type' => 'string',
                            'description' => 'Deskripsi baru untuk jadwal.',
                        ],
                    ],
                    'required' => [],
                ],
            ],

            'delete_schedule' => [
                'description' => 'Hapus jadwal. Gunakan schedule_id jika diketahui, atau title untuk mencari jadwal yang akan dihapus.',
                'module' => 'schedule',
                'mutates' => true,
                'action_type' => 'delete_schedule',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'schedule_id' => [
                            'type' => 'integer',
                            'description' => 'ID jadwal yang akan dihapus, jika diketahui.',
                        ],
                        'title' => [
                            'type' => 'string',
                            'description' => 'Judul jadwal yang akan dihapus, digunakan untuk mencari jadwal jika schedule_id tidak diisi.',
                        ],
                    ],
                    'required' => [],
                ],
            ],

            'view_schedules' => [
                'description' => 'Lihat daftar jadwal atau pengingat untuk periode tertentu.',
                'module' => 'schedule',
                'mutates' => false,
                'method' => 'getSchedules',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'period' => [
                            'type' => 'string',
                            'enum' => ['today', 'tomorrow', 'this_week', 'this_month'],
                            'description' => 'Periode jadwal yang ingin dilihat. Default this_month jika tidak diisi.',
                        ],
                    ],
                    'required' => [],
                ],
            ],

            'create_note' => [
                'description' => 'Buat catatan baru.',
                'module' => 'notes',
                'mutates' => true,
                'action_type' => 'create_note',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => [
                            'type' => 'string',
                            'description' => 'Judul catatan. Jika tidak diisi, akan menggunakan "Catatan <tanggal>".',
                        ],
                        'content' => [
                            'type' => 'string',
                            'description' => 'Isi catatan.',
                        ],
                        'tags' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Daftar tag untuk catatan.',
                        ],
                    ],
                    'required' => ['content'],
                ],
            ],

            'update_note' => [
                'description' => 'Ubah catatan yang sudah ada. Gunakan note_id jika diketahui, atau title/keyword untuk mencari catatan yang akan diubah.',
                'module' => 'notes',
                'mutates' => true,
                'action_type' => 'update_note',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'note_id' => [
                            'type' => 'integer',
                            'description' => 'ID catatan yang akan diubah, jika diketahui.',
                        ],
                        'title' => [
                            'type' => 'string',
                            'description' => 'Judul catatan saat ini, digunakan untuk mencari catatan jika note_id tidak diisi.',
                        ],
                        'keyword' => [
                            'type' => 'string',
                            'description' => 'Kata kunci untuk mencari catatan berdasarkan judul atau isi.',
                        ],
                        'new_title' => [
                            'type' => 'string',
                            'description' => 'Judul baru untuk catatan.',
                        ],
                        'content' => [
                            'type' => 'string',
                            'description' => 'Isi baru untuk catatan.',
                        ],
                        'tags' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Daftar tag baru untuk catatan.',
                        ],
                    ],
                    'required' => [],
                ],
            ],

            'delete_note' => [
                'description' => 'Hapus catatan. Gunakan note_id jika diketahui, atau title untuk mencari catatan yang akan dihapus.',
                'module' => 'notes',
                'mutates' => true,
                'action_type' => 'delete_note',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'note_id' => [
                            'type' => 'integer',
                            'description' => 'ID catatan yang akan dihapus, jika diketahui.',
                        ],
                        'title' => [
                            'type' => 'string',
                            'description' => 'Judul catatan yang akan dihapus, digunakan untuk mencari catatan jika note_id tidak diisi.',
                        ],
                    ],
                    'required' => [],
                ],
            ],

            'view_notes' => [
                'description' => 'Lihat daftar catatan, bisa dicari berdasarkan kata kunci atau tag.',
                'module' => 'notes',
                'mutates' => false,
                'method' => 'getNotes',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'search' => [
                            'type' => 'string',
                            'description' => 'Kata kunci untuk mencari catatan berdasarkan judul atau isi.',
                        ],
                        'tags' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Filter catatan berdasarkan tag.',
                        ],
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Jumlah maksimal catatan yang ditampilkan. Default 5.',
                        ],
                    ],
                    'required' => [],
                ],
            ],
        ];
    }

    /**
     * Tool definitions contributed by the user's active plugins.
     *
     * @return array<int, array{name: string, description: string, parameters: array}>
     */
    protected function pluginDefinitions(User $user): array
    {
        $definitions = [];

        $activePlugins = $this->pluginManager->getActivePluginsForUser($user->id);

        foreach ($activePlugins as $userPlugin) {
            $plugin = $userPlugin->plugin;
            $instance = $this->pluginManager->getPlugin($plugin->slug);

            if (! $instance || ! $instance->supportsChatIntegration()) {
                continue;
            }

            foreach ($instance->getChatIntents() as $intent) {
                $definitions[] = $this->buildPluginDefinition($intent);
            }
        }

        return $definitions;
    }

    /**
     * Build a single tool definition from a plugin's chat intent.
     *
     * @param  array{action: string, description: string, entities: array<string, string>, examples: array<string>}  $intent
     * @return array{name: string, description: string, parameters: array}
     */
    protected function buildPluginDefinition(array $intent): array
    {
        $properties = [];
        $required = [];

        foreach ($intent['entities'] as $key => $type) {
            $isOptional = str_ends_with($type, '|null');
            $baseType = str_replace('|null', '', $type);

            $properties[$key] = [
                'type' => $baseType === 'number' ? 'number' : 'string',
                'description' => ucfirst(str_replace('_', ' ', $key)),
            ];

            if (! $isOptional) {
                $required[] = $key;
            }
        }

        $exampleText = ! empty($intent['examples'])
            ? ' Contoh: '.$intent['examples'][0]
            : '';

        return [
            'name' => $intent['action'],
            'description' => $intent['description'].$exampleText,
            'parameters' => [
                'type' => 'object',
                'properties' => $properties,
                'required' => $required,
            ],
        ];
    }

    /**
     * Resolve a plugin tool name by searching all installed plugins'
     * chat intents for a matching action.
     *
     * @return array<string, mixed>|null
     */
    protected function resolvePluginTool(string $name): ?array
    {
        foreach ($this->pluginManager->getInstalledPlugins() as $plugin) {
            $instance = $this->pluginManager->getPlugin($plugin->slug);

            if (! $instance || ! $instance->supportsChatIntegration()) {
                continue;
            }

            foreach ($instance->getChatIntents() as $intent) {
                if ($intent['action'] === $name) {
                    return [
                        'name' => $name,
                        'module' => 'plugin',
                        'mutates' => false,
                        'plugin_slug' => $plugin->slug,
                        'action' => $name,
                    ];
                }
            }
        }

        return null;
    }
}
