<?php

/**
 * Ship package build script (Mono Market gate 4.2).
 *
 * Creates the release archives with archive hygiene per CHECKLIST.md.
 * Run: php docs/build-release.php
 *
 * Pure PHP — no rsync/zip external commands. Windows-compatible.
 *
 * Produces dist/clot-v{version}-source.zip and dist/clot-v{version}-docs.zip
 * plus SHA-256 checksums.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$version = '1.0.0';
$dist = $root.DIRECTORY_SEPARATOR.'dist';

echo "=== CLOT Ship Package Build v{$version} ===\n\n";

// --- 1. Clean dist ---
if (is_dir($dist)) {
    removeDir($dist);
}
mkdir($dist, 0777, true);

// --- 2. Copy source (excluding dev artifacts) ---
$sourceDir = $dist.DIRECTORY_SEPARATOR.'clot';
echo "Creating source copy...\n";

$exclusions = [
    'vendor', 'node_modules', '.git', '.github',
    '.env', '.env.backup', '.env.production',
    'storage/app', 'storage/logs',
    'storage/framework/cache', 'storage/framework/sessions', 'storage/framework/views',
    'database/database.sqlite', 'database/database.sqlite-journal',
    'tests', 'phpstan-ext', 'phpstan.neon',
    '.phpunit.result.cache', '.phpunit.cache',
    '_ide_helper.php', 'dist',
    'docs/adr', 'docs/CHECKLIST.md', 'docs/build-release.php', 'docs/BUILD-RELEASE.md',
    'public/build', 'public/hot', 'public/storage',
    'composer.lock', 'package-lock.json',
];

copyDir($root, $sourceDir, $exclusions);

// Ensure .env.example and storage structure exist
if (! file_exists($sourceDir.DIRECTORY_SEPARATOR.'.env.example')) {
    copy($root.DIRECTORY_SEPARATOR.'.env.example', $sourceDir.DIRECTORY_SEPARATOR.'.env.example');
}
foreach (['app', 'logs', 'framework'.DIRECTORY_SEPARATOR.'cache', 'framework'.DIRECTORY_SEPARATOR.'sessions', 'framework'.DIRECTORY_SEPARATOR.'views'] as $dir) {
    @mkdir($sourceDir.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.$dir, 0755, true);
}

// --- 3. Archive hygiene scan ---
echo "Running archive hygiene scan...\n";
$violations = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($sourceDir, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if (! $file->isFile()) {
        continue;
    }

    $content = file_get_contents($file->getPathname());

    if (preg_match('#/Users/[a-z]+/#', $content) === 1) {
        $violations[] = 'Local path: '.$file->getPathname();
    }
    if (preg_match('/sk-[a-zA-Z0-9]{20,}|AKIA[0-9A-Z]{16}/', $content) === 1) {
        $violations[] = 'Possible secret: '.$file->getPathname();
    }
    if (preg_match('/phone-home|telemetry/i', $content) === 1
        && ! str_contains($file->getPathname(), 'CHANGELOG')
        && ! str_contains($file->getPathname(), 'START-HERE')
        && str_ends_with($file->getPathname(), '.php')) {
        $violations[] = 'Telemetry detected: '.$file->getPathname();
    }
}

if ($violations !== []) {
    echo "VIOLATIONS FOUND:\n";
    foreach ($violations as $violation) {
        echo "  ✗ {$violation}\n";
    }
    exit(1);
}

echo "  ✓ No local paths, secrets, or telemetry detected\n";

// --- 4. Create zips ---
$sourceZip = $dist.DIRECTORY_SEPARATOR."clot-v{$version}-source.zip";
$docsZip = $dist.DIRECTORY_SEPARATOR."clot-v{$version}-docs.zip";

echo "\nCreating archives...\n";

// Source zip
$zip = new ZipArchive;
$zip->open($sourceZip, ZipArchive::CREATE | ZipArchive::OVERWRITE);
addDirToZip($zip, $sourceDir, 'clot');
$zip->close();
echo '  Source: '.$sourceZip.' ('.number_format(filesize($sourceZip) / 1024).' KB)'."\n";

// Docs zip
$zip = new ZipArchive;
$zip->open($docsZip, ZipArchive::CREATE | ZipArchive::OVERWRITE);
foreach (['START-HERE.md', 'CHANGELOG.md'] as $file) {
    if (file_exists($root.DIRECTORY_SEPARATOR.$file)) {
        $zip->addFile($root.DIRECTORY_SEPARATOR.$file, $file);
    }
}
$docsDir = $root.DIRECTORY_SEPARATOR.'docs';
if (is_dir($docsDir)) {
    addDirToZip($zip, $docsDir, 'docs');
}
$zip->close();
echo '  Docs: '.$docsZip.' ('.number_format(filesize($docsZip) / 1024).' KB)'."\n";

// --- 5. Checksums ---
$checksums = [];
foreach ([$sourceZip, $docsZip] as $file) {
    $hash = hash_file('sha256', $file);
    $checksums[basename($file)] = $hash;
    echo '  SHA-256 '.basename($file).": {$hash}\n";
}

file_put_contents($dist.DIRECTORY_SEPARATOR.'SHA256SUMS', implode("\n", array_map(
    fn ($name, $hash) => "{$hash}  {$name}",
    array_keys($checksums),
    $checksums,
)));

// --- 6. Final summary ---
echo "\n=== BUILD COMPLETE ===\n";
echo 'Archives: '.count(glob($dist.DIRECTORY_SEPARATOR.'*.zip'))."\n";
echo 'Checksums: '.$dist.DIRECTORY_SEPARATOR.'SHA256SUMS'."\n";
echo "\nNext: verify against CHECKLIST.md Part C (final submission gate).\n";

// --- Helper functions ---

function removeDir(string $dir): void
{
    if (! is_dir($dir)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($items as $item) {
        if ($item->isDir()) {
            @rmdir($item->getPathname());
        } else {
            @unlink($item->getPathname());
        }
    }
    @rmdir($dir);
}

/**
 * @param  list<string>  $exclude
 */
function copyDir(string $src, string $dst, array $exclude = []): void
{
    if (! is_dir($dst)) {
        mkdir($dst, 0755, true);
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $relative = str_replace($src.DIRECTORY_SEPARATOR, '', $item->getPathname());
        $relative = str_replace(DIRECTORY_SEPARATOR, '/', $relative);

        // Skip excluded paths (exact match or prefix match for dirs).
        foreach ($exclude as $pattern) {
            if ($relative === $pattern || str_starts_with($relative, $pattern.'/')) {
                continue 2;
            }
        }

        $target = $dst.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);

        if ($item->isDir()) {
            if (! is_dir($target)) {
                mkdir($target, 0755, true);
            }
        } else {
            $targetDir = dirname($target);
            if (! is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }
            copy($item->getPathname(), $target);
        }
    }
}

/**
 * @param  list<string>  $exclude
 */
function addDirToZip(ZipArchive $zip, string $dir, string $prefix): void
{
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (! $file->isFile()) {
            continue;
        }

        $relative = str_replace($dir.DIRECTORY_SEPARATOR, '', $file->getPathname());
        $zip->addFile($file->getPathname(), $prefix.'/'.$relative);
    }
}
