<?php

// Include all include files
require_once "./includes/autoload.php";

$sh->echo("Starting Dej...");

// Stop if root permissions not granted
if (!rootPermissions())
    return;

// If there are some screens running, prompt user
if (count(searchScreens()) > 0) {
    // Prompt user to stop started screens or not
    echo "Already running. Stop? [Y(es)/n(o)/c(ancel)] ";
    $cliInput = fopen("php://stdin", "r");
    // Analyze user input
    $response = strtolower(trim(fgetc($cliInput)));
    fclose($cliInput);

    // If user wants to cancel, cancel!
    if ($response === "c")
        $sh->exit("Canceled!");

    // Check if user wanted to stop or not, if yes, continue
    if ($response !== "n")
        $sh->echo(`php -f src/stop.php` . "Starting Dej...");
}

try {
    // Load configurations and validate it
    $config = (new DataValidation(new JSONFile("data.json", "config")))
        ->classValidation()
        ->typeValidation()
        ->returnData();
} catch (Throwable $e) {
    $sh->error($e);
}

// Perform comparison between files and backup files
$path = $config->save_to->path;
$backupDir = $config->backup->dir;
compare_files($path, $backupDir);

// Load executables
$php = $argv[1];
$screen = $config->executables->screen;
$tcpdump = $config->executables->tcpdump;
$logsDir = forceEndSlash($config->logs->path);

// Check for installed commands
$neededExecutables = [
    ["screen", $screen],
    ["tcpdump", $tcpdump]
];
foreach ($neededExecutables as $neededExecutable)
    if (!`which {$neededExecutable[1]}`)
        $sh->error("You must have {$neededExecutable[0]} command installed, i.e., the specified" .
            "executable file cannot be used ({$neededExecutable[1]}). Fix it by editing " .
            "executables field in config/data.json.");

// Names of directories and files
$sourceDir = "src";
$filenames = [
    "tcpdump",
    "sniffer",
    "backup"
];

// Run each file with a logger
foreach ($filenames as $filename) {
    // Check if logs were enabled for screen or not
    directory($logsDir);
    $filePath = $logsDir . $filename;
    $logPart = $config->logs->screen ? "-L -Logfile $filePath" : "";
    `$screen -S dej -d -m $logPart $php -f $sourceDir/$fname.php`;
}

sleep(1);
$screenCount = count(searchScreens());
if ($screenCount === 3)
    $sh->echo("Done!");
elseif ($screenCount < 3)
    $sh->error("Something went wrong. Try again!");
else
    $sh->warn("Too much instances are running.");
