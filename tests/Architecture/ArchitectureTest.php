<?php

declare(strict_types=1);

namespace Klaracase\Tests\Architecture;

use FilesystemIterator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;

/**
 * Rules about the shape of src/, checked on every file.
 */
final class ArchitectureTest extends TestCase
{
    /** Exception bases that have subclasses, so they cannot be final. */
    private const OPEN_BASES = [
        'Klaracase\Exception\KlaracaseException',
        'Klaracase\Exception\TransportException',
    ];

    /**
     * @return array<string, array{string, string}> class name and path by file
     */
    public static function sourceFiles(): array
    {
        $root = dirname(__DIR__, 2) . '/src';
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }
            $relative = substr($file->getPathname(), strlen($root) + 1, -4);
            $files[$relative] = ['Klaracase\\' . str_replace('/', '\\', $relative), $file->getPathname()];
        }
        ksort($files);

        return $files;
    }

    #[DataProvider('sourceFiles')]
    public function testEveryFileDeclaresStrictTypes(string $class, string $path): void
    {
        $this->assertMatchesRegularExpression('/^<\?php\s+declare\(strict_types=1\);/', (string) file_get_contents($path), $class);
    }

    #[DataProvider('sourceFiles')]
    public function testEveryClassIsFinalOrAnInterfaceEnumTraitOrAbstract(string $class, string $path): void
    {
        $reflection = self::reflect($class, $path);
        if ($reflection->isInterface() || $reflection->isEnum() || $reflection->isTrait() || $reflection->isAbstract()) {
            $this->addToAssertionCount(1);

            return;
        }
        $this->assertTrue($reflection->isFinal() || in_array($class, self::OPEN_BASES, true), $class . ' must be final');
    }

    #[DataProvider('sourceFiles')]
    public function testHttpAndSupportClassesAreMarkedInternal(string $class, string $path): void
    {
        if (! str_starts_with($class, 'Klaracase\Http\\') && ! str_starts_with($class, 'Klaracase\Support\\')) {
            $this->addToAssertionCount(1);

            return;
        }
        $doc = self::reflect($class, $path)->getDocComment();
        $this->assertIsString($doc, $class . ' needs a docblock');
        // DeprecationNotice is the argument of the public deprecation hook, so it is public API.
        if ($class === 'Klaracase\Http\DeprecationNotice') {
            $this->addToAssertionCount(1);

            return;
        }
        $this->assertStringContainsString('@internal', $doc, $class);
    }

    /**
     * @return ReflectionClass<object>
     */
    private static function reflect(string $class, string $path): ReflectionClass
    {
        if (class_exists($class) || interface_exists($class) || enum_exists($class) || trait_exists($class)) {
            return new ReflectionClass($class);
        }

        self::fail($class . ' is not loadable from ' . $path);
    }

    public function testTheOpenBasesReallyHaveSubclasses(): void
    {
        $classes = array_map(static fn (array $row): string => $row[0], self::sourceFiles());
        foreach (self::OPEN_BASES as $base) {
            $children = array_filter($classes, static fn (string $c): bool => class_exists($c) && is_subclass_of($c, $base));
            $this->assertNotEmpty($children, $base . ' is listed as open but nothing extends it');
        }
    }
}
