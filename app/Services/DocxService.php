<?php

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Style\Font;

/**
 * DOCX Service
 *
 * Generates professional cover letter DOCX files using PHPWord.
 * Formats the cover letter in a standard business letter layout
 * with contact header, date, body, and signature.
 *
 * @package    CoverLetterGenerator
 * @subpackage Services
 * @author     J.J.Johnson <email4johnson@gmail.com>
 * @copyright  2026 VisionQuest Services LLC
 */
class DocxService
{
    /**
     * Generate and stream a cover letter DOCX to the browser
     *
     * @param array      $letter  The cover letter data
     * @param array|null $profile The user profile data
     * @return void
     */
    public function generateCoverLetterDocx(array $letter, ?array $profile): void
    {
        $phpWord = new PhpWord();

        // Set default font from user preference
        $font = $profile['preferred_font'] ?? 'Calibri';
        $phpWord->setDefaultFontName($font);
        $phpWord->setDefaultFontSize(11);

        // Define styles
        $phpWord->addParagraphStyle('Header', ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
        $phpWord->addParagraphStyle('Body', ['alignment' => Jc::BOTH, 'spaceAfter' => 120, 'lineHeight' => 1.15]);
        $phpWord->addParagraphStyle('NoSpace', ['spaceAfter' => 0, 'lineHeight' => 1.15]);

        $section = $phpWord->addSection([
            'marginTop' => 1440,    // 1 inch
            'marginBottom' => 1440,
            'marginLeft' => 1440,
            'marginRight' => 1440,
        ]);

        // Build header with name and contact info
        $fullName = '';
        if ($profile) {
            $fullName = trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? ''));

            // Name
            $section->addText(
                $fullName,
                ['bold' => true, 'size' => 16, 'color' => '2c3e50'],
                'Header'
            );

            // Address lines
            $contactLines = [];
            if (!empty($profile['address_line1'])) {
                $contactLines[] = $profile['address_line1'];
            }
            if (!empty($profile['address_line2'])) {
                $contactLines[] = $profile['address_line2'];
            }
            $cityStateZip = array_filter([
                $profile['city'] ?? '',
                ($profile['state'] ?? '') . ' ' . ($profile['zip_code'] ?? ''),
            ]);
            if (!empty($cityStateZip)) {
                $contactLines[] = implode(', ', $cityStateZip);
            }

            foreach ($contactLines as $line) {
                $section->addText($line, ['size' => 9, 'color' => '555555'], 'Header');
            }

            // Contact details on one line
            $contactDetails = [];
            if (!empty($profile['phone_mobile'])) {
                $contactDetails[] = $profile['phone_mobile'];
            } elseif (!empty($profile['phone_home'])) {
                $contactDetails[] = $profile['phone_home'];
            }
            if (!empty($profile['email'])) {
                $contactDetails[] = $profile['email'];
            }
            if (!empty($profile['linkedin_url'])) {
                $contactDetails[] = $profile['linkedin_url'];
            }
            if (!empty($contactDetails)) {
                $section->addText(
                    implode(' | ', $contactDetails),
                    ['size' => 9, 'color' => '555555'],
                    'Header'
                );
            }

            // Horizontal line
            $section->addText('', [], ['borderBottomSize' => 12, 'borderBottomColor' => '2c3e50', 'spaceAfter' => 200]);
        }

        // Date
        $section->addText(date('F j, Y'), ['size' => 11], ['spaceAfter' => 200]);

        // Cover letter body — convert HTML to plain text for DOCX paragraphs
        $rawContent = $letter['generated_content'] ?? '';
        $plainContent = HtmlSanitizer::htmlToText($rawContent);
        $paragraphs = preg_split('/\n\s*\n/', trim($plainContent));

        foreach ($paragraphs as $p) {
            $trimmed = trim($p);
            if ($trimmed === '') continue;

            $lines = preg_split('/\n/', $trimmed);
            if (count($lines) === 1) {
                $section->addText($trimmed, ['size' => 11], 'Body');
            } else {
                $textRun = $section->addTextRun('Body');
                foreach ($lines as $idx => $line) {
                    $textRun->addText(trim($line), ['size' => 11]);
                    if ($idx < count($lines) - 1) {
                        $textRun->addTextBreak();
                    }
                }
            }
        }

        // Stream to browser
        $company = preg_replace('/[^a-zA-Z0-9]/', '_', $letter['company_name'] ?? 'Company');
        $date = date('Y-m-d');
        $filename = "Cover_Letter_{$company}_{$date}.docx";

        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save('php://output');
    }

    /**
     * Generate and stream a template DOCX to the browser
     *
     * @param array $template The template record
     * @return void
     */
    public function generateTemplateDocx(array $template): void
    {
        $phpWord = new PhpWord();
        $font = 'Calibri';

        $phpWord->setDefaultFontName($font);
        $phpWord->setDefaultFontSize(11);

        $section = $phpWord->addSection([
            'marginTop' => 1440,
            'marginBottom' => 1440,
            'marginLeft' => 1440,
            'marginRight' => 1440,
        ]);

        // Title
        $section->addText(
            $template['title'],
            ['bold' => true, 'size' => 16, 'color' => '2c3e50', 'name' => $font],
            ['alignment' => Jc::CENTER]
        );
        $section->addText(
            'Cover Letter Template — MyCoverLetters.com',
            ['size' => 9, 'color' => '888888', 'name' => $font],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 200]
        );

        // Horizontal line
        $section->addLine(['weight' => 1, 'width' => 450, 'height' => 0, 'color' => '2c3e50']);
        $section->addTextBreak(1);

        // Content paragraphs
        $paragraphs = preg_split('/\n\s*\n/', trim($template['content']));
        foreach ($paragraphs as $p) {
            $lines = explode("\n", trim($p));
            foreach ($lines as $line) {
                $section->addText(
                    trim($line),
                    ['size' => 11, 'name' => $font],
                    ['alignment' => Jc::BOTH, 'spaceAfter' => 120, 'lineHeight' => 1.15]
                );
            }
            $section->addTextBreak(1);
        }

        // Stream to browser
        $slug = preg_replace('/[^a-zA-Z0-9]/', '_', $template['slug'] ?? 'template');
        $filename = "Template_{$slug}.docx";

        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save('php://output');
    }
}
