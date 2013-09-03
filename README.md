UDTranslator
============

Plugin na lokalizace webu pro Nette Framework.

Instalace
---------

config.neon:
```neon
        UDTranslator:
                adminRole: administrator
                debugMode: TRUE
                diagnostics:
                        time: +1 day
                        path: %tempDir%/cache/_UDTranslator/
                        limit: 300
```

adminRole: nazev role z Nette\Security\User, ktera ma administratorska prava v UDTRanslatoru, NULL vsichni muzou editovat vse

debugMode: TRUE/FALSE cachovani retezcu

diagnostics: slouzi pro vyhledavani nepuzivanych stringu

time: minnimalni doba, po ktere se cachuji nekde pouzite stringy a pote je aktualizovan vysledny stav, ktery je zobrazen

path: umisteni souboru pro cachovani pouzitych stringu

limit: pokud je pocet nacachovanych stringu mensi nez tato hodnota, cache se uz nebude pouzivat a pri nalezeni noveho retezce se invaliduje vnitrni uloziste
  
Cache se vyuziva proto, aby byl potlacen prvotni napor pri zapnuti diagnostiky. Pote co odezni a dostane se pocet nepouzitich stringu na uroven v limit, se uloziste invaliduje pri kazdem dalsim najiti noveho stringu.

basepresenter.php
```php
abstract class BasePresenter extends Presenter {

    /** @persistent  */
    public $lang;
    
    /** @var UDTranslator\NetteTranslator */
    protected $translator;
    
    /** @var UDTranslator\Services\Editor */
    protected $translatorEditor;

    /**
     * 
     * @return array
     */
    public static function getPersistentParams() {
        return array('lang');
    }    
    
    /**
     * @param GettextTranslator\Gettext
     */
    public function injectUDTranslator(NetteTranslator $translator, Editor $editor)
    {
        $this->translator = $translator;
        $this->translatorEditor = $editor;
        
    }
    
    /**
     * 
     * @param type $class
     * @return type
     */
    public function createTemplate($class = NULL)
    {
        $template = parent::createTemplate($class);        
        // if not set, the default language will be used
        if (!isset($this->lang)) {
            $this->lang = 'en';
        } else {
            $this->translator->setLang($this->lang);
        }
        $template->setTranslator($this->translator);
        return $template;
    }
    
    /**
     * Component for editing translation
     * @return DTranslator\Services\Editor
     */
    protected function createComponentTranslatorEditor() {
        $this->translatorEditor->setLang($this->lang);
        return $this->translatorEditor;
    }
}
```

bootstrap.php
```php
UDTranslator\DI\UDTranslator::register($configurator);
```

Umistit na konec html soubru
layout.latte
```latte
{control translatorEditor}
</body>
```

CSS: nahrat styly ze souboru.
