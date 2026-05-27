<?php

namespace src\utils;

/**
 * Cache em arquivo com suporte a stale-while-revalidate para o Dashboard de Faturamento.
 *
 * Dois TTLs por entrada:
 *   exp      → expira o dado "fresco" (retorna null → dispara revalidação)
 *   hard_exp → expira o dado "stale" (descarta completamente)
 *
 * Fluxo:
 *   get()      → retorna dado se dentro de exp; null se expirado
 *   getStale() → retorna dado se dentro de hard_exp (mesmo que exp já passou)
 *   set()      → grava com exp e hard_exp (10 min depois de exp)
 */
class DashboardCache
{
    private static string $dir = '';

    private static function dir(): string
    {
        if (self::$dir === '') {
            self::$dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fat_dashboard_cache';
            if (!is_dir(self::$dir)) {
                mkdir(self::$dir, 0755, true);
            }
        }
        return self::$dir;
    }

    private static function path(string $key): string
    {
        return self::dir() . DIRECTORY_SEPARATOR . md5($key) . '.cache';
    }

    /** Retorna dado fresco (dentro do TTL) ou null se expirado. */
    public static function get(string $key): mixed
    {
        $data = self::read($key);
        if ($data === null || time() > $data['exp']) {
            return null;
        }
        return $data['val'];
    }

    /** Retorna dado mesmo que expirado (stale), enquanto hard_exp não passou. */
    public static function getStale(string $key): mixed
    {
        $data = self::read($key);
        if ($data === null) {
            return null;
        }
        if (time() > $data['hard_exp']) {
            @unlink(self::path($key));
            return null;
        }
        return $data['val'];
    }

    public static function set(string $key, mixed $value, int $ttl): void
    {
        @file_put_contents(
            self::path($key),
            serialize([
                'exp'      => time() + $ttl,
                'hard_exp' => time() + $ttl + 600,
                'val'      => $value,
            ]),
            LOCK_EX
        );
    }

    public static function forget(string $key): void
    {
        @unlink(self::path($key));
    }

    public static function flush(): void
    {
        foreach (glob(self::dir() . DIRECTORY_SEPARATOR . '*.cache') ?: [] as $file) {
            @unlink($file);
        }
    }

    private static function read(string $key): ?array
    {
        $file = self::path($key);
        if (!is_file($file)) {
            return null;
        }
        $raw = @file_get_contents($file);
        if ($raw === false) {
            return null;
        }
        $data = @unserialize($raw);
        return is_array($data) ? $data : null;
    }
}
