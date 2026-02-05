<?php

namespace App\Http\Controllers;

use App\Http\Requests\Plugin\StorePluginRatingRequest;
use App\Models\Plugin;
use App\Models\PluginRating;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PluginRatingController extends Controller
{
    /**
     * Store a new rating for a plugin.
     */
    public function store(StorePluginRatingRequest $request, Plugin $plugin): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        // Check if user already rated this plugin
        $existingRating = PluginRating::where('user_id', $user->id)
            ->where('plugin_id', $plugin->id)
            ->first();

        if ($existingRating) {
            return back()->with('error', 'Anda sudah memberikan rating untuk plugin ini.');
        }

        PluginRating::create([
            'user_id' => $user->id,
            'plugin_id' => $plugin->id,
            'rating' => $validated['rating'],
            'review' => $validated['review'] ?? null,
        ]);

        return back()->with('success', 'Rating berhasil diberikan.');
    }

    /**
     * Update an existing rating.
     */
    public function update(StorePluginRatingRequest $request, Plugin $plugin, PluginRating $rating): RedirectResponse
    {
        // Ensure user owns this rating
        if ($rating->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validated();

        $rating->update([
            'rating' => $validated['rating'],
            'review' => $validated['review'] ?? null,
        ]);

        return back()->with('success', 'Rating berhasil diperbarui.');
    }

    /**
     * Delete a rating.
     */
    public function destroy(Request $request, Plugin $plugin, PluginRating $rating): RedirectResponse
    {
        // Ensure user owns this rating
        if ($rating->user_id !== $request->user()->id) {
            abort(403);
        }

        $rating->delete();

        return back()->with('success', 'Rating berhasil dihapus.');
    }
}
