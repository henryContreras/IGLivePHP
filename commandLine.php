<?php
include_once __DIR__ . '/utils.php';
define("autoArchive", in_array("-a", $argv), in_array("--auto-archive", $argv));
define("autoDiscard", in_array("-d", $argv), in_array("--auto-discard", $argv));

Utils::log("Please wait while while the command line ensures that the live script is properly started!");
sleep(2);
Utils::log("Command Line Ready! Type \"help\" for help.");
newCommand();


function newCommand()
{
    $line = Utils::promptInput("\n>");
    switch ($line) {

        case 'ecomments':
        case 'dcomments':
        case 'unpin':
        case 'pinned':
        case 'url':
        case 'key':
        case 'info':
        case 'viewers':
        case 'questions':
        case 'hidequestion':
            {
                sendRequest($line, null);
                break;
            }
        case 'comment':
            {
                Utils::log("Please type what you would like to comment...");
                $text = Utils::promptInput();
                sendRequest("comment", [$text]);
                break;
            }
        case 'showquestion':
            {
                Utils::log("Please enter the question id you would like to display...");
                $questionId = Utils::promptInput();
                sendRequest("showquestion", [$questionId]);
                break;
            }
        case 'stop':
        case 'end':
            {
                $archived = "yes";
                if (!autoArchive && !autoDiscard) {
                    Utils::log("Would you like to keep the stream archived for 24 hours? Type \"yes\" to do so or anything else to not.");
                    $archived = Utils::promptInput();
                }
                sendRequest("end", [(autoArchive || $archived == 'yes' && !autoDiscard) ? "yes" : "no"]);
                Utils::log("Command Line Exiting! Stream *should* be ended.");
                sleep(2);
                exit(1);
                break;
            }
        case 'pin':
            {
                Utils::log("Please enter the comment id you would like to pin.");
                $commentId = Utils::promptInput();
                //TODO add comment id length check
                sendRequest("pin", [$commentId]);
                break;
            }
        case 'wave':
            {
                Utils::log("Please enter the user id you would like to wave at.");
                $viewerId = Utils::promptInput();
                sendRequest('wave', [$viewerId]);
                break;
            }
        case 'block':
            {
                Utils::log("Please enter the user id you would like to block.");
                $userId = Utils::promptInput();
                sendRequest('block', [$userId]);
                break;
            }
        case 'help':
            {
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
        block - Blocks a user from your account\n
        stop - Stops the Live Stream");
                break;
            }
        default:
            {
                Utils::log("Invalid Command. Type \"help\" for help!");
                break;
            }
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
    Utils::log("Request Sent! Please check the other window to view the response!");
    sleep(2);
}