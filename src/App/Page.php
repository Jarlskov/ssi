<?php

namespace App;

use League\CommonMark\MarkdownConverter;

class Page extends Content
{
    public function __construct(
        string $title,
        string $description,
        string $content,
        string $slug,
        public int $menu_order
    ) {
        parent::__construct($title, $description, $content, $slug);
    }

    public static function createFromFile(string $filePath, MarkdownConverter $converter): self
    {
        $parsed = self::parseFile($filePath, $converter);
        $matter = $parsed['matter'];
        $slug = self::slugify(basename($filePath, '.md'));

        return new self(
            title: $matter['title'] ?? 'Untitled',
            description: $matter['description'] ?? '',
            content: $parsed['content'],
            slug: $slug,
            menu_order: $matter['menu_order'] ?? 999
        );
    }
}