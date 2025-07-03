<?php

namespace App\Services\Setup;

class EnvironmentCheckService
{
    /**
     * @var array
     */
    protected $results = [];

    /**
     * @var array
     */
    protected $errors = [];

    /**
     * Run all environment checks.
     */
    public function run(): array
    {
        $this->checkPhpVersion();
        $this->checkExtensions();
        $this->checkDirectoryPermissions();

        return [
            'results' => $this->results,
            'errors' => count($this->errors),
            'error_messages' => $this->errors,
        ];
    }

    /**
     * Check the PHP version.
     */
    protected function checkPhpVersion(): void
    {
        $requiredVersion = '8.2';
        $currentVersion = PHP_VERSION;

        $isCompatible = version_compare($currentVersion, $requiredVersion, '>=');
        $this->addResult(
            'PHP Version',
            $isCompatible,
            "PHP version must be {$requiredVersion} or higher. Your version is {$currentVersion}.",
            "PHP version is {$currentVersion} (Required: >= {$requiredVersion})"
        );
    }

    /**
     * Check for required PHP extensions.
     */
    protected function checkExtensions(): void
    {
        $requiredExtensions = [
            'BCMath',
            'Ctype',
            'Fileinfo',
            'JSON',
            'Mbstring',
            'OpenSSL',
            'PDO',
            'Tokenizer',
            'XML',
            'gd',
            'curl',
        ];

        foreach ($requiredExtensions as $extension) {
            $isLoaded = extension_loaded($extension);
            $this->addResult(
                "PHP Extension: {$extension}",
                $isLoaded,
                "The {$extension} extension is required.",
                "{$extension} extension is loaded."
            );
        }
    }

    /**
     * Check directory permissions.
     */
    protected function checkDirectoryPermissions(): void
    {
        $directories = [
            storage_path(),
            storage_path('framework/'),
            storage_path('logs/'),
            app()->bootstrapPath('cache/'),
        ];

        foreach ($directories as $directory) {
            $isWritable = is_writable($directory);
            $this->addResult(
                'Directory Permissions: '.str_replace(base_path().'/', '', $directory),
                $isWritable,
                "The {$directory} directory must be writable.",
                'The '.str_replace(base_path().'/', '', $directory).' directory is writable.'
            );
        }
    }

    /**
     * Add a result to the results array.
     */
    protected function addResult(string $checkName, bool $success, string $errorMessage, string $successMessage): void
    {
        if (! $success) {
            $this->errors[] = $errorMessage;
        }

        $this->results[] = [
            'check' => $checkName,
            'success' => $success,
            'message' => $success ? $successMessage : $errorMessage,
        ];
    }
}
