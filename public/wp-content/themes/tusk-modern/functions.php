<?php
/**
 * Tusk Modern Theme Functions
 */

if (!function_exists('tusk_render_blocks')) {
    /**
     * Parses Editor.js JSON and converts it to HTML
     */
    function tusk_render_blocks(string $jsonContent): string
    {
        if (empty(trim($jsonContent))) {
            return '';
        }

        // Try decoding in case JSON has HTML entities (&quot;)
        $decodedJson = html_entity_decode($jsonContent, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $data = json_decode($decodedJson, true);
        
        // If it's not a valid JSON, return the text itself (for older posts)
        if (json_last_error() !== JSON_ERROR_NONE || !isset($data['blocks'])) {
            return nl2br(htmlspecialchars($jsonContent));
        }

        $html = '';

        foreach ($data['blocks'] as $block) {
            $type = $block['type'] ?? '';
            $content = $block['data'] ?? [];

            switch ($type) {
                case 'paragraph':
                    $html .= '<p>' . ($content['text'] ?? '') . '</p>';
                    break;
                case 'header':
                    $level = $content['level'] ?? 2;
                    $html .= "<h{$level}>" . ($content['text'] ?? '') . "</h{$level}>";
                    break;
                case 'list':
                    $style = ($content['style'] ?? 'unordered') === 'ordered' ? 'ol' : 'ul';
                    $html .= "<{$style}>";
                    foreach ($content['items'] ?? [] as $item) {
                        $html .= '<li>' . $item . '</li>';
                    }
                    $html .= "</{$style}>";
                    break;
                case 'image':
                    $url = $content['url'] ?? '';
                    $caption = $content['caption'] ?? '';
                    $withBorder = !empty($content['withBorder']) ? 'border: 1px solid var(--border);' : '';
                    $withBackground = !empty($content['withBackground']) ? 'background: rgba(0,0,0,0.1); padding: 1rem;' : '';
                    $stretched = !empty($content['stretched']) ? 'width: 100%;' : '';
                    
                    $html .= "<figure style='{$withBorder} {$withBackground} {$stretched}'>";
                    $html .= "<img src='{$url}' alt='{$caption}' style='max-width:100%; border-radius: var(--radius-sm);' />";
                    if ($caption) {
                        $html .= "<figcaption style='text-align:center; font-size:0.875rem; color:var(--text-secondary); margin-top:0.5rem;'>{$caption}</figcaption>";
                    }
                    $html .= "</figure>";
                    break;
                case 'quote':
                    $text = $content['text'] ?? '';
                    $caption = $content['caption'] ?? '';
                    $html .= "<blockquote>{$text}";
                    if ($caption) {
                        $html .= "<footer><cite>{$caption}</cite></footer>";
                    }
                    $html .= "</blockquote>";
                    break;
                case 'code':
                    $code = htmlspecialchars($content['code'] ?? '');
                    $html .= "<pre style='background: #1e1e1e; color: #d4d4d4; padding: 1rem; border-radius: var(--radius-sm); overflow-x: auto;'><code>{$code}</code></pre>";
                    break;
                default:
                    // Unknown block, ignore or render generically
                    break;
            }
        }

        return $html;
    }
}
