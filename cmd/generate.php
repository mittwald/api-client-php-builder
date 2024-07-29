#!/usr/bin/env php
<?php
require_once __DIR__ . "/../vendor/autoload.php";

$app = new \Symfony\Component\Console\Application("mittwald-api-generator", "1.0.0");
$app->add(new \Mittwald\ApiToolsPHP\Command\GenerateCommand());
$app->add(new \Mittwald\ApiToolsPHP\Command\GenerateCommitMessage());
$app->add(new \Mittwald\ApiToolsPHP\Command\GenerateReleaseNotes());
$app->add(new \Mittwald\ApiToolsPHP\Command\VersionCommand());
$app->run();
