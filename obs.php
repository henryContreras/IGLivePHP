<?php /** @noinspection PhpComposerExtensionStubsInspection */

class ObsHelper
{
    public $obs_path;
    public $service_path;
    public $settings_path;
    public $service_state;
    public $settings_state;
    public $attempted_service_save;
    public $attempted_settings_save;
    public $autoStream;

    /**
     * Checks for OBS installation and detects service file locations.
     * @param bool $autoStream Automatically starts streaming in OBS if true.
     */
    public function __construct(bool $autoStream)
    {
        $this->service_path = getenv("appdata") . "\obs-studio\basic\profiles\Untitled\service.json";
        $this->settings_path = getenv("appdata") . "\obs-studio\basic\profiles\Untitled\basic.ini";
        $this->service_state = null;
        $this->settings_state = null;
        $this->attempted_service_save = false;
        $this->attempted_settings_save = false;
        $this->autoStream = $autoStream;

        clearstatcache();
        if (@file_exists("C:/Program Files/obs-studio/")) {
            $this->obs_path = "C:/Program Files/obs-studio/";
        } elseif (@file_exists("C:/Program Files (x86)/obs-studio/")) {
            $this->obs_path = "C:/Program Files (x86)/obs-studio/";
        } else {
            $this->obs_path = null; //OBS's path could not be found, the script will disable OBS integration.
        }
    }

    /**
     * Creates backup of current service.json state, if exists.
     */
    public function saveServiceState()
    {
        $this->attempted_service_save = true;
        clearstatcache();
        if (@file_exists($this->service_path)) {
            $this->service_state = json_decode(@file_get_contents($this->service_path), true);
            return;
        }
        $this->service_state = null;
    }

    /**
     * Creates backup of current basic.ini state, if exists.
     */
    public function saveSettingsState()
    {
        $this->attempted_settings_save = true;
        clearstatcache();
        if (@file_exists($this->settings_path)) {
            $this->settings_state = @file_get_contents($this->settings_path);
            return;
        }
        $this->settings_state = null;
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
     * Resets basic.ini state to before script was run.
     * Deletes the file if non existed beforehand.
     */
    public function resetSettingsState()
    {
        clearstatcache();
        if (@file_exists($this->settings_path) && $this->settings_state == null) {
            @unlink($this->settings_path);
            return;
        }
        @file_put_contents($this->settings_path, $this->settings_state);
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
     * Updates the basic.ini with the proper stream configuration.
     */
    public function updateSettingsState()
    {
        @file_put_contents($this->settings_path, "[General]\nName=Untitled\n\n[Video]\nBaseCX=720\nBaseCY=1280\nOutputCX=720\nOutputCY=1280\n\n[Output]\nMode=Simple\n\n[SimpleOutput]\nVBitrate=4000");
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
        pclose(popen("cd \"$this->obs_path" . "bin/64bit\" && start /B obs64.exe" . ($this->autoStream ? " --startstreaming" : ""), "r"));
        return true;
    }

    /**
     * Checks to see if OBS is running.
     * @return bool Returns true if obs is running.
     */
    public function isObsRunning(): bool
    {
        $res = shell_exec("tasklist /FI \"IMAGENAME eq obs64.exe\" 2>NUL | find /I /N \"obs64.exe\">NUL && if \"%ERRORLEVEL%\"==\"0\" echo running");
        if (strcmp($res, "") !== 0) {
            return true;
        }
        return false;
    }

    /**
     * Waits for OBS to launch.
     * @return bool Returns true if obs launches within 15 seconds.
     */
    public function waitForOBS(): bool
    {
        $attempts = 0;
        while ($attempts != 15) {
            if ($this->isObsRunning()) {
                return true;
            }
            $attempts++;
            sleep(1);
        }
        return false;
    }
}