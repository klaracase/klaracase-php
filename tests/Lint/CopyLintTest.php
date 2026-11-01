<?php

declare(strict_types=1);

namespace Klaracase\Tests\Lint;

use FilesystemIterator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Copy lint: banned wording must not appear in the README, CHANGELOG, manifest or source.
 */
final class CopyLintTest extends TestCase
{
    private const FORBIDDEN = [
        '/hallucinat/i',
        '/halluzin/i',
        '/fabricat/i',
        '/fabrizier/i',
        '/\bfake\b/i',
        '/official source/i',
        '/offizielle quelle/i',
        '/amtlich/i',
    ];

    /** The required disclaimer says "fabricated" once. Only this exact sentence is exempt. */
    private const ALLOWED_SENTENCE = '/it is not a claim that the citation is fabricated\./i';

    /**
     * @return list<string> the forbidden patterns found in the text
     */
    private static function findForbidden(string $text): array
    {
        $withoutCommentMarks = (string) preg_replace('/^\s*(\*|\/\/|#|\/\*\*)\s?/m', '', $text);
        $flat = (string) preg_replace('/\s+/', ' ', $withoutCommentMarks);
        $cleaned = (string) preg_replace(self::ALLOWED_SENTENCE, '', $flat);

        return array_values(array_filter(
            self::FORBIDDEN,
            static fn (string $pattern): bool => preg_match($pattern, $cleaned) === 1,
        ));
    }

    /**
     * @return list<string>
     */
    private static function scannedFiles(): array
    {
        $root = dirname(__DIR__, 2);
        $files = [$root . '/README.md', $root . '/CHANGELOG.md', $root . '/composer.json'];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root . '/src', FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    public function testNoForbiddenWordingInDocsManifestAndSource(): void
    {
        $hits = [];
        foreach (self::scannedFiles() as $file) {
            foreach (self::findForbidden((string) file_get_contents($file)) as $pattern) {
                $hits[] = basename($file) . ': ' . $pattern;
            }
        }

        $this->assertSame([], $hits);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function forbiddenSamples(): array
    {
        return [
            'hallucinat' => ['This stops hallucinations.'],
            'halluzin' => ['Das verhindert Halluzinationen.'],
            'fabricat' => ['The citation is Fabricated.'],
            'fabrizier' => ['Das ist fabriziert.'],
            'fake' => ['A fake case.'],
            'official source' => ['The official source of law.'],
            'offizielle quelle' => ['Die offizielle Quelle.'],
            'amtlich' => ['Der amtliche Leitsatz.'],
        ];
    }

    #[DataProvider('forbiddenSamples')]
    public function testEveryForbiddenPatternIsFlagged(string $sample): void
    {
        $this->assertNotSame([], self::findForbidden($sample));
    }

    public function testTheRequiredDisclaimerIsAllowedAndNothingAroundIt(): void
    {
        $sentence = "A `not_found` verdict means the citation was not found in the corpus; it is not a claim that the\n * citation is fabricated.";
        $this->assertSame([], self::findForbidden($sentence));
        $this->assertNotSame([], self::findForbidden($sentence . ' It is fabricated.'));
    }
}
