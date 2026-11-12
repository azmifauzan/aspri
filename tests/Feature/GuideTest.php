<?php

namespace Tests\Feature;

use Tests\TestCase;

class GuideTest extends TestCase
{
    public function test_guide_page_renders_markdown_with_a_single_suaranetijen_link(): void
    {
        $this->withoutVite();

        $response = $this->get('/panduan/mencatat-pengeluaran-harian-bank-dan-e-wallet');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('legal/Guide')
            ->where('title', 'Cara Mencatat Pengeluaran Harian: Menyederhanakan Bank dan E-Wallet')
            ->where('html', fn ($html) => str_contains($html, '<h2>')
                && substr_count($html, 'https://suaranetijen.id/') === 1)
        );
    }

    public function test_unknown_guide_slug_returns_404(): void
    {
        $this->get('/panduan/tidak-ada')->assertNotFound();
    }
}
