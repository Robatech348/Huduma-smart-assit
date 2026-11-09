<?php
declare(strict_types=1);

/**
 * SmartAssist question answering.
 *
 * 1. Rank approved catalogue content (services + FAQs) against the question.
 * 2. If the Anthropic SDK and an API key are available, Claude writes the answer,
 *    grounded only in the approved catalogue (sent as a cached system prompt).
 * 3. Otherwise (or if the API call fails / is refused) build the answer directly
 *    from the best catalogue match.
 */
final class Assistant
{
    private const STOPWORDS = ['the','a','an','and','or','to','of','for','in','on','at','is','are','do','does','i','my','me','how','what','where','when','can','need','get','with','it','be','you','your','we','which','who','please','want','should','there','this','that','about','from','by','have','has','am','will','would','could','much','many','long','take','any','if','so','as','into','kwa','na','ya','wa','la','za','ni','je','nini','gani','vipi','nataka','ninataka','naomba'];

    /** Swahili / informal terms mapped onto catalogue vocabulary. */
    private const SYNONYMS = [
        'kitambulisho' => 'id', 'kipande' => 'id', 'identity' => 'id', 'idcard' => 'id',
        'cheti' => 'certificate', 'kuzaliwa' => 'birth', 'kifo' => 'death',
        'pasipoti' => 'passport', 'leseni' => 'licence', 'license' => 'licence', 'dl' => 'licence',
        'kodi' => 'kra', 'tax' => 'kra', 'pin' => 'kra', 'ushuru' => 'kra',
        'nhif' => 'sha', 'hospital' => 'sha', 'health' => 'sha', 'bima' => 'sha', 'afya' => 'sha',
        'pension' => 'nssf', 'retirement' => 'nssf', 'pensheni' => 'nssf',
        'police' => 'conduct', 'clearance' => 'conduct', 'polisi' => 'conduct',
        'lost' => 'replacement', 'replace' => 'replacement', 'kupoteza' => 'replacement', 'nimepoteza' => 'replacement', 'damaged' => 'replacement',
        'business' => 'business', 'biashara' => 'business', 'company' => 'business',
        'loan' => 'helb', 'mkopo' => 'helb', 'student' => 'helb', 'university' => 'helb',
        'marriage' => 'marriage', 'ndoa' => 'marriage', 'wedding' => 'marriage',
        'cost' => 'fee', 'price' => 'fee', 'pay' => 'fee', 'payment' => 'fee', 'bei' => 'fee', 'gharama' => 'fee',
        'open' => 'hours', 'opening' => 'hours', 'saa' => 'hours', 'closing' => 'hours',
        'book' => 'appointment', 'booking' => 'appointment', 'miadi' => 'appointment',
        'cancel' => 'cancel', 'reschedule' => 'cancel', 'ghairi' => 'cancel',
        'track' => 'status', 'progress' => 'status', 'hali' => 'status',
    ];

    public static function tokens(string $text): array
    {
        $text = mb_strtolower($text);
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
        $out = [];
        foreach (preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY) as $w) {
            if (in_array($w, self::STOPWORDS, true) || mb_strlen($w) < 2) {
                continue;
            }
            $w = self::SYNONYMS[$w] ?? $w;
            // crude plural stemming
            if (mb_strlen($w) > 4 && str_ends_with($w, 's') && !str_ends_with($w, 'ss')) {
                $w = mb_substr($w, 0, -1);
            }
            $out[] = $w;
        }
        return array_values(array_unique($out));
    }

    private static function score(array $qTokens, array $fields): float
    {
        $score = 0.0;
        foreach ($fields as [$text, $weight]) {
            $fieldTokens = self::tokens($text);
            foreach ($qTokens as $t) {
                foreach ($fieldTokens as $f) {
                    if ($t === $f) {
                        $score += $weight;
                        break;
                    }
                    if (mb_strlen($t) >= 4 && (str_starts_with($f, $t) || str_starts_with($t, $f)) && mb_strlen($f) >= 4) {
                        $score += $weight * 0.5;
                        break;
                    }
                }
            }
        }
        return $score;
    }

    /** @return array{services: array, faqs: array} ranked matches with scores */
    public static function search(string $question): array
    {
        $qt = self::tokens($question);
        $services = [];
        foreach (active_services() as $s) {
            $sc = self::score($qt, [
                [$s['name'], 5], [$s['keywords'], 4], [$s['agency'], 2], [$s['category'], 2],
                [$s['summary'], 1], [$s['requirements'], 0.5],
            ]);
            if ($sc > 0) {
                $s['score'] = $sc;
                $services[] = $s;
            }
        }
        usort($services, fn($a, $b) => $b['score'] <=> $a['score']);

        $faqs = [];
        foreach (q_all('SELECT * FROM faqs WHERE active = 1') as $f) {
            $sc = self::score($qt, [[$f['question'], 4], [$f['keywords'], 4], [$f['answer'], 0.5]]);
            if ($sc > 0) {
                $f['score'] = $sc;
                $faqs[] = $f;
            }
        }
        usort($faqs, fn($a, $b) => $b['score'] <=> $a['score']);
        return ['services' => array_slice($services, 0, 3), 'faqs' => array_slice($faqs, 0, 2)];
    }

    public static function aiAvailable(): bool
    {
        return class_exists('\\Anthropic\\Client') && config('assistant.anthropic_api_key') !== '';
    }

    /**
     * @param array $history list of ['role'=>'user'|'assistant','content'=>string]
     * @return array{answer:string, source:string, actions:array}
     */
    public static function answer(string $question, array $history = []): array
    {
        $matches = self::search($question);
        $source = 'catalogue';
        $answer = null;

        if (self::aiAvailable()) {
            $answer = self::askClaude($question, $history);
            if ($answer !== null) {
                $source = 'ai';
            }
        }
        if ($answer === null) {
            [$answer, $source] = self::catalogueAnswer($question, $matches);
        }

        // Link the services the answer is about.
        $actions = [];
        $linked = [];
        foreach (active_services() as $s) {
            if ($source === 'ai' && mb_stripos($answer, $s['name']) !== false) {
                $linked[$s['id']] = $s;
            }
        }
        if (!$linked && $matches['services']) {
            $top = $matches['services'][0];
            $linked[$top['id']] = $top;
        }
        foreach (array_slice($linked, 0, 2) as $s) {
            $actions[] = ['label' => 'View: ' . $s['name'], 'url' => url('service.php', ['slug' => $s['slug']])];
            if ($s['bookable']) {
                $actions[] = ['label' => 'Book an appointment', 'url' => url('book.php', ['service' => $s['id']])];
            }
        }

        $logId = 0;
        if (config('assistant.log_questions')) {
            q('INSERT INTO assistant_logs (question, source, matched_service_id) VALUES (?,?,?)', [
                mb_substr(self::redact($question), 0, 1000), $source, $matches['services'][0]['id'] ?? null,
            ]);
            $logId = (int)db()->lastInsertId();
        }

        return ['answer' => $answer, 'source' => $source, 'actions' => $actions, 'log_id' => $logId];
    }

    /** Remove likely personal identifiers before logging. */
    public static function redact(string $text): string
    {
        $text = preg_replace('/[\w.+-]+@[\w-]+\.[\w.]+/', '[email]', $text);
        return preg_replace('/\+?\d[\d\s-]{6,}\d/', '[number]', $text);
    }

    private static function catalogueAnswer(string $question, array $m): array
    {
        $topService = $m['services'][0] ?? null;
        $topFaq = $m['faqs'][0] ?? null;

        if ($topFaq && (!$topService || $topFaq['score'] >= $topService['score'])) {
            $text = $topFaq['answer'];
            if ($topService && $topService['score'] >= 4) {
                $text .= "\n\nRelated service: **{$topService['name']}**.";
            }
            return [$text, 'catalogue'];
        }

        if ($topService) {
            $s = $topService;
            $text = "**{$s['name']}** ({$s['agency']})\n{$s['summary']}\n\n**What to bring:**\n";
            foreach (lines($s['requirements']) as $r) {
                $text .= "- {$r}\n";
            }
            $text .= "\n**Fee:** {$s['fee']}  \n**Processing time:** {$s['processing_time']}";
            if ($s['bookable']) {
                $text .= "\n\nYou can book a visit at a Huduma Centre that offers this service to avoid waiting in a queue.";
            }
            $others = array_slice($m['services'], 1);
            if ($others) {
                $text .= "\n\nYou may also be looking for: " . implode(', ', array_map(fn($o) => $o['name'], $others)) . '.';
            }
            return [$text, 'catalogue'];
        }

        $names = array_map(fn($s) => $s['name'], array_slice(active_services(), 0, 6));
        return [
            "I could not find that in the approved service information. Try asking with the name of a service, for example: "
            . implode(', ', $names) . ".\n\nYou can also browse all services, or call " . config('app.support_phone')
            . ' to speak to a person. Staff at any Huduma Centre can help you in person.',
            'fallback',
        ];
    }

    /** Stable, cacheable system prompt containing the approved catalogue. */
    public static function systemPrompt(): string
    {
        $out = "You are Huduma SmartAssist, a helpful guide for citizens using Huduma Centres in Kenya.\n\n"
            . "Answer questions about government services using ONLY the approved service information below. Rules:\n"
            . "- If the answer is not in the approved information, say you are not sure and suggest calling "
            . config('app.support_phone') . " or asking staff at a Huduma Centre. Never invent fees, documents, timelines or policies.\n"
            . "- Reply in the language the citizen writes in (English or Kiswahili).\n"
            . "- Be brief and practical: a short answer, then a bullet list of what to bring when relevant. Use plain words.\n"
            . "- Refer to services by their exact names below so the website can link them.\n"
            . "- When a service is bookable, mention that the citizen can book an appointment on this website.\n"
            . "- Never ask for or repeat ID numbers, passwords, PINs or other sensitive personal data. If a citizen shares them, tell them not to.\n"
            . "- You cannot see a citizen's bookings or applications. For status, point them to 'My appointment' or 'Track request' pages with their reference number.\n"
            . "- Decisions about eligibility or approval are made by the responsible agency, not by you.\n\n"
            . "=== APPROVED SERVICES ===\n";
        foreach (active_services() as $s) {
            $centres = array_map(fn($c) => $c['name'], centres_for_service((int)$s['id']));
            $out .= "\n## {$s['name']}\nAgency: {$s['agency']}\nCategory: {$s['category']}\nSummary: {$s['summary']}\n"
                . "Requirements:\n- " . implode("\n- ", lines($s['requirements'])) . "\n"
                . "Steps:\n- " . implode("\n- ", lines($s['steps'])) . "\n"
                . "Fee: {$s['fee']}\nProcessing time: {$s['processing_time']}\n"
                . 'Bookable online: ' . ($s['bookable'] ? 'yes' : 'no') . "\n"
                . 'Offered at: ' . ($centres ? implode(', ', $centres) : 'ask at your nearest centre') . "\n";
        }
        $out .= "\n=== HUDUMA CENTRES ===\n";
        foreach (q_all('SELECT * FROM centres WHERE active = 1 ORDER BY name') as $c) {
            $out .= "- {$c['name']} ({$c['county']}): {$c['address']}. Hours: {$c['opening_hours']}.\n";
        }
        $out .= "\n=== FREQUENTLY ASKED QUESTIONS ===\n";
        foreach (q_all('SELECT * FROM faqs WHERE active = 1') as $f) {
            $out .= "Q: {$f['question']}\nA: {$f['answer']}\n\n";
        }
        return $out;
    }

    private static function askClaude(string $question, array $history): ?string
    {
        $messages = [];
        foreach (array_slice($history, -10) as $h) {
            $role = ($h['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
            $content = mb_substr(trim((string)($h['content'] ?? '')), 0, 2000);
            if ($content === '') {
                continue;
            }
            // Keep strict user/assistant alternation.
            if ($messages && end($messages)['role'] === $role) {
                $messages[count($messages) - 1]['content'] .= "\n\n" . $content;
            } else {
                $messages[] = ['role' => $role, 'content' => $content];
            }
        }
        if ($messages && $messages[0]['role'] === 'assistant') {
            array_shift($messages);
        }
        if ($messages && end($messages)['role'] === 'user') {
            array_pop($messages); // the current question is appended below
        }
        $messages[] = ['role' => 'user', 'content' => $question];

        try {
            // The SDK reads ANTHROPIC_API_KEY from the environment.
            putenv('ANTHROPIC_API_KEY=' . config('assistant.anthropic_api_key'));
            $client = new \Anthropic\Client();
            $message = $client->messages->create(
                model: config('assistant.model', 'claude-opus-5-5'),
                maxTokens: (int)config('assistant.max_tokens', 4000),
                system: [
                    ['type' => 'text', 'text' => self::systemPrompt(), 'cacheControl' => ['type' => 'ephemeral']],
                ],
                messages: $messages,
                outputConfig: ['effort' => config('assistant.effort', 'low')],
            );
            if ($message->stopReason === 'refusal') {
                return null; // fall back to catalogue content
            }
            $text = '';
            foreach ($message->content as $block) {
                if ($block->type === 'text') {
                    $text .= $block->text;
                }
            }
            $text = trim($text);
            return $text !== '' ? $text : null;
        } catch (\Anthropic\Core\Exceptions\APIStatusException $e) {
            error_log('SmartAssist Claude API error: ' . ($e->type?->value ?? '') . ' ' . $e->getMessage());
            return null;
        } catch (\Throwable $e) {
            error_log('SmartAssist Claude call failed: ' . $e->getMessage());
            return null;
        }
    }
}
