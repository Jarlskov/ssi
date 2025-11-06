<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Generator;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Node\Node;
use Symfony\Component\Filesystem\Filesystem;
use Twig\Environment as TwigEnvironment;
use Twig\Loader\FilesystemLoader;

// --- Configuration and Setup ---
$config = require __DIR__ . '/config.php';
$baseDir = __DIR__;

// --- Dependency Injection ---

// Filesystem
$filesystem = new Filesystem();

// Twig
$loader = new FilesystemLoader($baseDir . '/templates');
$twig = new TwigEnvironment($loader, [
    'cache' => false,
    'auto_reload' => true,
    'optimizations' => -1,
]);
$twig->addGlobal('site', $config);

// CommonMark (Markdown)
$cmEnvironment = new Environment([]);
$cmEnvironment->addExtension(new CommonMarkCoreExtension());
$cmEnvironment->addRenderer(FencedCode::class, new class () implements NodeRendererInterface {
    public function render(Node $node, ChildNodeRendererInterface $childRenderer)
    {
        assert($node instanceof FencedCode);
        $info = $node->getInfo();
        if ($info !== null && $info === 'mermaid') {
            $content = htmlspecialchars($node->getLiteral());
            return "<div class=\"mermaid\">{$content}</div>";
        }
        $defaultRenderer = new League\CommonMark\Extension\CommonMark\Renderer\Block\FencedCodeRenderer();
        return $defaultRenderer->render($node, $childRenderer);
    }
});
$markdownConverter = new MarkdownConverter($cmEnvironment);

// --- Generator Execution ---
$generator = new Generator($filesystem, $twig, $markdownConverter, $config, $baseDir);
$generator->run();