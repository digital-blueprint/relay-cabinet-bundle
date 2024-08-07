#!/usr/bin/env php
<?php

declare(strict_types=1);
function getRandomDate($startDate, $endDate): string
{
    $timestamp = mt_rand(strtotime($startDate), strtotime($endDate));

    return date('Y-m-d', $timestamp);
}

function getRandomElement($array)
{
    return $array[array_rand($array)];
}

$personFirstNames = ['John', 'Jane', 'Michael', 'Emily', 'Joshua', 'Emma', 'Olivia', 'Lucas', 'Sophia', 'Liam', 'Ava', 'Charlotte', 'James', 'Mia', 'Henry', 'Amelia', 'Grace', 'Isabella', 'Lily', 'Daniel', 'Ella'];
$personLastNames = ['Doe', 'Smith', 'Brown', 'Davis', 'Garcia', 'Johnson', 'Lee', 'Martinez', 'Robinson', 'Walker', 'Young', 'Hernandez', 'Lopez', 'Clark', 'King', 'Scott', 'Allen', 'Sanchez', 'Green', 'Adams', 'Roberts'];
$addresses = ['Maple Street', 'Oak Avenue', 'Pine Road', 'Birch Lane', 'Cedar Street', 'Spruce Road', 'Elm Street', 'Pine Lane', 'Walnut Street', 'Cherry Avenue', 'Magnolia Street', 'Cedar Road', 'Oak Lane', 'Cedar Lane', 'Birch Avenue', 'Cedar Lane', 'Oak Road'];
$cities = ['Beverly Hills', 'Atlanta', 'Chicago', 'Dallas', 'Seattle', 'New York', 'San Francisco', 'Phoenix'];
$zipCodes = ['90210', '30303', '60614', '75201', '98101', '10001', '94102', '85001'];
$countries = ['USA'];
$fileObjectTypes = ['fileCitizenshipCertificate', 'fileCommunication', 'fileEmail', 'fileLetter', 'filePersonalLicense'];

$lines = [];

for ($i = 0; $i < 200; ++$i) {
    $type = $i % 2 === 0 ? 'person' : 'file';

    if ($type === 'person') {
        $firstName = getRandomElement($personFirstNames);
        $lastName = getRandomElement($personLastNames);
        $line = [
            'objectType' => 'person',
            'type' => 'person',
            'name' => "$firstName $lastName",
            'file-filename' => '',
            'file-mimetype' => '',
            'file-filesize' => 0,
            'person-firstname' => $firstName,
            'person-lastname' => $lastName,
            'person-birthday' => getRandomDate('2003-01-01', '2010-12-31'),
            'person-address' => getRandomElement($addresses),
            'person-zip' => getRandomElement($zipCodes),
            'person-city' => getRandomElement($cities),
            'person-country' => getRandomElement($countries),
        ];
    } else {
        $objectType = getRandomElement($fileObjectTypes);
        $name = ucfirst(str_replace('file', '', $objectType)).' '.str_pad((string) mt_rand(1, 999), 3, '0', STR_PAD_LEFT);

        $line = [
            'objectType' => $objectType,
            'type' => 'file',
            'name' => $name,
            'file-filename' => strtolower(str_replace(' ', '_', $name)).'.pdf',
            'file-mimetype' => 'application/pdf',
            'file-filesize' => mt_rand(1024, 51200),
            'person-firstname' => '',
            'person-lastname' => '',
            'person-birthday' => '',
            'person-address' => '',
            'person-zip' => '',
            'person-city' => '',
            'person-country' => '',
        ];
    }

    $lines[] = json_encode($line);
}

file_put_contents('seed/documents.jsonl', implode("\n", $lines));

echo 'Generated 500 JSONL lines and saved to seed/documents.jsonl';
