<?php
include_once 'utils.php';
define("autoArchive", in_array("-a", $argv), in_array("--auto-archive", $argv));
define("autoDiscard", in_array("-d", $argv), in_array("--auto-discard", $argv));

Utils::log("Please wait while while the command line ensures that the live script is properly started!");
sleep(2);
Utils::log("Command Line Ready! Type \"help\" for help.");
newCommand();


function newCommand()
{
    $line = Utils::promptInput("\n>");
    if ($line == 'ecomments') {
        sendRequest("ecomments", null);
        Utils::log("Enabled Comments!");
    } elseif ($line == 'dcomments') {
        sendRequest("dcomments", null);
        Utils::log("Disabled Comments!");
    } elseif ($line == 'stop' || $line == 'end') {
        $archived = "yes";
        if (!autoArchive && !autoDiscard) {
            Utils::log("Would you like to keep the stream archived for 24 hours? Type \"yes\" to do so or anything else to not.");
            $archived = Utils::promptInput();
        }
        if (autoArchive || $archived == 'yes' && !autoDiscard) {
            sendRequest("end", ["yes"]);
        } else {
            sendRequest("end", ["no"]);
        }
        Utils::log("Command Line Exiting! Stream *should* be ended.");
        sleep(2);
        exit();
    } elseif ($line == 'pin') {
        Utils::log("Please enter the comment id you would like to pin.");
        $commentId = Utils::promptInput();
        //TODO add comment id length check
        Utils::log("Assuming that was a valid comment id, the comment should be pinned!");
        sendRequest("pin", [$commentId]);
    } elseif ($line == 'unpin') {
        Utils::log("Please check the other window to see if the unpin succeeded!");
        sendRequest("unpin", null);
    } elseif ($line == 'pinned') {
        Utils::log("Please check the other window to see the pinned comment!");
        sendRequest("pinned", null);
    } elseif ($line == 'comment') {
        Utils::log("Please enter what you would like to comment.");
        $text = Utils::promptInput();
        Utils::log("Commented! Check the other window to ensure the comment was made!");
        sendRequest("comment", [$text]);
    } elseif ($line == 'url') {
        Utils::log("Please check the other window for your stream url!");
        sendRequest("url", null);
    } elseif ($line == 'key') {
        Utils::log("Please check the other window for your stream key!");
        sendRequest("key", null);
    } elseif ($line == 'info') {
        Utils::log("Please check the other window for your stream info!");
        sendRequest("info", null);
    } elseif ($line == 'viewers') {
        Utils::log("Please check the other window for your viewers list!");
        sendRequest("viewers", null);
    } elseif ($line == 'questions') {
        Utils::log("Please check the other window for you questions list!");
        sendRequest("questions", null);
    } elseif ($line == 'showquestion') {
        Utils::log("Please enter the question id you would like to display.");
        $questionId = Utils::promptInput();
        Utils::log("Please check the other window to make sure the question was displayed!");
        sendRequest('showquestion', [$questionId]);
    } elseif ($line == 'hidequestion') {
        Utils::log("Please check the other window to make sure the question was removed!");
        sendRequest('hidequestion', null);
    } elseif ($line == 'wave') {
        Utils::log("Please enter the user id you would like to wave at.");
        $viewerId = Utils::promptInput();
        Utils::log("Please check the other window to make sure the person was waved at!");
        sendRequest('wave', [$viewerId]);
    } elseif ($line == 'help') {
        Utils::log("Commands:\n
        help - Prints this message\n
        url - Prints Stream URL\n
        key - Prints Stream Key\n
        info - Grabs Stream Info\n
        viewers - Grabs Stream Viewers\n
        ecomments - Enables Comments\n
        dcomments - Disables Comments\n
        pin - Pins a Comment\n
        unpin - Unpins a comment if one is pinned\n
        pinned - Gets the currently pinned comment\n
        comment - Comments on the stream\n
        questions - Shows all questions from the stream\n
        showquestion - Displays question on livestream\n
        hidequestion - Hides displayed question if one is displayed\n
        wave - Waves at a user who has joined the stream\n
        stop - Stops the Live Stream");
    } else {
        Utils::log("Invalid Command. Type \"help\" for help!");
    }
    newCommand();
}

function sendRequest(string $cmd, $values)
{
    /** @noinspection PhpComposerExtensionStubsInspection */
    file_put_contents(__DIR__ . '/request', json_encode([
        'cmd' => $cmd,
        'values' => isset($values) ? $values : [],
    ]));
    Utils::log("Please wait while we ensure the live script has received our request.");
    sleep(2);
}