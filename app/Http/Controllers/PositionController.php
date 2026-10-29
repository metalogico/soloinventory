<?php

namespace App\Http\Controllers;

use App\Models\Position;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PositionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['section_id' => ['required', 'integer', 'exists:sections,id']]);

        $positions = Position::where('section_id', $data['section_id'])->orderBy('name')->pluck('name');

        return response()->json(['data' => $positions]);
    }
}
