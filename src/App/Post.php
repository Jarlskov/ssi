<?php

namespace App;

use DateTime;
use League\CommonMark\MarkdownConverter;

class Post extends Content
{
    public function __construct(
        string $title,
        string $description,
        string $content,
        string $slug,
        public DateTime $date,
        public string $author,
        public string $image,
        public array $tags,
        public string $url
    ) {
        parent::__construct($title, $description, $content, $slug);
    }

    public static function createFromFile(string $filePath, MarkdownConverter $converter, array $config): self
    {
        $parsed = self::parseFile($filePath, $converter);
        $matter = $parsed['matter'];

        $slug = self::slugify(basename($filePath, '.md'));
        if (basename(dirname($filePath)) !== 'posts') {
            $slug = self::slugify(basename(dirname($filePath)));
        }

        $imageUrl = $matter['image'] ?? $config['defaultSocialImage'];
        if (!empty($imageUrl) && strpos($imageUrl, 'http') !== 0 && strpos($imageUrl, '/') !== 0) {
            $imageUrl = '/' . $imageUrl;
        }
        $imageUrl = $config['baseUrl'] . $imageUrl;

        return new self(
            title: $matter['title'] ?? 'Untitled',
            description: $matter['description'] ?? '',
            content: $parsed['content'],
            slug: $slug,
            date: (is_int($matter['date'])) ? (new DateTime('@' . $matter['date'])) : (new DateTime($matter['date'] ?? 'now')),
            author: $config['author'],
            image: $imageUrl,
            tags: array_map('trim', $matter['tags'] ?? []),
            url: $config['baseUrl'] . '/' . $slug . '.html'
        );
    }
}