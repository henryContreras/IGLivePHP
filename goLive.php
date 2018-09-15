<?php
if (php_sapi_name() !== "cli") {
    die("You may only run this script inside of the PHP Command Line! If you did run this in the command line, please report: \"" . php_sapi_name() . "\" to the InstagramLive-PHP Repo!");
}

logM("Loading InstagramLive-PHP v0.6...");
set_time_limit(0);
date_default_timezone_set('America/New_York');

//Argument Processing
define("help", in_array("-h", $argv) || in_array("--help", $argv));
define("bypassCheck", in_array("-b", $argv) || in_array("--bypass-check", $argv));
define("forceLegacy", in_array("-l", $argv) || in_array("--force-legacy", $argv));

if (help) {
    logM("Command Line Options:\n-h (--help): Displays this message.\n-b (--bypass-check): Bypasses the OS check. DO NOT USE THIS IF YOU DON'T KNOW WHAT YOU'RE DOING!\n-l (--force-legacy): Forces legacy mode even if you're on Windows.");
    exit();
}

//Load Depends from Composer...
require __DIR__ . '/vendor/autoload.php';

use InstagramAPI\Instagram;
use InstagramAPI\Exception\ChallengeRequiredException;
use InstagramAPI\Request\Live;
use InstagramAPI\Response\Model\User;
use InstagramAPI\Response\Model\Comment;

class ExtendedInstagram extends Instagram {
    public function changeUser( $username, $password ) {
        $this->_setUser( $username, $password );
    }
}

require_once 'config.php';

if (IG_USERNAME == "USERNAME" || IG_PASS == "PASSWORD") {
    logM("Default Username and Passwords have not been changed! Exiting...");
    exit();
}

//Login to Instagram
logM("Logging into Instagram, This can take up-to two minutes. Please wait...");
$ig = new ExtendedInstagram(false, false);
try {
    $loginResponse = $ig->login(IG_USERNAME, IG_PASS);

    if ($loginResponse !== null && $loginResponse->isTwoFactorRequired()) {
        logM("Two-Factor Required! Please check your phone for an SMS Code!");
        $twoFactorIdentifier = $loginResponse->getTwoFactorInfo()->getTwoFactorIdentifier();
        print "\nType your 2FA Code from SMS> ";
        $handle = fopen("php://stdin", "r");
        $verificationCode = trim(fgets($handle));
        logM("Logging in with 2FA Code...");
        $ig->finishTwoFactorLogin(IG_USERNAME, IG_PASS, $twoFactorIdentifier, $verificationCode);
    }
} catch (\Exception $e) {
    try {
        /** @noinspection PhpUndefinedMethodInspection */
        if ($e instanceof ChallengeRequiredException && $e->getResponse()->getErrorType() === 'checkpoint_challenge_required') {
            $response = $e->getResponse();

            logM("Your account has been flagged by Instagram. InstagramLive-PHP can attempt to verify your account by a text or an email. Would you like to do that? Type \"yes\" to do so or anything else to not!");
            logM("Note: If you already did this, and you think you entered the right code, do not attempt this again! Try logging into instagram.com from this same computer or enabling 2FA.");
            print "> ";
            $handle = fopen("php://stdin", "r");
            $attemptBypass = trim(fgets($handle));
            if ($attemptBypass == 'yes') {
                logM("Please wait while we prepare to verify your account.");
                sleep(3);

                logM("Type \"sms\" for text verification or \"email\" for email verification.\nNote: If you do not have a phone number or an email address linked to your account, don't use that method ;) You can also just press enter to abort.");
                print "> ";
                $handle = fopen("php://stdin", "r");
                $choice = trim(fgets($handle));
                if ($choice === "sms") {
                    $verification_method = 0;
                } elseif ($choice === "email") {
                    $verification_method = 1;
                } else {
                    logM("You have selected an invalid verification type. Aborting!");
                    exit();
                }

                /** @noinspection PhpUndefinedMethodInspection */
                $checkApiPath = substr($response->getChallenge()->getApiPath(), 1);
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
                            logM("Challenge Bypassed! Run the script again.");
                            exit();
                        }
                    }

                    logM("Please enter the code you received via " . ($verification_method ? 'email' : 'sms') . "!");
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

                    logM("Provided you entered the correct code, your login attempt has probably been successful. Please try re-running the script!");
                    exit();
                } catch (Exception $ex) {
                    echo $ex->getMessage();
                    exit;
                }
            } else {
                logM("Account Flagged: Please try logging into instagram.com from this exact computer before trying to run this script again!");
                exit();
            }
        }
    } catch (\LazyJsonMapper\Exception\LazyJsonMapperException $mapperException) {
        echo 'Error While Logging in to Instagram: ' . $e->getMessage() . "\n";
        exit();
    }

    echo 'Error While Logging in to Instagram: ' . $e->getMessage() . "\n";
    exit();
}

//Block Responsible for Creating the Livestream.
try {
    if (!$ig->isMaybeLoggedIn) {
        logM("Couldn't Login! Exiting!");
        exit();
    }
    logM("Logged In! Creating Livestream...");
    $stream = $ig->live->create();
    $broadcastId = $stream->getBroadcastId();

    $streamUploadUrl = preg_replace(
        '#^rtmps://([^/]+?):443/#ui',
        'rtmp://\1:80/',
        $stream->getUploadUrl()
    );

    //Grab the stream url as well as the stream key.
    $split = preg_split("[" . $broadcastId . "]", $streamUploadUrl);

    $streamUrl = $split[0];
    $streamKey = $broadcastId . $split[1];

    logM("================================ Stream URL ================================\n" . $streamUrl . "\n================================ Stream URL ================================");

    logM("======================== Current Stream Key ========================\n" . $streamKey . "\n======================== Current Stream Key ========================\n");

    logM("Please start streaming to the url and key above! When you start streaming in your streaming application, please press enter!");
    $pauseH = fopen("php://stdin", "r");
    $pauseR = fgets($pauseH);
    fclose($pauseH);

    $ig->live->start($broadcastId);
    // Switch from RTMPS to RTMP upload URL, since RTMPS doesn't work well.

    if ((strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' || bypassCheck) && !forceLegacy) {
        logM("You are using Windows! Therefore, your system supports the viewing of comments and likes!\nThis window will turn into the comment and like view and console output.\nA second window will open which will allow you to dispatch commands!");
        beginListener($ig, $broadcastId, $streamUrl, $streamKey);
    } else {
        logM("You are not using Windows! Therefore, the script has been put into legacy mode. New commands may not be added to legacy mode but backend features will remain updated.\nIt is recommended that you use Windows for the full experience!");
        logM("Live Stream is Ready for Commands:");
        newCommand($ig->live, $broadcastId, $streamUrl, $streamKey);
    }

    logM("Something Went Super Wrong! Attempting to At-Least Clean Up!");
    $ig->live->getFinalViewerList($broadcastId);
    $ig->live->end($broadcastId);
} catch (\Exception $e) {
    echo 'Error While Creating Livestream: ' . $e->getMessage() . "\n";
}

function addLike(User $user)
{
    logM("@" . $user->getUsername() . " has liked the stream!");
}

function addComment(Comment $comment)
{
    logM("Comment [ID " . $comment->getPk() . "] @" . $comment->getUser()->getUsername() . ": " . $comment->getText());
}

function beginListener(Instagram $ig, $broadcastId, $streamUrl, $streamKey)
{
    if (bypassCheck) {
        logM("You are bypassing the operating system check in an attempt to run the async command line on non-windows devices. THIS IS EXTREMELY UNSUPPORTED AND I DON'T RECOMMEND IT!");
        logM("That being said, if you cannot start the command line and *need* to end the stream just start the script again without bypassing the check and run the stop command.");
        logM("You must start commandLine.php manually.");
    } else {
        pclose(popen("start \"Command Line Input\" php commandLine.php", "r"));
    }
    cli_set_process_title("Live Chat and Like Output");
    $lastCommentTs = 0;
    $lastLikeTs = 0;
    $lastCommentPin = -1;
    $lastCommentPinHandle = '';
    $lastCommentPinText = '';
    $exit = false;

    @unlink(__DIR__ . '/request');

    do {
        /** @noinspection PhpComposerExtensionStubsInspection */
        $request = json_decode(@file_get_contents(__DIR__ . '/request'), true);
        if (!empty($request)) {
            $cmd = $request['cmd'];
            $values = $request['values'];
            if ($cmd == 'ecomments') {
                $ig->live->enableComments($broadcastId);
                logM("Enabled Comments!");
            } elseif ($cmd == 'dcomments') {
                $ig->live->disableComments($broadcastId);
                logM("Disabled Comments!");
            } elseif ($cmd == 'end') {
                $archived = $values[0];
                logM("Wrapping up and exiting...");
                //Needs this to retain, I guess?
                $ig->live->getFinalViewerList($broadcastId);
                $ig->live->end($broadcastId);
                if ($archived == 'yes') {
                    $ig->live->addToPostLive($broadcastId);
                    logM("Livestream added to Archive!");
                }
                logM("Ended stream!");
                unlink(__DIR__ . '/request');
                sleep(2);
                exit();
            } elseif ($cmd == 'pin') {
                $commentId = $values[0];
                if (strlen($commentId) === 17 && //Comment IDs are 17 digits
                    is_numeric($commentId) && //Comment IDs only contain numbers
                    strpos($commentId, '-') === false) { //Comment IDs are not negitive
                    $ig->live->pinComment($broadcastId, $commentId);
                    logM("Pinned a comment!");
                } else {
                    logM("You entered an invalid comment id!");
                }
            } elseif ($cmd == 'unpin') {
                if ($lastCommentPin == -1) {
                    logM("You have no comment pinned!");
                } else {
                    $ig->live->unpinComment($broadcastId, $lastCommentPin);
                    logM("Unpinned the pinned comment!");
                }
            } elseif ($cmd == 'pinned') {
                if ($lastCommentPin == -1) {
                    logM("There is no comment pinned!");
                } else {
                    logM("Pinned Comment:\n @" . $lastCommentPinHandle . ': ' . $lastCommentPinText);
                }
            } elseif ($cmd == 'comment') {
                $text = $values[0];
                if ($text !== "") {
                    $ig->live->comment($broadcastId, $text);
                    logM("Commented on stream!");
                } else {
                    logM("Comments may not be empty!");
                }
            } elseif ($cmd == 'url') {
                logM("================================ Stream URL ================================\n" . $streamUrl . "\n================================ Stream URL ================================");
            } elseif ($cmd == 'key') {
                logM("======================== Current Stream Key ========================\n" . $streamKey . "\n======================== Current Stream Key ========================");
            } elseif ($cmd == 'info') {
                $info = $ig->live->getInfo($broadcastId);
                $status = $info->getStatus();
                $muted = var_export($info->is_Messages(), true);
                $count = $info->getViewerCount();
                logM("Info:\nStatus: $status \nMuted: $muted \nViewer Count: $count");
            } elseif ($cmd == 'viewers') {
                logM("Viewers:");
                $ig->live->getInfo($broadcastId);
                $vCount = 0;
                foreach ($ig->live->getViewerList($broadcastId)->getUsers() as &$cuser) {
                    logM("@" . $cuser->getUsername() . " (" . $cuser->getFullName() . ")\n");
                    $vCount++;
                }
                if ($vCount > 0) {
                    logM("Total Count: " . $vCount);
                } else {
                    logM("There are no live viewers.");
                }
            }
            unlink(__DIR__ . '/request');
        }

        $commentsResponse = $ig->live->getComments($broadcastId, $lastCommentTs); //Request comments since the last time we checked
        $systemComments = $commentsResponse->getSystemComments(); //No idea what system comments are, but we need to so we can track comments
        $comments = $commentsResponse->getComments(); //Get the actual comments from the request we made
        if (!empty($systemComments)) {
            $lastCommentTs = end($systemComments)->getCreatedAt();
        }
        if (!empty($comments) && end($comments)->getCreatedAt() > $lastCommentTs) {
            $lastCommentTs = end($comments)->getCreatedAt();
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

        $ig->live->getHeartbeatAndViewerCount($broadcastId); //Maintain :clap: comments :clap: and :clap: likes :clap: after :clap: stream
        $likeCountResponse = $ig->live->getLikeCount($broadcastId, $lastLikeTs); //Get our current batch for likes
        $lastLikeTs = $likeCountResponse->getLikeTs();
        foreach ($likeCountResponse->getLikers() as $user) {
            $user = $ig->people->getInfoById($user->getUserId())->getUser();
            addLike($user);
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
 */
function newCommand(Live $live, $broadcastId, $streamUrl, $streamKey)
{
    print "\n> ";
    $handle = fopen("php://stdin", "r");
    $line = trim(fgets($handle));
    if ($line == 'ecomments') {
        $live->enableComments($broadcastId);
        logM("Enabled Comments!");
    } elseif ($line == 'dcomments') {
        $live->disableComments($broadcastId);
        logM("Disabled Comments!");
    } elseif ($line == 'stop' || $line == 'end') {
        fclose($handle);
        //Needs this to retain, I guess?
        $live->getFinalViewerList($broadcastId);
        $live->end($broadcastId);
        logM("Stream Ended!\nWould you like to keep the stream archived for 24 hours? Type \"yes\" to do so or anything else to not.");
        print "> ";
        $handle = fopen("php://stdin", "r");
        $archived = trim(fgets($handle));
        if ($archived == 'yes') {
            logM("Adding to Archive!");
            $live->addToPostLive($broadcastId);
            logM("Livestream added to archive!");
        }
        logM("Wrapping up and exiting...");
        exit();
    } elseif ($line == 'url') {
        logM("================================ Stream URL ================================\n" . $streamUrl . "\n================================ Stream URL ================================");
    } elseif ($line == 'key') {
        logM("======================== Current Stream Key ========================\n" . $streamKey . "\n======================== Current Stream Key ========================");
    } elseif ($line == 'info') {
        $info = $live->getInfo($broadcastId);
        $status = $info->getStatus();
        $muted = var_export($info->is_Messages(), true);
        $count = $info->getViewerCount();
        logM("Info:\nStatus: $status\nMuted: $muted\nViewer Count: $count");
    } elseif ($line == 'viewers') {
        logM("Viewers:");
        $live->getInfo($broadcastId);
        foreach ($live->getViewerList($broadcastId)->getUsers() as &$cuser) {
            logM("@" . $cuser->getUsername() . " (" . $cuser->getFullName() . ")");
        }
    } elseif ($line == 'help') {
        logM("Commands:\nhelp - Prints this message\nurl - Prints Stream URL\nkey - Prints Stream Key\ninfo - Grabs Stream Info\nviewers - Grabs Stream Viewers\necomments - Enables Comments\ndcomments - Disables Comments\nstop - Stops the Live Stream");
    } else {
        logM("Invalid Command. Type \"help\" for help!");
    }
    fclose($handle);
    newCommand($live, $broadcastId, $streamUrl, $streamKey);
}

/**
 * Logs a message in console but it actually uses new lines.
 * @param string $message message to be logged.
 */
function logM($message)
{
    print $message . "\n";
}