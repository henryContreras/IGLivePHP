<?php
/** @noinspection PhpUndefinedConstantInspection */
/** @noinspection PhpComposerExtensionStubsInspection */

set_time_limit(0);
date_default_timezone_set('America/New_York');
if (php_sapi_name() !== "cli") {
    die("This script may not be run on a website!");
}

if (!defined('PHP_MAJOR_VERSION') || PHP_MAJOR_VERSION < 7) {
    print("This script requires PHP version 7 or higher! Please update your php installation before attempting to run this script again!");
    exit();
}

//Argument Processing
$helpData = [];
$helpData = registerArgument($helpData, $argv, "help", "Displays this message.", "h", "help");
$helpData = registerArgument($helpData, $argv, "promptLogin", "Ignores config.php and prompts you for your username and password.", "p", "prompt-login");

require_once 'utils.php';

if (help) {
    Utils::log("Command Line Arguments:");
    foreach ($helpData as $option) {
        $dOption = json_decode($option, true);
        Utils::log($dOption['tacks']['mini'] . ($dOption['tacks']['full'] !== null ? " (" . $dOption['tacks']['full'] . "): " : ": ") . $dOption['description']);
    }
    exit();
}

Utils::existsOrError(__DIR__ . '/vendor/autoload.php', "Instagram API Files");
Utils::existsOrError('config.php', "Username & Password Storage");

require_once __DIR__ . '/vendor/autoload.php'; //Composer
require_once 'config.php';

$username = IG_USERNAME;
$password = IG_PASS;
if (promptLogin) {
    Utils::log("Please enter your credentials...");
    print "Username: ";
    $usernameHandle = fopen("php://stdin", "r");
    $username = trim(fgets($usernameHandle));
    fclose($usernameHandle);
    print "Password: ";
    $passwordHandle = fopen("php://stdin", "r");
    $password = trim(fgets($passwordHandle));
    fclose($passwordHandle);
}

if ($username == "USERNAME" || $password == "PASSWORD") {
    Utils::log("Default Username or Password have not been changed! Exiting...");
    exit();
}

//Login to Instagram
Utils::log("Logging into Instagram! Please wait as this can take up-to two minutes...");
$ig = Utils::loginFlow($username, $password);

Utils::log("Fetching Previous Livestreams...");
$storyFeed = $ig->story->getUserStoryFeed($ig->account_id);

if ($storyFeed->getPostLiveItem() === null || $storyFeed->getPostLiveItem()->getBroadcasts() === null) {
    Utils::log("You do not have any saved live broadcasts :(. If you recently saved one, and you're getting this message, check back in a few minutes.");
    exit();
}

Utils::log("Please select the livestream you want information about:");
$postLiveIndex = 0;
foreach ($storyFeed->getPostLiveItem()->getBroadcasts() as $broadcast) {
    Utils::log("[$postLiveIndex] - Published At: " . date("Y-m-d H:i:s", substr($broadcast->getPublishedTime(), 0, 10)));
    $postLiveIndex++;
}
print "Type the Livestream ID from the above selection...\n> ";
$handle = fopen("php://stdin", "r");
$postLiveIndex = trim(fgets($handle));
fclose($handle);
@$selectedBroadcast = $storyFeed->getPostLiveItem()->getBroadcasts()[$postLiveIndex];
if ($selectedBroadcast === null) {
    Utils::log("Invalid Livestream ID! Exiting...");
    exit();
}
Utils::log("\nSelected Broadcast ID: " . $selectedBroadcast->getId());

Utils::log("\nWhat would you selected stream? Type one of the following commands:\ninfo - Displays info about the broadcast.\ndelete - Removes the broadcast from public view.");
$handle = fopen("php://stdin", "r");
print "> ";
$cmd = trim(fgets($handle));
fclose($handle);

switch ($cmd) {
    case 'info':
        Utils::log("\nID: " . $selectedBroadcast->getId());
        Utils::log("Published Date: " . date("Y-m-d H:i:s", substr($selectedBroadcast->getPublishedTime(), 0, 10)));
        Utils::log("Expiry Date: " . date("Y-m-d H:i:s", substr($selectedBroadcast->getExpireAt(), 0, 10)));
        Utils::log("Unique Viewers: " . $selectedBroadcast->getTotalUniqueViewerCount());
        Utils::log("Cover Frame: " . $selectedBroadcast->getCoverFrameUrl());
        Utils::log("Playback URL: " . $selectedBroadcast->getRtmpPlaybackUrl());
        break;
    case 'delete':
        Utils::log("\nRemoving Livestream from your Story...");
        $ig->live->deletePostLive($selectedBroadcast->getId());
        Utils::log("Removed Livestream from your Story!");
        break;
    default:
        Utils::log("\nYou entered an unknown command! Exiting...");
        break;
}

/**
 * Registers a command line argument to a global variable.
 * @param array $helpData The array which holds the command data for the help menu.
 * @param array $argv The array of arguments passed to the script.
 * @param string $name The name to be used in the global variable.
 * @param string $description The description of the argument to be used in the help menu.
 * @param string $tack The mini-tack argument name.
 * @param string|null $fullTack The full-tack argument name.
 * @return array The array of help data with the new argument.
 */
function registerArgument(array $helpData, array $argv, string $name, string $description, string $tack, string $fullTack = null): array
{
    if ($fullTack !== null) {
        $fullTack = '--' . $fullTack;
    }
    define($name, in_array('-' . $tack, $argv) || in_array($fullTack, $argv));
    array_push($helpData, json_encode([
        'name' => $name,
        'description' => $description,
        'tacks' => [
            'mini' => '-' . $tack,
            'full' => $fullTack
        ]
    ]));
    return $helpData;
}