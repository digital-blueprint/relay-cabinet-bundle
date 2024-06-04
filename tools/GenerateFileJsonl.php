#!/usr/bin/env php
<?php

declare(strict_types=1);
// Define possible filetypes
$filetypes = ['personalLicence', 'letter', 'email', 'citizenshipCertificate', 'conversation'];

// Function to generate random file size
function generateFileSize(): int
{
    return rand(1000, 1000000); // File size between 1KB and 1MB
}

// Open a file for writing
$file = fopen('seed/files.jsonl', 'w');

if ($file) {
    // Generate 100 JSON objects
    for ($i = 1; $i <= 100; ++$i) {
        $filetype = $filetypes[array_rand($filetypes)];
        $filename = "file_{$i}.txt";
        $filesize = generateFileSize();

        $jsonObject = [
            'filetype' => $filetype,
            'filename' => $filename,
            'filesize' => $filesize,
        ];

        // Write JSON object to file as a JSON Line
        fwrite($file, json_encode($jsonObject)."\n");
    }

    // Close the file
    fclose($file);
    echo 'JSON Lines file created successfully.';
} else {
    echo 'Unable to open file for writing.';
}
