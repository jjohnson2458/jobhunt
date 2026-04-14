<?php

/**
 * Anthropic API Service
 *
 * Handles communication with the Anthropic Claude API for
 * generating cover letters. Sends structured prompts and
 * parses structured responses.
 *
 * @package    CoverLetterGenerator
 * @subpackage Services
 * @author     J.J.Johnson <email4johnson@gmail.com>
 * @copyright  2026 VisionQuest Services LLC
 */
class AnthropicService
{
    /**
     * @var string The Anthropic API endpoint
     */
    private string $apiUrl = 'https://api.anthropic.com/v1/messages';

    /**
     * @var string The API key
     */
    private string $apiKey;

    /**
     * @var string The model to use
     */
    private string $model;

    /**
     * @var int Maximum tokens for the response
     */
    private int $maxTokens;

    /**
     * Initialize the service with settings from the database
     *
     * @param string $apiKey    The Anthropic API key
     * @param string $model     The model identifier
     * @param int    $maxTokens Maximum response tokens
     */
    public function __construct(string $apiKey, string $model = 'claude-sonnet-4-20250514', int $maxTokens = 4096)
    {
        $this->apiKey = $apiKey;
        $this->model = $model;
        $this->maxTokens = $maxTokens;
    }

    /**
     * Generate a cover letter using the Claude API
     *
     * Sends the candidate profile, resume text, skills, and job description to Claude
     * and returns a structured response with the generated cover letter.
     *
     * @param array  $profile        The user profile data
     * @param string $resumeText     The extracted resume text
     * @param string $jobDescription The job posting description
     * @param string $skills         Comma or newline-separated skills list
     * @param string $companyName    Optional company name provided by user
     * @return array Associative array with keys: job_title, company_name, cover_letter, tokens_used, model
     * @throws Exception If the API call fails
     */
    public function generateCoverLetter(array $profile, string $resumeText, string $jobDescription, string $skills = '', string $companyName = ''): array
    {
        $systemPrompt = $this->buildSystemPrompt();
        $userMessage = $this->buildUserMessage($profile, $resumeText, $jobDescription, $skills, $companyName);

        $response = $this->callApi($systemPrompt, $userMessage);

        return $this->parseResponse($response);
    }

    /**
     * Build the system prompt for cover letter generation
     *
     * @return string The system prompt
     */
    private function buildSystemPrompt(): string
    {
        return <<<PROMPT
You are an expert career consultant and professional cover letter writer. Your task is to write a compelling, personalized cover letter based on the candidate's profile, resume, and the job description provided.

Instructions:
1. Analyze the job description to identify the job title, company name, key requirements, and desired qualifications.
2. Match the candidate's experience and skills from their resume to the job requirements.
3. If a separate skills list is provided, cross-reference those skills with the job requirements and naturally weave the most relevant ones into the cover letter. Do not simply list all skills; select only those that align with the position and integrate them contextually.
4. Write a professional cover letter that:
   - Is addressed appropriately (use "Hiring Manager" if no specific name is given)
   - Opens with a strong, attention-grabbing introduction mentioning the specific position
   - Highlights 2-3 most relevant experiences/skills that match the job requirements
   - Demonstrates knowledge of the company when possible
   - Shows enthusiasm for the role
   - Closes with a clear call to action
   - Maintains a professional yet personable tone
   - Is between 250-400 words in the body

5. Format your response EXACTLY as follows (these markers are required):

[JOB_TITLE]The exact job title from the posting[/JOB_TITLE]
[COMPANY]The company name from the posting[/COMPANY]
[COVER_LETTER]
The full cover letter text here, properly formatted with paragraphs.
[/COVER_LETTER]
PROMPT;
    }

    /**
     * Build the user message with profile, resume, skills, and job description
     *
     * @param array  $profile        The user profile data
     * @param string $resumeText     The resume text content
     * @param string $jobDescription The job posting
     * @param string $skills         Comma or newline-separated skills list
     * @param string $companyName    Optional company name provided by user
     * @param string $instruction   Optional instruction override for the closing prompt line
     * @return string The formatted user message
     */
    private function buildUserMessage(array $profile, string $resumeText, string $jobDescription, string $skills = '', string $companyName = '', string $instruction = 'Please generate a professional cover letter for this position based on the candidate\'s qualifications.'): string
    {
        $fullName = trim($profile['first_name'] . ' ' . $profile['last_name']);
        $address = implode(', ', array_filter([
            $profile['address_line1'] ?? '',
            $profile['city'] ?? '',
            ($profile['state'] ?? '') . ' ' . ($profile['zip_code'] ?? ''),
        ]));

        $contactInfo = array_filter([
            'Email: ' . ($profile['email'] ?? ''),
            !empty($profile['phone_mobile']) ? 'Phone: ' . $profile['phone_mobile'] : (!empty($profile['phone_home']) ? 'Phone: ' . $profile['phone_home'] : ''),
            !empty($profile['linkedin_url']) ? 'LinkedIn: ' . $profile['linkedin_url'] : '',
        ]);

        // Build skills section if provided
        $skillsSection = '';
        if (!empty(trim($skills))) {
            // Normalize: split by commas or newlines, trim, remove empties
            $skillList = preg_split('/[,\n\r]+/', $skills);
            $skillList = array_map('trim', $skillList);
            $skillList = array_filter($skillList);
            $formattedSkills = implode(', ', $skillList);
            $skillsSection = "\n## Key Skills\n{$formattedSkills}\n";
        }

        // Build company name hint if provided
        $companySection = '';
        if (!empty(trim($companyName))) {
            $companySection = "\n## Target Company\n{$companyName}\n";
        }

        return <<<MESSAGE
## Candidate Profile
Name: {$fullName}
Address: {$address}
{$this->formatLines($contactInfo)}
{$skillsSection}{$companySection}
## Resume
{$resumeText}

## Job Description
{$jobDescription}

{$instruction}
MESSAGE;
    }

    /**
     * Format an array of strings into newline-separated lines
     *
     * @param array $lines The lines to format
     * @return string Formatted lines
     */
    private function formatLines(array $lines): string
    {
        return implode("\n", $lines);
    }

    /**
     * Call the Anthropic API
     *
     * @param string $systemPrompt The system prompt
     * @param string $userMessage  The user message
     * @return array The decoded API response
     * @throws Exception If the API call fails
     */
    private function callApi(string $systemPrompt, string $userMessage): array
    {
        $payload = [
            'model' => $this->model,
            'max_tokens' => $this->maxTokens,
            'system' => $systemPrompt,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $userMessage,
                ],
            ],
        ];

        $ch = curl_init($this->apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: 2023-06-01',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new Exception("API connection error: {$curlError}");
        }

        $decoded = json_decode($response, true);

        if ($httpCode !== 200) {
            $errorMsg = $decoded['error']['message'] ?? "HTTP {$httpCode}";
            throw new Exception("API error: {$errorMsg}");
        }

        if (!$decoded || !isset($decoded['content'][0]['text'])) {
            throw new Exception("Invalid API response format");
        }

        return $decoded;
    }

    /**
     * Parse the API response to extract structured cover letter data
     *
     * @param array $response The decoded API response
     * @return array Parsed data with job_title, company_name, cover_letter, tokens_used, model
     */
    private function parseResponse(array $response): array
    {
        $text = $response['content'][0]['text'];
        $tokensUsed = ($response['usage']['input_tokens'] ?? 0) + ($response['usage']['output_tokens'] ?? 0);

        // Extract job title
        $jobTitle = '';
        if (preg_match('/\[JOB_TITLE\](.*?)\[\/JOB_TITLE\]/s', $text, $matches)) {
            $jobTitle = trim($matches[1]);
        }

        // Extract company name
        $companyName = '';
        if (preg_match('/\[COMPANY\](.*?)\[\/COMPANY\]/s', $text, $matches)) {
            $companyName = trim($matches[1]);
        }

        // Extract cover letter
        $coverLetter = '';
        if (preg_match('/\[COVER_LETTER\](.*?)\[\/COVER_LETTER\]/s', $text, $matches)) {
            $coverLetter = trim($matches[1]);
        } else {
            // Fallback: use the entire text if markers not found
            $coverLetter = $text;
        }

        return [
            'job_title' => $jobTitle,
            'company_name' => $companyName,
            'cover_letter' => $coverLetter,
            'tokens_used' => $tokensUsed,
            'input_tokens' => $response['usage']['input_tokens'] ?? 0,
            'output_tokens' => $response['usage']['output_tokens'] ?? 0,
            'model' => $this->model,
        ];
    }

    /**
     * Generate a professional bio from resume and/or cover letter content
     *
     * @param array  $profile   The user profile data
     * @param string $resumeText   Resume text (may be empty)
     * @param string $coverLetterText Cover letter text (may be empty)
     * @param string $skills    Comma-separated skills
     * @return array Associative array with keys: bio, tokens_used, model
     * @throws Exception If the API call fails
     */
    public function generateBio(array $profile, string $resumeText, string $coverLetterText, string $skills = ''): array
    {
        $systemPrompt = <<<PROMPT
You are an expert career consultant. Write a concise, compelling professional bio (3-5 sentences) for use on a professional portfolio page.

Instructions:
1. Analyze the provided resume and/or cover letter to understand the person's experience, strengths, and career focus.
2. Write a bio that:
   - Opens with their professional identity and years of experience (if determinable)
   - Highlights 2-3 key strengths or areas of expertise
   - Mentions notable achievements or specializations
   - Ends with what they're looking for or passionate about
   - Is written in third person
   - Is between 80-150 words
3. Do NOT include contact information, addresses, or phone numbers.
4. Format your response EXACTLY as:

[BIO]
The bio text here.
[/BIO]
PROMPT;

        $fullName = trim($profile['first_name'] . ' ' . $profile['last_name']);

        $sourceSections = "## Professional: {$fullName}\n";
        if (!empty($skills)) {
            $sourceSections .= "## Skills: {$skills}\n";
        }
        if (!empty($resumeText)) {
            $sourceSections .= "\n## Resume\n{$resumeText}\n";
        }
        if (!empty($coverLetterText)) {
            $sourceSections .= "\n## Cover Letter\n{$coverLetterText}\n";
        }

        $userMessage = $sourceSections . "\nPlease generate a professional bio based on the above information.";

        $response = $this->callApi($systemPrompt, $userMessage);

        $text = $response['content'][0]['text'];
        $tokensUsed = ($response['usage']['input_tokens'] ?? 0) + ($response['usage']['output_tokens'] ?? 0);

        $bio = '';
        if (preg_match('/\[BIO\](.*?)\[\/BIO\]/s', $text, $matches)) {
            $bio = trim($matches[1]);
        } else {
            $bio = trim($text);
        }

        return [
            'bio' => $bio,
            'tokens_used' => $tokensUsed,
            'input_tokens' => $response['usage']['input_tokens'] ?? 0,
            'output_tokens' => $response['usage']['output_tokens'] ?? 0,
            'model' => $this->model,
        ];
    }

    /**
     * Generate a professional headline/title for a portfolio page
     *
     * @param array  $profile        The user profile data
     * @param string $resumeText     Resume text (may be empty)
     * @param string $coverLetterText Cover letter text (may be empty)
     * @param string $skills         Comma-separated skills
     * @return array Associative array with keys: title, tokens_used, model
     * @throws Exception If the API call fails
     */
    public function generateTitle(array $profile, string $resumeText, string $coverLetterText, string $skills = ''): array
    {
        $systemPrompt = <<<PROMPT
Generate a concise professional headline for a portfolio page (like a LinkedIn headline). It should be 5-10 words that capture the person's professional identity and key strengths. Examples: "Senior Full Stack Developer | Cloud Architecture", "Marketing Director | Brand Strategy & Analytics". Return ONLY the headline text, nothing else.

Format: [TITLE]The headline here[/TITLE]
PROMPT;

        $fullName = trim($profile['first_name'] . ' ' . $profile['last_name']);
        $sourceSections = "## Professional: {$fullName}\n";
        if (!empty($skills)) {
            $sourceSections .= "## Skills: {$skills}\n";
        }
        if (!empty($resumeText)) {
            $sourceSections .= "\n## Resume\n" . substr($resumeText, 0, 3000) . "\n";
        }
        if (!empty($coverLetterText)) {
            $sourceSections .= "\n## Cover Letter\n" . substr($coverLetterText, 0, 2000) . "\n";
        }

        $response = $this->callApi($systemPrompt, $sourceSections . "\nGenerate a professional headline.");
        $text = $response['content'][0]['text'];
        $tokensUsed = ($response['usage']['input_tokens'] ?? 0) + ($response['usage']['output_tokens'] ?? 0);

        $title = '';
        if (preg_match('/\[TITLE\](.*?)\[\/TITLE\]/s', $text, $matches)) {
            $title = trim($matches[1]);
        } else {
            $title = trim($text);
        }

        return [
            'title' => $title,
            'tokens_used' => $tokensUsed,
            'input_tokens' => $response['usage']['input_tokens'] ?? 0,
            'output_tokens' => $response['usage']['output_tokens'] ?? 0,
            'model' => $this->model,
        ];
    }

    /**
     * Suggest resume edits using the Claude API
     *
     * Sends the candidate profile, resume text, skills, and job description to Claude
     * and returns actionable suggestions and a revised resume.
     *
     * @param array  $profile        The user profile data
     * @param string $resumeText     The extracted resume text
     * @param string $jobDescription The job posting description
     * @param string $skills         Comma or newline-separated skills list
     * @param string $companyName    Optional company name provided by user
     * @return array Associative array with keys: job_title, suggestions, revised_resume, tokens_used, model
     * @throws Exception If the API call fails
     */
    public function suggestResumeEdits(array $profile, string $resumeText, string $jobDescription, string $skills = '', string $companyName = ''): array
    {
        $systemPrompt = $this->buildResumeEditSystemPrompt();
        $instruction = 'Please analyze the job description against my resume and provide specific suggestions for improving my resume, along with a complete revised version.';
        $userMessage = $this->buildUserMessage($profile, $resumeText, $jobDescription, $skills, $companyName, $instruction);

        $response = $this->callApi($systemPrompt, $userMessage);

        return $this->parseResumeEditResponse($response);
    }

    /**
     * Build the system prompt for resume edit suggestions
     *
     * @return string The system prompt
     */
    private function buildResumeEditSystemPrompt(): string
    {
        return <<<PROMPT
You are an expert resume optimization specialist and career consultant. Your task is to analyze a candidate's resume against a specific job description and provide actionable suggestions to improve the resume for that position.

Instructions:
1. Carefully analyze the job description to identify:
   - Key requirements and qualifications
   - Important keywords and phrases used by the employer
   - Technical skills and soft skills mentioned
   - Industry-specific terminology

2. Compare the candidate's current resume against these requirements and identify:
   - Missing keywords that should be incorporated
   - Experiences that could be better highlighted or reworded
   - Skills that are present but not emphasized enough
   - Sections that could be reorganized for better impact
   - Quantifiable achievements that could be added or enhanced

3. Provide numbered, specific, actionable suggestions. Each suggestion should explain:
   - What to change
   - Why it matters for this specific job
   - How to implement the change

4. Produce a complete revised resume that:
   - Incorporates all your suggestions
   - Preserves all factual information (dates, companies, education, etc.)
   - Optimizes keyword placement for ATS (Applicant Tracking Systems)
   - Maintains professional formatting
   - Does NOT fabricate any experience, skills, or qualifications

5. Format your response EXACTLY as follows (these markers are required):

[JOB_TITLE]The exact job title from the posting[/JOB_TITLE]
[SUGGESTIONS]
Your numbered suggestions here, each as a clear paragraph.
[/SUGGESTIONS]
[REVISED_RESUME]
The complete revised resume text here.
[/REVISED_RESUME]
PROMPT;
    }

    /**
     * Parse the API response to extract resume edit suggestions
     *
     * @param array $response The decoded API response
     * @return array Parsed data with job_title, suggestions, revised_resume, tokens_used, model
     */
    private function parseResumeEditResponse(array $response): array
    {
        $text = $response['content'][0]['text'];
        $tokensUsed = ($response['usage']['input_tokens'] ?? 0) + ($response['usage']['output_tokens'] ?? 0);

        // Extract job title
        $jobTitle = '';
        if (preg_match('/\[JOB_TITLE\](.*?)\[\/JOB_TITLE\]/s', $text, $matches)) {
            $jobTitle = trim($matches[1]);
        }

        // Extract suggestions
        $suggestions = '';
        if (preg_match('/\[SUGGESTIONS\](.*?)\[\/SUGGESTIONS\]/s', $text, $matches)) {
            $suggestions = trim($matches[1]);
        }

        // Extract revised resume
        $revisedResume = '';
        if (preg_match('/\[REVISED_RESUME\](.*?)\[\/REVISED_RESUME\]/s', $text, $matches)) {
            $revisedResume = trim($matches[1]);
        } else {
            // Fallback: use the entire text if markers not found
            $revisedResume = $text;
        }

        return [
            'job_title' => $jobTitle,
            'suggestions' => $suggestions,
            'revised_resume' => $revisedResume,
            'tokens_used' => $tokensUsed,
            'input_tokens' => $response['usage']['input_tokens'] ?? 0,
            'output_tokens' => $response['usage']['output_tokens'] ?? 0,
            'model' => $this->model,
        ];
    }
}
