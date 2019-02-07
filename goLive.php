<?php /** @noinspection PhpComposerExtensionStubsInspection */
/** @noinspection PhpUndefinedConstantInspection */

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
$helpData = registerArgument($helpData, $argv, "bypassCheck", "Bypasses the operating system check. Please do not use this if you don't use this if you don't know what you're doing!", "b", "bypass-check");
$helpData = registerArgument($helpData, $argv, "forceLegacy", "Forces legacy mode for Windows users.", "l", "force-legacy");
$helpData = registerArgument($helpData, $argv, "bypassCutoff", "Bypasses stream cutoff after one hour. Please do not use this if you are not verified!", "-bypass-cutoff");
$helpData = registerArgument($helpData, $argv, "infiniteStream", "Automatically starts a new stream after the hour cutoff.", "i", "infinite-stream");
$helpData = registerArgument($helpData, $argv, "autoArchive", "Automatically archives a live stream after it ends.", "a", "auto-archive");
$helpData = registerArgument($helpData, $argv, "logCommentOutput", "Logs comment and like output into a text file.", "o", "comment-output");
$helpData = registerArgument($helpData, $argv, "obsAutomationAccept", "Automatically accepts the OBS prompt.", "-obs");
$helpData = registerArgument($helpData, $argv, "obsNoStream", "Disables automatic stream start in OBS.", "-obs-no-stream");
$helpData = registerArgument($helpData, $argv, "disableObsAutomation", "Disables OBS automation and subsequently disables the path check.", "-no-obs");
$helpData = registerArgument($helpData, $argv, "startDisableComments", "Automatically disables commands when the stream starts.", "-dcomments");
$helpData = registerArgument($helpData, $argv, "useRmtps", "Uses rmtps rather than rmtp for clients that refuse rmtp.", "-use-rmtps");
$helpData = registerArgument($helpData, $argv, "thisIsAPlaceholder", "Sets the amount of time to limit the stream to in seconds. (Example: --stream-sec=60).", "-stream-sec");
$helpData = registerArgument($helpData, $argv, "thisIsAPlaceholder1", "Automatically pins a comment when the live stream starts. Note: Use underscores for spaces. (Example: --auto-pin=Hello_World!).", "-auto-pin");
$helpData = registerArgument($helpData, $argv, "dump", "Forces an error dump for debug purposes.", "d", "dump");
$helpData = registerArgument($helpData, $argv, "dumpFlavor", "Dumps", "-dumpFlavor");

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

//Load Utils
require 'utils.php';

define("scriptVersion", "1.2");
define("scriptVersionCode", "28");
define("scriptFlavor", "custom");
Utils::log("Loading InstagramLive-PHP v" . scriptVersion . "...");

if (Utils::checkForUpdate(scriptVersionCode, scriptFlavor)) {
    Utils::log("Update: A new update is available, run the `update.php` script to fetch it!");
}

if (dumpFlavor) {
    Utils::log(scriptFlavor);
    exit();
}

if (dump) {
    Utils::dump();
    exit();
}

if (help) {
    Utils::log("Command Line Arguments:");
    foreach ($helpData as $option) {
        $dOption = json_decode($option, true);
        Utils::log($dOption['tacks']['mini'] . ($dOption['tacks']['full'] !== null ? " (" . $dOption['tacks']['full'] . "): " : ": ") . $dOption['description']);
    }
    exit();
}

//Check for required files
Utils::existsOrError(__DIR__ . '/vendor/autoload.php', "Instagram API Files");
Utils::existsOrError('obs.php', "OBS Integration");
Utils::existsOrError('config.php', "Username & Password Storage");

//Load Classes
require __DIR__ . '/vendor/autoload.php'; //Composer
require 'obs.php'; //OBS Utils

use InstagramAPI\Instagram;
use InstagramAPI\Exception\ChallengeRequiredException;
use InstagramAPI\Request\Live;
use InstagramAPI\Response\Model\User;
use InstagramAPI\Response\Model\Comment;

class ExtendedInstagram extends Instagram
{
    public function changeUser($username, $password)
    {
        $this->_setUser($username, $password);
    }
}

require_once 'config.php';

//Run the script and spawn a new console window if applicable.
main(true, new ObsHelper(!obsNoStream, disableObsAutomation), $streamTotalSec, $autoPin);

function main($console, ObsHelper $helper, $streamTotalSec, $autoPin)
{
    if (IG_USERNAME == "USERNAME" || IG_PASS == "PASSWORD") {
        Utils::log("Default Username or Password have not been changed! Exiting...");
        exit();
    }

//Login to Instagram
    Utils::log("Logging into Instagram! Please wait as this can take up-to two minutes...");
    $ig = new ExtendedInstagram(false, false);
    try {
        $loginResponse = $ig->login(IG_USERNAME, IG_PASS);

        if ($loginResponse !== null && $loginResponse->isTwoFactorRequired()) {
            Utils::log("Two-Factor Authentication Required! Please provide your verification code from your texts/other means.");
            $twoFactorIdentifier = $loginResponse->getTwoFactorInfo()->getTwoFactorIdentifier();
            print "\nType your verification code> ";
            $handle = fopen("php://stdin", "r");
            $verificationCode = trim(fgets($handle));
            Utils::log("Logging in with verification token...");
            $ig->finishTwoFactorLogin(IG_USERNAME, IG_PASS, $twoFactorIdentifier, $verificationCode);
        }
    } catch (\Exception $e) {
        try {
            /** @noinspection PhpUndefinedMethodInspection */
            if ($e instanceof ChallengeRequiredException && $e->getResponse()->getErrorType() === 'checkpoint_challenge_required') {
                $response = $e->getResponse();

                Utils::log("Suspicious Login: Would you like to verify your account via text or email? Type \"yes\" or just press enter to ignore.");
                Utils::log("Suspicious Login: Please only attempt this once or twice if your attempts are unsuccessful. If this keeps happening, this script is not for you :(.");
                print "> ";
                $handle = fopen("php://stdin", "r");
                $attemptBypass = trim(fgets($handle));
                if ($attemptBypass == 'yes') {
                    Utils::log("Preparing to verify account...");
                    sleep(3);

                    Utils::log("Suspicious Login: Please select your verification option by typing \"sms\" or \"email\" respectively. Otherwise press enter to abort.");
                    print "> ";
                    $handle = fopen("php://stdin", "r");
                    $choice = trim(fgets($handle));
                    if ($choice === "sms") {
                        $verification_method = 0;
                    } elseif ($choice === "email") {
                        $verification_method = 1;
                    } else {
                        Utils::log("Aborting!");
                        exit();
                    }

                    /** @noinspection PhpUndefinedMethodInspection */
                    $checkApiPath = trim(substr($response->getChallenge()->getApiPath(), 1));
                    $customResponse = $ig->request($checkApiPath)
                        ->setNeedsAuth(false)
                        ->addPost('choice', $verification_method)
                        ->addPost('_uuid', $ig->uuid)
                        ->addPost('guid', $ig->uuid)
                        ->addPost('device_id', $ig->device_id)
                        ->addPost('_uid', $ig->account_id)
                        ->addPost('_csrftoken', $ig->client->getToken())
                        ->getDecodedResponse();

                    try {
                        if ($customResponse['status'] === 'ok' && isset($customResponse['action'])) {
                            if ($customResponse['action'] === 'close') {
                                Utils::log("Suspicious Login: Account challenge successful, please re-run the script!");
                                exit();
                            }
                        }

                        Utils::log("Please enter the code you received via " . ($verification_method ? 'email' : 'sms') . "...");
                        print "> ";
                        $handle = fopen("php://stdin", "r");
                        $cCode = trim(fgets($handle));
                        $ig->changeUser(IG_USERNAME, IG_PASS);
                        $customResponse = $ig->request($checkApiPath)
                            ->setNeedsAuth(false)
                            ->addPost('security_code', $cCode)
                            ->addPost('_uuid', $ig->uuid)
                            ->addPost('guid', $ig->uuid)
                            ->addPost('device_id', $ig->device_id)
                            ->addPost('_uid', $ig->account_id)
                            ->addPost('_csrftoken', $ig->client->getToken())
                            ->getDecodedResponse();

                        if (@$customResponse['status'] === 'ok' && @$customResponse['logged_in_user']['pk'] !== null) {
                            Utils::log("Suspicious Login: Account challenge successful, please re-run the script!");
                            exit();
                        } else {
                            Utils::log("Suspicious Login: I have no clue if that just worked, re-run me to check.");
                            exit();
                        }
                    } catch (Exception $ex) {
                        Utils::log("Suspicious Login: Account Challenge Failed :(.");
                        Utils::dump($ex->getMessage());
                        exit();
                    }
                } else {
                    Utils::log("Suspicious Login: Account Challenge Failed :(.");
                    Utils::dump();
                    exit();
                }
            }
        } catch (\LazyJsonMapper\Exception\LazyJsonMapperException $mapperException) {
            Utils::log("Error While Logging in to Instagram: " . $e->getMessage());
            Utils::dump();
            exit();
        }

        Utils::log("Error While Logging in to Instagram: " . $e->getMessage());
        Utils::dump();
        exit();
    }

//Block Responsible for Creating the Livestream.
    try {
        if (!$ig->isMaybeLoggedIn) {
            Utils::log("Error While Logging in to Instagram: isMaybeLoggedIn fail!");
            Utils::dump();
            exit();
        }
        Utils::log("Logged In! Creating Livestream...");
        $stream = $ig->live->create();
        $broadcastId = $stream->getBroadcastId();

        // Switch from RTMPS to RTMP upload URL, since RTMPS doesn't work well.
        $streamUploadUrl = (!useRmtps === true ? preg_replace(
            '#^rtmps://([^/]+?):443/#ui',
            'rtmp://\1:80/',
            $stream->getUploadUrl()
        ) : $stream->getUploadUrl());

        //Grab the stream url as well as the stream key.
        $split = preg_split("[" . $broadcastId . "]", $streamUploadUrl);

        $streamUrl = trim($split[0]);
        $streamKey = trim($broadcastId . $split[1]);

        $obsAutomation = true;
        if ($helper->obs_path === null) {
            Utils::log("OBS Integration: OBS was not detected, disabling!" . (!Utils::isWindows() ? " Please note macOS is not supported!" : " Please make a ticket on GitHub if you have OBS installed."));
            $obsAutomation = false;
        } else {
            if (!obsAutomationAccept) {
                Utils::log("OBS Integration: Would you like the script to automatically start streaming to OBS? Type \"yes\" or press enter to ignore.");
                print "> ";
                $eoiH = fopen("php://stdin", "r");
                $eoi = trim(fgets($eoiH));
                if ($eoi !== "yes") {
                    $obsAutomation = false;
                }
                fclose($eoiH);
            }
        }

        Utils::log("================================ Stream URL ================================\n" . $streamUrl . "\n================================ Stream URL ================================");

        Utils::log("======================== Current Stream Key ========================\n" . $streamKey . "\n======================== Current Stream Key ========================\n");

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
            Utils::log("OBS Integration: Re-launching OBS...");
            $helper->spawnOBS();
            Utils::log("OBS Integration: Waiting up to 15 seconds for OBS...");
            if ($helper->waitForOBS()) {
                sleep(1);
                Utils::log("OBS Integration: OBS Launched Successfully! Starting Stream...");
            } else {
                Utils::log("OBS Integration: OBS was not detected! Press enter once you confirm OBS is streaming...");
                $oPauseH = fopen("php://stdin", "r");
                fgets($oPauseH);
                fclose($oPauseH);
            }
        }

        if (!$obsAutomation || obsNoStream) {
            Utils::log("Please start streaming to the url and key above! Once you are live, please press enter!");
            $pauseH = fopen("php://stdin", "r");
            fgets($pauseH);
            fclose($pauseH);
        }

        $ig->live->start($broadcastId);

        if (startDisableComments) {
            Utils::log("Automatically disabled comments.");
            $ig->live->disableComments($broadcastId);
        }

        if ((Utils::isWindows() || bypassCheck) && !forceLegacy) {
            Utils::log("Command Line: Windows Detected! A new console will open for command input and this will become command/like output.");
            beginListener($ig, $broadcastId, $streamUrl, $streamKey, $console, $obsAutomation, $helper, $streamTotalSec, $autoPin);
        } else {
            Utils::log("Command Line: macOS/Linux Detected! The script has entered legacy mode. Please use Windows for all the latest features.");
            newCommand($ig->live, $broadcastId, $streamUrl, $streamKey, $obsAutomation, $helper);
        }

        Utils::log("Unable to start command lines, attempting clean up!");
        $ig->live->getFinalViewerList($broadcastId);
        $ig->live->end($broadcastId);
        Utils::dump();
        exit();
    } catch (\Exception $e) {
        echo 'Error While Creating Livestream: ' . $e->getMessage() . "\n";
        Utils::dump($e->getMessage());
        exit();
    }
}

function addLike(User $user)
{
    $cmt = "@" . $user->getUsername() . " has liked the stream!";
    Utils::log($cmt);
    if (logCommentOutput) {
        Utils::logOutput($cmt);
    }
}

function addComment(Comment $comment)
{
    $cmt = "Comment [ID " . $comment->getPk() . "] @" . $comment->getUser()->getUsername() . ": " . $comment->getText();
    Utils::log($cmt);
    if (logCommentOutput) {
        Utils::logOutput($cmt);
    }
}

function beginListener(Instagram $ig, $broadcastId, $streamUrl, $streamKey, $console, bool $obsAuto, ObsHelper $helper, int $streamTotalSec, string $autoPin)
{
    if (bypassCheck && !Utils::isWindows()) {
        Utils::log("Command Line: You are forcing the new command line. This is unsupported and may result in issues.");
        Utils::log("Command Line: To start the new command line, please run the commandLine.php script.");
    } else {
        if ($console) {
            pclose(popen("start \"InstagramLive-PHP: Command Line\" \"" . PHP_BINARY . "\" commandLine.php" . (autoArchive === true ? " -a" : ""), "r"));
        }
    }
    cli_set_process_title("InstagramLive-PHP: Live Chat & Likes");
    $lastCommentTs = 0;
    $lastLikeTs = 0;
    $lastQuestion = -1;
    $lastCommentPin = -1;
    $lastCommentPinHandle = '';
    $lastCommentPinText = '';
    $exit = false;
    $startTime = time();

    if ($autoPin !== null) {
        $ig->live->pinComment($broadcastId, $ig->live->comment($broadcastId, $autoPin)->getComment()->getPk());
    }

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
                if ($cmd == 'ecomments') {
                    $ig->live->enableComments($broadcastId);
                    Utils::log("Enabled Comments!");
                } elseif ($cmd == 'dcomments') {
                    $ig->live->disableComments($broadcastId);
                    Utils::log("Disabled Comments!");
                } elseif ($cmd == 'end') {
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
                    $ig->live->getFinalViewerList($broadcastId);
                    $ig->live->end($broadcastId);
                    if ($archived == 'yes') {
                        $ig->live->addToPostLive($broadcastId);
                        Utils::log("Livestream added to Archive!");
                    }
                    Utils::log("Ended stream!");
                    unlink(__DIR__ . '/request');
                    sleep(2);
                    exit();
                } elseif ($cmd == 'pin') {
                    $commentId = $values[0];
                    if (strlen($commentId) === 17 && //Comment IDs are 17 digits
                        is_numeric($commentId) && //Comment IDs only contain numbers
                        strpos($commentId, '-') === false) { //Comment IDs are not negative
                        $ig->live->pinComment($broadcastId, $commentId);
                        Utils::log("Pinned a comment!");
                    } else {
                        Utils::log("You entered an invalid comment id!");
                    }
                } elseif ($cmd == 'unpin') {
                    if ($lastCommentPin == -1) {
                        Utils::log("You have no comment pinned!");
                    } else {
                        $ig->live->unpinComment($broadcastId, $lastCommentPin);
                        Utils::log("Unpinned the pinned comment!");
                    }
                } elseif ($cmd == 'pinned') {
                    if ($lastCommentPin == -1) {
                        Utils::log("There is no comment pinned!");
                    } else {
                        Utils::log("Pinned Comment:\n @" . $lastCommentPinHandle . ': ' . $lastCommentPinText);
                    }
                } elseif ($cmd == 'comment') {
                    $text = $values[0];
                    if ($text !== "") {
                        $ig->live->comment($broadcastId, $text);
                        Utils::log("Commented on stream!");
                    } else {
                        Utils::log("Comments may not be empty!");
                    }
                } elseif ($cmd == 'url') {
                    Utils::log("================================ Stream URL ================================\n" . $streamUrl . "\n================================ Stream URL ================================");
                } elseif ($cmd == 'key') {
                    Utils::log("======================== Current Stream Key ========================\n" . $streamKey . "\n======================== Current Stream Key ========================");
                    if (Utils::isWindows()) {
                        shell_exec("echo " . Utils::sanitizeStreamKey($streamKey) . " | clip");
                        Utils::log("Windows: Your stream key has been pre-copied to your clipboard.");
                    }
                } elseif ($cmd == 'info') {
                    $info = $ig->live->getInfo($broadcastId);
                    $status = $info->getStatus();
                    $muted = var_export($info->is_Messages(), true);
                    $count = $info->getViewerCount();
                    Utils::log("Info:\nStatus: $status \nMuted: $muted \nViewer Count: $count");
                } elseif ($cmd == 'viewers') {
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
                } elseif ($cmd == 'questions') {
                    Utils::log("Questions:");
                    foreach ($ig->live->getQuestions()->getQuestions() as $cquestion) {
                        Utils::log("[ID: " . $cquestion->getQid() . "] @" . $cquestion->getUser()->getUsername() . ": " . $cquestion->getText());
                    }
                } elseif ($cmd == 'showquestion') {
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
                } elseif ($cmd == 'hidequestion') {
                    if ($lastQuestion == -1) {
                        Utils::log("There is no question displayed!");
                    } else {
                        $ig->live->hideQuestion($broadcastId, $lastQuestion);
                        $lastQuestion = -1;
                        Utils::log("Removed the displayed question!");
                    }
                } elseif ($cmd == 'wave') {
                    $viewerId = $values[0];
                    try {
                        $ig->live->wave($broadcastId, $viewerId);
                        Utils::log("Waved at a user!");
                    } catch (Exception $waveError) {
                        Utils::log("Could not wave at user! Make sure you're waving at people who are in the stream. Additionally, you can only wave at a person once per stream!");
                        Utils::dump($waveError->getMessage());
                    }
                }
                unlink(__DIR__ . '/request');
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

        //Process Likes
        $ig->live->getHeartbeatAndViewerCount($broadcastId); //Maintain :clap: comments :clap: and :clap: likes :clap: after :clap: stream
        $likeCountResponse = $ig->live->getLikeCount($broadcastId, $lastLikeTs); //Get our current batch for likes
        $lastLikeTs = $likeCountResponse->getLikeTs();
        foreach ($likeCountResponse->getLikers() as $user) {
            $user = $ig->people->getInfoById($user->getUserId())->getUser();
            addLike($user);
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
            $ig->live->getFinalViewerList($broadcastId);
            $ig->live->end($broadcastId);
            Utils::log("Stream has ended due to user requested stream limit of $streamTotalSec seconds!");

            $archived = "yes";
            if (!autoArchive) {
                print "Would you like to archive this stream?\n> ";
                $handle = fopen("php://stdin", "r");
                $archived = trim(fgets($handle));
            }
            if ($archived == 'yes') {
                Utils::log("Adding to Archive...");
                $ig->live->addToPostLive($broadcastId);
                Utils::log("Livestream added to archive!");
            }
            Utils::log("Stream Ended! Please close the console window!");
            @unlink(__DIR__ . '/request');
            sleep(2);
            exit();
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
            $ig->live->getFinalViewerList($broadcastId);
            $ig->live->end($broadcastId);
            Utils::log("Stream has ended due to Instagram's one hour time limit!");
            $archived = "yes";
            if (!autoArchive) {
                print "Would you like to archive this stream?\n> ";
                $handle = fopen("php://stdin", "r");
                $archived = trim(fgets($handle));
            }
            if ($archived == 'yes') {
                Utils::log("Adding to Archive...");
                $ig->live->addToPostLive($broadcastId);
                Utils::log("Livestream added to archive!");
            }
            $restart = "yes";
            if (!infiniteStream) {
                Utils::log("Would you like to go live again?");
                print "> ";
                $handle = fopen("php://stdin", "r");
                $restart = trim(fgets($handle));
            }
            if ($restart == 'yes') {
                Utils::log("Restarting Livestream!");
                main(false, $helper, $streamTotalSec, $autoPin);
            }
            Utils::log("Stream Ended! Please close the console window!");
            @unlink(__DIR__ . '/request');
            sleep(2);
            exit();
        }

        sleep(1);
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
    $handle = fopen("php://stdin", "r");
    $line = trim(fgets($handle));
    if ($line == 'ecomments') {
        $live->enableComments($broadcastId);
        Utils::log("Enabled Comments!");
    } elseif ($line == 'dcomments') {
        $live->disableComments($broadcastId);
        Utils::log("Disabled Comments!");
    } elseif ($line == 'stop' || $line == 'end') {
        fclose($handle);
        if ($obsAuto) {
            Utils::log("OBS Integration: Killing OBS...");
            $helper->killOBS();
            Utils::log("OBS Integration: Restoring old basic.ini...");
            $helper->resetSettingsState();
            Utils::log("OBS Integration: Restoring old service.json...");
            $helper->resetServiceState();
        }
        //Needs this to retain, I guess?
        $live->getFinalViewerList($broadcastId);
        $live->end($broadcastId);
        Utils::log("Stream Ended!");
        $archived = "yes";
        if (!autoArchive) {
            Utils::log("Would you like to keep the stream archived for 24 hours? Type \"yes\" to do so or anything else to not.");
            print "> ";
            $handle = fopen("php://stdin", "r");
            $archived = trim(fgets($handle));
        }
        if ($archived == 'yes') {
            Utils::log("Adding to Archive!");
            $live->addToPostLive($broadcastId);
            Utils::log("Livestream added to archive!");
        }
        Utils::log("Wrapping up and exiting...");
        exit();
    } elseif ($line == 'url') {
        Utils::log("================================ Stream URL ================================\n" . $streamUrl . "\n================================ Stream URL ================================");
    } elseif ($line == 'key') {
        Utils::log("======================== Current Stream Key ========================\n" . $streamKey . "\n======================== Current Stream Key ========================");
        if (Utils::isWindows()) {
            shell_exec("echo " . Utils::sanitizeStreamKey($streamKey) . " | clip");
            Utils::log("Windows: Your stream key has been pre-copied to your clipboard.");
        }
    } elseif ($line == 'info') {
        $info = $live->getInfo($broadcastId);
        $status = $info->getStatus();
        $muted = var_export($info->is_Messages(), true);
        $count = $info->getViewerCount();
        Utils::log("Info:\nStatus: $status\nMuted: $muted\nViewer Count: $count");
    } elseif ($line == 'viewers') {
        Utils::log("Viewers:");
        $live->getInfo($broadcastId);
        foreach ($live->getViewerList($broadcastId)->getUsers() as &$cuser) {
            Utils::log("@" . $cuser->getUsername() . " (" . $cuser->getFullName() . ")");
        }
    } elseif ($line == 'help') {
        Utils::log("Commands:\nhelp - Prints this message\nurl - Prints Stream URL\nkey - Prints Stream Key\ninfo - Grabs Stream Info\nviewers - Grabs Stream Viewers\necomments - Enables Comments\ndcomments - Disables Comments\nstop - Stops the Live Stream");
    } else {
        Utils::log("Invalid Command. Type \"help\" for help!");
    }
    fclose($handle);
    newCommand($live, $broadcastId, $streamUrl, $streamKey, $obsAuto, $helper);
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
    /** @noinspection PhpComposerExtensionStubsInspection */
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