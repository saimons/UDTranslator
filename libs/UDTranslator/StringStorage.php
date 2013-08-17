<?php

namespace UDTranslator;

use Nette,
    Nette\Http\Session,
    Nette\Caching\Cache,
    Nette\Utils\Strings;

class StringStorage extends Nette\Object {

    /** @var string */
    public static $namespace = 'UDTranslator';

    /** @var string */
    protected $lang;

    /** @var Nette\Http\SessionSection */
    private $sessionStorage;

    /** @var DBStorage */
    private $dbStorage;

    /** @var Nette\Caching\Cache */
    private $cache;

    /** @var bool */
    private $debugMode;

    public function __construct(Session $session, Nette\Caching\IStorage $cacheStorage, DBStorage $dbStorage) {
        $this->sessionStorage = $session->getSection(self::$namespace);
        $this->cache = new Cache($cacheStorage, self::$namespace);
        $this->dbStorage = $dbStorage;

        if (!isset($this->sessionStorage->newStrings) || !is_array($this->sessionStorage->newStrings)) {
            $this->sessionStorage->setExpiration('+ 1 hour');
            $this->sessionStorage->newStrings = array();
        }
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
     * Delete all sessions and caches
     * @param bool $debug
     */
    public function deleteCache() {
        unset($this->sessionStorage->newStrings[$this->lang]);
        $this->cache->clean(array(
            'tags' => array(
                'dictionary-base',
                'plural-forms-' . $this->lang,
                'localization-' . $this->lang
            )
        ));
    }

    /**
     * Set debug mode
     * @param bool $debugMode
     */
    public function setDebugMode($debugMode = FALSE) {
        $this->debugMode = $debugMode;
    }

    /**
     * Get data for translation
     * @return array
     */
    public function loadDictonary() {
        if (!isset($this->cache['localization-' . $this->lang]) OR $this->debugMode) {
            $dictonary = $this->createDictionary();
        } else {
            $dictonary = $this->cache['localization-' . $this->lang];
        }
        return $dictonary;
    }

    /**
     * Load dictionary for translation and curent lang
     * @return array
     */
    private function createDictionary() {
        $prekladlokalizace = $this->dbStorage->getPrekladlokalizace();
        $dictonary = array();
        foreach ($prekladlokalizace AS $p) {
            $key = bin2hex($p->prekladzaklad->retezec_md5);
            if (isset($dictonary[$key])) {
                if (!isset($dictonary[$key][$p->forma])) {
                    $dictonary[$key][$p->forma] = $p->preklad;
                }
            } else {
                $dictonary[$key][$p->forma] = $p->preklad;
            }
        }
        $this->createLocalizationCache($dictonary);
        return $dictonary;
    }

    /**
     * Create localization dictionary cache
     * @param array $dictonary
     */
    private function createLocalizationCache($dictonary) {
        $this->cache->save('localization-' . $this->lang, $dictonary, array(
            Cache::EXPIRE => '+ 30 days',
            Cache::TAGS => array('localization-' . $this->lang, 'UDTranslator')
        ));
    }

    /**
     * Save untranslated strings
     * @param string $message
     */
    public function setMessage($message) {
        $this->saveUntranslatedStringOnPage($message);
        $this->saveToDB($message);
    }

    /**
     * Save to session untranslated string on page
     * @param string $message
     */
    private function saveUntranslatedStringOnPage($message) {
        if ($this->sessionStorage) {
            if (!isset($this->sessionStorage->newStrings[$this->lang])) {
                $this->sessionStorage->newStrings[$this->lang] = array();
            }
            if (!in_array($message, $this->sessionStorage->newStrings[$this->lang])) {
                $this->sessionStorage->newStrings[$this->lang][] = md5($message);
            }
        }
    }

    /**
     * Return untranslated string on the page for active lang
     * @return array
     */
    public function getUntranslatedStringOnPage() {
        if (isset($this->sessionStorage->newStrings[$this->lang])) {
            $untranslatedStringsOnPage = $this->dbStorage->getStringsByHash($this->sessionStorage->newStrings[$this->lang]);
            unset($this->sessionStorage->newStrings[$this->lang]);
        } else {
            $untranslatedStringsOnPage = NULL;
        }
        return $untranslatedStringsOnPage;
    }

    /**
     * Check DB string and fill in if not exist
     * @param string $message
     */
    private function saveToDB($message) {
        if (!isset($this->cache['dictionary-base']) OR $this->debugMode) {
            $allStringsHashs = $this->createDictionaryBaseCache();
        } else {
            $allStringsHashs = $this->cache['dictionary-base'];
        }
        if (!in_array(md5($message), $allStringsHashs)) {
            $this->dbStorage->insertStringToPrekladzaklad($message);
            $allStringsHashs[] = md5($message);
            $this->createCacheDictionaryBase($allStringsHashs);
        }
    }

    /**
     * Create cache of all strings for translate
     * @return array
     */
    private function createDictionaryBaseCache() {
        $allStrings = $this->dbStorage->getPrekladzakladAll();

        $allStringsHashs = array();
        foreach ($allStrings as $as) {
            $allStringsHashs[] = bin2hex($as->retezec_md5);
        }
        $this->createCacheDictionaryBase($allStringsHashs);
        return $allStringsHashs;
    }

    /**
     * Save hashs of strings to cache dictionary-base
     * @param array $hashs MD5
     */
    private function createCacheDictionaryBase($hashs) {
        $this->cache->clean(array(
            'tags' => 'dictionary-base'
        ));
        $this->cache->save('dictionary-base', $hashs, array(
            Cache::EXPIRE => '+ 30 days',
            Cache::TAGS => array('dictionary-base', 'UDTranslator')
        ));
    }

    /**
     * Get count of plurals form for curent language
     * @return int
     */
    public function getNPlurals() {
        if (!isset($this->cache['plural-forms-' . $this->lang]) OR $this->debugMode) {
            $pluralFroms = $this->dbStorage->getPluralForms();
            $this->cache->save('plural-forms-' . $this->lang, $pluralFroms, array(
                Cache::EXPIRE => '+ 30 days',
                Cache::TAGS => array('plural-forms-' . $this->lang, 'UDTranslator')
            ));
        } else {
            $pluralFroms = $this->cache['plural-forms-' . $this->lang];
        }
        return (int) substr($pluralFroms, 9, 1);
    }

    /**
     * Get plural variants for curent lang
     * @return array
     */
    public function getPluralVariants() {
        $compare = 100;
        $buffer = array();
        for ($i = 1; $i < 10; $i++) {
            $result = $this->getPluralForms($i);
            if ($result != $compare) {
                $buffer[] = $i;
            }
            $compare = $result;
        }
        return $buffer;
    }

    /**
     * Get plural form by number
     * @param int $n
     * @return int
     */
    public function getPluralForms($n) {
        if (!isset($this->cache['plural-forms-' . $this->lang]) OR $this->debugMode) {
            $pluralFroms = $this->dbStorage->getPluralForms();
            $this->cache->save('plural-forms-' . $this->lang, $pluralFroms, array(
                Cache::EXPIRE => '+ 30 days',
                Cache::TAGS => array('plural-forms-' . $this->lang, 'UDTranslator')
            ));
        } else {
            $pluralFroms = $this->cache['plural-forms-' . $this->lang];
        }
        $string = $this->sanitize_plural_expression($pluralFroms);
        $string = str_replace('nplurals', "\$total", $string);
        $string = str_replace("n", $n, $string);
        $string = str_replace('plural', "\$plural", $string);

        $total = 0;
        $plural = 0;

        eval("$string");
        if ($plural >= $total) {
            $plural = $total - 1;
        }
        return $plural + 1;
    }

    /**
     * Sanitize plural form expression for use in PHP eval call.
     * @return string sanitized plural form expression
     */
    private function sanitize_plural_expression($expr) {
        // Get rid of disallowed characters.
        $expr = preg_replace('@[^a-zA-Z0-9_:;\(\)\?\|\&=!<>+*/\%-]@', '', $expr);

        // Add parenthesis for tertiary '?' operator.
        $expr .= ';';
        $res = '';
        $p = 0;
        for ($i = 0; $i < strlen($expr); $i++) {
            $ch = $expr[$i];
            switch ($ch) {
                case '?':
                    $res .= ' ? (';
                    $p++;
                    break;
                case ':':
                    $res .= ') : (';
                    break;
                case ';':
                    $res .= str_repeat(')', $p) . ';';
                    $p = 0;
                    break;
                default:
                    $res .= $ch;
            }
        }
        return $res;
    }

    /**
     * Is string has plural form
     * @param string $string
     */
    public function isPlural($string) {
        return Strings::contains($string, '%s');
    }

    /**
     * Save translation and invalidate cache
     * @param int $$translationbase_id
     * @param array $translation
     * @param int $uzer_id
     */
    public function saveTranslation($translationbase_id, $translation, $user_id) {
        $this->dbStorage->saveTranslation($translationbase_id, $translation, $user_id);
        $this->cache->clean(array(
            'tags' => array(
                'localization-' . $this->lang
            )
        ));
    }
}
