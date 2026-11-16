<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use ParseError;
use ReflectionClass;
use Symfony\Component\Finder\SplFileInfo;
use Tests\TestCase;

/**
 * Cheap guard against views that cannot render at all. Feature tests only hit the pages they
 * know about, and an error page can still contain the text a test asserts, so every Blade view
 * is compiled here and the compiled PHP is parsed (without running it).
 */
class BladeViewsCompileTest extends TestCase
{
    /** @return array<string, SplFileInfo> */
    private function views(): array
    {
        $views = [];
        foreach (File::allFiles(resource_path('views')) as $file) {
            if (str_ends_with($file->getFilename(), '.blade.php')) {
                $views[$file->getRelativePathname()] = $file;
            }
        }
        ksort($views);
        $this->assertNotEmpty($views);

        return $views;
    }

    public function test_every_blade_view_compiles_to_valid_php(): void
    {
        $failures = [];
        foreach ($this->views() as $name => $file) {
            try {
                token_get_all(Blade::compileString($file->getContents()), TOKEN_PARSE);
            } catch (ParseError $e) {
                $failures[] = "{$name}: {$e->getMessage()} (compiled line {$e->getLine()})";
            }
        }

        $this->assertSame([], $failures, "These views do not compile to valid PHP:\n".implode("\n", $failures));
    }

    public function test_no_blade_directive_is_glued_to_the_word_before_it(): void
    {
        // Blade only compiles a directive when the "@" does not follow a word character, so
        // "money@if (...)" is printed as text while its @endif still compiles.
        $compiler = Blade::getFacadeRoot();
        $directives = array_keys($compiler->getCustomDirectives());
        foreach ((new ReflectionClass($compiler))->getMethods() as $method) {
            if (preg_match('/^compile([A-Z]\w*)$/', $method->getName(), $m)) {
                $directives[] = lcfirst($m[1]);
            }
        }
        $pattern = '/\w+@('.implode('|', array_map('preg_quote', array_unique($directives))).')\b/';

        $failures = [];
        foreach ($this->views() as $name => $file) {
            foreach (preg_split('/\R/', $file->getContents()) as $i => $line) {
                if (preg_match($pattern, $line, $m)) {
                    $failures[] = "{$name}:".($i + 1)." \"{$m[0]}\" (put a space or {{-- --}} before the @)";
                }
            }
        }

        $this->assertSame([], $failures, "Blade directives glued to the preceding word:\n".implode("\n", $failures));
    }
}
