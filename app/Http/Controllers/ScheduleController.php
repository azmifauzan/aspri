<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    public function index()
    {
        $schedules = \App\Models\Schedule::where('user_id', auth()->id())
            ->orderBy('start_time', 'asc')
            ->get();

        return \Inertia\Inertia::render('Schedules/Index', [
            'schedules' => $schedules,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
            'location' => 'nullable|string|max:255',
            'is_completed' => 'boolean',
            'is_recurring' => 'boolean',
            'recurrence_rule' => 'nullable|string',
            'is_all_day' => 'boolean',
        ]);

        $request->user()->schedules()->create($validated);

        return redirect()->back();
    }

    public function update(Request $request, \App\Models\Schedule $schedule)
    {
        if ($schedule->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
            'location' => 'nullable|string|max:255',
            'is_completed' => 'boolean',
            'is_recurring' => 'boolean',
            'recurrence_rule' => 'nullable|string',
            'is_all_day' => 'boolean',
        ]);

        $schedule->update($validated);

        return redirect()->back();
    }

    public function destroy(Request $request, \App\Models\Schedule $schedule)
    {
        if ($schedule->user_id !== $request->user()->id) {
            abort(403);
        }

        $schedule->delete();

        return redirect()->back();
    }
}
