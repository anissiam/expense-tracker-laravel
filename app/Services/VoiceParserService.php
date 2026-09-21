<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * Phase 2: deterministic voice/text-to-expense parser.
 *
 * Pure function — never touches the database, never creates records.
 * Unconfirmed voice input isolation is guaranteed by design: this service
 * only extracts fields; persistence happens explicitly via ExpenseService
 * after the user presses "Confirm & Add Expense".
 */
class VoiceParserService
{
    private const FILLER_PATTERNS = [
        '/\bplease\b/i',
        '/\badd\s+(an?\s+)?expense\b/i',
        '/\blog\s+(an?\s+)?expense\b/i',
        '/\bi\s+(just\s+)?(bought|spent|paid|payed|purchase[sd]?)\b/i',
        '/\bwe\s+(just\s+)?(bought|spent|paid)\b/i',
        '/\bmy\s+(expense|purchase)\s+(was|is)\b/i',
        '/\bfrom\s+the\b/i',
        '/\bin\s+the\s+category\b/i',
        '/\bcategory\b/i',
    ];

    private const WORD_NUMBERS = [
        'zero' => 0, 'one' => 1, 'two' => 2, 'three' => 3, 'four' => 4,
        'five' => 5, 'six' => 6, 'seven' => 7, 'eight' => 8, 'nine' => 9,
        'ten' => 10, 'eleven' => 11, 'twelve' => 12, 'thirteen' => 13,
        'fourteen' => 14, 'fifteen' => 15, 'sixteen' => 16, 'seventeen' => 17,
        'eighteen' => 18, 'nineteen' => 19, 'twenty' => 20, 'thirty' => 30,
        'forty' => 40, 'fifty' => 50, 'sixty' => 60, 'seventy' => 70,
        'eighty' => 80, 'ninety' => 90,
    ];

    /**
     * @param  string  $transcript  raw speech-to-text (or typed fallback)
     * @param  array|\Illuminate\Support\Collection  $categories  rows with id, name, parent_id
     */
    public function parse(string $transcript, $categories, ?string $today = null): array
    {
        $now = $today ? Carbon::parse($today)->startOfDay() : Carbon::now()->startOfDay();
        $original = trim($transcript);
        $text = mb_strtolower($original);

        $cats = collect($categories)->map(fn ($c) => is_array($c) ? (object) $c : $c);
        $parents = $cats->filter(fn ($c) => empty($c->parent_id))->values();
        $children = $cats->filter(fn ($c) => !empty($c->parent_id))->values();

        $amount = $this->extractAmount($text);
        $currency = $this->extractCurrency($text);

        [$category, $subcategory] = $this->extractCategory($text, $parents, $children);

        $date = $this->extractDate($text, $now);
        $description = $this->extractDescription($original, $amount, $category, $subcategory);

        if ($amount === null) {
            return $this->clarify('amount', 'How much did you spend?', [
                'category_id' => $category?->id,
                'category_name' => $category?->name,
                'subcategory_id' => $subcategory?->id,
                'subcategory_name' => $subcategory?->name,
                'date' => $date,
                'description' => $description,
                'currency' => $currency,
            ]);
        }

        if ($category === null) {
            return $this->clarify('category', 'What category should I use?', [
                'amount' => $amount,
                'date' => $date,
                'description' => $description,
                'currency' => $currency,
            ]);
        }

        return [
            'status' => 'READY_FOR_REVIEW',
            'amount' => $amount,
            'currency' => $currency,
            'category_id' => $category->id,
            'category_name' => $category->name,
            'subcategory_id' => $subcategory?->id,
            'subcategory_name' => $subcategory?->name,
            'date' => $date,
            'description' => $description,
            'missingField' => null,
            'prompt' => null,
        ];
    }

    private function clarify(string $field, string $prompt, array $partial): array
    {
        return array_merge([
            'status' => 'NEEDS_CLARIFICATION',
            'amount' => null,
            'currency' => 'USD',
            'category_id' => null,
            'category_name' => null,
            'subcategory_id' => null,
            'subcategory_name' => null,
            'date' => null,
            'description' => null,
            'missingField' => $field,
            'prompt' => $prompt,
        ], array_filter($partial, fn ($v) => $v !== null));
    }

    // ---------------- amount ----------------

    private function extractAmount(string $text): ?float
    {
        // $25.50 / $ 25
        if (preg_match('/\$\s?(\d+(?:\.\d{1,2})?)/', $text, $m)) {
            return (float) $m[1];
        }

        // 25 dollars / 30 bucks / 25.50 usd / 20 euros / 15 pounds
        if (preg_match('/(\d+(?:\.\d{1,2})?)\s?(dollars?|bucks?|usd|euros?|eur|pounds?|gbp|shekels?|ils|riyals?|sar|dirhams?|aed|dinars?|egp|jod|qar|kwd|₪)/i', $text, $m)) {
            return (float) $m[1];
        }

        // word numbers: "twenty five dollars", "forty bucks"
        $words = implode('|', array_keys(self::WORD_NUMBERS));
        if (preg_match('/\b(' . $words . ')((?:[\s-]+(' . $words . '))?)\s?(dollars?|bucks?|usd|euros?|eur|pounds?|gbp)\b/i', $text, $m)) {
            return (float) $this->wordsToNumber(trim($m[1] . ' ' . trim($m[2] ?? '')));
        }

        // bare number fallback (e.g. "spent 25 on food") — ignore years & dates
        $sanitized = preg_replace('/\b(19|20)\d{2}(-\d{2}-\d{2})?\b/', ' ', $text);
        $sanitized = preg_replace('/\b\d{4}-\d{2}-\d{2}\b/', ' ', $sanitized);
        $sanitized = preg_replace('/\b\d{1,2}\/\d{1,2}(\/\d{2,4})?\b/', ' ', $sanitized);
        if (preg_match_all('/\b(\d+(?:\.\d{1,2})?)\b/', $sanitized, $all)) {
            $nums = array_map('floatval', $all[1]);
            // Prefer the largest plausible expense value (dates already stripped)
            if (count($nums) === 1) {
                return $nums[0] > 0 ? $nums[0] : null;
            }
            if (count($nums) > 1) {
                // "Yesterday I spent 15 dollars on coffee" already matched above;
                // here pick first positive (deterministic for tests).
                foreach ($nums as $n) {
                    if ($n > 0) {
                        return $n;
                    }
                }
            }
        }

        return null;
    }

    private function wordsToNumber(string $phrase): float
    {
        $total = 0;
        foreach (preg_split('/[\s-]+/', strtolower($phrase)) as $w) {
            $total += self::WORD_NUMBERS[$w] ?? 0;
        }

        return (float) $total;
    }

    private function extractCurrency(string $text): string
    {
        if (str_contains($text, '$') || preg_match('/\b(dollars?|usd|bucks?)\b/i', $text)) {
            return 'USD';
        }
        if (str_contains($text, '€') || preg_match('/\b(euros?|eur)\b/i', $text)) {
            return 'EUR';
        }
        if (str_contains($text, '£') || preg_match('/\b(pounds?|gbp)\b/i', $text)) {
            return 'GBP';
        }
        if (str_contains($text, '₪') || preg_match('/\b(shekels?|ils|nis)\b/i', $text)) {
            return 'ILS';
        }
        if (preg_match('/\b(sar|riyals?)\b/i', $text)) {
            return 'SAR';
        }
        if (preg_match('/\b(aed|dirhams?)\b/i', $text)) {
            return 'AED';
        }

        return 'USD';
    }

    // ---------------- category ----------------

    private function extractCategory(string $text, $parents, $children): array
    {
        // 1) subcategory substring match — longest name wins (most specific)
        $subMatch = null;
        $subLen = 0;
        foreach ($children as $sub) {
            $name = mb_strtolower(trim($sub->name));
            if ($name !== '' && str_contains($text, $name) && strlen($name) > $subLen) {
                $subMatch = $sub;
                $subLen = strlen($name);
            }
        }
        if (!$subMatch) {
            $subMatch = $this->fuzzyMatch($text, $children);
        }

        if ($subMatch) {
            $parent = $parents->firstWhere('id', $subMatch->parent_id)
                ?? $children->firstWhere('id', $subMatch->parent_id);
            // Parent row may itself be a child in deep trees; fall back to sub's parent id.
            if (!$parent) {
                $parent = (object) ['id' => $subMatch->parent_id, 'name' => ''];
            }

            return [$parent, $subMatch];
        }

        // 2) top-level category match
        foreach ($parents as $cat) {
            $name = mb_strtolower(trim($cat->name));
            if ($name !== '' && str_contains($text, $name)) {
                return [$cat, null];
            }
        }
        $fuzzy = $this->fuzzyMatch($text, $parents);

        return [$fuzzy, null];
    }

    private function fuzzyMatch(string $text, $options)
    {
        $words = preg_split('/[^a-z]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $best = null;
        $bestDist = 3;

        foreach ($options as $opt) {
            $name = mb_strtolower(trim($opt->name));
            foreach (preg_split('/\s+/', $name) as $part) {
                if (strlen($part) < 4) {
                    continue;
                }
                foreach ($words as $w) {
                    if (strlen($w) < 4) {
                        continue;
                    }
                    $d = levenshtein($w, $part);
                    if ($d < $bestDist) {
                        $bestDist = $d;
                        $best = $opt;
                    }
                }
            }
        }

        return $bestDist <= 2 ? $best : null;
    }

    // ---------------- date ----------------

    private function extractDate(string $text, Carbon $now): string
    {
        if (preg_match('/\byesterday\b/i', $text)) {
            return $now->copy()->subDay()->toDateString();
        }
        if (preg_match('/\btoday\b/i', $text)) {
            return $now->toDateString();
        }
        if (preg_match('/\btomorrow\b/i', $text)) {
            return $now->copy()->addDay()->toDateString();
        }

        // ISO + common numeric dates
        if (preg_match('/\b(\d{4}-\d{2}-\d{2})\b/', $text, $m)) {
            return Carbon::parse($m[1])->toDateString();
        }
        if (preg_match('/\b(\d{1,2})\/(\d{1,2})(?:\/(\d{2,4}))?\b/', $text, $m)) {
            $y = isset($m[3]) && $m[3] !== '' ? (int) $m[3] : (int) $now->format('Y');
            if ($y < 100) {
                $y += 2000;
            }

            return Carbon::create($y, (int) $m[1], (int) $m[2])->toDateString();
        }

        // "September 15" / "Sep 15"
        if (preg_match('/\b(january|february|march|april|may|june|july|august|september|sept|october|oct|november|nov|december|dec)\s+(\d{1,2})(?:st|nd|rd|th)?\b/i', $text, $m)) {
            return Carbon::parse($m[1] . ' ' . $m[2] . ' ' . $now->format('Y'))->toDateString();
        }

        // weekday: "last Monday", "on Friday", "monday"
        $days = ['sunday' => 0, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4, 'friday' => 5, 'saturday' => 6];
        foreach ($days as $name => $idx) {
            if (preg_match('/\b' . $name . '\b/i', $text)) {
                $diff = ($now->dayOfWeek - $idx + 7) % 7;
                if ($diff === 0) {
                    $diff = 7; // "last Monday" on a Monday => 7 days ago
                }

                return $now->copy()->subDays($diff)->toDateString();
            }
        }

        return $now->toDateString();
    }

    // ---------------- description ----------------

    private function extractDescription(string $original, ?float $amount, $category, $subcategory): string
    {
        $desc = ' ' . mb_strtolower($original) . ' ';

        // strip amount + currency phrases
        $desc = preg_replace('/\$\s?\d+(?:\.\d{1,2})?/', ' ', $desc);
        $desc = preg_replace('/\d+(?:\.\d{1,2})?\s?(dollars?|bucks?|usd|euros?|eur|pounds?|gbp|shekels?|ils|₪|sar|aed)/i', ' ', $desc);
        $desc = preg_replace('/\b\d{4}-\d{2}-\d{2}\b/', ' ', $desc);
        $desc = preg_replace('/\b\d{1,2}\/\d{1,2}(\/\d{2,4})?\b/', ' ', $desc);

        // strip date words
        $desc = preg_replace('/\b(yesterday|today|tomorrow|last\s+(monday|tuesday|wednesday|thursday|friday|saturday|sunday)|on\s+(monday|tuesday|wednesday|thursday|friday|saturday|sunday))\b/i', ' ', $desc);

        // strip matched category / subcategory names
        foreach ([$subcategory?->name, $category?->name] as $name) {
            if ($name) {
                $desc = str_ireplace(mb_strtolower($name), ' ', $desc);
            }
        }

        foreach (self::FILLER_PATTERNS as $pattern) {
            $desc = preg_replace($pattern, ' ', $desc);
        }

        $desc = preg_replace('/\b(for|on|at|in|of|a|an|the|to|from|with|and)\b/i', ' ', $desc);
        $desc = preg_replace('/[^a-z0-9\s]/i', ' ', $desc);
        $desc = trim(preg_replace('/\s+/', ' ', $desc));

        if ($desc === '') {
            $desc = mb_strtolower($subcategory?->name ?? $category?->name ?? 'expense');
        }

        return ucfirst($desc);
    }
}
