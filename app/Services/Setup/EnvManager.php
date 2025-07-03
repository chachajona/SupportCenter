<?php

namespace App\Services\Setup;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;

class EnvManager
{
    /**
     * @var string
     */
    private $envPath;

    /**
     * @var bool
     */
    private $isTesting;

    public function __construct()
    {
        $this->isTesting = app()->environment('testing');
        $this->envPath = app()->environmentFilePath();
    }

    /**
     * Get the path to the .env file.
     */
    public function getEnvPath(): string
    {
        return $this->envPath;
    }

    /**
     * Save database credentials to the .env file or in-memory config.
     */
    public function saveDatabaseCredentials(array $credentials): void
    {
        foreach ($credentials as $key => $value) {
            $configKey = 'database.connections.mysql.'.strtolower($key);
            if ($this->isTesting) {
                Config::set($configKey, $value);
            } else {
                $this->updateEnvFile('DB_'.strtoupper($key), $value);
            }
        }

        if ($this->isTesting) {
            // Invalidate the old database connection
            app('db')->purge('mysql');
        }
    }

    /**
     * Save other settings to the .env file or in-memory config.
     */
    public function saveAppSettings(array $settings): void
    {
        foreach ($settings as $key => $value) {
            if ($this->isTesting) {
                $configKey = strtolower(str_replace('_', '.', $key));
                Config::set($configKey, $value);
            } else {
                $this->updateEnvFile(strtoupper($key), $value);
            }
        }
    }

    /**
     * Update a key in the .env file.
     *
     * @param  mixed  $value
     */
    private function updateEnvFile(string $key, $value): void
    {
        if (! File::exists($this->envPath)) {
            return;
        }

        $content = File::get($this->envPath);
        $value = is_string($value) && str_contains($value, ' ') ? '"'.$value.'"' : $value;

        $escapedKey = preg_quote($key, '/');

        $pattern = "/^{$escapedKey}=.*/m";

        if (preg_match($pattern, $content)) {
            $content = preg_replace($pattern, "{$key}={$value}", $content);
        } else {
            $content .= "\n{$key}={$value}";
        }

        File::put($this->envPath, $content);
    }
}
