<?php

namespace App\Http\Controllers;

use App\Models\Template;
use Illuminate\Http\Request;

class TemplateController extends Controller
{
    public function index(Request $request)
    {
        $templates = Template::where('user_id', $request->user()->id)
            ->orWhere('is_system', true)
            ->get();
            
        return response()->json($templates);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:500',
            'icon' => 'nullable|string|max:50',
            'config' => 'required|array',
        ]);

        $template = Template::create([
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'type' => $validated['type'] ?? 'custom',
            'description' => $validated['description'] ?? null,
            'icon' => $validated['icon'] ?? 'Bookmark',
            'is_system' => false,
            'is_default' => false,
            'config' => $validated['config'],
        ]);

        return response()->json($template, 201);
    }

    public function show(Template $template)
    {
        $user = request()->user();
        if ($template->user_id !== $user->id && !$template->is_system) {
            abort(403);
        }
        
        return response()->json($template);
    }
}
