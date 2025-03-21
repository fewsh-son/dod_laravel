<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Filesystem\Filesystem;

class AnkiService
{
    private $apiKey;

    public function __construct()
    {
        $this->apiKey = config('anki.api_key');
    }

    public function createCsv(array $words)
    {
        $data = $this->fetchMultipleWordsData($words);

        $csvContent = "";
        foreach ($data as $row) {
            $csvContent .= implode("\t", $row) . "\n";
        }

        $filesystem = new Filesystem();
        $nameFile = 'anki_data_' . time() . '.txt';
        $path = storage_path('app/' . $nameFile);
        $filesystem->put($path, $csvContent);
    }

    private function fetchMultipleWordsData(array $words)
    {
        $responses = Http::pool(fn ($pool) => array_map(
            fn ($word) => $pool->get("https://www.dictionaryapi.com/api/v3/references/collegiate/json/{$word}?key=" . $this->apiKey),
            $words
        ));

        $data = [];
        $definitions = [];

        foreach ($words as $index => $word) {
            $response = $responses[$index];

            if (!$response->failed() && !empty($response->json())) {
                $wordData = $this->processWordData($response->json(), $word);
                $definitions[] = $wordData[0];
                $data[] = $wordData;
            }
        }

        if (empty($data)) {
            return [];
        }


        $translationResponses = Http::pool(fn ($pool) => array_map(
            fn ($definition) => $pool->get("https://api.mymemory.translated.net/get", [
                'q' => $definition,
                'langpair' => 'en|vi'
            ]),
            $definitions
        ));

        foreach ($data as $key => &$row) {
            $translatedDefinition = $translationResponses[$key]->successful()
                ? ($translationResponses[$key]->json()['responseData']['translatedText'] ?? $row[0])
                : $row[0];

            $row[4] = $translatedDefinition;
        }

        return $data;
    }


    function processWordData($res, $word)
    {

        $data = $res[0];

        $cloze = $this->generateCloze($word);
        $phoneticMerriamWebster = Arr::get($data, 'hwi.prs.0.mw', 'Phonetic not found');
        $phoneticIpa = $this->convertMerriamWebsterToIPA($phoneticMerriamWebster);
        $phonetic = sprintf('/%s/ --- /%s/', $phoneticMerriamWebster, $phoneticIpa);
        $audio = Arr::get($data, 'hwi.prs.0.sound.audio', '');
        $audioUrl = $audio ? sprintf('https://media.merriam-webster.com/audio/prons/en/us/mp3/%s/%s.mp3', $audio[0], $audio) : 'Audio not found';
        $extraInfo = Arr::get($data, 'shortdef.0', 'No definition available');

        $syn = Arr::get($data, 'syns');
        $synonyms = $syn ? $this->getSynonyms($syn) : 'No synonyms found';
        $stems = Arr::get($data, 'meta.stems', []);
        $stemsFormatted = !empty($stems) ? implode('; ', $stems) : 'No related words found';

        return [
            $word,          // 1. Word
            $cloze,         // 2. Cloze
            $phonetic,      // 3. Phonetic symbol
            $audioUrl,      // 4. Audio
            $word,    // 5. Definition
            'Picture not available', // 6. Picture
            $synonyms,      // 7. Synonyms
            $extraInfo,     // 8. Extra information
            $stemsFormatted // 9. Related Words
        ];

    }

    private function generateCloze($word)
    {
        $length = strlen($word);

        if ($length <= 3) {
            return str_repeat('_', $length);
        }

        $firstChar = $word[0];
        $lastChar = $word[$length - 1];

        $middleIndex = (int) floor($length / 2);
        $middleChar = $word[$middleIndex];

        $clozeArray = array_fill(0, $length, '_');
        $clozeArray[0] = $firstChar;
        $clozeArray[$middleIndex] = $middleChar;
        $clozeArray[$length - 1] = $lastChar;

        return implode('', $clozeArray);
    }


    function convertMerriamWebsterToIPA($mwPhonetic)
    {
        $conversionMap = [
            'ä' => 'ɑː', 'a' => 'æ', 'ā' => 'eɪ',
            'e' => 'ɛ', 'ē' => 'iː', 'i' => 'ɪ',
            'ī' => 'aɪ', 'o' => 'ɒ', 'ō' => 'oʊ',
            'ô' => 'ɔː', 'u' => 'ʌ', 'ü' => 'ʊ',
            'ū' => 'uː', 'yü' => 'juː',

            'b' => 'b', 'ch' => 'tʃ', 'd' => 'd',
            'f' => 'f', 'g' => 'ɡ', 'h' => 'h',
            'j' => 'dʒ', 'k' => 'k', 'l' => 'l',
            'm' => 'm', 'n' => 'n', 'ng' => 'ŋ',
            'p' => 'p', 'r' => 'r', 's' => 's',
            'sh' => 'ʃ', 't' => 't', 'th' => 'θ',
            'v' => 'v', 'w' => 'w', 'y' => 'j',
            'z' => 'z', 'zh' => 'ʒ',

            'ˈ' => 'ˈ',
            '-' => '.',
        ];

        foreach ($conversionMap as $mw => $ipa) {
            $mwPhonetic = str_replace($mw, $ipa, $mwPhonetic);
        }

        return $mwPhonetic;
    }

    function getSynonyms($syn)
    {
        $synonyms = '';
        $firstPtText = Arr::get($syn, '0.pt.0.1', '');

        preg_match_all('/{sc}(.*?){\/sc}/', $firstPtText, $matches);

        if (!empty($matches[1])) {
            $synonyms = implode(';', $matches[1]);
        }

        return $synonyms;
    }
}
