<?php
declare(strict_types=1);


final class Markdown
{
    public static function render(string $source, ?Closure $resolve = null): string
    {
        $lines = explode("\n", str_replace("\r\n", "\n", $source));
        $out = '';
        $list = '';
        $code = null;
        $paragraph = [];
        $flush = static function () use (&$out, &$paragraph, $resolve): void {
            if ($paragraph) {
                $out .= '<p>'.self::inline(implode("\n", $paragraph), $resolve).'</p>';
                $paragraph = [];
            }
        };
        $closeList = static function () use (&$out, &$list): void {
            if ($list) {
                $out .= '</'.$list.'>';
                $list = '';
            }
        };
        for ($i = 0; $i < count($lines); $i++) {
            $line = $lines[$i];
            if (preg_match('/^\s*(\x60{3,}|~{3,})(.*)$/', $line, $m)) {
                $flush();
                $closeList();
                if ($code === null) {
                    $code = ['marker' => $m[1][0], 'text' => '', 'language' => trim($m[2])];
                } elseif ($code['marker'] === $m[1][0]) {
                    $out .= '<pre><code class="language-'.e($code['language']).'">'.e($code['text']).'</code></pre>';
                    $code = null;
                }
                continue;
            }
            if ($code !== null) {
                $code['text'] .= $line."\n";
                continue;
            }
            if (trim($line) === '') {
                $flush();
                $closeList();
                continue;
            }
            if (preg_match('/^(#{1,6})\s+(.+)$/', $line, $m)) {
                $flush();
                $closeList();
                $level = strlen($m[1]);
                $out .= '<h'.$level.'>'.self::inline($m[2], $resolve).'</h'.$level.'>';
                continue;
            }
            if (str_contains($line, '|') && isset($lines[$i + 1])
                && preg_match('/^\s*\|?\s*:?-{3,}:?\s*(\|\s*:?-{3,}:?\s*)+\|?\s*$/', $lines[$i + 1])) {
                $flush();
                $closeList();
                $out .= '<div class="table-wrap"><table class="data-table"><thead><tr>';
                foreach (self::cells($line) as $cell) {
                    $out .= '<th>'.self::inline($cell, $resolve).'</th>';
                }
                $out .= '</tr></thead><tbody>';
                $i++;
                while (isset($lines[$i + 1]) && str_contains($lines[$i + 1], '|') && trim($lines[$i + 1]) !== '') {
                    $out .= '<tr>';
                    foreach (self::cells($lines[++$i]) as $cell) {
                        $out .= '<td>'.self::inline($cell, $resolve).'</td>';
                    }
                    $out .= '</tr>';
                }
                $out .= '</tbody></table></div>';
                continue;
            }
            if (preg_match('/^\s*(?:([-*+])\s+|(\d+)[.)]\s+)(.+)$/', $line, $m)) {
                $flush();
                $type = $m[2] !== '' ? 'ol' : 'ul';
                if ($list !== $type) {
                    $closeList();
                    $out .= '<'.$type.'>';
                    $list = $type;
                }
                $out .= '<li>'.self::inline($m[3], $resolve).'</li>';
                continue;
            }
            $closeList();
            if (preg_match('/^>\s?(.*)$/', $line, $m)) {
                $flush();
                $out .= '<blockquote>'.self::inline($m[1], $resolve).'</blockquote>';
            } elseif (preg_match('/^\s*(---+|\*\*\*+)\s*$/', $line)) {
                $flush();
                $out .= '<hr>';
            } else {
                $paragraph[] = $line;
            }
        }
        $flush();
        $closeList();
        if ($code !== null) {
            $out .= '<pre><code>'.e($code['text']).'</code></pre>';
        }
        return $out;
    }

    private static function cells(string $line): array
    {
        return preg_split('/(?<!\\\\)\|/', trim(trim($line), '|')) ?: [];
    }

    public static function inline(string $source, ?Closure $resolve = null): string
    {
        $tokens = [];
        $stash = static function (string $html) use (&$tokens): string {
            $key = "\x1a".count($tokens)."\x1a";
            $tokens[$key] = $html;
            return $key;
        };
        $source = preg_replace_callback('/\x60([^\x60]+)\x60/', static fn(array $m): string => $stash('<code>'.e($m[1]).'</code>'), $source);
        $source = preg_replace_callback('/(!?)\[([^\]]*)\]\(([^)\s]+)(?:\s+"[^"]*")?\)/', static function (array $m) use ($resolve, $stash): string {
            $url = html_entity_decode($m[3], ENT_QUOTES, 'UTF-8');
            $url = $resolve ? $resolve($url, $m[1] === '!') : $url;
            if (!is_string($url) || !self::safeUrl($url)) {
                return $stash(e($m[2]));
            }
            return $stash($m[1] === '!'
                ? '<img loading="lazy" src="'.e($url).'" alt="'.e($m[2]).'">'
                : '<a href="'.e($url).'" rel="noopener noreferrer">'.e($m[2]).'</a>');
        }, $source);
        $html = e($source);
        $html = preg_replace('/\*\*([^*\n]+)\*\*/', '<strong>$1</strong>', $html);
        $html = preg_replace('/(?<!\*)\*([^*\n]+)\*(?!\*)/', '<em>$1</em>', $html);
        return strtr($html, $tokens);
    }

    public static function safeUrl(string $url): bool
    {
        if (preg_match('/[\x00-\x20\x7f]/', $url) || str_starts_with($url, '//')) {
            return false;
        }
        $scheme = parse_url($url, PHP_URL_SCHEME);
        return $scheme === null || $scheme === false ? !str_starts_with($url, '\\')
            : in_array(strtolower($scheme), ['https', 'http', 'mailto'], true);
    }
}
