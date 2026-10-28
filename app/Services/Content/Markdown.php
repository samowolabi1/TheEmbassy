<?php

namespace App\Services\Content;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Blade;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\ExternalLink\ExternalLinkExtension;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\MarkdownConverter;

/**
 * Turns an article written in Markdown into safe HTML for the blog.
 *
 * Raw HTML is removed and unsafe links (javascript: and the like) are dropped. Two extras match the design:
 *  - a quote whose last line starts with "—" becomes a scripture card, with that line as the reference;
 *  - a block between ":::reflect Title" and ":::" becomes the "reflect" box.
 */
class Markdown
{
    private MarkdownConverter $converter;

    public function __construct()
    {
        $environment = new Environment([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 20,
            'external_link' => [
                'internal_hosts' => [parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost'],
                'open_in_new_window' => true,
                'nofollow' => '',
                'noopener' => 'external',
                'noreferrer' => 'external',
            ],
        ]);
        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new AutolinkExtension);
        $environment->addExtension(new StrikethroughExtension);
        $environment->addExtension(new ExternalLinkExtension);

        $this->converter = new MarkdownConverter($environment);
    }

    public function toHtml(?string $markdown): string
    {
        $markdown = str_replace("\r\n", "\n", (string) $markdown);

        // Pull the reflect boxes out first, so their insides are rendered on their own.
        $boxes = [];
        $markdown = preg_replace_callback('/^:::[ \t]*reflect[ \t]*(.*?)[ \t]*\n(.*?)\n:::[ \t]*$/ms', function ($m) use (&$boxes) {
            $key = 'REFLECTBOX'.count($boxes).'X';
            $title = trim($m[1]) !== '' ? trim($m[1]) : 'Reflect';
            $boxes[$key] = '<div class="reflect"><span class="eyebrow">'.e(mb_strtoupper($title)).'</span>'.$this->convert($m[2]).'</div>';

            return "\n\n{$key}\n\n";
        }, $markdown) ?? $markdown;

        $html = $this->tidy($this->convert($markdown));

        foreach ($boxes as $key => $box) {
            $html = str_replace("<p>{$key}</p>", $box, $html);
        }

        return $html;
    }

    /**
     * Rough reading time at 220 words a minute.
     */
    public static function readingMinutes(?string $markdown): int
    {
        return max(1, (int) ceil(str_word_count(strip_tags((string) $markdown)) / 220));
    }

    private function convert(string $markdown): string
    {
        return trim($this->converter->convert($markdown)->getContent());
    }

    /**
     * Scripture cards, one h1 per page (the title), and lazy images.
     */
    private function tidy(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?><div id="root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);

        foreach (iterator_to_array($xpath->query('//h1')) as $h1) {
            $h2 = $dom->createElement('h2');
            while ($h1->firstChild) {
                $h2->appendChild($h1->firstChild);
            }
            $h1->parentNode->replaceChild($h2, $h1);
        }

        foreach ($xpath->query('//img') as $img) {
            /** @var DOMElement $img */
            $img->setAttribute('loading', 'lazy');
            $img->setAttribute('decoding', 'async');
        }

        $crown = null;
        foreach (iterator_to_array($xpath->query('//blockquote')) as $quote) {
            /** @var DOMElement $quote */
            $last = $quote->lastElementChild;
            if ($last?->nodeName !== 'p') {
                continue;
            }
            $lines = preg_split('/\n/', $last->textContent) ?: [];
            $cite = trim((string) end($lines));
            if (! preg_match('/^(—|–|--)\s*(.+)$/u', $cite, $m)) {
                continue;
            }

            // Take the reference line off the end of the quote.
            if (count($lines) === 1) {
                $quote->removeChild($last);
            } else {
                $this->dropLastLine($last);
            }

            $figure = $dom->createElement('figure');
            $figure->setAttribute('class', 'scripture');
            $crown ??= Blade::render('<x-crown :width="34" :height="28" />');
            $fragment = $dom->createDocumentFragment();
            $fragment->appendXML($crown);
            $figure->appendChild($fragment);
            $quote->parentNode->replaceChild($figure, $quote);
            $figure->appendChild($quote);
            $caption = $dom->createElement('figcaption');
            $caption->appendChild($dom->createTextNode($m[2]));
            $figure->appendChild($caption);
        }

        $root = $dom->getElementById('root');
        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $dom->saveHTML($child);
        }

        return trim($out);
    }

    /**
     * Remove the text after the last line break in a paragraph, and the break itself.
     */
    private function dropLastLine(DOMElement $p): void
    {
        while ($p->lastChild) {
            $node = $p->lastChild;
            if ($node->nodeName === 'br') {
                $p->removeChild($node);
                break;
            }
            if ($node->nodeType === XML_TEXT_NODE && str_contains($node->textContent, "\n")) {
                $node->textContent = rtrim(substr($node->textContent, 0, strrpos($node->textContent, "\n")));
                break;
            }
            $p->removeChild($node);
        }
        // A soft line break leaves a trailing newline in the text before it.
        if ($p->lastChild?->nodeType === XML_TEXT_NODE) {
            $p->lastChild->textContent = rtrim($p->lastChild->textContent);
        }
    }
}
