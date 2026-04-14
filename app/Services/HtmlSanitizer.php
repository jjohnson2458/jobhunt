<?php

/**
 * HTML Sanitizer Service
 *
 * Wraps HTMLPurifier to sanitize user-submitted rich text content.
 * Allows safe formatting tags while stripping scripts and dangerous attributes.
 *
 * @package    CoverLetterGenerator
 * @subpackage Services
 * @author     J.J.Johnson <email4johnson@gmail.com>
 * @copyright  2026 VisionQuest Services LLC
 */
class HtmlSanitizer
{
    /**
     * Sanitize HTML content for safe storage and display
     *
     * Allows basic formatting: bold, italic, underline, lists, links, paragraphs.
     * Strips scripts, iframes, event handlers, and all other dangerous content.
     *
     * @param string $html Raw HTML input
     * @return string Sanitized HTML
     */
    public static function clean(string $html): string
    {
        $config = \HTMLPurifier_Config::createDefault();
        $config->set('HTML.Allowed', 'p,br,strong,b,em,i,u,ul,ol,li,a[href|target],h1,h2,h3,h4,h5,h6,span,blockquote');
        $config->set('HTML.TargetBlank', true);
        $config->set('AutoFormat.RemoveEmpty', true);
        $config->set('CSS.AllowedProperties', []);
        $config->set('Cache.SerializerPath', sys_get_temp_dir());

        $purifier = new \HTMLPurifier($config);
        return $purifier->purify($html);
    }

    /**
     * Convert plain text content to basic HTML
     *
     * Used for migrating existing plain-text content to HTML format.
     * Double newlines become paragraph breaks, single newlines become <br>.
     *
     * @param string $text Plain text content
     * @return string HTML content
     */
    public static function textToHtml(string $text): string
    {
        $text = trim($text);
        if (empty($text)) return '';

        // If it already contains HTML tags, return as-is (already converted)
        if (preg_match('/<(p|br|strong|em|ul|ol|li|h[1-6])\b/i', $text)) {
            return $text;
        }

        $paragraphs = preg_split('/\n\s*\n/', $text);
        $html = '';
        foreach ($paragraphs as $p) {
            $html .= '<p>' . nl2br(htmlspecialchars(trim($p))) . '</p>';
        }
        return $html;
    }

    /**
     * Convert HTML content back to plain text
     *
     * Used when plain text is needed (e.g., AI prompts, clipboard copy).
     *
     * @param string $html HTML content
     * @return string Plain text
     */
    public static function htmlToText(string $html): string
    {
        $text = str_replace(['<br>', '<br/>', '<br />'], "\n", $html);
        $text = str_replace(['</p>', '</li>'], "\n\n", $text);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        return trim(preg_replace('/\n{3,}/', "\n\n", $text));
    }
}
