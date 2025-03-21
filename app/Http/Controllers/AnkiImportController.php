<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\AnkiService;

class AnkiImportController extends Controller
{
    protected $ankiService;

    public function __construct(AnkiService $ankiService)
    {
        $this->ankiService = $ankiService;
    }

    public function generateCsv(Request $request)
    {
        $words = $request->input('words', []);

        if (empty($words)) {
            return response()->json(['error' => 'No words provided'], 400);
        }

        return $this->ankiService->createCsv($words);
    }
}
