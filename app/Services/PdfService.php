<?php

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * PDF Service
 *
 * Generates professional cover letter PDFs using Dompdf.
 * Formats the cover letter in a standard business letter layout
 * with contact header, date, body, and signature.
 *
 * @package    CoverLetterGenerator
 * @subpackage Services
 * @author     J.J.Johnson <email4johnson@gmail.com>
 * @copyright  2026 VisionQuest Services LLC
 */
class PdfService
{
    /**
     * Generate and stream a cover letter PDF to the browser
     *
     * @param array      $letter  The cover letter data
     * @param array|null $profile The user profile data
     * @return void
     */
    public function generateCoverLetterPdf(array $letter, ?array $profile): void
    {
        $font = $profile['preferred_font'] ?? 'Helvetica';

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', $font);

        $dompdf = new Dompdf($options);

        $html = $this->buildHtml($letter, $profile, $font);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();

        $company = preg_replace('/[^a-zA-Z0-9]/', '_', $letter['company_name'] ?? 'Company');
        $date = date('Y-m-d');
        $filename = "Cover_Letter_{$company}_{$date}.pdf";

        $dompdf->stream($filename, ['Attachment' => true]);
    }

    /**
     * Build the HTML template for the cover letter PDF
     *
     * @param array      $letter  The cover letter data
     * @param array|null $profile The user profile data
     * @return string The complete HTML document
     */
    private function buildHtml(array $letter, ?array $profile, string $font = 'Helvetica'): string
    {
        $fontEscaped = htmlspecialchars($font, ENT_QUOTES);
        $fullName = '';
        $contactBlock = '';

        if ($profile) {
            $fullName = htmlspecialchars(trim($profile['first_name'] . ' ' . $profile['last_name']));

            $contactParts = [];
            if (!empty($profile['address_line1'])) {
                $contactParts[] = htmlspecialchars($profile['address_line1']);
            }
            if (!empty($profile['address_line2'])) {
                $contactParts[] = htmlspecialchars($profile['address_line2']);
            }
            $cityStateZip = array_filter([
                $profile['city'] ?? '',
                ($profile['state'] ?? '') . ' ' . ($profile['zip_code'] ?? ''),
            ]);
            if (!empty($cityStateZip)) {
                $contactParts[] = htmlspecialchars(implode(', ', $cityStateZip));
            }

            $contactDetails = [];
            if (!empty($profile['phone_mobile'])) {
                $contactDetails[] = htmlspecialchars($profile['phone_mobile']);
            } elseif (!empty($profile['phone_home'])) {
                $contactDetails[] = htmlspecialchars($profile['phone_home']);
            }
            if (!empty($profile['email'])) {
                $contactDetails[] = htmlspecialchars($profile['email']);
            }
            if (!empty($profile['linkedin_url'])) {
                $contactDetails[] = htmlspecialchars($profile['linkedin_url']);
            }

            $contactBlock = implode('<br>', $contactParts);
            if (!empty($contactDetails)) {
                $contactBlock .= '<br>' . implode(' | ', $contactDetails);
            }
        }

        $currentDate = date('F j, Y');

        // Render content — supports both HTML and plain text
        $rawContent = $letter['generated_content'] ?? '';
        $content = HtmlSanitizer::textToHtml($rawContent);

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: '{$fontEscaped}', 'Arial', sans-serif;
            font-size: 11pt;
            line-height: 1.5;
            color: #333;
            margin: 0;
            padding: 0;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #2c3e50;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .header h1 {
            font-size: 18pt;
            margin: 0 0 5px 0;
            color: #2c3e50;
            letter-spacing: 1px;
        }
        .header .contact {
            font-size: 9pt;
            color: #555;
        }
        .date {
            margin-bottom: 20px;
        }
        .body-content {
            text-align: justify;
        }
        .body-content p {
            margin: 0 0 10px 0;
        }
        .body-content p:last-child {
            margin-bottom: 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{$fullName}</h1>
        <div class="contact">{$contactBlock}</div>
    </div>

    <div class="date">{$currentDate}</div>

    <div class="body-content">
        {$content}
    </div>
</body>
</html>
HTML;
    }

    /**
     * Generate and stream a template PDF to the browser
     *
     * @param array $template The template record
     * @return void
     */
    public function generateTemplatePdf(array $template): void
    {
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);

        $title = htmlspecialchars($template['title']);
        $paragraphs = preg_split('/\n\s*\n/', trim($template['content']));
        $content = '';
        foreach ($paragraphs as $p) {
            $content .= '<p>' . nl2br(htmlspecialchars(trim($p))) . '</p>';
        }

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 11pt; line-height: 1.5; color: #333; }
        .header { text-align: center; border-bottom: 2px solid #2c3e50; padding-bottom: 10px; margin-bottom: 20px; }
        .header h1 { font-size: 16pt; margin: 0; color: #2c3e50; }
        .header .subtitle { font-size: 9pt; color: #888; margin-top: 4px; }
        .placeholder { background: #f0f0f0; color: #666; padding: 2px 6px; border-radius: 3px; font-style: italic; }
        .body-content { text-align: justify; }
        .body-content p { margin: 0 0 10px 0; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{$title}</h1>
        <div class="subtitle">Cover Letter Template — MyCoverLetters.com</div>
    </div>
    <div class="body-content">
        {$content}
    </div>
</body>
</html>
HTML;

        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();

        $slug = preg_replace('/[^a-zA-Z0-9]/', '_', $template['slug'] ?? 'template');
        $dompdf->stream("Template_{$slug}.pdf", ['Attachment' => true]);
    }
}
