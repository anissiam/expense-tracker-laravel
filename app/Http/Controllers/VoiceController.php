<?php

namespace App\Http\Controllers;

use App\Services\CategoryService;
use App\Services\VoiceParserService;
use Illuminate\Http\Request;

class VoiceController extends Controller
{
    /**
     * Phase 2: parse a voice transcript (or typed fallback) into reviewable fields.
     * Read-only: never creates expenses or touches budgets.
     */
    public function parse(Request $request, VoiceParserService $parser, CategoryService $categories)
    {
        $validated = $request->validate([
            'transcript' => 'required|string|max:2000',
        ]);

        $rows = $categories->getUserCategories($request->user()->id);

        $result = $parser->parse($validated['transcript'], $rows);

        return response()->json($result);
    }
}
