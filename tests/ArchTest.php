<?php

use Illuminate\Support\Str;

arch('it will not use debugging functions')
    ->expect(['dd', 'dump', 'ray'])
    ->each->not->toBeUsed();

arch('ensures `.env` variables are not referenced outside of config files')
    ->expect('env')
    ->toBeUsedInNothing();

it('does not type no-content controller actions as json responses', function () {
    $controllerPath = realpath(__DIR__ . '/../src/Http/Controllers/Api');
    $offenders = [];

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($controllerPath)
    );

    foreach ($files as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $contents = file_get_contents($file->getPathname());

        preg_match_all(
            '/function\s+([A-Za-z0-9_]+)\s*\([^)]*\)\s*:\s*([^{]+)\{/',
            $contents,
            $matches,
            PREG_OFFSET_CAPTURE | PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            $methodName = $match[1][0];
            $returnType = mb_trim($match[2][0]);

            if (!Str::contains($returnType, 'JsonResponse')) {
                continue;
            }

            $methodBody = mb_substr(
                $contents,
                $match[0][1],
                findMethodBodyLength($contents, $match[0][1])
            );

            if (Str::contains($methodBody, 'response()->noContent()')) {
                $offenders[] = $file->getPathname() . '::' . $methodName;
            }
        }
    }

    expect($offenders)->toBeEmpty();
});

function findMethodBodyLength(string $contents, int $methodStart): int
{
    $bodyStart = mb_strpos($contents, '{', $methodStart);
    $depth = 0;
    $length = mb_strlen($contents);

    for ($position = $bodyStart; $position < $length; $position++) {
        if ($contents[$position] === '{') {
            $depth++;
        }

        if ($contents[$position] === '}') {
            $depth--;

            if ($depth === 0) {
                return $position - $methodStart + 1;
            }
        }
    }

    return $length - $methodStart;
}
