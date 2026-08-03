<?php

declare(strict_types=1);

final class Scraper
{
    private array $urls;
    private string $sourceBaseUrl;
    private int $maxBlogItems;
    private int $maxShopPages;

    public function __construct(private readonly array $config, private readonly HttpClient $http)
    {
        $this->urls = $config['WATCH_URLS'] ?? [];
        $this->sourceBaseUrl = rtrim((string) ($config['SOURCE_BASE_URL'] ?? 'https://pilatesvitalite.webactiondemo.ca'), '/');
        $this->maxBlogItems = (int) ($config['MAX_BLOG_ITEMS'] ?? 10);
        $this->maxShopPages = max(1, (int) ($config['MAX_SHOP_PAGES'] ?? 10));
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

    public function nextCourse(): ?array
    {
        $shopUrl = (string) ($this->urls['shop'] ?? $this->sourceBaseUrl . '/shop');
        $studioUrl = (string) ($this->urls['studio_shop'] ?? $this->studioShopUrl($shopUrl));
        $firstPageHtml = $this->http->get($studioUrl);
        $pageCount = min($this->shopPageCount($firstPageHtml), $this->maxShopPages);
        $courses = [];

        for ($page = 1; $page <= $pageCount; $page++) {
            $pageUrl = $page === 1 ? $studioUrl : $this->shopPageUrl($studioUrl, $page);
            $html = $page === 1 ? $firstPageHtml : $this->http->get($pageUrl);

            foreach ($this->parseCourseItems($html, $pageUrl) as $item) {
                $courses[$item['item_key']] = $item['payload'];
            }
        }

        $today = new DateTimeImmutable('today');
        $nextCourse = null;

        foreach ($courses as $course) {
            $url = trim((string) ($course['url'] ?? ''));
            if ($url === '') {
                continue;
            }

            foreach ($this->sessionVariants($this->http->get($url)) as $variant) {
                $date = trim((string) ($variant['date'] ?? ''));
                $sessionDate = $this->isoDate($date);
                $stock = is_numeric($variant['stock'] ?? null) ? (int) $variant['stock'] : 0;
                if ($sessionDate === null || $sessionDate < $today || $stock <= 0) {
                    continue;
                }

                $variantId = trim((string) ($variant['id'] ?? ''));
                if ($variantId === '') {
                    $variantId = substr(hash('sha256', $date . '|' . (string) ($variant['sku'] ?? '')), 0, 16);
                }

                $price = trim((string) ($variant['currency_price'] ?? ''));
                if ($price === '') {
                    $price = trim((string) ($course['price'] ?? ''));
                }

                $candidate = [
                    'id' => (string) ($course['id'] ?? app_slug_from_url($url)) . ':' . $variantId,
                    'title' => trim((string) ($course['title'] ?? '')),
                    'url' => $url,
                    'date' => $date,
                    'dateLabel' => trim((string) ($variant['label'] ?? '')),
                    'price' => $price,
                    'imageUrl' => trim((string) ($course['imageUrl'] ?? '')),
                    'mode' => 'studio',
                ];

                if ($candidate['title'] !== '' && ($nextCourse === null || $this->courseComesBefore($candidate, $nextCourse))) {
                    $nextCourse = $candidate;
                }
            }
        }

        return $nextCourse;
    }

    public function watchedItems(): array
    {
        return array_merge(
            $this->itemsFromLatest($this->latest()),
            $this->courseItems(),
            $this->packageItems()
        );
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

    private function courseItems(): array
    {
        $shopUrl = (string) ($this->urls['shop'] ?? $this->sourceBaseUrl . '/shop');
        $firstPageHtml = $this->http->get($shopUrl);
        $pageCount = min($this->shopPageCount($firstPageHtml), $this->maxShopPages);
        $items = [];

        for ($page = 1; $page <= $pageCount; $page++) {
            $pageUrl = $page === 1 ? $shopUrl : $this->shopPageUrl($shopUrl, $page);
            $html = $page === 1 ? $firstPageHtml : $this->http->get($pageUrl);

            foreach ($this->parseCourseItems($html, $pageUrl) as $item) {
                $items[$item['item_key']] = $item;
            }
        }

        return array_values($items);
    }

    private function parseCourseItems(string $html, string $pageUrl): array
    {
        $xpath = $this->xpath($html);
        $items = [];

        foreach ($xpath->query("//div[contains(concat(' ', normalize-space(@class), ' '), ' wsus__product_item ')]") ?: [] as $card) {
            $linkNode = $this->first(
                $xpath,
                ".//a[contains(@href, '/shop/product/') and contains(concat(' ', normalize-space(@class), ' '), ' title ')]",
                $card
            ) ?? $this->first($xpath, ".//a[contains(@href, '/shop/product/')]", $card);

            $url = $this->href($linkNode, $pageUrl);
            $title = $this->text($linkNode);
            if ($url === '' || $title === '') {
                continue;
            }

            $slug = app_slug_from_url($url);
            $itemKey = $this->stableItemKey('product', $slug);
            $price = $this->text($this->first($xpath, './/div[contains(@class, "text")]//h4[1]', $card));
            $imageNode = $this->first($xpath, './/div[contains(@class, "img")]//img[@src][1]', $card);
            $imageUrl = $this->src($imageNode, $pageUrl);
            $payload = [
                'id' => $slug,
                'type' => 'course',
                'title' => $title,
                'url' => $url,
                'price' => $price,
                'imageUrl' => $imageUrl,
            ];

            $items[$itemKey] = [
                'section' => 'course',
                'item_key' => $itemKey,
                'source_url' => $url,
                'content_hash' => app_json_hash($payload),
                'title' => $title,
                'excerpt' => $price !== '' ? $price : null,
                'image_url' => $imageUrl !== '' ? $imageUrl : null,
                'published_at' => null,
                'payload' => $payload,
            ];
        }

        return array_values($items);
    }

    private function studioShopUrl(string $shopUrl): string
    {
        return $shopUrl
            . (str_contains($shopUrl, '?') ? '&' : '?')
            . 'search=&category%5B%5D=en-studio';
    }

    private function sessionVariants(string $html): array
    {
        if (!preg_match('/\b(?:const|let|var)\s+sessionVariants\s*=\s*/', $html, $matches, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $assignment = $matches[0];
        $start = strpos($html, '[', $assignment[1] + strlen($assignment[0]));
        if ($start === false) {
            return [];
        }

        $depth = 0;
        $inString = false;
        $escaped = false;
        $length = strlen($html);

        for ($index = $start; $index < $length; $index++) {
            $character = $html[$index];

            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($character === '\\') {
                    $escaped = true;
                } elseif ($character === '"') {
                    $inString = false;
                }
                continue;
            }

            if ($character === '"') {
                $inString = true;
                continue;
            }

            if ($character === '[') {
                $depth++;
                continue;
            }

            if ($character !== ']') {
                continue;
            }

            $depth--;
            if ($depth !== 0) {
                continue;
            }

            try {
                $variants = json_decode(
                    substr($html, $start, $index - $start + 1),
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );
            } catch (JsonException) {
                return [];
            }

            return is_array($variants)
                ? array_values(array_filter($variants, static fn (mixed $variant): bool => is_array($variant)))
                : [];
        }

        return [];
    }

    private function isoDate(string $date): ?DateTimeImmutable
    {
        $value = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $errors = DateTimeImmutable::getLastErrors();

        if ($value === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return null;
        }

        return $value;
    }

    private function courseComesBefore(array $candidate, array $current): bool
    {
        $dateComparison = strcmp((string) $candidate['date'], (string) $current['date']);
        if ($dateComparison !== 0) {
            return $dateComparison < 0;
        }

        $titleComparison = strcasecmp((string) $candidate['title'], (string) $current['title']);

        return $titleComparison !== 0
            ? $titleComparison < 0
            : strcmp((string) $candidate['id'], (string) $current['id']) < 0;
    }

    private function shopPageCount(string $html): int
    {
        $xpath = $this->xpath($html);
        $pageCount = 1;

        foreach ($xpath->query("//a[contains(@href, 'page=')]") ?: [] as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }

            $query = (string) parse_url(html_entity_decode($node->getAttribute('href'), ENT_QUOTES | ENT_HTML5, 'UTF-8'), PHP_URL_QUERY);
            parse_str($query, $parameters);
            $page = filter_var($parameters['page'] ?? null, FILTER_VALIDATE_INT);
            if ($page !== false && $page > $pageCount) {
                $pageCount = $page;
            }
        }

        return max(1, $pageCount);
    }

    private function shopPageUrl(string $shopUrl, int $page): string
    {
        if (preg_match('/([?&])page=\d+/i', $shopUrl)) {
            return preg_replace('/([?&])page=\d+/i', '$1page=' . $page, $shopUrl, 1) ?? $shopUrl;
        }

        return $shopUrl . (str_contains($shopUrl, '?') ? '&' : '?') . 'page=' . $page;
    }

    private function packageItems(): array
    {
        $membershipUrl = (string) ($this->urls['membership'] ?? $this->sourceBaseUrl . '/membership');
        $xpath = $this->xpath($this->http->get($membershipUrl));
        $items = [];

        foreach ($xpath->query("//div[contains(concat(' ', normalize-space(@class), ' '), ' wsus__single_pricing ')]") ?: [] as $card) {
            $joinNode = $this->first(
                $xpath,
                ".//a[contains(concat(' ', normalize-space(@class), ' '), ' join-now ') and @data-plan_id]",
                $card
            );
            if (!$joinNode instanceof DOMElement) {
                continue;
            }

            $planId = trim($joinNode->getAttribute('data-plan_id'));
            $title = $this->text($this->first($xpath, './/h3[1]', $card));
            if ($planId === '' || $title === '') {
                continue;
            }

            $safePlanId = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $planId) ?? '';
            $safePlanId = trim($safePlanId, '-');
            if ($safePlanId === '') {
                $safePlanId = hash('sha256', $planId);
            }

            $itemKey = $this->stableItemKey('plan', $safePlanId);
            if (isset($items[$itemKey])) {
                continue;
            }

            $price = $this->text($this->first($xpath, './/h2[1]', $card));
            $payload = [
                'id' => $planId,
                'type' => 'package',
                'title' => $title,
                'url' => $membershipUrl,
                'price' => $price,
                'billing' => trim($joinNode->getAttribute('data-plan')),
            ];

            $items[$itemKey] = [
                'section' => 'package',
                'item_key' => $itemKey,
                'source_url' => $membershipUrl,
                'content_hash' => app_json_hash($payload),
                'title' => $title,
                'excerpt' => $price !== '' ? $price : null,
                'image_url' => null,
                'published_at' => null,
                'payload' => $payload,
            ];
        }

        return array_values($items);
    }

    private function stableItemKey(string $prefix, string $identity): string
    {
        $itemKey = $prefix . ':' . $identity;

        return strlen($itemKey) <= 190 ? $itemKey : $prefix . ':' . hash('sha256', $identity);
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

        usort($articles, static function (array $left, array $right): int {
            $dateComparison = app_date_sort_key($right['date'] ?? null) <=> app_date_sort_key($left['date'] ?? null);

            return $dateComparison !== 0
                ? $dateComparison
                : strcmp((string) ($left['id'] ?? ''), (string) ($right['id'] ?? ''));
        });

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
