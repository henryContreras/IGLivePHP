<?php
/** @noinspection PhpComposerExtensionStubsInspection */
/** @noinspection PhpUndefinedConstantInspection */

set_time_limit(0);
date_default_timezone_set('America/New_York');
if (php_sapi_name() !== "cli") {
    die("This script may not be run on a website!");
}

if (!defined('PHP_MAJOR_VERSION') || PHP_MAJOR_VERSION < 7) {
    print("This script requires PHP version 7 or higher! Please update your php installation before attempting to run this script again!");
    exit(1);
}

//Argument Processing
$helpData = [];
$helpData = registerArgument($helpData, $argv, "help", "Help", "Displays this message.", "h", "help");
$helpData = registerArgument($helpData, $argv, "bypassCheck", "Bypass OS Check", "Bypasses the operating system check. Please do not use this if don't know what you're doing!", "b", "bypass-check");
$helpData = registerArgument($helpData, $argv, "forceLegacy", "Force Legacy Mode", "Forces legacy mode for Windows & Mac users.", "l", "force-legacy");
$helpData = registerArgument($helpData, $argv, "bypassCutoff", "Bypass Cutoff", "Bypasses stream cutoff after one hour. Please do not use this if you are not verified!", "-bypass-cutoff");
$helpData = registerArgument($helpData, $argv, "infiniteStream", "Infinite Stream", "Automatically starts a new stream after the hour cutoff.", "i", "infinite-stream");
$helpData = registerArgument($helpData, $argv, "autoArchive", "Auto Archive", "Automatically archives a live stream after it ends.", "a", "auto-archive");
$helpData = registerArgument($helpData, $argv, "autoDiscard", "Auto Discard", "Automatically discards a live stream after it ends.", "d", "auto-discard");
$helpData = registerArgument($helpData, $argv, "logCommentOutput", "Log Comment Output", "Logs comment and like output into a text file.", "o", "comment-output");
$helpData = registerArgument($helpData, $argv, "obsAutomationAccept", "Accept OBS Automation Prompt", "Automatically accepts the OBS prompt.", "-obs");
$helpData = registerArgument($helpData, $argv, "obsNoStream", "Disable OBS Auto-Launch", "Disables automatic stream start in OBS.", "-obs-no-stream");
$helpData = registerArgument($helpData, $argv, "obsNoIni", "Disable OBS Auto-Settings", "Disable automatic resolution changes and only modifies the stream url/key.", "-obs-only-key");
$helpData = registerArgument($helpData, $argv, "disableObsAutomation", "Disable OBS Automation", "Disables OBS automation and subsequently disables the path check.", "-no-obs");
$helpData = registerArgument($helpData, $argv, "startDisableComments", "Disable Comments", "Automatically disables commands when the stream starts.", "-dcomments");
$helpData = registerArgument($helpData, $argv, "thisIsAPlaceholder", "Limit Stream Time", "Sets the amount of time to limit the stream to in seconds. (Example: --stream-sec=60).", "-stream-sec");
$helpData = registerArgument($helpData, $argv, "thisIsAPlaceholder1", "Auto Pin Comment", "Sets a comment to automatically pin when the live stream starts. Note: Use underscores for spaces. (Example: --auto-pin=Hello_World!).", "-auto-pin");
$helpData = registerArgument($helpData, $argv, "forceSlobs", "Force StreamLabs-OBS", "Forces OBS Integration to prefer Streamlabs OBS over normal OBS.", "-streamlabs-obs");
$helpData = registerArgument($helpData, $argv, "promptLogin", "Prompt Username & Password", "Ignores config.php and prompts you for your username and password.", "p", "prompt-login");
$helpData = registerArgument($helpData, $argv, "bypassPause", "Bypass Pause", "Dangerously bypasses pause before starting the livestream.", "-bypass-pause");
$helpData = registerArgument($helpData, $argv, "noBackup", "Disable Stream Recovery", "Disables stream recovery for crashes or accidental window closes.", "-no-recovery");
$helpData = registerArgument($helpData, $argv, "fightCopyright", "Bypass Copyright Takedowns", "Acknowledges Instagram copyright takedowns but lets you continue streaming. This is at your own risk although it should be safe.", "-auto-policy");
$helpData = registerArgument($helpData, $argv, "experimentalQuestion", "Enable Stream Questions", "Experimental: Attempts to allow viewers to ask questions while streaming.", "q", "stream-ama");
$helpData = registerArgument($helpData, $argv, "debugMode", "Enable Debug Mode", "Displays all requests being sent to Instagram.", "-debug");
$helpData = registerArgument($helpData, $argv, "dump", "Trigger Dump", "Forces an error dump for debug purposes.", "-dump");
$helpData = registerArgument($helpData, $argv, "dumpVersion", "", "Dumps current release version.", "-dumpVersion");
$helpData = registerArgument($helpData, $argv, "dumpFlavor", "", "Dumps current release flavor.", "-dumpFlavor");
$helpData = registerArgument($helpData, $argv, "dumpCli", "", "Dumps current command-line arguments into json.", "-dumpCli");

if (dumpCli) {
    print json_encode($helpData);
    exit(0);
}

$streamTotalSec = 0;
$autoPin = null;

foreach ($argv as $curArg) {
    if (strpos($curArg, '--stream-sec=') !== false) {
        $streamTotalSec = (int)str_replace('--stream-sec=', '', $curArg);
    }
    if (strpos($curArg, '--auto-pin=') !== false) {
        $autoPin = str_replace('_', ' ', str_replace('--auto-pin=', '', $curArg));
    }
}

//Load Config & Utils
require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/config.php';

define("scriptVersion", "1.7.2");
define("scriptVersionCode", "50");
define("scriptFlavor", "stable");

if (dumpVersion) {
    Utils::log(scriptVersion);
    exit(1);
}

if (dumpFlavor) {
    Utils::log(scriptFlavor);
    exit(1);
}

if (dump) {
    Utils::dump();
    exit(1);
}

//Check for required files
Utils::existsOrError(__DIR__ . '/vendor/autoload.php', "Instagram API Files");
Utils::existsOrError(__DIR__ . '/obs.php', "OBS Integration");
Utils::existsOrError(__DIR__ . '/config.php', "Username & Password Storage");

Utils::log("Loading InstagramLive-PHP v" . scriptVersion . "...");

if (Utils::checkForUpdate(scriptVersionCode, scriptFlavor)) {
    if (UPDATE_AUTO) {
        Utils::log("Update: A new version of InstagramLive-PHP has been detected and will be installed momentarily...");
        exec("\"" . PHP_BINARY . "\" update.php");
        Utils::log("Update: Finished! Exiting the script, please re-run the script now.");
        exit(1);
    }
    Utils::log("\nUpdate: A new update is available, run the `update.php` script to fetch it!\nProtip: You can set 'UPDATE_AUTO' to true in the config.php to have updates automatically install!\n");
}

if (!Utils::isApiDevMaster()) {
    Utils::log("Update: Outdated Instagram-API version detected, attempting to fix this for you. This may take a while...");
    exec("\"" . PHP_BINARY . "\" update.php");
    Utils::log("Update: Finished! Exiting the script, please re-run the script now.");
    exit(1);
}

if (help) {
    Utils::log("Command Line Arguments:");
    foreach ($helpData as $option) {
        $dOption = json_decode($option, true);
        Utils::log($dOption['tacks']['mini'] . ($dOption['tacks']['full'] !== null ? " (" . $dOption['tacks']['full'] . "): " : ": ") . $dOption['description']);
    }
    exit(1);
}

//Load Classes
require_once __DIR__ . '/vendor/autoload.php'; //Composer
require_once __DIR__ . '/obs.php'; //OBS Utils

use InstagramAPI\Instagram;
use InstagramAPI\Request\Live;
use InstagramAPI\Response\FinalViewerListResponse;
use InstagramAPI\Response\GenericResponse;
use InstagramAPI\Response\Model\Comment;

//Run the script and spawn a new console window if applicable.
main(true, new ObsHelper(!obsNoStream, disableObsAutomation, forceSlobs, (!obsNoIni && OBS_MODIFY_SETTINGS)), $streamTotalSec, $autoPin, $argv);

function main($console, ObsHelper $helper, $streamTotalSec, $autoPin, array $args)
{
    $username = trim(IG_USERNAME);
    $password = trim(IG_PASS);
    if (promptLogin) {
        Utils::log("Please enter your credentials...");
        $username = Utils::promptInput("Username:");
        $password = Utils::promptInput("Password:");
    }

    if ($username == "USERNAME" || $password == "PASSWORD") {
        Utils::log("Default Username or Password have not been changed! Exiting...\nProtip: You can run the script like 'php goLive.php -p' to avoid using the config.php for credentials!");
        exit(1);
    }

//Login to Instagram
    Utils::log("Logging into Instagram! Please wait as this can take up-to two minutes...");
    $ig = Utils::loginFlow($username, $password, debugMode);

//Block Responsible for Creating the Livestream.
    try {
        if (!$ig->isMaybeLoggedIn) {
            Utils::log("Error While Logging in to Instagram: isMaybeLoggedIn fail!");
            Utils::dump();
            exit(1);
        }
        Utils::log("Logged In!");

        try {
            if (Utils::isRecovery() && $ig->live->getInfo(Utils::getRecovery()['broadcastId'])->getBroadcastStatus() === 'stopped') {
                Utils::log("Detected recovery was outdated, deleting recovery...");
                Utils::deleteRecovery();
                Utils::log("Deleted Outdated Recovery!");
            }
        } catch (Exception $e) {
            Utils::log("Detected recovery was outdated, deleting recovery...");
            Utils::deleteRecovery();
            Utils::log("Deleted Outdated Recovery!");
        }

        $obsAutomation = true;
        if (!Utils::isRecovery()) {
            Utils::log("Creating Livestream...");

            $stream = $ig->live->create(OBS_X, OBS_Y);
            $broadcastId = $stream->getBroadcastId();

            if (!ANALYTICS_OPT_OUT) {
                Utils::analytics("live", scriptVersion, scriptFlavor, PHP_OS, count($args));
            }

            // Switch from RTMPS to RTMP upload URL, since RTMPS doesn't work well.
            $streamUploadUrl = $stream->getUploadUrl();

            //Grab the stream url as well as the stream key.
            $split = preg_split("[" . $broadcastId . "]", $streamUploadUrl);

            $streamUrl = trim($split[0]);
            $streamKey = trim($broadcastId . $split[1]);
        } else {
            Utils::log("Recovery Detected, Restarting Stream...");
            $recoveryData = Utils::getRecovery();
            $broadcastId = $recoveryData['broadcastId'];
            $streamUrl = $recoveryData['streamUrl'];
            $streamKey = $recoveryData['streamKey'];
            $obsAutomation = (bool)$recoveryData['obs'];
            $helper = unserialize($recoveryData['obsObject']);
        }

        if (!Utils::isRecovery()) {
            if ($helper->obs_path === null) {
                Utils::log("OBS Integration: OBS was not detected, disabling!" . (!Utils::isWindows() ? " Please note macOS is not supported!" : " Please make a ticket on GitHub if you have OBS installed."));
                $obsAutomation = false;
            } else {
                if (!obsAutomationAccept) {
                    Utils::log("OBS Integration: Would you like the script to automatically start streaming to OBS? Type \"yes\" or press enter to ignore.\nProtip: You can run the script like 'php goLive.php --obs' to automatically accept this prompt or 'php goLive.php --no-obs' to automatically reject this.");
                    if (Utils::promptInput() !== "yes") {
                        $obsAutomation = false;
                    }
                }
            }
        }

        Utils::log("**Please** update your current Stream URL if you have one in your current streaming program. A recent update has made the old url not work so please use the one below!");
        Utils::log("================================ Stream URL ================================\n" . $streamUrl . "\n================================ Stream URL ================================");
        Utils::log("======================== Current Stream Key ========================\n" . $streamKey . "\n======================== Current Stream Key ========================\n");
        Utils::log("**Please** update your current Stream URL if you have one in your current streaming program. A recent update has made the old url not work so please use the one below!");

        if (!Utils::isRecovery()) {
            if (!$obsAutomation) {
                if (Utils::isWindows()) {
                    shell_exec("echo " . Utils::sanitizeStreamKey($streamKey) . " | clip");
                    Utils::log("Windows: Your stream key has been pre-copied to your clipboard.");
                }
            } else {
                if ($helper->isObsRunning()) {
                    Utils::log("OBS Integration: Killing OBS...");
                    $helper->killOBS();
                }
                if (!$helper->attempted_settings_save) {
                    Utils::log("OBS Integration: Backing-up your old OBS basic.ini...");
                    $helper->saveSettingsState();
                }
                Utils::log("OBS Integration: Loading basic.ini with optimal OBS settings...");
                $helper->updateSettingsState();
                if (!$helper->attempted_service_save) {
                    Utils::log("OBS Integration: Backing-up your old OBS service.json...");
                    $helper->saveServiceState();
                }
                Utils::log("OBS Integration: Populating service.json with new stream url & key.");
                $helper->setServiceState($streamUrl, $streamKey);
                if (!$helper->slobsPresent) {
                    Utils::log("OBS Integration: Re-launching OBS...");
                    $helper->spawnOBS();
                    Utils::log("OBS Integration: Waiting up to 15 seconds for OBS...");
                    if ($helper->waitForOBS()) {
                        sleep(1);
                        Utils::log("OBS Integration: OBS Launched Successfully! Starting Stream...");
                    } else {
                        Utils::log("OBS Integration: OBS was not detected! Press enter once you confirm OBS is streaming...");
                        if (!bypassPause) {
                            Utils::promptInput("");
                        }
                    }
                }
            }
        }

        if ((!$obsAutomation || obsNoStream || $helper->slobsPresent) && !Utils::isRecovery()) {
            Utils::log("Please " . ($helper->slobsPresent ? "launch Streamlabs OBS and " : " ") . "start streaming to the url and key above! Once you are live, please press enter!");
            if (!bypassPause) {
                Utils::promptInput("");
            }
        }

        if (!Utils::isRecovery()) {
            Utils::log("Starting Stream...");
            $ig->live->start($broadcastId);
            if (experimentalQuestion && !$ig->isExperimentEnabled('ig_android_live_qa_broadcaster_v1_universe', 'is_enabled')) {
                try {
                    $ig->request("live/{$broadcastId}/question_status/")
                        ->setSignedPost(false)
                        ->addPost('_csrftoken', $ig->client->getToken())
                        ->addPost('_uuid', $ig->uuid)
                        ->addPost('allow_question_submission', true)
                        ->getResponse(new GenericResponse());
                    Utils::log("Successfully enabled experimental viewer AMA!");
                } catch (Exception $e) {
                    Utils::log("Unable to enable experimental viewer AMA!");
                }
            }
        }

        if ($autoPin !== null) {
            $ig->live->pinComment($broadcastId, $ig->live->comment($broadcastId, $autoPin)->getComment()->getPk());
            Utils::log("Automatically Pinned a Comment!");
        }

        if (startDisableComments) {
            $ig->live->disableComments($broadcastId);
            Utils::log("Automatically Disabled Comments!");
        }

        if ((Utils::isWindows() || Utils::isMac() || bypassCheck) && !forceLegacy) {
            Utils::log("Command Line: Windows/macOS Detected! A new console will open for command input and this will become command/like output.");
            $startCommentTs = 0;
            $startLikeTs = 0;
            $startingQuestion = -1;
            $startingTime = -1;
            if (Utils::isRecovery()) {
                $recoveryData = Utils::getRecovery();
                $startCommentTs = $recoveryData['lastCommentTs'];
                $startLikeTs = $recoveryData['lastLikeTs'];
                $startingQuestion = $recoveryData['lastQuestion'];
                $startingTime = $recoveryData['startTime'];
            }
            beginListener($ig, $broadcastId, $streamUrl, $streamKey, $console, $obsAutomation, $helper, $streamTotalSec, $autoPin, $args, $startCommentTs, $startLikeTs, $startingQuestion, $startingTime);
        } else {
            Utils::log("Command Line: Linux Detected! The script has entered legacy mode. Please use Windows or macOS for all the latest features.");
            newCommand($ig->live, $broadcastId, $streamUrl, $streamKey, $obsAutomation, $helper);
        }

        Utils::log("Unable to start command lines, attempting clean up!");
        parseFinalViewers($ig->live->getFinalViewerList($broadcastId));
        $ig->live->end($broadcastId);
        Utils::dump();
        exit(1);
    } catch (Exception $e) {
        echo 'Error While Creating Livestream: ' . $e->getMessage() . "\n";
        Utils::dump($e->getMessage());
        exit(1);
    }
}

function addLike(string $username)
{
    $cmt = "$username has liked the stream!";
    Utils::log($cmt);
    if (logCommentOutput) {
        Utils::logOutput($cmt);
    }
}

function addComment(Comment $comment, bool $system = false)
{
    $cmt = ($system ? "" : ("Comment [ID " . $comment->getPk() . "] @" . $comment->getUser()->getUsername() . ": ")) . $comment->getText();
    Utils::log($cmt);
    if (logCommentOutput) {
        Utils::logOutput($cmt);
    }
}

/**
 * @param FinalViewerListResponse $finalResponse
 */
function parseFinalViewers($finalResponse)
{
    $finalViewers = '';
    foreach ($finalResponse->getUsers() as $user) {
        $finalViewers = $finalViewers . '@' . $user->getUsername() . ', ';
    }
    $finalViewers = rtrim($finalViewers, " ,");

    if ($finalResponse->getTotalUniqueViewerCount() > 0) {
        Utils::log($finalResponse->getTotalUniqueViewerCount() . " Final Viewer(s).");
        Utils::log("Top Viewers: $finalViewers");
    } else {
        Utils::log("Your stream had no viewers :(");
    }


    if (logCommentOutput) {
        Utils::logOutput($finalResponse->getTotalUniqueViewerCount() . " Final Viewer(s): $finalViewers");
    }
}

function beginListener(Instagram $ig, $broadcastId, $streamUrl, $streamKey, $console, bool $obsAuto, ObsHelper $helper, int $streamTotalSec, $autoPin, array $args, int $startCommentTs = 0, int $startLikeTs = 0, int $startingQuestion = -1, int $startingTime = -1)
{
    if (bypassCheck && !Utils::isMac() && !Utils::isWindows()) {
        Utils::log("Command Line: You are forcing the new command line. This is unsupported and may result in issues.");
        Utils::log("Command Line: To start the new command line, please run the commandLine.php script.");
    } else {
        if ($console) {
            if (Utils::isWindows()) {
                pclose(popen("start \"InstagramLive-PHP: Command Line\" \"" . PHP_BINARY . "\" commandLine.php" . (autoArchive === true ? " -a" : "") . (autoDiscard === true ? " -d" : ""), "r"));
            } elseif (Utils::isMac()) {
                pclose(popen("osascript -e 'tell application \"Terminal\" to do script \"" . PHP_BINARY . " " . __DIR__ . "/commandLine.php" . (autoArchive === true ? " -a" : "") . (autoDiscard === true ? " -d" : "") . "\"'", "r"));
            }
        }
    }
    @cli_set_process_title("InstagramLive-PHP: Live Chat & Likes");
    $broadcastStatus = 'Unknown';
    $topLiveEligible = 0;
    $viewerCount = 0;
    $totalViewerCount = 0;
    $lastCommentTs = $startCommentTs;
    $lastLikeTs = $startLikeTs;
    $lastQuestion = $startingQuestion;
    $lastCommentPin = -1;
    $lastCommentPinHandle = '';
    $lastCommentPinText = '';
    $exit = false;
    $startTime = ($startingTime === -1 ? time() : $startingTime);
    $userCache = array();

    @unlink(__DIR__ . '/request');

    if (logCommentOutput) {
        Utils::logOutput(PHP_EOL . "--- New Session At Epoch: " . time() . " ---" . PHP_EOL);
    }

    do {
        /** @noinspection PhpComposerExtensionStubsInspection */

        //Check for commands
        $request = json_decode(@file_get_contents(__DIR__ . '/request'), true);
        if (!empty($request)) {
            try {
                $cmd = $request['cmd'];
                $values = $request['values'];
                switch ($cmd) {

                    case 'ecomments':
                        {
                            $ig->live->enableComments($broadcastId);
                            Utils::log("Enabled Comments!");
                            break;
                        }
                    case 'dcomments':
                        {
                            $ig->live->disableComments($broadcastId);
                            Utils::log("Disabled Comments!");
                            break;
                        }
                    case 'end':
                        {
                            if ($obsAuto) {
                                Utils::log("OBS Integration: Killing OBS...");
                                $helper->killOBS();
                                Utils::log("OBS Integration: Restoring old basic.ini...");
                                $helper->resetSettingsState();
                                Utils::log("OBS Integration: Restoring old service.json...");
                                $helper->resetServiceState();
                            }
                            $archived = $values[0];
                            Utils::log("Wrapping up and exiting...");
                            //Needs this to retain, I guess?
                            parseFinalViewers($ig->live->getFinalViewerList($broadcastId));
                            $ig->live->end($broadcastId);
                            if ($archived == 'yes') {
                                $ig->live->addToPostLive($broadcastId);
                                Utils::log("Livestream added to Archive!");
                            }
                            Utils::log("Ended stream!");
                            Utils::deleteRecovery();
                            @unlink(__DIR__ . '/request');
                            sleep(2);
                            exit(1);
                            break;
                        }
                    case 'pin':
                        {
                            $commentId = $values[0];
                            if (strlen($commentId) === 17 && //Comment IDs are 17 digits
                                is_numeric($commentId) && //Comment IDs only contain numbers
                                strpos($commentId, '-') === false) { //Comment IDs are not negative
                                $ig->live->pinComment($broadcastId, $commentId);
                                Utils::log("Pinned a comment!");
                            } else {
                                Utils::log("You entered an invalid comment id!");
                            }
                            break;
                        }
                    case 'unpin':
                        {
                            if ($lastCommentPin == -1) {
                                Utils::log("You have no comment pinned!");
                            } else {
                                $ig->live->unpinComment($broadcastId, $lastCommentPin);
                                Utils::log("Unpinned the pinned comment!");
                            }
                            break;
                        }
                    case 'pinned':
                        {
                            if ($lastCommentPin == -1) {
                                Utils::log("There is no comment pinned!");
                            } else {
                                Utils::log("Pinned Comment:\n @" . $lastCommentPinHandle . ': ' . $lastCommentPinText);
                            }
                            break;
                        }
                    case 'comment':
                        {
                            $text = $values[0];
                            if ($text !== "") {
                                $ig->live->comment($broadcastId, $text);
                                Utils::log("Commented on stream!");
                            } else {
                                Utils::log("Comments may not be empty!");
                            }
                            break;
                        }
                    case 'url':
                        {
                            Utils::log("================================ Stream URL ================================\n" . $streamUrl . "\n================================ Stream URL ================================");
                            break;
                        }
                    case 'key':
                        {
                            Utils::log("======================== Current Stream Key ========================\n" . $streamKey . "\n======================== Current Stream Key ========================");
                            if (Utils::isWindows()) {
                                shell_exec("echo " . Utils::sanitizeStreamKey($streamKey) . " | clip");
                                Utils::log("Windows: Your stream key has been pre-copied to your clipboard.");
                            }
                            break;
                        }
                    case 'info':
                        {
                            Utils::log("Info:\nStatus: $broadcastStatus\nTop Live Eligible: " . ($topLiveEligible === 1 ? "true" : "false") . "\nViewer Count: $viewerCount\nTotal Unique Viewer Count: $totalViewerCount");
                            break;
                        }
                    case 'viewers':
                        {
                            Utils::log("Viewers:");
                            $ig->live->getInfo($broadcastId);
                            $vCount = 0;
                            foreach ($ig->live->getViewerList($broadcastId)->getUsers() as &$cuser) {
                                Utils::log("[" . $cuser->getPk() . "] @" . $cuser->getUsername() . " (" . $cuser->getFullName() . ")\n");
                                $vCount++;
                            }
                            if ($vCount > 0) {
                                Utils::log("Total Viewers: " . $vCount);
                            } else {
                                Utils::log("There are no live viewers.");
                            }
                            break;
                        }
                    case 'questions':
                        {
                            Utils::log("Questions:");
                            foreach ($ig->live->getLiveBroadcastQuestions($broadcastId)->getQuestions() as $cquestion) {
                                Utils::log("[ID: " . $cquestion->getQid() . "] @" . $cquestion->getUser()->getUsername() . ": " . $cquestion->getText());
                            }
                            break;
                        }
                    case 'showquestion':
                        {
                            $questionId = $values[0];
                            if (strlen($questionId) === 17 && //Question IDs are 17 digits
                                is_numeric($questionId) && //Question IDs only contain numbers
                                strpos($questionId, '-') === false) { //Question IDs are not negative
                                $lastQuestion = $questionId;
                                $ig->live->showQuestion($broadcastId, $questionId);
                                Utils::log("Displayed question!");
                            } else {
                                Utils::log("Invalid question id!");
                            }
                            break;
                        }
                    case 'hidequestion':
                        {
                            if ($lastQuestion == -1) {
                                Utils::log("There is no question displayed!");
                            } else {
                                $ig->live->hideQuestion($broadcastId, $lastQuestion);
                                $lastQuestion = -1;
                                Utils::log("Removed the displayed question!");
                            }
                            break;
                        }
                    case 'wave':
                        {
                            $viewerId = $values[0];
                            try {
                                @$ig->live->wave($broadcastId, $viewerId);
                                Utils::log("Waved at a user!");
                            } catch (Exception $waveError) {
                                Utils::log("Could not wave at user! Make sure you're waving at people who are in the stream. Additionally, you can only wave at a person once per stream!");
                                Utils::dump($waveError->getMessage());
                            }
                            break;
                        }
                    case 'block':
                        {
                            $userId = $values[0];
                            @$ig->people->block($userId);
                            Utils::log("Blocked a user!");
                            break;
                        }
                        break;
                }
                @unlink(__DIR__ . '/request');
            } catch (Exception $cmdExc) {
                echo 'Error While Executing Command: ' . $cmdExc->getMessage() . "\n";
                Utils::dump($cmdExc->getMessage());
            }
        }

        //Process Comments
        $commentsResponse = $ig->live->getComments($broadcastId, $lastCommentTs); //Request comments since the last time we checked
        $systemComments = $commentsResponse->getSystemComments(); //Metric data about comments and likes
        $comments = $commentsResponse->getComments(); //Get the actual comments from the request we made
        if (!empty($systemComments)) {
            $lastCommentTs = $systemComments[0]->getCreatedAt();
        }
        if (!empty($comments) && $comments[0]->getCreatedAt() > $lastCommentTs) {
            $lastCommentTs = $comments[0]->getCreatedAt();
        }

        if ($commentsResponse->isPinnedComment()) {
            $pinnedComment = $commentsResponse->getPinnedComment();
            $lastCommentPin = $pinnedComment->getPk();
            $lastCommentPinHandle = $pinnedComment->getUser()->getUsername();
            $lastCommentPinText = $pinnedComment->getText();
        } else {
            $lastCommentPin = -1;
        }

        if (!empty($comments)) {
            foreach ($comments as $comment) {
                addComment($comment);
            }
        }
        if (!empty($systemComments)) {
            foreach ($systemComments as $systemComment) {
                if (strpos($systemComment->getPk(), "joined_at") !== false) {
                    if (!isset($userCache[$systemComment->getUser()->getPk()])) {
                        $userCache[$systemComment->getUser()->getPk()] = $systemComment->getUser()->getUsername();
                    }
                }
                addComment($systemComment, true);
            }
        }

        //Process Likes
        $likeCountResponse = $ig->live->getLikeCount($broadcastId, $lastLikeTs); //Get our current batch for likes
        $lastLikeTs = $likeCountResponse->getLikeTs();
        foreach ($likeCountResponse->getLikers() as $user) {
            addLike((isset($userCache[$user->getUserId()]) ? ("@" . $userCache[$user->getUserId()]) : "An Unknown User"));
        }

        //Send Heartbeat and Fetch Info
        $heartbeatResponse = $ig->live->getHeartbeatAndViewerCount($broadcastId); //Maintain :clap: comments :clap: and :clap: likes :clap: after :clap: stream
        $broadcastStatus = $heartbeatResponse->getBroadcastStatus();
        $topLiveEligible = $heartbeatResponse->getIsTopLiveEligible();
        $viewerCount = $heartbeatResponse->getViewerCount();
        $totalViewerCount = $heartbeatResponse->getTotalUniqueViewerCount();

        $ig->live->getJoinRequestCounts($broadcastId);

        //Handle Livestream Takedowns
        if ($heartbeatResponse->isIsPolicyViolation() && (int)$heartbeatResponse->getIsPolicyViolation() === 1) {
            Utils::log("Policy: Instagram has sent a policy violation" . (fightCopyright ? "." : " and you stream has been stopped!") . " The following policy was broken: " . ($heartbeatResponse->getPolicyViolationReason() == null ? "Unknown" : $heartbeatResponse->getPolicyViolationReason()));
            if (!fightCopyright) {
                Utils::dump("Policy Violation: " . ($heartbeatResponse->getPolicyViolationReason() == null ? "Unknown" : $heartbeatResponse->getPolicyViolationReason()));
                if ($obsAuto) {
                    Utils::log("OBS Integration: Killing OBS...");
                    $helper->killOBS();
                    Utils::log("OBS Integration: Restoring old basic.ini...");
                    $helper->resetSettingsState();
                    Utils::log("OBS Integration: Restoring old service.json...");
                    $helper->resetServiceState();
                }
                Utils::log("Wrapping up and exiting...");
                parseFinalViewers($ig->live->getFinalViewerList($broadcastId));
                $ig->live->end($broadcastId, true);
                Utils::log("Ended stream!");
                Utils::deleteRecovery();
                @unlink(__DIR__ . '/request');
                sleep(2);
                exit(1);
            }
            $ig->live->resumeBroadcastAfterContentMatch($broadcastId);
        }

        //Calculate Times for Limiter Argument
        if ($streamTotalSec > 0 && (time() - $startTime) >= $streamTotalSec) {
            if ($obsAuto) {
                Utils::log("OBS Integration: Killing OBS...");
                $helper->killOBS();
                Utils::log("OBS Integration: Restoring old basic.ini...");
                $helper->resetSettingsState();
                Utils::log("OBS Integration: Restoring old service.json...");
                $helper->resetServiceState();
            }
            parseFinalViewers($ig->live->getFinalViewerList($broadcastId));
            $ig->live->end($broadcastId);
            Utils::log("Stream has ended due to user requested stream limit of $streamTotalSec seconds!");

            $archived = "yes";
            if (!autoArchive && !autoDiscard) {
                Utils::log("Would you like to archive this stream?");
                $archived = Utils::promptInput();
            }
            if (autoArchive || $archived == 'yes' && !autoDiscard) {
                Utils::log("Adding to Archive...");
                $ig->live->addToPostLive($broadcastId);
                Utils::log("Livestream added to archive!");
            }
            Utils::log("Stream Ended! Please close the console window!");
            Utils::deleteRecovery();
            @unlink(__DIR__ . '/request');
            sleep(2);
            exit(1);
        }

        //Calculate Times for Hour-Cutoff
        if (!bypassCutoff && (time() - $startTime) >= 3480) {
            if ($obsAuto) {
                Utils::log("OBS Integration: Killing OBS...");
                $helper->killOBS();
                Utils::log("OBS Integration: Restoring old basic.ini...");
                $helper->resetSettingsState();
                Utils::log("OBS Integration: Restoring old service.json...");
                $helper->resetServiceState();
            }
            parseFinalViewers($ig->live->getFinalViewerList($broadcastId));
            $ig->live->end($broadcastId);
            Utils::log("Stream has ended due to Instagram's one hour time limit!");
            $archived = "yes";
            if (!autoArchive && !autoDiscard) {
                Utils::log("Would you like to archive this stream?");
                $archived = Utils::promptInput();
            }
            if (autoArchive || $archived == 'yes' && !autoDiscard) {
                Utils::log("Adding to Archive...");
                $ig->live->addToPostLive($broadcastId);
                Utils::log("Livestream added to archive!");
            }
            $restart = "yes";
            if (!infiniteStream) {
                Utils::log("Would you like to go live again?");
                $restart = Utils::promptInput();
            }
            if ($restart == 'yes') {
                Utils::log("Restarting Livestream!");
                Utils::deleteRecovery();
                main(false, $helper, $streamTotalSec, $autoPin, $args);
            }
            Utils::log("Stream Ended! Please close the console window!");
            Utils::deleteRecovery();
            @unlink(__DIR__ . '/request');
            sleep(2);
            exit(1);
        }

        if (!noBackup && STREAM_RECOVERY) {
            Utils::saveRecovery($broadcastId, $streamUrl, $streamKey, $lastCommentTs, $lastLikeTs, $lastQuestion, $startTime, $obsAuto, serialize($helper));
        }
        sleep(2);
    } while (!$exit);
}

/**
 * The handler for interpreting the commands passed via the command line.
 * @param Live $live Instagram live endpoints.
 * @param string $broadcastId The id of the live stream.
 * @param string $streamUrl The rtmp link of the stream.
 * @param string $streamKey The stream key.
 * @param bool $obsAuto True if obs automation is enabled.
 * @param ObsHelper $helper The helper class for obs utils.
 */
function newCommand(Live $live, $broadcastId, $streamUrl, $streamKey, bool $obsAuto, ObsHelper $helper)
{
    print "\n> ";
    $line = Utils::promptInput();
    switch ($line) {

        case 'ecomments':
            {
                $live->enableComments($broadcastId);
                Utils::log("Enabled Comments!");
                break;
            }
        case 'dcomments':
            {
                $live->disableComments($broadcastId);
                Utils::log("Disabled Comments!");
                break;
            }
        case 'stop':
        case 'end':
            {
                if ($obsAuto) {
                    Utils::log("OBS Integration: Killing OBS...");
                    $helper->killOBS();
                    Utils::log("OBS Integration: Restoring old basic.ini...");
                    $helper->resetSettingsState();
                    Utils::log("OBS Integration: Restoring old service.json...");
                    $helper->resetServiceState();
                }
                //Needs this to retain, I guess?
                parseFinalViewers($live->getFinalViewerList($broadcastId));
                $live->end($broadcastId);
                Utils::log("Stream Ended!");
                $archived = "yes";
                if (!autoArchive && !autoDiscard) {
                    Utils::log("Would you like to archive this stream?");
                    $archived = Utils::promptInput();
                }
                if (autoArchive || $archived == 'yes' && !autoDiscard) {
                    Utils::log("Adding to Archive...");
                    $live->addToPostLive($broadcastId);
                    Utils::log("Livestream added to archive!");
                }
                Utils::log("Wrapping up and exiting...");
                exit(1);
                break;
            }
        case 'url':
            {
                Utils::log("================================ Stream URL ================================\n" . $streamUrl . "\n================================ Stream URL ================================");
                break;
            }
        case 'key':
            {
                Utils::log("======================== Current Stream Key ========================\n" . $streamKey . "\n======================== Current Stream Key ========================");
                if (Utils::isWindows()) {
                    shell_exec("echo " . Utils::sanitizeStreamKey($streamKey) . " | clip");
                    Utils::log("Windows: Your stream key has been pre-copied to your clipboard.");
                }
                break;
            }
        case 'info':
            {
                $info = $live->getInfo($broadcastId);
                $status = $info->getStatus();
                $muted = var_export($info->is_Messages(), true);
                $count = $info->getViewerCount();
                Utils::log("Info:\nStatus: $status\nMuted: $muted\nViewer Count: $count");
                break;
            }
        case 'viewers':
            {
                Utils::log("Viewers:");
                $live->getInfo($broadcastId);
                $vCount = 0;
                foreach ($live->getViewerList($broadcastId)->getUsers() as &$cuser) {
                    Utils::log("[" . $cuser->getPk() . "] @" . $cuser->getUsername() . " (" . $cuser->getFullName() . ")\n");
                    $vCount++;
                }
                if ($vCount > 0) {
                    Utils::log("Total Viewers: " . $vCount);
                } else {
                    Utils::log("There are no live viewers.");
                }
                break;
            }
        case 'wave':
            {
                Utils::log("Please enter the user id you would like to wave at.");
                $viewerId = Utils::promptInput();
                try {
                    $live->wave($broadcastId, $viewerId);
                    Utils::log("Waved at a user!");
                } catch (Exception $waveError) {
                    Utils::log("Could not wave at user! Make sure you're waving at people who are in the stream. Additionally, you can only wave at a person once per stream!");
                    Utils::dump($waveError->getMessage());
                }
                break;
            }
        case 'comment':
            {
                Utils::log("Please enter the text you wish to comment.");
                $text = Utils::promptInput();
                if ($text !== "") {
                    $live->comment($broadcastId, $text);
                    Utils::log("Commented on stream!");
                } else {
                    Utils::log("Comments may not be empty!");
                }
                break;
            }
        case 'help':
            {
                Utils::log("Commands:\nhelp - Prints this message\nurl - Prints Stream URL\nkey - Prints Stream Key\ninfo - Grabs Stream Info\nviewers - Grabs Stream Viewers\necomments - Enables Comments\ndcomments - Disables Comments\ncomment - Leaves a comment on your stream\nwave - Waves at a User\nstop - Stops the Live Stream");
                break;
            }
        default:
            {
                Utils::log("Invalid Command. Type \"help\" for help!");
                break;
            }
    }
    newCommand($live, $broadcastId, $streamUrl, $streamKey, $obsAuto, $helper);
}


/**
 * Registers a command line argument to a global variable.
 * @param array $helpData The array which holds the command data for the help menu.
 * @param array $argv The array of arguments passed to the script.
 * @param string $name The name to be used in the global variable.
 * @param string $humanName The name to be used in the docs.
 * @param string $description The description of the argument to be used in the help menu.
 * @param string $tack The mini-tack argument name.
 * @param string|null $fullTack The full-tack argument name.
 * @return array The array of help data with the new argument.
 */
function registerArgument(array $helpData, array $argv, string $name, string $humanName, string $description, string $tack, string $fullTack = null): array
{
    if ($fullTack !== null) {
        $fullTack = '--' . $fullTack;
    }
    define($name, in_array('-' . $tack, $argv) || in_array($fullTack, $argv));
    array_push($helpData, json_encode([
        'name' => $name,
        'humanName' => $humanName,
        'description' => $description,
        'tacks' => [
            'mini' => '-' . $tack,
            'full' => $fullTack
        ]
    ]));
    return $helpData;
}
