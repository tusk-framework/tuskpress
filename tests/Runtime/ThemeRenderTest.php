<?php

declare(strict_types=1);

namespace TuskPress\Tests\Runtime;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use TuskPress\Domain\Option\Option;
use TuskPress\Domain\Post\Post;
use TuskPress\Infrastructure\Persistence\MySqlOptionRepository;
use TuskPress\Plugin\HookBus;
use TuskPress\Theme\ThemeEngine;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class ThemeRenderTest extends TestCase
{
    public static function templates(): array
    {
        return [['index.php'], ['single.php'], ['page.php'], ['404.php']];
    }

    #[DataProvider('templates')]
    public function test_each_actual_template_includes_header_and_footer_on_both_renders(string $template): void
    {
        require dirname(__DIR__, 2).'/bootstrap/app.php';
        $config = ORMSetup::createAttributeMetadataConfiguration([dirname(__DIR__, 2).'/src/Domain'], true);
        $em = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
        (new SchemaTool($em))->createSchema([$em->getClassMetadata(Option::class)]);
        $options = new MySqlOptionRepository($em);
        $options->set('template', 'tusk-modern');
        $engine = new ThemeEngine($options, new HookBus());
        $path = $engine->getThemeDir().'/'.$template;
        $data = ['post' => new Post(id: new \TuskPress\Domain\Post\PostId(42), title: 'Rendered post', content: 'Rendered content'), 'posts' => []];
        for ($render = 1; $render <= 2; $render++) {
            $html = $engine->render($path, $data);
            self::assertStringContainsString('<!DOCTYPE html>', $html, "Render {$render} omitted header.");
            self::assertStringContainsString('<header class="navbar">', $html);
            self::assertStringContainsString('<footer class="site-footer">', $html, "Render {$render} omitted footer.");
            self::assertStringContainsString('</html>', $html);
        }
    }
}
