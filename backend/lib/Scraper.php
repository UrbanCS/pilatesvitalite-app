<?php

declare(strict_types=1);

final class Scraper
{
    private array $urls;
    private string $sourceBaseUrl;
    private int $maxBlogItems;

    public function __construct(private readonly array $config, private readonly HttpClient $http)
    {
        $this->urls = $config['WATCH_URLS'] ?? [];
        $this->sourceBaseUrl = rtrim((string) ($config['SOURCE_BASE_URL'] ?? 'https://pilatesvitalite.webactiondemo.ca'), '/');
        $this->maxBlogItems = (int) ($config['MAX_BLOG_ITEMS'] ?? 10);
    }

    public function latest(): array
    {
        $homeUrl = $this->urls['home'] ?? $this->sourceBaseUrl . '/';
        $aboutUrl = $this->urls['about'] ?? $this->sourceBaseUrl . '/about-us';
        $blogUrl = $this->urls['blog'] ?? $this->sourceBaseUrl . '/blogs';
        $contactUrl = $this->urls['contact'] ?? $this->sourceBaseUrl . '/contact';

        $homeHtml = $this->http->get($homeUrl);
        $aboutHtml = $this->http->get($aboutUrl);
        $blogHtml = $this->http->get($blogUrl);
        $contactHtml = $this->http->get($contactUrl);

        return [
            'fetchedAt' => date(DATE_ATOM),
            'site' => [
                'name' => 'Pilates Vitalité',
                'tagline' => 'Bien-être physique et mental par le Pilates',
                'fullSiteUrl' => $this->sourceBaseUrl,
                'finalDomain' => (string) ($this->config['FINAL_DOMAIN'] ?? 'https://pilatesvitalite.ca'),
            ],
            'home' => $this->parseHome($homeHtml, $homeUrl),
            'about' => $this->parseAbout($aboutHtml, $aboutUrl),
            'blog' => [
                'title' => 'Blogue',
                'sourceUrl' => $blogUrl,
                'articles' => $this->parseBlogList($blogHtml, $blogUrl),
            ],
            'contact' => $this->parseContact($contactHtml, $contactUrl),
        ];
    }

    public function watchedItems(): array
    {
        return $this->itemsFromLatest($this->latest());
    }

    public function itemsFromLatest(array $latest): array
    {
        $items = [];

        $homePayload = $latest['home'];
        $items[] = [
            'section' => 'home',
            'item_key' => 'home',
            'source_url' => $homePayload['sourceUrl'],
            'content_hash' => app_json_hash([
                'title' => $homePayload['title'],
                'summary' => $homePayload['summary'],
                'benefits' => $homePayload['benefits'] ?? [],
                'benefitsIntro' => $homePayload['benefitsIntro'] ?? '',
                'benefitsOutro' => $homePayload['benefitsOutro'] ?? '',
                'highlights' => $homePayload['highlights'],
            ]),
            'title' => $homePayload['title'] ?: 'Accueil',
            'excerpt' => $homePayload['summary'],
            'image_url' => $homePayload['imageUrl'] ?? null,
            'published_at' => null,
            'payload' => $homePayload,
        ];

        $aboutPayload = $latest['about'];
        $items[] = [
            'section' => 'about',
            'item_key' => 'about',
            'source_url' => $aboutPayload['sourceUrl'],
            'content_hash' => app_json_hash([
                'title' => $aboutPayload['title'],
                'summary' => $aboutPayload['summary'],
                'sections' => $aboutPayload['sections'],
            ]),
            'title' => $aboutPayload['title'] ?: 'À propos',
            'excerpt' => $aboutPayload['summary'],
            'image_url' => $aboutPayload['imageUrl'] ?? null,
            'published_at' => null,
            'payload' => $aboutPayload,
        ];

        foreach (($latest['blog']['articles'] ?? []) as $article) {
            $items[] = [
                'section' => 'blog',
                'item_key' => $article['id'],
                'source_url' => $article['url'],
                'content_hash' => app_json_hash([
                    'title' => $article['title'],
                    'date' => $article['date'] ?? null,
                    'excerpt' => $article['excerpt'] ?? null,
                    'content' => $article['content'] ?? null,
                ]),
                'title' => $article['title'],
                'excerpt' => $article['excerpt'] ?? null,
                'image_url' => $article['imageUrl'] ?? null,
                'published_at' => $article['date'] ?? null,
                'payload' => $article,
            ];
        }

        return $items;
    }

    private function parseHome(string $html, string $url): array
    {
        $xpath = $this->xpath($html);

        $title = $this->text($this->first($xpath, "//section[contains(@class,'wsus__banner_slider')]//h1"));
        $summaryNode = $this->first($xpath, "//section[contains(@class,'wsus__machine')]//div[contains(@class,'wsus__machine_text')]//p[normalize-space()][1]");
        $summary = $this->text($summaryNode);
        $benefitsIntro = 'Faire du Pilates avec Pilates Vitalité vous permet :';
        if (preg_match('/(Faire du Pilates avec Pilates Vitalité vous permet\s*:?)\s*$/u', $summary, $matches)) {
            $benefitsIntro = trim($matches[1]);
        }
        $summary = preg_replace('/\s*Faire du Pilates avec Pilates Vitalité vous permet\s*:?\s*$/u', '', $summary) ?? $summary;
        if ($summary === '') {
            $summary = $this->metaDescription($xpath);
        }

        $highlights = [];
        foreach ($xpath->query("//section[contains(@class,'wsus__programs_page')]//a[contains(@class,'title')]") ?: [] as $node) {
            $label = $this->text($node);
            if ($label !== '') {
                $highlights[] = $label;
            }
        }

        $benefits = [];
        foreach ($xpath->query("//section[contains(@class,'wsus__machine')]//div[contains(@class,'wsus__machine_text')]//li") ?: [] as $node) {
            $benefit = $this->text($node);
            $benefit = preg_replace('/^\d+\s*/u', '', $benefit) ?? $benefit;
            if ($benefit !== '') {
                $benefits[] = $benefit;
            }
        }

        if ($benefits === []) {
            $benefits = [
                'De prévenir les blessures causées par la mauvaise posture.',
                'De soulager un grand nombre de douleurs.',
                "D'augmenter votre concentration mentale.",
            ];
        }

        $benefitsOutro = $this->text($this->first($xpath, "//section[contains(@class,'wsus__machine')]//div[contains(@class,'wsus__machine_text')]//p[contains(., 'Et plus encore')]"));
        if ($benefitsOutro === '') {
            $benefitsOutro = 'Et plus encore!';
        }

        $imageNode = $this->first($xpath, "//section[contains(@class,'wsus__programs_page')]//img[@src]");

        return [
            'title' => $title ?: 'Améliorez - Soulagez - Musclez',
            'summary' => $summary ?: 'Cours de Pilates en studio et en ligne pour développer la mobilité, la force et la vitalité.',
            'sourceUrl' => $url,
            'imageUrl' => $this->src($imageNode, $url),
            'benefits' => array_values(array_unique($benefits)),
            'benefitsIntro' => $benefitsIntro,
            'benefitsOutro' => $benefitsOutro,
            'highlights' => array_values(array_unique($highlights)),
        ];
    }

    private function parseAbout(string $html, string $url): array
    {
        $xpath = $this->xpath($html);
        $sections = [];

        $queries = [
            "//div[contains(@class,'wsus__about_us_text')]",
            "//div[contains(@class,'wsus__why_choose_us_text')]",
            "//div[contains(@class,'wsus__join_event_2_text')]",
            "//div[contains(@class,'wsus__counter_text')]",
        ];

        foreach ($queries as $query) {
            $node = $this->first($xpath, $query);
            $section = $this->sectionFromNode($xpath, $node);
            if ($section !== null) {
                $sections[] = $section;
            }
        }

        $summary = $sections[0]['body'] ?? $this->metaDescription($xpath);
        $imageNode = $this->first($xpath, "//div[contains(@class,'wsus__about_us_img')]//img[@src]");

        return [
            'title' => 'À propos de nous',
            'summary' => app_truncate($summary, 320),
            'sourceUrl' => $url,
            'imageUrl' => $this->src($imageNode, $url),
            'sections' => $sections,
        ];
    }

    private function parseBlogList(string $html, string $url): array
    {
        $xpath = $this->xpath($html);
        $cards = $xpath->query(
            "//div[contains(concat(' ', normalize-space(@class), ' '), ' wsus__blog_3_item ') or contains(concat(' ', normalize-space(@class), ' '), ' wsus__blog_item ')]"
        );
        $articles = [];
        $seen = [];

        foreach ($cards ?: [] as $card) {
            if (count($articles) >= $this->maxBlogItems) {
                break;
            }

            $linkNode = $this->first($xpath, ".//a[contains(@href, '/blog/') and contains(@class, 'title')]", $card)
                ?? $this->first($xpath, ".//a[contains(@href, '/blog/')]", $card);

            $href = $this->href($linkNode, $url);
            if ($href === '' || isset($seen[$href])) {
                continue;
            }
            $seen[$href] = true;

            $title = $this->text($linkNode);
            if ($title === '') {
                continue;
            }

            $imageNode = $this->first($xpath, ".//img[@src and not(contains(@src, 'clock')) and not(contains(@src, 'massage'))]", $card);
            $date = $this->extractDate($this->text($card));
            $detail = $this->parseArticleDetailSafe($href);

            $articles[] = [
                'id' => app_slug_from_url($href),
                'title' => $detail['title'] ?: $title,
                'url' => $href,
                'date' => $date ?: ($detail['date'] ?? ''),
                'excerpt' => $detail['excerpt'] ?? '',
                'imageUrl' => $detail['imageUrl'] ?: $this->src($imageNode, $url),
                'content' => $detail['content'] ?? '',
            ];
        }

        return $articles;
    }

    private function parseArticleDetailSafe(string $url): array
    {
        try {
            return $this->parseArticleDetail($this->http->get($url), $url);
        } catch (Throwable) {
            return [
                'title' => '',
                'date' => '',
                'excerpt' => '',
                'imageUrl' => '',
                'content' => '',
            ];
        }
    }

    private function parseArticleDetail(string $html, string $url): array
    {
        $xpath = $this->xpath($html);
        $title = $this->text($this->first($xpath, "//div[contains(@class,'wsus__blog_details_left')]//h2[1]"));
        $contentNode = $this->first($xpath, "//div[contains(@class,'wsus__blog_det_text')]");
        $content = app_clean_text($this->text($contentNode));
        $dateText = $this->text($this->first($xpath, "//ul[contains(@class,'wsus__blog_details_img_info')]"));
        $imageNode = $this->first($xpath, "//div[contains(@class,'wsus__blog_details_img_1')]//img[@src]");

        return [
            'title' => $title,
            'date' => $this->extractDate($dateText),
            'excerpt' => app_truncate($content, 220),
            'imageUrl' => $this->src($imageNode, $url),
            'content' => $content,
        ];
    }

    private function parseContact(string $html, string $url): array
    {
        $xpath = $this->xpath($html);
        $contact = [
            'title' => 'Contact',
            'sourceUrl' => $url,
            'phone' => '',
            'email' => '',
            'address' => '',
        ];

        foreach ($xpath->query("//div[contains(@class,'wsus__contact_item')]") ?: [] as $item) {
            $label = strtolower($this->text($this->first($xpath, './/h6', $item)));
            $body = $this->text($item);

            if (str_contains($label, 'adresse')) {
                $contact['address'] = app_clean_text(str_replace($this->text($this->first($xpath, './/h6', $item)), '', $body));
            }

            if (str_contains($label, 'mail') || str_contains($label, 'e-mail')) {
                $email = $this->extractEmail($body);
                $contact['email'] = $email ?: $contact['email'];
            }

            if (str_contains($label, 'phone') || str_contains($label, 'telephone') || str_contains($label, 'tel')) {
                $phone = $this->extractPhone($body);
                $contact['phone'] = $phone ?: $contact['phone'];
            }
        }

        $fullText = $this->text($xpath->document);
        $contact['email'] = $contact['email'] ?: $this->extractEmail($fullText);
        $contact['phone'] = $contact['phone'] ?: $this->extractPhone($fullText);

        return $contact;
    }

    private function xpath(string $html): DOMXPath
    {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();

        return new DOMXPath($dom);
    }

    private function first(DOMXPath $xpath, string $query, ?DOMNode $context = null): ?DOMNode
    {
        $nodes = $context ? $xpath->query($query, $context) : $xpath->query($query);

        return ($nodes && $nodes->length > 0) ? $nodes->item(0) : null;
    }

    private function text(?DOMNode $node): string
    {
        return $node ? app_clean_text($node->textContent ?? '') : '';
    }

    private function href(?DOMNode $node, string $baseUrl): string
    {
        if (!$node instanceof DOMElement) {
            return '';
        }

        return app_absolute_url($node->getAttribute('href'), $baseUrl);
    }

    private function src(?DOMNode $node, string $baseUrl): string
    {
        if (!$node instanceof DOMElement) {
            return '';
        }

        return app_absolute_url($node->getAttribute('src'), $baseUrl);
    }

    private function metaDescription(DOMXPath $xpath): string
    {
        $node = $this->first($xpath, "//meta[translate(@name, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz')='description']");

        if (!$node instanceof DOMElement) {
            return '';
        }

        return app_clean_text($node->getAttribute('content'));
    }

    private function sectionFromNode(DOMXPath $xpath, ?DOMNode $node): ?array
    {
        if ($node === null) {
            return null;
        }

        $title = $this->text($this->first($xpath, './/h2[1]', $node));
        $body = $this->text($node);
        if ($title !== '' && str_starts_with($body, $title)) {
            $body = trim(substr($body, strlen($title)));
        }

        $body = app_truncate($body, 420);
        if ($title === '' || $body === '') {
            return null;
        }

        return [
            'title' => $title,
            'body' => $body,
        ];
    }

    private function extractDate(string $text): string
    {
        if (preg_match('/\b\d{1,2}\s+[A-Za-zA-Zéû]+\s*,?\s+\d{4}\b/u', $text, $matches)) {
            return trim($matches[0]);
        }

        if (preg_match('/\b[A-Za-zA-Zéû]+\s+\d{1,2},\s+\d{4}\b/u', $text, $matches)) {
            return trim($matches[0]);
        }

        return '';
    }

    private function extractEmail(string $text): string
    {
        if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $text, $matches)) {
            return $matches[0];
        }

        return '';
    }

    private function extractPhone(string $text): string
    {
        if (preg_match('/\b(?:\+?1[\s.-]?)?\(?\d{3}\)?[\s.-]?\d{3}[\s.-]?\d{4}\b/', $text, $matches)) {
            return trim($matches[0]);
        }

        return '';
    }
}
