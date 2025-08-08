<?php
namespace WebOffice\tools;

class Markdown {
    private array $patterns = [], $emoji = [];
    /**
     * Creates a Markdown object
     */
    public function __construct() {
        $this->emoji = [
            'joy'=>'😂',
            'angry'=>'😠',
            'face_with_raised_eyebrow'=>'🤨'
        ];
        $this->patterns = [
            // Emoji
            'emoji' => '/:([a-z_]*):/',
            // Headers with optional custom ID
            'headers' => '/^(#{1,6})\s*(.+?)(?:\s*\{#([\w\-]+)\})?\s*$/m',

            // Blockquote
            'blockquote' => '/(?<=\s|^)>(?:.*(?:\n>.*)*)/',

            // Checkboxes (unchecked)
            'checkbox_unchecked' => '/^- \[ \] (.+)$/m',
            // Checkboxes (checked), case-insensitive
            'checkbox_checked' => '/^- \[(x|X)\] (.+)$/m',

            // Tables (simplified)
            'table' => '/^(?:\|.*\|.*\n)+(?:^\|(?:\s*-+\s*\|)+\s*\n)(?:^\|.*\|.*\n)*/m',

            // Inline code
            'inline_code' => '/`([^`]+)`/',

            // Fenced code block
            'fenced_code' => '/```(\w+)?\n([\s\S]*?)```/',

            // Highlight
            'highlight' => '/==(.+?)==/',

            // Strikethrough
            'strikethrough' => '/~~(.+?)~~/',

            // Superscript
            'superscript' => '/\^(.+?)\^/',

            // Subscript
            'subscript' => '/~(.+?)~/',

            // Definition term
            'term' => '/^(.+)\n: (.+)$/m',

            // Footnote
            'footnote' => '/\[\^(\d+)\]/',

            // Horizontal rule
            'hr' => '/^---|\*\*\*$/m',

            // Bold
            'bold' => '/\*\*(.+)\*\*|__(.+)__/',

            // Italic
            'italic' => '/\*(.+)\*|_(.+)_/',

            //Link
            'link' => '/\[(.+?)\]\((.+?)\)/',

            // Image
            'image' => '/!\[(.*?)\]\((.+?)\)/',

            // Unordered list items
            'ul_list' => '/^(?:\s*[-\*+]\s+.+\n?)+/m',

            // Ordered list items
            'ol_list' => '/^(?:\s*\d+\.\s+.+\n?)+/m',
        ];
    }
    /**
     * Parses Markdown to HTML
     * @param string $text Markdown
     * @return string HTML String
     */
    public function parse(string $text): string {
        // Escape HTML entities
        $html = $text;

        $html = preg_replace_callback($this->patterns['emoji'], fn($matches): string=>$this->emoji[$matches[1]], $html);
        // Horizontal rule
        $html = preg_replace($this->patterns['hr'], '<hr>', $html);

        // Process blockquotes with fixed nesting: all nested blockquotes wrapped under one parent
        $html = preg_replace_callback($this->patterns['blockquote'], function ($matches): string {
            $lines = preg_split('/\r?\n/', $matches[0]);
            $result = '';
            $indentLevel = 0;

            // Stack to hold text for each nesting level
            $blockquoteTextStack = [];
            // Initialize at level 0
            $blockquoteTextStack[0] = '';

            foreach ($lines as $line) {
                if (preg_match('/^(>+)\s*(.*)$/', $line, $lineMatches)) {
                    $currentLevel = strlen($lineMatches[1]);
                    $text = $lineMatches[2];

                    if ($currentLevel > $indentLevel) {
                        // Before opening new nested blockquote(s), close current paragraph if any
                        if ($blockquoteTextStack[$indentLevel] !== '') {
                            $result .= "<p>" . htmlspecialchars($blockquoteTextStack[$indentLevel]) . "</p>\n";
                            $blockquoteTextStack[$indentLevel] = '';
                        }
                        // Open new nested blockquote(s)
                        for ($i = $indentLevel; $i < $currentLevel; $i++) {
                            $result .= "<blockquote class=\"blockquote\">\n";
                            // Initialize stack for the new level
                            $blockquoteTextStack[$i + 1] = '';
                        }
                    } elseif ($currentLevel < $indentLevel) {
                        // Close nested blockquote(s) and wrap their accumulated text
                        for ($i = $indentLevel; $i > $currentLevel; $i--) {
                            if ($blockquoteTextStack[$i] !== '') {
                                $result .= "<p>" . htmlspecialchars($blockquoteTextStack[$i]) . "</p>\n";
                                $blockquoteTextStack[$i] = '';
                            }
                            $result .= "</blockquote>\n";
                        }
                    }

                    // Append current line's text to current level
                    if (!isset($blockquoteTextStack[$currentLevel])) {
                        $blockquoteTextStack[$currentLevel] = '';
                    }
                    $blockquoteTextStack[$currentLevel] .= ($blockquoteTextStack[$currentLevel] !== '' ? ' ' : '') . $text;

                    $indentLevel = $currentLevel;
                } else {
                    // Normal line outside of blockquote
                    // Close all open blockquotes, wrapping their texts
                    while ($indentLevel > 0) {
                        if ($blockquoteTextStack[$indentLevel] !== '') {
                            $result .= "<p>" . htmlspecialchars($blockquoteTextStack[$indentLevel]) . "</p>\n";
                            $blockquoteTextStack[$indentLevel] = '';
                        }
                        $result .= "</blockquote>\n";
                        $indentLevel--;
                    }
                    // Add normal line as paragraph
                    $result .= "<p>" . htmlspecialchars($line) . "</p>\n";
                }
            }

            // After processing all lines, close remaining open blockquotes
            while ($indentLevel > 0) {
                if ($blockquoteTextStack[$indentLevel] !== '') {
                    $result .= "<p>" . htmlspecialchars($blockquoteTextStack[$indentLevel]) . "</p>\n";
                    $blockquoteTextStack[$indentLevel] = '';
                }
                $result .= "</blockquote>\n";
                $indentLevel--;
            }

            return $result;
        }, $html);

        // Process headers with optional IDs
        $html = preg_replace_callback($this->patterns['headers'], function ($matches) {
            $level = strlen($matches[1]);
            $content = trim($matches[2]);
            $id = $matches[3] ?? '';
            $idAttr = $id ? " id=\"$id\"" : '';
            return "<h$level$idAttr>$content</h$level>";
        }, $html);

        // Bold
        $html = preg_replace_callback($this->patterns['bold'], function ($matches): string {
            $content = $matches[1] ?? $matches[2];
            return "<strong>$content</strong>";
        }, $html);

        // Italic
        $html = preg_replace_callback($this->patterns['italic'], function ($matches): string {
            $content = $matches[1] ?? $matches[2];
            return "<em>$content</em>";
        }, $html);

        // Checkboxes unchecked
        $html = preg_replace_callback($this->patterns['checkbox_unchecked'], function ($matches) {
            $label = htmlspecialchars($matches[1]);
            $id = uniqid('md-check-');
            return "<div class=\"form-check\">
                <input id=\"$id\" class=\"form-check-input\" type=\"checkbox\" disabled>
                <label for=\"$id\" class=\"form-check-label\">$label</label></div>";
        }, $html);

        // Checkboxes checked
        $html = preg_replace_callback($this->patterns['checkbox_checked'], function ($matches) {
            $label = htmlspecialchars($matches[2]);
            $id = uniqid('md-check-');
            return "<div class=\"form-check\">
                <input id=\"$id\" class=\"form-check-input\" type=\"checkbox\" disabled checked>
                <label for=\"$id\" class=\"form-check-label\">$label</label></div>";
        }, $html);

        // Tables
        if (preg_match($this->patterns['table'], $html, $matches)) {
            $tableText = $matches[0];
            $rows = preg_split('/\n/', trim($tableText));
            $htmlTable = '<table class="table table-bordered table-striped">';
            $headerProcessed = false;
            unset($rows[1]);
            $rows = array_values($rows);

            foreach ($rows as $row) {
                $cells = array_map('trim', explode('|', trim($row, '|')));
                // Skip empty lines
                if (empty(array_filter($cells, fn($cell) => $cell !== ''))) {
                    continue;
                }
                // Identify separator line (e.g., --- | ---)
                if (!$headerProcessed && preg_match('/^\s*-+\s*$/', $cells[0]) && count($cells) > 1) {
                    // This is the separator line; skip it
                    $headerProcessed = true;
                    continue;
                }
                if (!$headerProcessed) {
                    // Header row
                    $htmlTable .= '<thead><tr>';
                    foreach ($cells as $cell) {
                        $htmlTable .= '<th>' . htmlspecialchars($cell) . '</th>';
                    }
                    $htmlTable .= '</tr></thead><tbody>';
                    $headerCells = $cells;
                    $headerProcessed = true;
                } else {
                    // Data row
                    $htmlTable .= '<tr>';
                    foreach ($cells as $cell) {
                        $htmlTable .= '<td>' . htmlspecialchars($cell) . '</td>';
                    }
                    $htmlTable .= '</tr>';
                }
            }
            $htmlTable .= '</tbody></table>';
            $html = str_replace($tableText, $htmlTable, $html);
        }

        // Fenced code blocks
        $html = preg_replace_callback($this->patterns['fenced_code'], function ($matches) {
            $language = $matches[1] ?? '';
            $code = htmlspecialchars($matches[2]);
            return "<pre class=\"language-$language line-numbers\"><code>$code</code></pre>";
        }, $html);

        // Inline code
        $html = preg_replace_callback($this->patterns['inline_code'], fn($matches): string=>
            "<code class=\"language-none\">" . htmlspecialchars($matches[1]) . "</code>"
        , $html);

        // Highlight
        $html = preg_replace_callback($this->patterns['highlight'], function ($matches) {
            return '<mark>' . htmlspecialchars($matches[1]) . '</mark>';
        }, $html);

        // Strikethrough
        $html = preg_replace_callback($this->patterns['strikethrough'], function ($matches) {
            return '<del>' . htmlspecialchars($matches[1]) . '</del>';
        }, $html);

        // Superscript
        $html = preg_replace_callback($this->patterns['superscript'], function ($matches) {
            return '<sup>' . htmlspecialchars($matches[1]) . '</sup>';
        }, $html);

        // Subscript
        $html = preg_replace_callback($this->patterns['subscript'], function ($matches) {
            return '<sub>' . htmlspecialchars($matches[1]) . '</sub>';
        }, $html);

        // Definition lists
        $html = preg_replace_callback($this->patterns['term'], function ($matches) {
            $term = htmlspecialchars($matches[1]);
            $definition = htmlspecialchars($matches[2]);
            return "<dl><dt>$term</dt><dd>$definition</dd></dl>";
        }, $html);

        // Footnotes (reference)
        $html = preg_replace_callback('/\[\^(\d+)\]/', function ($matches) {
            $num = htmlspecialchars($matches[1]);
            return "<sup id=\"fnref$num\"><a href=\"#fn$num\">$num</a></sup>";
        }, $html);

        // Footnote content
        preg_match_all('/^\[\^(\d+)\]: (.+)$/m', $text, $matches);
        if ($matches[1]) {
            $html .= '<div class="footnotes"><hr><ol>';
            foreach ($matches[1] as $index => $num) {
                $content = htmlspecialchars($matches[2][$index]);
                $html .= "<li id=\"fn$num\">$content</li>";
            }
            $html .= '</ol></div>';
        }

        // Images
        $html = preg_replace_callback($this->patterns['image'], fn($matches): string => "<img src=\"".htmlspecialchars($matches[2])."\" alt=\"".htmlspecialchars($matches[1])."\">", $html);

        // Links
        $html = preg_replace_callback($this->patterns['link'], fn($matches): string => "<a href=\"".htmlspecialchars($matches[2])."\">".htmlspecialchars($matches[1])."</a>", $html);

        // Unordered lists
        $html = preg_replace_callback($this->patterns['ul_list'], function ($matches): string {
            $lines = preg_split('/\r?\n/', trim($matches[0]));
            $listItems = '';
            foreach ($lines as $line) {
                if (preg_match('/^\s*[-\*\+]\s+(.*)$/', $line, $m)) {
                    $listItems .= '<li class="list-group-item">' . htmlspecialchars($m[1]) . '</li>';
                }
            }
            return "<ul class=\"list-group\">$listItems</ul>";
        }, $html);

        // Ordered lists
        $html = preg_replace_callback($this->patterns['ol_list'], function ($matches): string {
            $lines = preg_split('/\r?\n/', trim($matches[0]));
            $listItems = '';
            foreach ($lines as $line) {
                if (preg_match('/^\s*\d+\.\s+(.*)$/', $line, $m)) {
                    $listItems .= '<li class="list-group-item">' . htmlspecialchars($m[1]) . '</li>';
                }
            }
            return "<ol class=\"list-group list-group-numbered\">$listItems</ol>";
        }, $html);

        
        

        // Wrap paragraphs
        // Final paragraph wrapping: wrap only blocks that are not table, blockquote, pre, code, etc.
        // Define a regex to match elements that should NOT be wrapped
        // We'll split by block-level tags and wrap the rest

        $finalHtml = '';
        $pattern = '/(<!--.*?-->|<pre.*?>.*?<\/pre>|<table.*?>.*?<\/table>|<blockquote.*?>.*?<\/blockquote>|<h\d.*?>.*?<\/h\d>|<hr\s*\/?>|<ul.*?>.*?<\/ul>|<ol.*?>.*?<\/ol>|<li.*?>.*?<\/li>|<p>.*?<\/p>|<div.*?>.*?<\/div>)/is';

        $pos = 0;
        while (preg_match($pattern, $html, $match, PREG_OFFSET_CAPTURE, $pos)) {
            $matchStart = $match[0][1];
            $matchText = $match[0][0];

            // Wrap the text before the match if needed
            if ($matchStart > $pos) {
                $before = substr($html, $pos, $matchStart - $pos);
                // Wrap in <p> if not empty
                $before = trim($before);
                if ($before !== '') {
                    $finalHtml .= "<p>$before</p>";
                }
            }

            // Append the matched element as is
            $finalHtml .= $matchText;

            // Update position
            $pos = $matchStart + strlen($matchText);
        }

        // Wrap remaining part if any
        if ($pos < strlen($html)) {
            $remaining = substr($html, $pos);
            $remaining = trim($remaining);
            if ($remaining !== '') {
                $finalHtml .= "<p>$remaining</p>";
            }
        }

        return $finalHtml;
    }
    
    /**
     * Adds an emoji to the list
     * @param array{name: string, result: string} $emoji Associate array with the ['face_with_raised_eyebrow'=>'🤨']
     * @return Markdown
     */
    public function addEmoji(array $emoji): static{
        $this->emoji = array_merge($this->emoji, array_map(fn($e): string=>strtolower($e),$emoji));
        return $this;
    }
}
