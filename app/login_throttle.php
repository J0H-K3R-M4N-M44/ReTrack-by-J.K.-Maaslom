<?php
declare(strict_types=1);

// Reserve attempts before checking passwords. Files live outside the public site.
// This small local limiter is for a single server, not a distributed deployment.
function reserveLoginAttempt(string $email, string $address): bool
{
    $folder = sys_get_temp_dir() . '/repairtrack-login-' . hash('sha256', __DIR__);
    if (!is_dir($folder) && !mkdir($folder, 0700, true) && !is_dir($folder)) {
        throw new RuntimeException('Cannot create login attempt storage.');
    }

    foreach (
        ['ip:' . $address => 20, 'email:' . strtolower(trim($email)) => 5]
        as $identity => $limit
    ) {
        $file = fopen($folder . '/' . hash('sha256', $identity) . '.json', 'c+');
        if (!$file) {
            throw new RuntimeException('Cannot open login attempt storage.');
        }
        try {
            if (!flock($file, LOCK_EX)) {
                throw new RuntimeException('Cannot lock login attempt storage.');
            }
            $attempt = json_decode(stream_get_contents($file), true);
            if (!is_array($attempt) || ($attempt['until'] ?? 0) <= time()) {
                $attempt = ['count' => 0, 'until' => time() + 900];
            }
            if ($attempt['count'] >= $limit) {
                return false;
            }
            $attempt['count']++;
            rewind($file);
            if (
                !ftruncate($file, 0) ||
                fwrite($file, json_encode($attempt, JSON_THROW_ON_ERROR)) === false ||
                !fflush($file)
            ) {
                throw new RuntimeException('Cannot save login attempt.');
            }
        } finally {
            flock($file, LOCK_UN);
            fclose($file);
        }
    }
    return true;
}
