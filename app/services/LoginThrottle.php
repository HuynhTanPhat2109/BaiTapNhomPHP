<?php

namespace App\Services;

/** Small single-server limiter. No usernames/passwords are written to disk. */
final class LoginThrottle
{
    public const LIMIT = 20;
    public const WINDOW = 600;
    private string $directory;

    public function __construct(?string $directory = null)
    {
        $this->directory = $directory ?? dirname(__DIR__, 2) . '/storage/temp/login-throttle';
    }

    /** Reserve an attempt atomically; return seconds to wait, or zero. */
    public function consume(string $address, ?int $now = null): int
    {
        $now = $now ?? time();
        if (!is_dir($this->directory) && !mkdir($this->directory, 0700, true) && !is_dir($this->directory)) {
            throw new \RuntimeException('Throttle storage unavailable.');
        }
        $file = fopen($this->directory . '/' . hash('sha256', $address) . '.json', 'c+');
        if ($file === false) {
            throw new \RuntimeException('Throttle storage unavailable.');
        }
        try {
            if (!flock($file, LOCK_EX)) {
                throw new \RuntimeException('Throttle lock unavailable.');
            }
            $state = json_decode(stream_get_contents($file), true);
            if (!is_array($state) || !isset($state['until'], $state['count'])
                || !is_int($state['until']) || !is_int($state['count']) || $state['until'] <= $now) {
                $state = ['until' => $now + self::WINDOW, 'count' => 0];
            }
            if ($state['count'] >= self::LIMIT) {
                return $state['until'] - $now;
            }
            $state['count']++;
            $json = json_encode($state, JSON_THROW_ON_ERROR);
            rewind($file);
            if (!ftruncate($file, 0) || fwrite($file, $json) !== strlen($json) || !fflush($file)) {
                throw new \RuntimeException('Throttle write failed.');
            }
            return 0;
        } finally {
            flock($file, LOCK_UN);
            fclose($file);
        }
    }
}
