<?php

namespace UDTranslator;

use Nette,
    Nette\Caching\Cache,
    Nette\Utils\Strings;

class Diagnostics extends Nette\Object {

    /** @var string */
    public static $namespace = 'UDTranslator';

    /** @var string */
    protected $lang;

    /** @var DBStorage */
    private $dbStorage;

    /** @var Nette\Caching\Cache */
    private $cache;

    /** @var string */
    private $time;

    /** @var string */
    private $path;

    /** @var int */
    private $limit;

    /** @var string */
    private $filename = '_diagnostics.php';

    public function __construct(Nette\Caching\IStorage $cacheStorage, DBStorage $dbStorage) {
        $this->cache = new Cache($cacheStorage, self::$namespace);
        $this->dbStorage = $dbStorage;
    }

    /**
     * Set new language
     * @return this
     */
    public function setLang($lang) {
        if (empty($lang)) {
            throw new Nette\InvalidStateException('Language must be nonempty string.');
        }
        if ($this->lang === $lang) {
            return;
        }
        $this->lang = $lang;
        $this->dbStorage->setLang($this->lang);
        return $this;
    }

    /**
     * Set time for diagnostics
     * @param string $option
     */
    public function setDiagnosticsTime($time) {
        $this->time = (string) $time;
    }

    /**
     * Set path for diagnostics
     * @param string $option
     */
    public function setDiagnosticsPath($path) {
        $this->path = (string) $path;
    }

    /**
     * Set limit for diagnostics
     * @param int $option
     */
    public function setDiagnosticsLimit($limit) {
        $this->limit = (int) $limit;
    }

    /**
     * Is diagnostics running
     * @return bool
     */
    public function isDiagnostics() {
        return isset($this->cache['diagnostics']);
    }

    /**
     * Create cache from DB
     */
    public function turnOnDiagnostics() {
        if ($this->path AND $this->time AND $this->limit) {
            $cache = array('date' => new \DateTime, 'extfile' => TRUE, 'strings' => array());
            foreach ($this->dbStorage->getPrekladzakladAll() AS $s) {
                $cache['strings'][] = bin2hex($s->retezec_md5);
            }
            $this->createCache($cache);
            if (!$this->createFile()) {
                return 'Failed to create cache file.';
            }
        } else {
            return 'You have to set the options for diagnostic in config file.';
        }
    }

    /**
     * Delete cache for diagnostics
     */
    public function turnOfDiagnostics() {
        $this->cleanCache();
    }

    /**
     * Clean cache
     */
    private function cleanCache() {
        $this->cache->clean(array(
            'tags' => array(
                'diagnostics'
            )
        ));
    }

    /**
     * Get strings which wasn't used
     * @return Nette\Database\Table\Selection
     */
    public function getDiagnostics() {
        if ($this->isDiagnostics()) {
            $cache = $this->cache['diagnostics'];
            return (object) array('strings' => $this->dbStorage->getStringsByHash($cache['strings']), 'date' => $cache['date']);
        } else {
            return NULL;
        }
    }

    /**
     * Delete unused strings from DB
     * @param int $id
     */
    public function deleteDiagnostic($id) {
        if ($id) {
            $this->dbStorage->deleteString($id);
        } else {
            $this->dbStorage->getStringsByHash($this->cache['diagnostics']['strings'])->delete();
            $this->turnOfDiagnostics();
        }
    }

    /**
     * Delete hash of string from cache storage
     * @param string $message
     */
    public function unsetString($message) {
        $hash = md5($message);
        $cache = $this->cache['diagnostics'];

        if (in_array($hash, $cache['strings'])) {            
            $this->checkString($hash, $cache);            
        }
    }

    /**
     * Find if use file cache or Nette cache
     * @param hash $hash
     * @param array $cache
     */
    private function checkString($hash, $cache) {
        if ($cache['extfile']) {
            $this->saveKeyFile($hash);
            $date = $cache['date'];
            $date->add(\DateInterval::createFromDateString($this->time));
            if (new \DateTime > $date) {
                $this->rebuildCache($cache);
            }
        } else {
            $stringCache = array_flip($cache['strings']);
            unset($stringCache[$hash]);
            $this->cleanCache();
            $cache = array('date' => new \DateTime, 'extfile' => FALSE, 'strings' => array_flip($stringCache));
            $this->createCache($cache);
        }
    }

    /**
     * Create file for file cache
     * @return bool
     */
    private function createFile() {
        $data = '<?php $_S = array();';
        return file_put_contents($this->path . $this->filename, $data);
    }

    /**
     * Save to cache file hash key
     * @param string $hash hash
     */
    private function saveKeyFile($hash) {
        require ($this->path . $this->filename);
        if (!in_array($hash, $_S)) {
            $data = '$_S[]="' . $hash . '";';
            file_put_contents($this->path . $this->filename, $data, FILE_APPEND | LOCK_EX);
        }
    }

    /**
     * Find if cache should be rebuild or stop file cache 
     * @param array $cache
     */
    private function rebuildCache($cache) {
        require ($this->path . $this->filename);
        $count = count($_S);
        $stringsFromCache = array_flip($cache['strings']);
        foreach ($_S AS $f) {
            unset($stringsFromCache[$f]);
        }
        $this->createFile();
        $this->cleanCache();
        $cache = array('date' => new \DateTime, 'extfile' => ($count <= $this->limit ? FALSE : TRUE), 'strings' => array_flip($stringsFromCache));
        $this->createCache($cache);
    }

    /**
     * Create cache
     * @param array $cache
     */
    private function createCache($cache) {
        $this->cache->save('diagnostics', $cache, array(
            Cache::EXPIRE => '+ 5 months',
            Cache::TAGS => array('diagnostics', 'UDTranslator')
        ));
    }

}