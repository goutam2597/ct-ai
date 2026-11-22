<?php

namespace App\Modules\Ops\Installer;

use Illuminate\Support\Facades\File;

/**
 * Minimal .env editor for the installer: replace existing keys in place or
 * append them. Comments and ordering are preserved. Never renders secrets
 * back to the browser.
 */
final class EnvEditor
{
    public function __construct(private readonly string $path) {}

    public function exists(): bool
    {
        return File::exists($this->path);
    }

    public function get(string $key, ?string $default = null): ?string
    {
        if (! $this->exists()) {
            return $default;
        }

        if (preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', (string) File::get($this->path), $m) === 1) {
            return trim($m[1], "\" \t");
        }

        return $default;
    }

    /** @param  array<string, string|int|float|bool|null>  $values */
    public function set(array $values): void
    {
        $content = $this->exists() ? (string) File::get($this->path) : '';

        foreach ($values as $key => $value) {
            $formatted = $this->formatValue($value);

            if (preg_match('/^'.preg_quote($key, '/').'=.*$/m', $content) === 1) {
                $content = (string) preg_replace(
                    '/^'.preg_quote($key, '/').'=.*$/m',
                    $key.'='.$formatted,
                    $content
                );
            } else {
                $content = rtrim($content, "\r\n")."\n".$key.'='.$formatted."\n";
            }
        }

        File::put($this->path, $content);
    }

    private function formatValue(string|int|float|bool|null $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        $string = (string) $value;

        return ($string === '' || preg_match('/[\s"#]/', $string) === 1) ? '"'.$string.'"' : $string;
    }
}
