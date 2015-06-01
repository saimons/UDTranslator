<?php

namespace UDTranslator;

use Nette,
        Nette\Database\Context,
    Nette\Database\SqlLiteral;

class DBStorage extends Nette\Object {

    /** @var Nette\Database\Connection */
    private $database;

    /** @var string */
    protected $lang;

    /** @var int */
    private $sysjazyk_id;

    /** @var string */
    private $pluralForms;

    public function __construct(Context $database) {
        $this->database = $database;
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
        return $this;
    }

    /**
     * Object of table: sysjazyk
     * @return Nette\Database\Table\Selection
     */
    private function getSysjazyk() {
        return $this->database->table('sysjazyk');
    }

    /**
     * Object of table: prekladzaklad
     * @return Nette\Database\Table\Selection
     */
    private function getPrekladzaklad() {
        return $this->database->table('prekladzaklad');
    }

    /**
     * Object of table: prekladlokalizace
     * @return Nette\Database\Table\Selection
     */
    public function getPrekladlokalizace() {
        $sysjazyk_id = $this->getSysjazyk()->where('zkratka', $this->lang)->fetch()->id;
        return $this->database->table('prekladlokalizace')->where('sysjazyk_id', $sysjazyk_id);
    }

    /**
     * Return all rows from prekladZaklad
     * @return Nette\Database\Table\Selection
     */
    public function getPrekladzakladAll() {
        return $this->getPrekladzaklad();
    }

    /**
     * Insert string to DB
     * @param type $message
     * @return boolean
     */
    public function insertStringToPrekladzaklad($message) {
        try {
            $this->getPrekladzaklad()->insert(array('retezec' => $message));
            return TRUE;
        } catch (\PDOException $e) {
            if ($e->getCode() == 23000) {
                return FALSE;
            } else {
                throw new Nette\InvalidStateException('Something wrong during saving data to database.');
            }
        }
    }

    /**
     * Get plural forms for selected language
     * @return string
     */
    public function getPluralForms() {
        return $this->pluralForms ? : $this->pluralForms = $this->getSysjazyk()->where('zkratka', $this->lang)->fetch()->forma;
    }

    /**
     * Get all unclassified strings
     * @return Nette\Database\Table\Selection
     */
    public function getUnclassified() {
        return $this->getPrekladzaklad()->where('prekladkategorie_id IS NULL');
    }

    /**
     * Get all untranslated strings for curent language
     * @return Nette\Database\Table\Selection
     */
    public function getUntranslated() {
        $translated = $this->getPrekladlokalizace()->select('DISTINCT prekladlokalizace.prekladzaklad_id AS id');
        if (count($translated)) {
            return $this->getPrekladzaklad()->where('prekladkategorie_id IS NOT NULL')->where('neprelozitelne', 0)->where('id NOT IN (?)', $translated);
        } else {
            return $this->getPrekladzaklad()->where('prekladkategorie_id IS NOT NULL')->where('neprelozitelne', 0);
        }
    }

    /**
     * Get untranslated strings on page with category
     * @param array $hashs
     * @return Nette\Database\Table\Selection
     */
    public function getStringsByHash($hashs) {
        return $this->getPrekladzaklad()->where('MD5(retezec) IN ?', array_values($hashs))->where('prekladkategorie_id IS NOT NULL')->where('neprelozitelne', 0);
    }

    /**
     * Get untranslated string on page with category
     * @param int $prekladzaklad_id
     * @return Nette\Database\Table\Selection
     */
    public function getStringsBID($prekladzaklad_id) {
        return $this->getPrekladzaklad()->where('id', $prekladzaklad_id)->fetch();
    }

    /**
     * Get translation of selectyd string
     * @param int $prekladzaklad_id
     * @return Nette\Database\Table\Selection
     */
    public function getTranslationOfString($prekladzaklad_id) {
        return $this->getPrekladlokalizace()->where('prekladzaklad_id', $prekladzaklad_id);
    }

    /**
     * Get translation of selectyd string fatch pairs
     * @param int $prekladzaklad_id
     * @return Nette\Database\Table\Selection
     */
    public function getTranslationofStringFatchPairs($prekladzaklad_id) {
        return $this->getTranslationOfString($prekladzaklad_id)->fetchPairs('forma', 'preklad');
    }

    /**
     * Get sysjazyk_id by zkratka
     * @return int
     */
    public function getSysjazykID() {
        return $this->sysjazyk_id ? : $this->sysjazyk_id = $this->getSysjazyk()->where('zkratka', $this->lang)->fetch()->id;
    }

    /**
     * Save untranslated string to DB
     * @param type $prekladzaklad_id
     * @param type $translation
     * @param type $uzivatel_id
     */
    public function saveTranslation($prekladzaklad_id, $translation, $uzivatel_id) {
        //$this->getPrekladlokalizace()->where('prekladzaklad_id', $prekladzaklad_id)->delete();
        foreach ($translation->text AS $forma => $t) {
            $result = TRUE;
            $exist = (bool) $this->getPrekladlokalizace()->where('prekladzaklad_id', $prekladzaklad_id)->where('forma', $forma)->where('preklad', $t)->count();
            if (!$exist) {
                $result = $this->getPrekladlokalizace()->where('prekladzaklad_id', $prekladzaklad_id)->where('forma', $forma)->update(array(
                    'uzivatel_id' => $uzivatel_id,
                    'datumCas' => new SqlLiteral('NOW()'),
                    'preklad' => $t
                ));
            }
            if (!$result) {
                $this->database->table('prekladlokalizace')->insert(array(
                    'uzivatel_id' => $uzivatel_id,
                    'sysjazyk_id' => $this->getSysjazykID(),
                    'prekladzaklad_id' => $prekladzaklad_id,
                    'preklad' => $t,
                    'datumCas' => new SqlLiteral('NOW()'),
                    'forma' => $forma
                ));
            }
        }
        if (isset($translation->category_id)) {
            $this->getPrekladzaklad()->where('id', $prekladzaklad_id)->update(array('prekladkategorie_id' => $translation->category_id));
        }
    }

    /**
     * Get category names
     * @return array
     */
    public function getCategories() {
        return $this->database->table('prekladkategorie')->order('nazev')->fetchPairs('id', 'nazev');
    }

    /**
     * Assign category to strings
     * @param int $prekladkategorie_id
     * @param array $data
     * @param bool $neprelozitelne
     * @return bool
     */
    public function saveCategory($prekladkategorie_id, $data, $neprelozitelne = FALSE) {
        foreach ($data AS $k => $s) {
            if ($s) {
                $this->getPrekladzaklad()->where('id', $k)->update(array('prekladkategorie_id' => $prekladkategorie_id, 'neprelozitelne' => $neprelozitelne));
            }
        }
    }

    /**
     * Get translated string
     * @return Nette\Database\Table\Selection
     */
    public function getTranslatedStrings() {
        $translated = $this->getPrekladlokalizace()->select('DISTINCT prekladlokalizace.prekladzaklad_id AS id');
        if (count($translated)) {
            return $this->getPrekladzaklad()->where('prekladkategorie_id IS NOT NULL')->where('id IN (?)', $translated);
        }
    }

    /**
     * Apply category filter for strings
     * @param Nette\Database\Table\Selection $data
     * @param int $prekladkategorie_id
     * @return Nette\Database\Table\Selection
     */
    public function applyCategoryFilter($data, $prekladkategorie_id = NULL) {
        if ($prekladkategorie_id) {
            $data->where('prekladkategorie_id', $prekladkategorie_id);
        }
        return $data;
    }

    /**
     * Delete unused string from DB by id
     * @param int $id
     */
    public function deleteString($id) {
        $this->getPrekladzaklad()->where('id', $id)->limit(1)->delete();
    }

}