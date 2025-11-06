<?php

namespace App;

use League\CommonMark\MarkdownConverter;
use Spatie\YamlFrontMatter\YamlFrontMatter;
use Symfony\Component\Filesystem\Filesystem;
use Twig\Environment as TwigEnvironment;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use SplFileInfo;

class Generator
{
    private array $posts = [];
    private array $pages = [];
    private array $tagIndex = [];
    private string $indexPageContent = '';

    public function __construct(
        private Filesystem $filesystem,
        private TwigEnvironment $twig,
        private MarkdownConverter $markdownConverter,
        private array $config,
        private string $baseDir
    ) {
    }

    public function run(): void
    {
        echo "\n--- Starting Static Site Generation ---\n";

        $this->clean();
        $this->copyAssets();
        $this->processContent();
        $this->render();

        echo "\n--- Static Site Generation Complete!---\n";
    }

    private function clean(): void
    {
        $distDir = $this->baseDir . '/dist';
        echo "Cleaning output directory: {$distDir}\n";
        if ($this->filesystem->exists($distDir)) {
            $this->filesystem->remove($distDir);
        }
        $this->filesystem->mkdir($distDir);
        $this->filesystem->mkdir($distDir . '/tags');
    }

    private function copyAssets(): void
    {
        $assetsDir = $this->baseDir . '/src/assets';
        $distDir = $this->baseDir . '/dist';
        echo "Copying assets from {$assetsDir} to {$distDir}/assets\n";
        if ($this->filesystem->exists($assetsDir)) {
            $this->filesystem->mirror($assetsDir, $distDir . '/assets');
        }
    }

    private function processContent(): void
    {
        $this->processPages();
        $this->processPosts();
        $this->aggregateTags();
    }

    private function processDirectory(string $directory, callable $processor): array
    {
        $items = [];
        if (!is_dir($directory)) {
            return [];
        }
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'md') {
                $result = $processor($file);
                if ($result) {
                    $items[] = $result;
                }
            }
        }
        return $items;
    }

    private function processPages(): void
    {
        echo "Processing static pages...\n";
        $pagesDir = $this->baseDir . '/src/pages';

        $processor = function (SplFileInfo $file) {
            if ($file->getBasename() === '_index.md') {
                $document = YamlFrontMatter::parse(file_get_contents($file->getPathname()));
                $this->indexPageContent = $this->markdownConverter->convert($document->body())->getContent();
                return null; // Don't add to pages array
            }
            return Page::createFromFile($file->getPathname(), $this->markdownConverter);
        };

        $this->pages = $this->processDirectory($pagesDir, $processor);
        usort($this->pages, fn (Page $a, Page $b) => $a->menu_order <=> $b->menu_order);
        $this->twig->addGlobal('navigation_pages', $this->pages);
    }

    private function processPosts(): void
    {
        echo "Processing blog posts...\n";
        $postsDir = $this->baseDir . '/src/posts';

        $processor = function (SplFileInfo $file) {
            $post = Post::createFromFile($file->getPathname(), $this->markdownConverter, $this->config);
            $this->copyPostAssets($file->getPathname());
            return $post;
        };

        $this->posts = $this->processDirectory($postsDir, $processor);
        usort($this->posts, fn (Post $a, Post $b) => $b->date <=> $a->date);
    }

    private function aggregateTags(): void
    {
        echo "Aggregating tags...\n";
        foreach ($this->posts as $post) {
            foreach ($post->tags as $tag) {
                $sluggedTag = Content::slugify($tag);
                $this->tagIndex[$sluggedTag]['name'] = $tag;
                $this->tagIndex[$sluggedTag]['slug'] = $sluggedTag;
                $this->tagIndex[$sluggedTag]['posts'][] = $post;
            }
        }

        foreach ($this->tagIndex as & $tagData) {
            usort($tagData['posts'], fn (Post $a, Post $b) => $b->date <=> $a->date);
            $tagData['post_count'] = count($tagData['posts']);
        }
        uasort($this->tagIndex, fn ($a, $b) => $a['name'] <=> $b['name']);
        $this->twig->addGlobal('all_tags', $this->tagIndex);
    }

    private function render(): void
    {
        $this->renderPages();
        $this->renderPosts();
        $this->renderTags();
        $this->renderIndex();
    }

    private function renderPages(): void
    {
        echo "Rendering static pages...\n";
        $distDir = $this->baseDir . '/dist';
        foreach ($this->pages as $page) {
            $outputPath = $distDir . '/' . $page->slug . '.html';
            file_put_contents($outputPath, $this->twig->render('page.html.twig', ['page' => $page]));
            echo "  - Rendered {$page->slug}.html\n";
        }
    }

    private function renderPosts(): void
    {
        echo "Rendering blog posts...\n";
        $distDir = $this->baseDir . '/dist';
        foreach ($this->posts as $index => $post) {
            $prevPost = $this->posts[$index + 1] ?? null;
            $nextPost = $this->posts[$index - 1] ?? null;
            $outputPath = $distDir . '/' . $post->slug . '.html';
            file_put_contents($outputPath, $this->twig->render('post.html.twig', [
                'post' => $post,
                'prev_post' => $prevPost,
                'next_post' => $nextPost,
            ]));
            echo "  - Rendered {$post->slug}.html\n";
        }
    }

    private function renderTags(): void
    {
        echo "Rendering tag pages...\n";
        $distDir = $this->baseDir . '/dist';
        foreach ($this->tagIndex as $sluggedTag => $tagData) {
            $outputPath = $distDir . '/tags/' . $sluggedTag . '.html';
            file_put_contents($outputPath, $this->twig->render('tag.html.twig', [
                'tag_name' => $tagData['name'],
                'posts' => $tagData['posts'],
            ]));
            echo "  - Rendered tags/{$sluggedTag}.html\n";
        }

        echo "Rendering all tags page...\n";
        $outputPath = $distDir . '/tags.html';
        file_put_contents($outputPath, $this->twig->render('tags.html.twig', ['all_tags' => $this->tagIndex]));
        echo "  - Rendered tags.html\n";
    }

    private function renderIndex(): void
    {
        echo "Rendering index page...\n";
        $distDir = $this->baseDir . '/dist';
        $outputPath = $distDir . '/index.html';
        file_put_contents($outputPath, $this->twig->render('index.html.twig', [
            'posts' => $this->posts,
            'index_content' => $this->indexPageContent,
        ]));
        echo "  - Rendered index.html\n";
    }

    private function copyPostAssets(string $filePath): void
    {
        $postsDir = $this->baseDir . '/src/posts';
        $distDir = $this->baseDir . '/dist';
        $postSubDir = dirname($filePath);
        if (basename($postSubDir) !== 'posts') {
            $relativePostPath = str_replace($postsDir . DIRECTORY_SEPARATOR, '', $postSubDir);
            $targetPostAssetsDir = $distDir . DIRECTORY_SEPARATOR . $relativePostPath;
            if ($this->filesystem->exists($postSubDir)) {
                $this->filesystem->mirror($postSubDir, $targetPostAssetsDir, null, ['override' => true, 'copy_on_demand' => true]);
                $this->filesystem->remove($targetPostAssetsDir . DIRECTORY_SEPARATOR . basename($filePath));
            }
        }
    }
}
