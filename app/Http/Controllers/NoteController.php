<?php

namespace App\Http\Controllers;

use App\Models\Note;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NoteController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Inertia::render('notes/Index', [
            'notes' => Note::where('user_id', auth()->id())
                ->orderBy('is_pinned', 'desc')
                ->orderBy('updated_at', 'desc')
                ->get(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'is_pinned' => 'boolean',
            'color' => 'nullable|string',
            'tags' => 'nullable|array',
        ]);

        $request->user()->notes()->create($validated);

        return redirect()->back();
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Note $note)
    {
        $this->authorize('update', $note);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'is_pinned' => 'boolean',
            'color' => 'nullable|string',
            'tags' => 'nullable|array',
        ]);

        $note->update($validated);

        return redirect()->back();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Note $note)
    {
        $this->authorize('delete', $note);

        $note->delete();

        return redirect()->back();
    }
}
