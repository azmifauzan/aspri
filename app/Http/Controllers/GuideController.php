<?php

namespace App\Http\Controllers;

use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class GuideController extends Controller
{
    /**
     * Guides are markdown files in resources/markdown/guides, keyed by slug.
     *
     * @var array<string, array{title: string, description: string}>
     */
    private const GUIDES = [
        'mencatat-pengeluaran-harian-bank-dan-e-wallet' => [
            'title' => 'Cara Mencatat Pengeluaran Harian: Menyederhanakan Bank dan E-Wallet',
            'description' => 'Cara mencatat pengeluaran harian dengan rapi: kurangi jumlah rekening dan e-wallet, pilih layanan dengan kriteria jelas, dan catat saat uang keluar.',
        ],
    ];

    public function show(string $guide): Response
    {
        abort_unless(array_key_exists($guide, self::GUIDES), 404);

        return Inertia::render('legal/Guide', [
            'title' => self::GUIDES[$guide]['title'],
            'description' => self::GUIDES[$guide]['description'],
            'html' => Str::markdown(
                (string) file_get_contents(resource_path("markdown/guides/{$guide}.md")),
                ['html_input' => 'strip'],
            ),
        ]);
    }
}
