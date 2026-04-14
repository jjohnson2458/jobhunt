<?php
/**
 * Generates a tailored cover letter (and copies the resume template)
 * for a given listing + track. Uses AnthropicService when an API key
 * is configured; otherwise falls back to a templated letter.
 */
class CoverLetterGenerator
{
    public function generate(array $listing, ?array $track): array
    {
        $appConfig = require BASE_PATH . '/config/app.php';
        $apiKey = $appConfig['anthropic_key'] ?? '';
        $model  = $appConfig['claude_model']  ?? 'claude-sonnet-4-20250514';

        $profile  = @file_get_contents(BASE_PATH . '/profile.txt')    ?: '';
        $history  = @file_get_contents(BASE_PATH . '/jobhistory.txt') ?: '';
        $refs     = @file_get_contents(BASE_PATH . '/docs/references2.txt') ?: '';
        $tone     = $track['cover_letter_tone'] ?? 'professional';
        $template = $track['resume_template']   ?? '';

        // 1. Cover letter text
        if ($apiKey) {
            $svc = new AnthropicService($apiKey, $model, 2048);
            $system = "You write tailored, concise (under 350 words) cover letters in the requested tone. Use only facts from the candidate profile + history. Do not invent employers or dates. Output ONLY the letter body, no preamble.";
            $user = "TONE: $tone\n\n=== CANDIDATE PROFILE ===\n$profile\n\n=== WORK HISTORY ===\n$history\n\n=== JOB POSTING ===\nTitle: {$listing['title']}\nCompany: {$listing['company']}\nLocation: " . ($listing['location'] ?? '') . "\n\n{$listing['description']}\n\nWrite the cover letter now.";
            $letter = $this->callClaude($svc, $system, $user);
        } else {
            $letter = $this->fallback($listing, $track, $profile);
        }

        // 2. Save cover letter
        $stamp = date('Ymd_His');
        $slug  = preg_replace('/[^a-z0-9]+/i', '-', strtolower($listing['company'] . '-' . $listing['title']));
        $slug  = trim(substr($slug, 0, 60), '-');
        $dir   = BASE_PATH . '/storage/generated';
        if (!is_dir($dir)) { mkdir($dir, 0775, true); }
        $coverTxt = "$dir/cover_{$slug}_{$stamp}.txt";
        file_put_contents($coverTxt, $letter);

        // 3. Copy resume template (tailored variant — for now, straight copy)
        $resumeOut = null;
        if ($template && file_exists(BASE_PATH . '/' . $template)) {
            $ext = pathinfo($template, PATHINFO_EXTENSION);
            $resumeOut = "storage/generated/resume_{$slug}_{$stamp}.{$ext}";
            copy(BASE_PATH . '/' . $template, BASE_PATH . '/' . $resumeOut);
        }

        return [
            'cover_letter_text' => $letter,
            'cover_letter_path' => 'storage/generated/' . basename($coverTxt),
            'resume_path'       => $resumeOut,
        ];
    }

    private function callClaude(AnthropicService $svc, string $system, string $user): string
    {
        // AnthropicService may have a generic message method; if not, build one inline.
        if (method_exists($svc, 'message')) {
            return (string) $svc->message($system, $user);
        }
        // Inline curl fallback so we don't depend on a specific helper API.
        $appConfig = require BASE_PATH . '/config/app.php';
        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $appConfig['anthropic_key'],
                'anthropic-version: 2023-06-01',
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'model' => $appConfig['claude_model'],
                'max_tokens' => 2048,
                'system' => $system,
                'messages' => [['role' => 'user', 'content' => $user]],
            ]),
        ]);
        $resp = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($resp, true);
        return $data['content'][0]['text'] ?? '(no response)';
    }

    private function fallback(array $listing, ?array $track, string $profile): string
    {
        $name = 'J.J. Johnson';
        return "Dear {$listing['company']} Hiring Team,\n\n" .
            "I am writing to express my interest in the {$listing['title']} role. " .
            substr(trim($profile), 0, 600) . "\n\n" .
            "I would welcome the chance to discuss how my background fits this role.\n\n" .
            "Sincerely,\n$name\n\n" .
            "[Generated without Claude API — set ANTHROPIC_API_KEY in .env to enable AI tailoring.]";
    }
}
