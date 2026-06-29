<?php
if (!function_exists('workflow_status_icon')) {
    function workflow_status_icon(string $status): string {
        $iconMap = [
            'Open'        => 'bi-circle text-info',        // brighter blue
            'In Progress' => 'bi-hourglass-split text-primary',
            'Completed'   => 'bi-check-circle text-success',
            'Approved'    => 'bi-hand-thumbs-up text-success',
            'Rejected'    => 'bi-x-circle text-danger',
            'Closed'      => 'bi-lock text-warning',       // yellow so visible
            'Not Set'     => 'bi-question-circle text-warning',
        ];

        $cls = $iconMap[$status] ?? 'bi-circle';
        return '<i class="bi ' . $cls . ' me-1"></i>';
    }
}

if (!function_exists('workflow_sanitize_rich_text')) {
    function workflow_sanitize_rich_text(?string $value): string {
        $html = trim((string)$value);
        if ($html === '') {
            return '';
        }

        $html = str_replace("\0", '', $html);
        $html = preg_replace('/<!--.*?-->/s', '', $html) ?? $html;
        $html = preg_replace('/<(script|style|iframe|object|embed|form|input|button|select|textarea|meta|link)[^>]*>.*?<\/\1>/is', '', $html) ?? $html;
        $html = preg_replace('/<\/?(script|style|iframe|object|embed|form|input|button|select|textarea|meta|link)[^>]*>/is', '', $html) ?? $html;

        $allowedTags = [
            'a' => true,
            'b' => true,
            'blockquote' => true,
            'br' => true,
            'code' => true,
            'div' => true,
            'em' => true,
            'h1' => true,
            'h2' => true,
            'h3' => true,
            'h4' => true,
            'h5' => true,
            'h6' => true,
            'i' => true,
            'li' => true,
            'ol' => true,
            'p' => true,
            'pre' => true,
            's' => true,
            'strong' => true,
            'u' => true,
            'ul' => true,
        ];

        if (class_exists('DOMDocument')) {
            $previousUseErrors = libxml_use_internal_errors(true);
            $document = new DOMDocument('1.0', 'UTF-8');
            $document->loadHTML(
                '<!DOCTYPE html><html><body><div id="workflow-rich-root">' . $html . '</div></body></html>',
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
            );
            libxml_clear_errors();
            libxml_use_internal_errors($previousUseErrors);

            $root = $document->getElementById('workflow-rich-root');
            if ($root instanceof DOMElement) {
                workflow_sanitize_rich_text_node($root, $allowedTags);

                $output = '';
                foreach ($root->childNodes as $child) {
                    $output .= $document->saveHTML($child);
                }

                return workflow_normalize_rich_text_output($output);
            }
        }

        $html = strip_tags($html, '<a><b><blockquote><br><code><div><em><h1><h2><h3><h4><h5><h6><i><li><ol><p><pre><s><strong><u><ul>');
        $html = preg_replace_callback('/<([a-z0-9]+)(\s[^>]*)?>/i', static function (array $match): string {
            $tag = strtolower((string)$match[1]);
            if ($tag !== 'a') {
                return '<' . $tag . '>';
            }

            $attributes = (string)($match[2] ?? '');
            if (!preg_match('/\shref\s*=\s*([\'"])(.*?)\1/i', $attributes, $hrefMatch)) {
                return '<a>';
            }

            $href = workflow_sanitize_rich_text_url((string)$hrefMatch[2]);
            if ($href === '') {
                return '<a>';
            }

            return '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer">';
        }, $html) ?? $html;

        return workflow_normalize_rich_text_output($html);
    }
}

if (!function_exists('workflow_sanitize_rich_text_node')) {
    /**
     * @param array<string, bool> $allowedTags
     */
    function workflow_sanitize_rich_text_node(DOMNode $node, array $allowedTags): void {
        for ($child = $node->firstChild; $child !== null;) {
            $next = $child->nextSibling;

            if ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);
                if (!isset($allowedTags[$tag])) {
                    workflow_sanitize_rich_text_node($child, $allowedTags);
                    while ($child->firstChild !== null) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);
                    $child = $next;
                    continue;
                }

                workflow_sanitize_rich_text_attributes($child);
                workflow_sanitize_rich_text_node($child, $allowedTags);
            } elseif (!($child instanceof DOMText)) {
                $node->removeChild($child);
            }

            $child = $next;
        }
    }
}

if (!function_exists('workflow_sanitize_rich_text_attributes')) {
    function workflow_sanitize_rich_text_attributes(DOMElement $element): void {
        $tag = strtolower($element->tagName);
        $href = '';
        $title = '';

        if ($tag === 'a') {
            $href = workflow_sanitize_rich_text_url($element->getAttribute('href'));
            $title = trim($element->getAttribute('title'));
        }

        while ($element->attributes->length > 0) {
            $element->removeAttributeNode($element->attributes->item(0));
        }

        if ($tag === 'a' && $href !== '') {
            $element->setAttribute('href', $href);
            $element->setAttribute('target', '_blank');
            $element->setAttribute('rel', 'noopener noreferrer');
            if ($title !== '') {
                $element->setAttribute('title', substr($title, 0, 200));
            }
        }
    }
}

if (!function_exists('workflow_sanitize_rich_text_url')) {
    function workflow_sanitize_rich_text_url(string $url): string {
        $url = trim(html_entity_decode($url, ENT_QUOTES, 'UTF-8'));
        if ($url === '') {
            return '';
        }

        $lower = strtolower($url);
        if (str_starts_with($lower, 'javascript:') || str_starts_with($lower, 'data:') || str_starts_with($lower, 'vbscript:')) {
            return '';
        }

        if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $url) === 1
            && !preg_match('/^(https?|mailto|tel):/i', $url)
        ) {
            return '';
        }

        return substr($url, 0, 1000);
    }
}

if (!function_exists('workflow_normalize_rich_text_output')) {
    function workflow_normalize_rich_text_output(string $html): string {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $plain = workflow_rich_text_to_plain_text($html);
        if ($plain === '') {
            return '';
        }

        return $html;
    }
}

if (!function_exists('workflow_rich_text_to_plain_text')) {
    function workflow_rich_text_to_plain_text(?string $value): string {
        $html = trim((string)$value);
        if ($html === '') {
            return '';
        }

        $text = preg_replace('/<\s*br\s*\/?>/i', "\n", $html) ?? $html;
        $text = preg_replace('/<\/\s*(p|div|li|h[1-6]|blockquote|pre|ul|ol)\s*>/i', "\n", $text) ?? $text;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = str_replace("\xc2\xa0", ' ', $text);
        $text = preg_replace("/[ \t]+\n/", "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }
}

if (!function_exists('workflow_render_rich_text')) {
    function workflow_render_rich_text(?string $value): string {
        $raw = trim((string)$value);
        if ($raw === '') {
            return '';
        }

        if (preg_match('/<[a-z][\s\S]*>/i', $raw) !== 1) {
            return nl2br(htmlspecialchars($raw, ENT_QUOTES, 'UTF-8'));
        }

        return workflow_sanitize_rich_text($raw);
    }
}
