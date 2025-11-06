<?php

namespace App;

use League\CommonMark\MarkdownConverter;
use Spatie\YamlFrontMatter\YamlFrontMatter;

abstract class Content
{
    public function __construct(
        public string $title,
        public string $description,
        public string $content,
        public string $slug
    ) {
    }

    protected static function parseFile(string $filePath, MarkdownConverter $converter): array
    {
        $document = YamlFrontMatter::parse(file_get_contents($filePath));

        return [
            'matter' => $document->matter(),
            'content' => $converter->convert($document->body())->getContent(),
        ];
    }

    public static function slugify(string $text): string
    {
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);
        $text = strtolower($text);
        return empty($text) ? 'n-a' : $text;
    }
}
