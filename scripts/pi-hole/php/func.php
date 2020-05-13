<?php
/* Pi-hole: A black hole for Internet advertisements
*  (c) 2017 Pi-hole, LLC (https://pi-hole.net)
*  Network-wide ad blocking via your own hardware.
*
*  This file is copyright under the latest version of the EUPL.
*  Please see LICENSE file for your rights under this license. */

class LocalDomainList implements JsonSerializable
{
    const DOMAIN_FILE = '/etc/dnsmasq.d/10-hosts.conf';
    const BASE_BACKUP_FILE = '/tmp/10-hosts.conf';

    /**
     * @var LocalDomain[]
     */
    private /*array*/ $domains = [];

    private /*bool*/ $mutated = false;

    /**
     * LocalDomainList constructor.
     */
    public function __construct() { }

    /**
     * @return string
     */
    private function getBackupFileName() : string {
        return self::BASE_BACKUP_FILE . date('Ymd.His');
    }

    /**
     * Has this list changed since it was created or loaded from a file.
     * @return bool
     */
    public function isMutated() : bool {
        return $this->mutated;
    }

    /**
     * @param LocalDomain $ld
     * @param bool $replace
     */
    public function add(LocalDomain $ld, $replace = false) : void {
        if (!empty($this->domains[$ld->getIpv4()])) {
            $currentDomain = &$this->domains[$ld->getIpv4()];
            if (!$this->mutated && $currentDomain->getHosts() != $ld->getHosts()) {
                $this->mutated = true;
            }
            $replace ? $currentDomain = $ld : $currentDomain->mergeHosts($ld);
        }
        else {
            $this->domains[$ld->getIpv4()] = $ld;
            $this->mutated = true;
        }
    }

    /**
     * @param string $ip
     * @return bool
     */
    public function remove(string $ip) : bool {
        $ip = trim($ip);
        if (!array_key_exists($ip, $this->domains)) {
            return false;
        }

        unset($this->domains[$ip]);
        return true;
    }

    /**
     * @return string
     */
    public function toHostRecords() : string {
        $str = '';
        array_map(function (LocalDomain $ld) use (&$str) {
            $str .= 'host-record=' . implode(',', $ld->getHosts()) . ',' . $ld->getIpV4() . PHP_EOL;
        }, $this->domains);

        return $str;
    }

    /**
     * @param bool $backupFile
     * @return bool
     */
    public function saveToFile($backupFile = true) : bool {
        if (!is_writable(self::DOMAIN_FILE)) {
            throw new \RuntimeException("Domain File Not Writeable: check permissions of file");
        }
        if ($backupFile) {
            echo exec(sprintf('sudo -n cp "%s" "%s"',
                escapeshellcmd(self::DOMAIN_FILE),
                $this->getBackupFileName()
            ), $output, $returnVar);
        }

        $result = file_put_contents(self::DOMAIN_FILE, $this->toHostRecords(), LOCK_EX);
        return ($result !== false);
    }

    /**
     * @param $domainFile
     * @return LocalDomainList
     * @throws RuntimeException
     */
    static public function createFromFile(string $domainFile, $checkWriteable = false) : LocalDomainList {
        $domainList = new self;

        if (!file_exists($domainFile)) {
            if ($checkWriteable && !touch($domainFile)) {
                throw new \RuntimeException("Unable to create config file to store local domains name: $domainFile");
            }
            return $domainList;
        }

        if ($checkWriteable && !is_writable($domainFile)) {
            throw new \RuntimeException("Insufficient permissions to write to file: $domainFile");
        }

        // Read domain file, split
        $hostRecords = file($domainFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        // Parse lines into structured hashmap
        foreach ($hostRecords as $curHostRecord) {
            $domainList->add(LocalDomain::createFromHostRecord($curHostRecord));
        }

        $domainList->mutated = false; // Reset mutation marker when loading from file
        return $domainList;
    }

    /**
     * @return array|mixed
     */
    public function jsonSerialize()
    {
        $ar = [];
        foreach ($this->domains as $ip => $domains) {
            $ar[] = ['ip' => $ip, 'domains' => $domains];
        }
        return $ar;
    }
}

class LocalDomain implements JsonSerializable {
    private /*string*/ $ipv4;
    private /*string*/ $ipv6; // UNIMPLEMENTED
    private /*array*/ $hosts = [];

    const HOST_RECORD_REGEX = '/^host-record=/';

    /**
     * @param array $hosts
     * @param string $ipv4
     * @param string|null $ipv6
     */
    public function __construct(array $hosts, string $ipv4, string $ipv6 = null) {
        $this->ipv4 = $ipv4;
        $this->ipv6 = $ipv6;

        $this->hosts = array_filter($hosts);
    }

    public function getIpV4() : string {
        return $this->ipv4;
    }

    public function getIpV6() : string {
        throw new BadMethodCallException('Not yet implemented');
    }

    public function getHosts() : array {
        return $this->hosts;
    }

    public function autoAddAlias() : void {
        $aliases = array_map(function ($domain) {
            return explode('.', $domain)[0];
        }, $this->hosts);
        $this->_mergeHosts($aliases);
    }

    public function mergeHosts(LocalDomain $localDomain) : void {
        $this->_mergeHosts($localDomain->getHosts());
    }

    private function _mergeHosts(array $newHosts) : void {
        $this->hosts = array_unique(array_merge($this->hosts, $newHosts));
    }

    /**
     * Create a domain from a dnsmasq "host-record" line.
     * @param string $hostRecord
     * @return LocalDomain
     */
    static public function createFromHostRecord(string $hostRecord) : LocalDomain {
        // Format: --host-record=<name>[,<name>....],[<IPv4-address>],[<IPv6-address>][,<TTL>]
        $parts = explode(',', preg_replace(self::HOST_RECORD_REGEX, '', $hostRecord));

        $ips = [];
        $last_item = array_pop($parts);
        while (filter_var($last_item, FILTER_VALIDATE_IP)) {
            $ips[] = $last_item;
            $last_item = array_pop($parts);
        }
        $hosts = $parts;
        $hosts[] = $last_item;

        return new self($hosts, ...array_reverse($ips));
    }

    public function jsonSerialize()
    {
        return $this->hosts;
    }
}

function is_valid_domain_name($domain_name) : bool
{
    return (preg_match("/^((-|_)*[a-z\d]((-|_)*[a-z\d])*(-|_)*)(\.(-|_)*([a-z\d]((-|_)*[a-z\d])*))*$/i", $domain_name) && // Valid chars check
        preg_match("/^.{1,253}$/", $domain_name) && // Overall length check
        preg_match("/^[^\.]{1,63}(\.[^\.]{1,63})*$/", $domain_name)); // Length of each label
}

function get_ip_type($ip)
{
    return  filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? 4 :
           (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? 6 :
            0);
}

function checkfile($filename) : string {
    if (is_readable($filename))
    {
        return $filename;
    }
    else {
        return "/dev/null"; // substitute dummy file
    }
}

// Credit: http://php.net/manual/en/function.hash-equals.php#119576
if(!function_exists('hash_equals')) {
    function hash_equals($known_string, $user_string) {
        $ret = 0;

        if (strlen($known_string) !== strlen($user_string)) {
         $user_string = $known_string;
         $ret = 1;
        }

        $res = $known_string ^ $user_string;

        for ($i = strlen($res) - 1; $i >= 0; --$i) {
         $ret |= ord($res[$i]);
        }

        return !$ret;
   }
}

function add_regex($regex, $mode=FILE_APPEND, $append="\n")
{
    global $regexfile;
    if(file_put_contents($regexfile, $regex.$append, $mode) === FALSE)
    {
        $err = error_get_last()["message"];
        echo "Unable to add regex \"".htmlspecialchars($regex)."\" to ${regexfile}<br>Error message: $err";
    }
    else
    {
        // Send SIGHUP to pihole-FTL using a frontend command
        // to force reloading of the regex domains
        // This will also wipe the resolver's cache
        echo exec("sudo -n pihole restartdns reload");
    }
}

?>
