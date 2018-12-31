<?php /** @noinspection PhpComposerExtensionStubsInspection */

class ObsHelper
{
    public $service_path;
    public $obs_path;
    public $service_state;

    /**
     * Checks for OBS installation and detects service file locations.
     */
    public function __construct()
    {
        $this->service_path = getenv("appdata") . "\obs-studio\basic\profiles\Untitled\service.json";
        $this->service_state = null;

        clearstatcache();
        if (@file_exists("C:/Program Files/obs-studio/")) {
            $this->obs_path = "C:/Program Files/obs-studio/";
        } elseif (@file_exists("C:/Program Files (x86)/obs-studio/")) {
            $this->obs_path = "C:/Program Files (x86)/obs-studio/";
        } else {
            logM("OBS is not detected! OBS-Integration is now disabling...");
            $this->obs_path = null;
        }
    }

    /**
     * Creates backup of current service.json state, if exists.
     */
    public function saveServiceState()
    {
        clearstatcache();
        if (@file_exists($this->service_path)) {
            $this->service_state = json_decode(@file_get_contents($this->service_path), true);
            return;
        }
        $this->service_state = null;
    }

    /**
     * Resets service.json state to before script was run.
     * Deletes the file if none existed beforehand.
     */
    public function resetServiceState()
    {
        clearstatcache();
        if (@file_exists($this->service_path) && $this->service_state == null) {
            @unlink($this->service_path);
            return;
        }
        @file_put_contents($this->service_path, json_encode($this->service_state, JSON_PRETTY_PRINT));
    }

    /**
     * Updates the service.json file with streaming url and key.
     * @param string $uri The rmtp uri.
     * @param string $key The stream key.
     */
    public function setServiceState(string $uri, string $key)
    {
        @file_put_contents($this->service_path, json_encode([
            'settings' => [
                'key' => $key,
                'server' => $uri
            ],
            'type' => 'rtmp_custom'
        ], JSON_PRETTY_PRINT));
    }

    /**
     * Kills OBS, if running.
     * @return bool Returns true if successful.
     */
    public function killOBS(): bool
    {
        return strpos(shell_exec("taskkill /IM obs64.exe /F"), "SUCCESS");
    }

    /**
     * Starts OBS with startstreaming flag.
     */
    public function spawnOBS()
    {
        clearstatcache();
        pclose(popen("cd \"$this->obs_path" . "bin/64bit\" && start /B obs64.exe --startstreaming", "r"));
        return true;
    }

    /**
     * Waits for OBS to launch.
     * @return bool Returns true if obs launches within 15 seconds.
     */
    function waitForOBS(): bool
    {
        $attempts = 0;
        while ($attempts != 15) {
            $res = shell_exec("tasklist /FI \"IMAGENAME eq obs64.exe\" 2>NUL | find /I /N \"obs64.exe\">NUL && if \"%ERRORLEVEL%\"==\"0\" echo running");
            if (strcmp($res, "") !== 0) {
                return true;
            }
            $attempts++;
            sleep(1);
        }
        return false;
    }
}

/**
 * Logs a message in console but it actually uses new lines.
 * @param string $message message to be logged.
 */
function logM($message)
{
    print $message . "\n";
}