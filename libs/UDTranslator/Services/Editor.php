<?php

namespace UDTranslator\Services;

use Nette\Application\UI,
    UDTranslator\StringStorage,
    UDTranslator\DBStorage,
    UDTranslator\Diagnostics,
    Nette\Application\UI\Form,
    Nette\Utils\Html;

/**
 * 
 * @uses UDTranslator\Service\Editor
 */
class Editor extends UI\Control {

    /** @var UDTranslator\Diagnostics */
    private $stringStorage;

    /** @var string */
    protected $lang;

    /** @var UDTranslator\DBStorage */
    private $dbStorage;

    /** @var UDTranslator\Diagnostics */
    private $diagnostics;

    /** @var bool */
    private $pages = array();

    /** @var bool */
    private $plural = TRUE;

    /** @persistent */
    public $translationbase_id;

    /** @persistent */
    public $category_id;

    /** @var string */
    private $authorization;

    public function __construct(StringStorage $stringstorage, DBStorage $dbStorage, Authorization $authorization, Diagnostics $diagnostics) {
        parent::__construct();
        $this->stringStorage = $stringstorage;
        $this->dbStorage = $dbStorage;
        $this->authorization = $authorization;
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
        $this->dbStorage->setLang($this->lang);
        $this->stringStorage->setLang($this->lang);
        $this->authorization->setLang($this->lang);
        return $this;
    }

    public function render() {
        $template = $this->getTemplate();
        $template->setFile(dirname(__FILE__) . "/default.latte");
        $template->authorization = $this->authorization;

        //handleUntranslatedStringOnPage
        $untranslatedOnPage = $this->stringStorage->getUntranslatedStringOnPage();
        $template->CuntranslatedOnPage = count($untranslatedOnPage);
        if (isset($this->pages['untranslatedStringOnPage']) AND $this->pages['untranslatedStringOnPage']) {
            $template->strings = $this->dbStorage->applyCategoryFilter($untranslatedOnPage, $this->category_id);
        }

        //handleUntranslated
        $untranslated = $this->dbStorage->getUntranslated();
        $template->Cuntranslated = count($untranslated);
        if (isset($this->pages['untranslated']) AND $this->pages['untranslated']) {
            $template->strings = $this->dbStorage->applyCategoryFilter($untranslated, $this->category_id);
        }
        //handleUnclassified()
        $unclassified = $this->dbStorage->getUnclassified();
        $template->Cunclassified = count($unclassified);
//        if (isset($this->pages['unclassified']) AND $this->pages['unclassified']) {
//            $template->strings = $this->dbStorage->applyCategoryFilter($unclassified, $this->category_id);
//        }
        //handleTranslated()
        if (isset($this->pages['translated']) AND $this->pages['translated']) {
            $template->strings = $this->dbStorage->applyCategoryFilter($this->dbStorage->getTranslatedStrings(), $this->category_id);
        }

        //handleDiagnostic
        if (isset($this->pages['diagnostics']) AND $this->pages['diagnostics']) {
            $data = $this->diagnostics->getDiagnostics();
            if ($data) {
                $template->strings = $data->strings;
                $template->diagnosticDate = $data->date;
                $template->today = new \DateTime;
            } else {
                $template->strings = NULL;
            }
        }

        $categoryForm = $this['categoryForm'];
        $categoryForm->setDefaults(array('category_id' => $this->category_id));

        $template->page = array_search(TRUE, $this->pages);
        $template->auth = $this->authorization;

        $template->render();
    }

    /**
     * Show untranslated strings on the page
     */
    public function handleUntranslatedStringOnPage() {
        $this->pages['untranslatedStringOnPage'] = TRUE;
    }

    /**
     * Show untranslated strings on the page
     */
    public function handleUntranslated() {
        $this->pages['untranslated'] = TRUE;
    }

    /**
     * Show untranslated strings on the page
     */
    public function handleUnclassified() {
        if ($this->authorization->isAllowed('unclassified')) {
            $this->pages['unclassified'] = TRUE;
        }
    }

    /**
     * Show untranslated strings on the page
     */
    public function handleTranslated() {
        $this->pages['translated'] = TRUE;
    }

    /**
     * Diagnostics for unused strings 
     */
    public function handleDiagnostics() {
        if ($this->authorization->isAllowed('diagnostics')) {
            $this->pages['diagnostics'] = TRUE;
        }
    }

    /**
     * Turn on the diagnostics
     */
    public function handleTurnOnDiagnostics() {
        if ($this->authorization->isAllowed('diagnostics')) {
            $this->pages['diagnostics'] = TRUE;
            $fm = $this->diagnostics->turnOnDiagnostics();
            $this->flashMessage($fm);
        }
    }

    /**
     * Turn off the diagnostics 
     */
    public function handleTurnOffDiagnostics() {
        if ($this->authorization->isAllowed('diagnostics')) {
            $this->pages['diagnostics'] = TRUE;
            $this->diagnostics->turnOfDiagnostics();
        }
    }

    public function handleDeleteDiagnostics($id = 0) {
        if ($this->authorization->isAllowed('diagnostics')) {
            $this->diagnostics->deleteDiagnostic($id);
            $this->pages['diagnostics'] = TRUE;
        }
    }

    /**
     * Generate forms and data for translate
     * @param int $translationbase_id
     */
    public function handleTranslate($translationbase_id, $page) {
        $this->pages[$page] = TRUE;
        $this->translationbase_id = $translationbase_id;

        $this->template->string = $string = $this->dbStorage->getStringsBID($this->translationbase_id);
        $this->plural = $this->stringStorage->isPlural($string->retezec);

        $addTranslationForm = $this['addTranslationForm'];
        $addTranslationForm->setDefaults(array('category_id' => $string->prekladkategorie_id));

        $translationOfStringFatchPairs = $this->dbStorage->getTranslationofStringFatchPairs($this->translationbase_id);
        if (count($translationOfStringFatchPairs)) {
            $addTranslationForm->setDefaults(array('text' => $translationOfStringFatchPairs));
            foreach ($this->dbStorage->getTranslationofString($this->translationbase_id) AS $t) {
                $addTranslationForm['text'][$t->forma]->setOption('description', Html::el('p')->setText("Translated by: " . $t->uzivatel->login . " (" . $t->datumCas->format('d. m. Y H:i') . ")"));
            }
        }
    }

    /**
     * Generate forms and data for classifying string
     * @param int $translationbase_id
     */
    public function handleClassify($translationbase_id, $page) {
        if ($this->authorization->isAllowed('unclassified')) {
            $this->pages[$page] = TRUE;
            $this->translationbase_id = $translationbase_id;

            $this->template->string = $string = $this->dbStorage->getStringsBID($this->translationbase_id);
        }
    }

    /**
     * Component form for translation
     * @return Nette\Application\UI\Form
     */
    protected function createComponentAddTranslationForm() {
        $form = new Form;

        $string = $this->dbStorage->getStringsBID($this->translationbase_id);
        $this->plural = $this->stringStorage->isPlural($string->retezec);

        $pluralForms = $this->stringStorage->getPluralVariants();
        $container = $form->addContainer('text');

        if ($this->authorization->isAllowed('unclassified')) {
            $form->addSelect('category_id', 'Select category', $this->dbStorage->getCategories())
                    ->setRequired('This item must be filled.');
        }

        for ($i = 1; $i <= ($this->plural ? $this->stringStorage->getNPlurals() : 1); $i++) {
            $label = 'Shape: ' . $pluralForms[$i - 1] . (isset($pluralForms[$i]) ? (' - ' . ($pluralForms[$i] - 1)) : ' and more');
            $container->addTextArea($i, $label, 20, 2)
                    ->setRequired('This item must be filled.');
            if ($this->plural) {
                $container[$i]->addRule(Form::PATTERN, 'The string must contain the sign for number.', '.*(%s).*');
            }
        }

        $form->addHidden('page', array_search(TRUE, $this->pages));
        $form->addSubmit('save', 'Save translation');

        $form->onSuccess[] = callback($this, 'addTranslationFormSubmitted');
        return $form;
    }

    public function addTranslationFormSubmitted(Form $form) {
        if ($this->authorization->isAllowed('save-string')) {
            $data = $form->getValues();
            if (!$this->authorization->isAllowed('unclassified')) {
                unset($data->category_id);
            }
            $this->stringStorage->saveTranslation($this->translationbase_id, $data, $this->authorization->userID);
            $this->flashMessage('Translation was saved.');
            $this->presenter->redirect('translatorEditor-' . $data->page . '!');
        }
    }


    /**
     * Component for filtrin category
     * @return Nette\Application\UI\Form
     */
    protected function createComponentCategoryForm() {
        $form = new Form;

        $form->addSelect('category_id', NULL, $this->dbStorage->getCategories())
                ->setPrompt('All categories');

        $form->addHidden('page', array_search(TRUE, $this->pages));
        $form->addSubmit('save', 'Apply filter');

        $form->onSuccess[] = callback($this, 'categoryFormSubmitted');
        return $form;
    }

    public function categoryFormSubmitted(Form $form) {
        $data = $form->getValues();
        $this->category_id = $data->category_id;
        $this->presenter->redirect('translatorEditor-' . $data->page . '!');
    }

    /**
     * Component form for translation
     * @return Nette\Application\UI\Form
     */
    protected function createComponentUnclassifiedForm() {
        $form = new Form;

        $unclassified = $this->dbStorage->getUnclassified();

        $container = $form->addContainer('strings');
        $form->addSelect('category_id', 'Move strings to', $this->dbStorage->getCategories())
                ->setRequired('This item must be filled.');

        foreach ($unclassified AS $u) {
            $container->addCheckbox($u->id, $u->retezec);
        }

        $form->addCheckbox('untranslatable', 'Set all selected strings as untranslatable.');
        $form->addHidden('page', array_search(TRUE, $this->pages));
        $form->addSubmit('save', 'Move translations to categry');

        $form->onSuccess[] = callback($this, 'unclassifiedFormSubmitted');
        return $form;
    }

    public function unclassifiedFormSubmitted(Form $form) {
        if ($this->authorization->isAllowed('unclassified')) {
            $data = $form->getValues();
            $this->dbStorage->saveCategory($data->category_id, $data->strings, $data->untranslatable);
            $this->flashMessage('Category was assigned.');
            $this->presenter->redirect('translatorEditor-' . $data->page . '!');
        }
    }

}