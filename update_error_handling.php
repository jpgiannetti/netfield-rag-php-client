<?php
/**
 * Script to update error handling in PHP client files
 * Adds error_code and error_data extraction to all GuzzleException catch blocks
 */

function updateFileErrorHandling(string $filePath): void
{
    $content = file_get_contents($filePath);

    // Pattern to match the old exception throwing style
    $oldPattern = '/(\s+)(}\s+catch\s+\(GuzzleException\s+\$e\)\s+\{\s*\n)' .
                  '(\s+\$errorMessage\s+=\s+\$this->extractErrorMessage\(\$e\);\s*\n)' .
                  '(\s+\$this->logger->error\([^;]+;\s*\n)' .
                  '(\s+throw\s+new\s+RagApiException\([^,]+,\s+\$e->getCode\(\),\s+\$e\);\s*\n)/';

    // New pattern with error_code and error_data
    $newReplacement = '$1$2' .
                     '$3' .
                     '$1            $errorData = $this->extractErrorData($e);' . "\n" .
                     '$1            $errorCode = $this->extractErrorCode($e);' . "\n" .
                     '$4' .  // Keep the logger line but we'll need to update it separately
                     '$5';

    $updatedContent = preg_replace($oldPattern, $newReplacement, $content);

    if ($updatedContent === null) {
        echo "ERROR: preg_replace failed for $filePath\n";
        return;
    }

    // Now update the throw statements to include error_code and error_data
    $throwPattern = '/throw\s+new\s+RagApiException\(([^,]+),\s+\$e->getCode\(\),\s+\$e\);/';
    $throwReplacement = 'throw new RagApiException($1, $e->getCode(), $e, null, $errorCode, $errorData);';

    $updatedContent = preg_replace($throwPattern, $throwReplacement, $updatedContent);

    if ($updatedContent === null) {
        echo "ERROR: preg_replace failed for throw statements in $filePath\n";
        return;
    }

    // Update logger calls to include error_code
    $loggerPattern = '/(\$this->logger->error\([^,]+,\s+\[[\'"]error[\'"]\s+=>\s+\$errorMessage)\]/';
    $loggerReplacement = '$1, \'error_code\' => $errorCode]';

    $updatedContent = preg_replace($loggerPattern, $loggerReplacement, $updatedContent);

    if ($updatedContent === $content) {
        echo "WARNING: No changes made to $filePath\n";
        return;
    }

    file_put_contents($filePath, $updatedContent);
    echo "SUCCESS: Updated $filePath\n";
}

$files = [
    __DIR__ . '/src/Client/RagClient.php',
    __DIR__ . '/src/Client/AdminClient.php',
    __DIR__ . '/src/Client/OrganizationClient.php',
];

foreach ($files as $file) {
    if (!file_exists($file)) {
        echo "ERROR: File not found: $file\n";
        continue;
    }
    updateFileErrorHandling($file);
}

echo "\nDone!\n";
