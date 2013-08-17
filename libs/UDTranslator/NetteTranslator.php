<?php

namespace UDTranslator;

use Nette;

class NetteTranslator extends Nette\Object implements Nette\Localization\ITranslator {

    /** @var string */
    protected $lang;

    /** @var array */
    protected $dictionary = array();

    /** @var bool */
    private $loaded = FALSE;

    /** @var UDTranslator\Diagnostics */
    private $stringStorage;

    /** @var bool */
    private $debugMode;
    
    /** @var UDTranslator\Diagnostics */
    private $diagnostics;

    public function __construct(StringStorage $stringstorage, Diagnostics $diagnostics) {
        $this->stringStorage = $stringstorage;
        $this->diagnostics = $diagnostics;
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
        $this->dictionary = array();
        $this->loaded = FALSE;
        $this->stringStorage->setLang($this->lang);
        $this->diagnostics->setLang($this->lang);
        return $this;
    }

    /**
     * Set debug mode
     * @param bool $debugMode
     */
    public function setDebugMode($debugMode) {
        $this->debugMode = $debugMode;
    }

    /**
     * Load data
     */
    protected function loadDictonary() {
        if (!$this->loaded) {
            $this->dictionary = $this->stringStorage->loadDictonary();
            $this->loaded = TRUE;
        }
    }

    /**
     * Translate given string
     * @param string $message
     * @param int $form plural form (positive number)
     * @return string
     */
    public function translate($message, $count = 1) {
        if ($this->debugMode) {
            $this->stringStorage->deleteCache();
            $this->stringStorage->setDebugMode($this->debugMode);
            $this->debugMode = FALSE;
        }
        
        if ($this->diagnostics->isDiagnostics()) {
            $this->diagnostics->unsetString($message);
        }

        if (empty($message)) {
            return NULL;
        }

        $this->loadDictonary();
        $message = (string) $message;

        if (is_array($count) && $count !== NULL) {
            $count = (int) end($count);
        } elseif (is_numeric($count)) {
            $count = (int) $count;
        } elseif (!is_int($count) || $count === NULL) {
            $count = 1;
        }

        $form = $this->stringStorage->getPluralForms($count);

        if (isset($this->dictionary[md5($message)][$form])) {
            $message = $this->dictionary[md5($message)][$form];
        } else {
            $this->stringStorage->setMessage($message);
        }

        if (is_array($message)) {
            $message = current($message);
        }

        if ($form > 1) {
            $message = str_replace(array('%label', '%name', '%value'), array('#label', '#name', '#value'), $message);
            $message = vsprintf($message, $count);
            $message = str_replace(array('#label', '#name', '#value'), array('%label', '%name', '%value'), $message);
        }

        return $message;
    }

}
