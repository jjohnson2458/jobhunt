<?php

use Smalot\PdfParser\Parser as PdfParser;

/**
 * Resume Parser Service
 *
 * Extracts text content from uploaded resume files.
 * Supports PDF (via smalot/pdfparser), DOCX (via ZipArchive), and TXT formats.
 *
 * @package    CoverLetterGenerator
 * @subpackage Services
 * @author     J.J.Johnson <email4johnson@gmail.com>
 * @copyright  2026 VisionQuest Services LLC
 */
class ResumeParser
{
    /**
     * Extract text from a resume file
     *
     * Automatically detects file type and uses the appropriate parser.
     *
     * @param string $filePath The full path to the file
     * @param string $fileType The MIME type or extension of the file
     * @return string The extracted text content
     * @throws Exception If file type is unsupported or parsing fails
     */
    public function extractText(string $filePath, string $fileType): string
    {
        if (!file_exists($filePath)) {
            throw new Exception("File not found: {$filePath}");
        }

        $fileType = strtolower($fileType);

        if (str_contains($fileType, 'pdf')) {
            return $this->parsePdf($filePath);
        }

        if (str_contains($fileType, 'wordprocessingml') || str_contains($fileType, 'docx')) {
            return $this->parseDocx($filePath);
        }

        if (str_contains($fileType, 'text') || str_contains($fileType, 'txt')) {
            return $this->parseTxt($filePath);
        }

        throw new Exception("Unsupported file type: {$fileType}");
    }

    /**
     * Parse a PDF file and extract text
     *
     * @param string $filePath Path to the PDF file
     * @return string Extracted text
     */
    private function parsePdf(string $filePath): string
    {
        try {
            $parser = new PdfParser();
            $pdf = $parser->parseFile($filePath);
            $text = $pdf->getText();
            return $this->cleanText($text);
        } catch (Exception $e) {
            return "[PDF parsing failed: {$e->getMessage()}. You may edit the text manually.]";
        }
    }

    /**
     * Parse a DOCX file and extract text
     *
     * Uses PHP's built-in ZipArchive to read word/document.xml.
     *
     * @param string $filePath Path to the DOCX file
     * @return string Extracted text
     */
    private function parseDocx(string $filePath): string
    {
        try {
            $zip = new ZipArchive();
            if ($zip->open($filePath) !== true) {
                throw new Exception("Could not open DOCX file");
            }

            $xml = $zip->getFromName('word/document.xml');
            $zip->close();

            if ($xml === false) {
                throw new Exception("Could not read document content");
            }

            // Strip XML tags but preserve paragraph breaks
            $text = str_replace('</w:p>', "\n", $xml);
            $text = strip_tags($text);
            return $this->cleanText($text);
        } catch (Exception $e) {
            return "[DOCX parsing failed: {$e->getMessage()}. You may edit the text manually.]";
        }
    }

    /**
     * Read a plain text file
     *
     * @param string $filePath Path to the TXT file
     * @return string File contents
     */
    private function parseTxt(string $filePath): string
    {
        $text = file_get_contents($filePath);
        return $this->cleanText($text ?: '');
    }

    /**
     * Clean extracted text by normalizing whitespace
     *
     * @param string $text The raw extracted text
     * @return string Cleaned text
     */
    private function cleanText(string $text): string
    {
        // Normalize line endings
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        // Remove excessive blank lines
        $text = preg_replace("/\n{3,}/", "\n\n", $text);
        // Trim
        return trim($text);
    }
}
