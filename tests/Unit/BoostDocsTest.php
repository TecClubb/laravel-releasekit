<?php

use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;
use TecClub\ReleaseKit\Models\Artifact;
use TecClub\ReleaseKit\Models\Link;
use TecClub\ReleaseKit\Models\Release;
use TecClub\ReleaseKit\Models\Stat;
use TecClub\ReleaseKit\Models\Track;
use TecClub\ReleaseKit\ReleaseKit;

/*
 * Keeps the Boost guideline, the releasekit skill, its references and the
 * README in step with the code (plan §16.5). Any PR that changes an Action
 * signature, a config key or a public class must update the docs too.
 */

function packagePath(string $path = ''): string
{
    return dirname(__DIR__, 2).($path !== '' ? '/'.$path : '');
}

function boostDocs(): array
{
    return [
        'guideline' => packagePath('resources/boost/guidelines/core.blade.php'),
        'skill' => packagePath('resources/boost/skills/releasekit/SKILL.md'),
        'actions' => packagePath('resources/boost/skills/releasekit/references/actions.md'),
        'config' => packagePath('resources/boost/skills/releasekit/references/config.md'),
        'api' => packagePath('resources/boost/skills/releasekit/references/api.md'),
        'client' => packagePath('resources/boost/skills/releasekit/references/client-integration.md'),
        'readme' => packagePath('README.md'),
    ];
}

it('ships the guideline and the skill where Boost discovers them', function () {
    foreach (boostDocs() as $path) {
        expect($path)->toBeFile();
    }
});

it('keeps the always-loaded guideline short and Blade-safe', function () {
    $guideline = file_get_contents(boostDocs()['guideline']);

    expect(substr_count($guideline, "\n"))->toBeLessThanOrEqual(30)
        // Rendered through Blade by Boost: no echo tags or directives.
        ->and($guideline)->not->toContain('{{')
        ->and($guideline)->not->toMatch('/(?<![\w`])@\w/')
        ->and($guideline)->toContain('`releasekit` skill');
});

it('gives the skill the frontmatter Boost requires', function () {
    $content = file_get_contents(boostDocs()['skill']);

    preg_match('/\A---\R(.*?)\R---/s', $content, $matches);
    $frontmatter = Yaml::parse($matches[1] ?? '');

    expect($frontmatter['name'] ?? null)->toBe('releasekit')
        ->and($frontmatter['description'] ?? '')->toContain('tecclub/releasekit');

    foreach (['actions.md', 'config.md', 'api.md', 'client-integration.md'] as $reference) {
        expect($content)->toContain("references/{$reference}");
    }
});

it('only names classes that exist', function () {
    foreach (boostDocs() as $name => $path) {
        preg_match_all('/TecClub\\\\ReleaseKit\\\\[A-Za-z\\\\]+/', file_get_contents($path), $matches);

        foreach (array_unique($matches[0]) as $reference) {
            $reference = rtrim($reference, '\\');

            // Namespace references such as TecClub\ReleaseKit\Actions\* are fine if classes live there.
            $exists = class_exists($reference) || interface_exists($reference) || enum_exists($reference)
                || is_dir(packagePath('src/'.str_replace('\\', '/', Str::after($reference, 'TecClub\\ReleaseKit\\'))));

            expect($exists)->toBeTrue("{$name} references missing {$reference}");
        }
    }
});

it('documents every Action with its real parameter names', function () {
    $doc = file_get_contents(boostDocs()['actions']);

    $actions = collect(glob(packagePath('src/Actions/*.php')))
        ->map(fn (string $file) => basename($file, '.php'))
        ->values();

    expect($actions)->not->toBeEmpty();

    foreach ($actions as $action) {
        $class = "TecClub\\ReleaseKit\\Actions\\{$action}";

        expect($doc)->toContain("### {$action}");

        $section = Str::of($doc)->after("### {$action}")->before('### ')->toString();

        foreach (['handle', 'warnings'] as $method) {
            if (! method_exists($class, $method) || ($method === 'warnings' && $action !== 'PromoteRelease')) {
                continue;
            }

            $documented = Str::of($section)->match('/'.$method.'\((.*?)\):/s')->toString();
            preg_match_all('/\$(\w+)/', $documented, $params);

            $actual = array_map(fn (ReflectionParameter $p) => $p->getName(), (new ReflectionMethod($class, $method))->getParameters());

            expect($params[1])->toBe($actual, "{$action}::{$method}() signature drifted from actions.md");
        }
    }
});

it('names only Actions, events and exceptions that exist in the docs tables', function () {
    $doc = file_get_contents(boostDocs()['actions']);

    preg_match('/## Exceptions(.*?)## Releases/s', $doc, $section);
    preg_match_all('/^\| `(\w+)` \|/m', $section[1], $exceptions);

    foreach ($exceptions[1] as $exception) {
        expect(class_exists("TecClub\\ReleaseKit\\Exceptions\\{$exception}"))->toBeTrue("Unknown exception {$exception}");
    }

    $documentedExceptions = collect(glob(packagePath('src/Exceptions/*.php')))
        ->map(fn (string $file) => basename($file, '.php'))
        ->reject(fn (string $name) => $name === 'ReleaseKitException');

    expect($documentedExceptions->diff($exceptions[1])->all())->toBe([]);

    foreach (glob(packagePath('src/Events/*.php')) as $event) {
        expect($doc)->toContain('`'.basename($event, '.php').'`');
    }
});

it('documents every top-level config key', function () {
    $config = require packagePath('config/releasekit.php');
    $doc = file_get_contents(boostDocs()['config']);

    foreach (array_keys($config) as $key) {
        expect($doc)->toContain($key);
    }

    foreach (['storage', 'delivery', 'routes', 'access', 'cache', 'analytics'] as $group) {
        foreach (array_keys($config[$group]) as $key) {
            if ($key === 'endpoints') {
                continue;
            }

            expect($doc)->toContain("`{$group}.{$key}`");
        }
    }
});

it('names only model scopes that exist', function () {
    $scopes = [
        Stat::class => ['between', 'event', 'forPlatform', 'forChannel', 'forArtifact', 'groupByVersion', 'groupByDate', 'groupByCountry', 'groupByPlatform'],
        Release::class => ['forPlatform', 'forChannel', 'published', 'drafts', 'yanked', 'withStatus'],
        Artifact::class => ['active', 'forArchitecture', 'ordered'],
        Link::class => ['active', 'forPlatform', 'permanent', 'ordered'],
        Track::class => ['forPlatform', 'forChannel'],
    ];

    $docs = file_get_contents(boostDocs()['actions']).file_get_contents(boostDocs()['config']);

    foreach ($scopes as $model => $names) {
        foreach ($names as $scope) {
            expect(method_exists($model, 'scope'.ucfirst($scope)))->toBeTrue("{$model} has no {$scope} scope")
                ->and($docs)->toContain($scope);
        }
    }
});

it('keeps the shipped client contract identical to docs/client-integration.md', function () {
    expect(file_get_contents(boostDocs()['client']))->toBe(file_get_contents(packagePath('docs/client-integration.md')));
});

it('documents every facade method', function () {
    $readme = file_get_contents(boostDocs()['readme']);

    foreach (['check', 'downloads', 'downloadsFor', 'releases'] as $method) {
        expect(method_exists(ReleaseKit::class, $method))->toBeTrue()
            ->and($readme)->toContain("ReleaseKit::{$method}(");
    }
});
