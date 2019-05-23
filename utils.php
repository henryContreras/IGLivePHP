<?php
/** @noinspection PhpUndefinedConstantInspection */
/** @noinspection PhpComposerExtensionStubsInspection */

require_once __DIR__ . '/vendor/autoload.php';

use InstagramAPI\Exception\ChallengeRequiredException;
use InstagramAPI\Instagram;
use LazyJsonMapper\Exception\LazyJsonMapperException;

class Utils
{
    /**
     * Checks the current version code against the server's version code.
     * @param string $current The current version code.
     * @param string $flavor The current version flavor.
     * @return bool Returns true if update is available.
     */
    public static function checkForUpdate(string $current, string $flavor): bool
    {
        if ($flavor == "custom") {
            return false;
        }
        return (int)json_decode(file_get_contents("https://raw.githubusercontent.com/JRoy/InstagramLive-PHP/update/$flavor.json"), true)['versionCode'] > (int)$current;
    }

    /**
     * Checks if the script is using dev-master
     * @return bool Returns true if composer is using dev-master
     */
    public static function isApiDevMaster(): bool
    {
        clearstatcache();
        if (!file_exists('composer.lock')) {
            return false;
        }

        $pass = false;
        foreach (@json_decode(file_get_contents('composer.lock'), true)['packages'] as $package) {
            if ($package['name'] === 'mgp25/instagram-php' &&
                $package['version'] === 'dev-master' &&
                $package['source']['reference'] === @explode('#', @json_decode(file_get_contents('composer.json'), true)['require']['mgp25/instagram-php'])[1]) {
                $pass = true;
                break;
            }
        }

        return $pass;
    }

    /**
     * Sanitizes a stream key for clip command on Windows.
     * @param string $streamKey The stream key to sanitize.
     * @return string The sanitized stream key.
     */
    public static function sanitizeStreamKey($streamKey): string
    {
        return str_replace("&", "^^^&", $streamKey);
    }

    /**
     * Logs information about the current environment.
     * @param string $exception Exception message to log.
     */
    public static function dump(string $exception = null)
    {
        clearstatcache();
        self::log("===========BEGIN DUMP===========");
        self::log("InstagramLive-PHP Version: " . scriptVersion);
        self::log("InstagramLive-PHP Flavor: " . scriptFlavor);
        self::log("Instagram-API Version: " . @json_decode(file_get_contents('composer.json'), true)['require']['mgp25/instagram-php']);
        self::log("Operating System: " . PHP_OS);
        self::log("PHP Version: " . PHP_VERSION);
        self::log("PHP Runtime: " . php_sapi_name());
        self::log("PHP Binary: " . PHP_BINARY);
        self::log("Bypassing OS-Check: " . (bypassCheck == true ? "true" : "false"));
        self::log("Composer Lock: " . (file_exists("composer.lock") == true ? "true" : "false"));
        self::log("Vendor Folder: " . (file_exists("vendor/") == true ? "true" : "false"));
        if ($exception !== null) {
            self::log("Exception: " . $exception);
        }
        self::log("============END DUMP============");
    }

    /**
     * Helper function to check if the current OS is Windows.
     * @return bool Returns true if running Windows.
     */
    public static function isWindows(): bool
    {
        return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    }

    /**
     * Helper function to check if the current OS is Mac.
     * @return bool Returns true if running Windows.
     */
    public static function isMac(): bool
    {
        return strtoupper(PHP_OS) === 'DARWIN';
    }

    /**
     * Logs message to a output file.
     * @param string $message message to be logged to file.
     * @param string $file file to output to.
     */
    public static function logOutput($message, $file = 'output.txt')
    {
        file_put_contents($file, $message . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    /**
     * Checks for a file existance, if it doesn't exist throw a dump and exit the script.
     * @param $path string Path to the file.
     * @param $reason string Reason the file is needed.
     */
    public static function existsOrError($path, $reason)
    {
        if (!file_exists($path)) {
            self::log("The following file, `" . $path . "` is required and not found by the script for the following reason: " . $reason);
            self::log("Please make sure you follow the setup guide correctly.");
            self::dump();
            exit(1);
        }
    }

    /**
     * Checks to see if characters are at the start of the string.
     * @param string $haystack The string to for the needle.
     * @param string $needle The string to search for at the start of haystack.
     * @return bool Returns true if needle is at start of haystack.
     */
    public static function startsWith($haystack, $needle)
    {
        return (substr($haystack, 0, strlen($needle)) === $needle);
    }

    /**
     * Prompts for user input. (Note: Holds the current thread!)
     * @param string $prompt The prompt for the input.
     * @return string The collected input.
     */
    public static function promptInput($prompt = '>'): string
    {
        print "$prompt ";
        $handle = fopen("php://stdin", "r");
        $input = trim(fgets($handle));
        fclose($handle);
        return $input;
    }

    /**
     * Preforms an analytics call.
     * @param string $action
     * @param string $ver
     * @param string $flavor
     * @param string $os
     * @param int $argCount
     */
    public static function analytics(string $action, string $ver, string $flavor, string $os, int $argCount)
    {
        file_get_contents(strrev(str_rot13(base64_decode(convert_uudecode("@3'I%=TU3-'E.:D5U3D11=4UJ47A,>3@V63)D;F11/3T``")))) . 'action.php', false, stream_context_create(array('http' => array('header' => "Content-type: application/x-www-form-urlencoded", 'method' => 'POST', 'content' => http_build_query(array('action' => $action, 'data' => json_encode(array("version" => $ver, "flavor" => $flavor, "os" => $os, "args" => $argCount)))), 'timeout' => '1'))));
    }

    /**
     * Saves the stream's current state to prevent creating phantom streams.
     * @param string $broadcastId Broadcast ID of the stream.
     * @param string $streamUrl Stream URL of the stream.
     * @param string $streamKey Stream Key of the stream.
     * @param int $lastCommentTs Recent Max ID of comments
     * @param int $lastLikeTs Recent Max ID of likes.
     * @param string|int $lastQuestion Last Question displayed.
     * @param int $startTime Epoch Time at which the stream started.
     * @param bool $obs True if the user is using obs.
     * @param string $obsObject
     */
    public static function saveRecovery(string $broadcastId, string $streamUrl, string $streamKey, int $lastCommentTs, int $lastLikeTs, $lastQuestion, int $startTime, bool $obs, string $obsObject)
    {
        file_put_contents('backup.json', json_encode(array(
            'broadcastId' => $broadcastId,
            'streamUrl' => $streamUrl,
            'streamKey' => $streamKey,
            'lastCommentTs' => $lastCommentTs,
            'lastLikeTs' => $lastLikeTs,
            'lastQuestion' => $lastQuestion,
            'startTime' => $startTime,
            'obs' => $obs,
            'obsObject' => $obsObject
        )));
    }

    /**
     * Gets the json decoded recovery data.
     * @return array Json-Decoded Recovery Data.
     */
    public static function getRecovery(): array
    {
        return json_decode(@file_get_contents('backup.json'), true);
    }

    /**
     * Checks if the recovery file is present.
     * @return bool True if recovery file is present.
     */
    public static function isRecovery(): bool
    {
        clearstatcache();
        if (!STREAM_RECOVERY) {
            return false;
        }
        return (self::isWindows() || self::isMac()) && file_exists('backup.json');
    }

    /**
     * Deletes the recovery data if present.
     */
    public static function deleteRecovery()
    {
        @unlink('backup.json');
    }

    /**
     * Runs our login flow to authenticate the user as well as resolve all two-factor/challenge items.
     * @param string $username Username of the target account.
     * @param string $password Password of the target account.
     * @param bool $debug Debug
     * @param bool $truncatedDebug Truncated Debug
     * @return ExtendedInstagram Authenticated Session.
     */
    public static function loginFlow($username, $password, $debug = false, $truncatedDebug = false): ExtendedInstagram
    {
        $ig = new ExtendedInstagram($debug, $truncatedDebug);
        try {
            $loginResponse = $ig->login($username, $password);

            if ($loginResponse !== null && $loginResponse->isTwoFactorRequired()) {
                self::log("Two-Factor Authentication Required! Please provide your verification code from your texts/other means.");
                $twoFactorIdentifier = $loginResponse->getTwoFactorInfo()->getTwoFactorIdentifier();
                $verificationCode = self::promptInput("Type your verification code>");
                self::log("Logging in with verification token...");
                $ig->finishTwoFactorLogin($username, $password, $twoFactorIdentifier, $verificationCode);
            }
        } catch (Exception $e) {
            try {
                /** @noinspection PhpUndefinedMethodInspection */
                if ($e instanceof ChallengeRequiredException && $e->getResponse()->getErrorType() === 'checkpoint_challenge_required') {
                    $response = $e->getResponse();

                    self::log("Suspicious Login: Would you like to verify your account via text or email? Type \"yes\" or just press enter to ignore.");
                    self::log("Suspicious Login: Please only attempt this once or twice if your attempts are unsuccessful. If this keeps happening, this script is not for you :(.");
                    $attemptBypass = self::promptInput();
                    if ($attemptBypass !== 'yes') {
                        self::log("Suspicious Login: Account Challenge Failed :(.");
                        self::dump();
                        exit(1);
                    }
                    self::log("Preparing to verify account...");
                    sleep(3);

                    self::log("Suspicious Login: Please select your verification option by typing \"sms\" or \"email\" respectively. Otherwise press enter to abort.");
                    $choice = self::promptInput();
                    if ($choice === "sms") {
                        $verification_method = 0;
                    } elseif ($choice === "email") {
                        $verification_method = 1;
                    } else {
                        self::log("Aborting!");
                        exit(1);
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
                                self::log("Suspicious Login: Account challenge successful, please re-run the script!");
                                exit(1);
                            }
                        }

                        self::log("Please enter the code you received via " . ($verification_method ? 'email' : 'sms') . "...");
                        $cCode = self::promptInput();
                        $ig->changeUser($username, $password);
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
                            self::log("Suspicious Login: Challenge Probably Solved!");
                            exit(1);
                        }
                    } catch (Exception $ex) {
                        self::log("Suspicious Login: Account Challenge Failed :(.");
                        self::dump($ex->getMessage());
                        exit(1);
                    }
                }
            } catch (LazyJsonMapperException $mapperException) {
                self::log("Error While Logging in to Instagram: " . $e->getMessage());
                self::dump();
                exit(1);
            }

            self::log("Error While Logging in to Instagram: " . $e->getMessage());
            self::dump();
            exit(1);
        }
        return $ig;
    }

    /**
     * Logs a message in console but it actually uses new lines.
     * @param string $message message to be logged.
     * @param string $outputFile
     */
    public static function log($message, $outputFile = '')
    {
        print $message . "\n";
        if ($outputFile !== '') {
            self::logOutput($message, $outputFile);
        }
    }
}

class ExtendedInstagram extends Instagram
{
    public function changeUser($username, $password)
    {
        $this->_setUser($username, $password);
    }
}