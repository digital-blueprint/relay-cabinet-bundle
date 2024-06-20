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

$studentFirstNames = ['John', 'Jane', 'Michael', 'Emily', 'Joshua', 'Emma', 'Olivia', 'Lucas', 'Sophia', 'Liam', 'Ava', 'Charlotte', 'James', 'Mia', 'Henry', 'Amelia', 'Grace', 'Isabella', 'Lily', 'Daniel', 'Ella'];
$studentLastNames = ['Doe', 'Smith', 'Brown', 'Davis', 'Garcia', 'Johnson', 'Lee', 'Martinez', 'Robinson', 'Walker', 'Young', 'Hernandez', 'Lopez', 'Clark', 'King', 'Scott', 'Allen', 'Sanchez', 'Green', 'Adams', 'Roberts'];
$addresses = ['Maple Street', 'Oak Avenue', 'Pine Road', 'Birch Lane', 'Cedar Street', 'Spruce Road', 'Elm Street', 'Pine Lane', 'Walnut Street', 'Cherry Avenue', 'Magnolia Street', 'Cedar Road', 'Oak Lane', 'Cedar Lane', 'Birch Avenue', 'Cedar Lane', 'Oak Road'];
$cities = ['Beverly Hills', 'Atlanta', 'Chicago', 'Dallas', 'Seattle', 'New York', 'San Francisco', 'Phoenix'];
$zipCodes = ['90210', '30303', '60614', '75201', '98101', '10001', '94102', '85001'];
$countries = ['USA'];
$fileObjectTypes = ['fileCitizenshipCertificate', 'fileConversation', 'fileEmail', 'fileLetter', 'filePersonalLicense'];

$lines = [];

for ($i = 0; $i < 200; ++$i) {
    $type = $i % 2 === 0 ? 'student' : 'file';

    if ($type === 'student') {
        $firstName = getRandomElement($studentFirstNames);
        $lastName = getRandomElement($studentLastNames);
        $line = [
            'objectType' => 'student',
            'type' => 'student',
            'name' => "$firstName $lastName",
            'file-filename' => '',
            'file-mimetype' => '',
            'file-filesize' => 0,
            'student-firstname' => $firstName,
            'student-lastname' => $lastName,
            'student-birthday' => getRandomDate('2003-01-01', '2010-12-31'),
            'student-address' => getRandomElement($addresses),
            'student-zip' => getRandomElement($zipCodes),
            'student-city' => getRandomElement($cities),
            'student-country' => getRandomElement($countries),
        ];
    } else {
        $objectType = getRandomElement($fileObjectTypes);
        $line = [
            'objectType' => $objectType,
            'type' => 'file',
            'name' => ucfirst(str_replace('file', '', $objectType)),
            'file-filename' => strtolower(str_replace(' ', '_', ucfirst(str_replace('file', '', $objectType)))).'.pdf',
            'file-mimetype' => 'application/pdf',
            'file-filesize' => mt_rand(1024, 51200),
            'student-firstname' => '',
            'student-lastname' => '',
            'student-birthday' => '',
            'student-address' => '',
            'student-zip' => '',
            'student-city' => '',
            'student-country' => '',
        ];
    }

    $lines[] = json_encode($line);
}

file_put_contents('seed/documents.jsonl', implode("\n", $lines));

echo 'Generated 200 JSONL lines and saved to seed/documents.jsonl';
