<?php

/**
 * Ship package build script (Mono Market gate 4.2).
 *
 * Creates the release archives with archive hygiene per CHECKLIST.md.
 * Run: php docs/build-release.php
 *
 * Produces dist/clot-v{version}-source.zip and dist/clot-v{version}-docs.zip
 * plus SHA-256 checksums.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$version = '1.0.0';
$dist = $root.'/dist';

echo "=== CLOT Ship Package Build v{$version} ===\n\n";

// --- 1. Clean dist ---
if (is_dir($dist)) {
    exec('rm -rf '.escapeshellarg($dist));
}
mkdir($dist, 0777, true);

// --- 2. Create source archive (no dev artifacts) ---
$sourceDir = $dist.'/clot';
echo "Creating source copy...\n";
exec(sprintf(
    'rsync -a --exclude=vendor --exclude=node_modules --exclude=.git --exclude=.github --exclude=.env --exclude=storage/app --exclude=storage/logs --exclude=storage/framework/cache --exclude=storage/framework/sessions --exclude=storage/framework/views --exclude=database/database.sqlite --exclude=tests --exclude=phpstan-ext --exclude=phpstan.neon --exclude=.phpunit.result.cache --exclude=.phpunit.cache --exclude=_ide_helper.php --exclude=dist --exclude=docs/adr --exclude=docs/CHECKLIST.md %s/ %s/',
    escapeshellarg($root),
    escapeshellarg($sourceDir),
));

// Ensure .env.example and storage structure exist
if (! file_exists($sourceDir.'/.env.example')) {
    copy($root.'/.env.example', $sourceDir.'/.env.example');
}
foreach (['app', 'logs', 'framework/cache', 'framework/sessions', 'framework/views'] as $dir) {
    @mkdir($sourceDir.'/storage/'.$dir, 0755, true);
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

    if (preg_match('/\/Users\/[a-z]+\/|C:\\\\Users\\\\|D:\\\\installation/', $content) === 1) {
        $violations[] = 'Local path: '.$file->getPathname();
    }
    if (preg_match('/sk-[a-zA-Z0-9]{20,}|AKIA[0-9A-Z]{16}|BEGIN (RSA|OPENSSH) PRIVATE KEY/', $content) === 1) {
        $violations[] = 'Possible secret: '.$file->getPathname();
    }
    if (preg_match('/phone-home|telemetry/i', $content) === 1) {
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
$sourceZip = "{$dist}/clot-v{$version}-source.zip";
$docsZip = "{$dist}/clot-v{$version}-docs.zip";

echo "\nCreating archives...\n";

// Source zip (everything in sourceDir)
$zip = new ZipArchive;
$zip->open($sourceZip, ZipArchive::CREATE | ZipArchive::OVERWRITE);

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($sourceDir, FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
    if ($file->isFile()) {
        $relative = str_replace($sourceDir.'/', '', $file->getPathname());
        $zip->addFile($file->getPathname(), 'clot/'.$relative);
    }
}
$zip->close();
echo "  Source: {$sourceZip}\n";

// Docs zip
$zip = new ZipArchive;
$zip->open($docsZip, ZipArchive::CREATE | ZipArchive::OVERWRITE);
foreach (['START-HERE.md', 'CHANGELOG.md'] as $file) {
    if (file_exists($root.'/'.$file)) {
        $zip->addFile($root.'/'.$file, $file);
    }
}
$docsFiles = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root.'/docs', FilesystemIterator::SKIP_DOTS)
);
foreach ($docsFiles as $file) {
    if ($file->isFile()) {
        $zip->addFile($file->getPathname(), 'docs/'.str_replace($root.'/docs/', '', $file->getPathname()));
    }
}
$zip->close();
echo "  Docs: {$docsZip}\n";

// --- 5. Checksums ---
$checksums = [];
foreach ([$sourceZip, $docsZip] as $file) {
    $hash = hash_file('sha256', $file);
    $checksums[basename($file)] = $hash;
    echo '  SHA-256 '.basename($file).": {$hash}\n";
}

file_put_contents("{$dist}/SHA256SUMS", implode("\n", array_map(
    fn ($name, $hash) => "{$hash}  {$name}",
    array_keys($checksums),
    $checksums,
)));

// --- 6. Final summary ---
echo "\n=== BUILD COMPLETE ===\n";
echo 'Archives: '.count(glob("{$dist}/*.zip"))."\n";
echo "Checksums: {$dist}/SHA256SUMS\n";
echo "\nNext: verify against CHECKLIST.md Part C (final submission gate).\n";
