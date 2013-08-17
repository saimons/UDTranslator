<?php

namespace UDTranslator\DI;

use Nette;

if (!class_exists('Nette\DI\CompilerExtension')) {
    class_alias('Nette\Config\CompilerExtension', 'Nette\DI\CompilerExtension');
}

class UDTranslator extends Nette\DI\CompilerExtension {

    /** @var array */
    private $defaults = array(
        'adminRole' => NULL, //everyone can set category of string
        'debugMode' => FALSE,
        'diagnostics' => array('time' => FALSE, 'path' => FALSE, 'limit' => FALSE)
    );

    public function loadConfiguration() {
        $config = $this->getConfig($this->defaults);
        $builder = $this->getContainerBuilder();

        $builder->addDefinition($this->prefix('DBStorage'))
                ->setClass('UDTranslator\DBStorage', array('@database'));

        $builder->addDefinition($this->prefix('stringstorage'))
                ->setClass('UDTranslator\StringStorage', array('@session', '@cacheStorage', $this->prefix('@DBStorage')));
        
        $builder->addDefinition($this->prefix('diagnostics'))
                ->setClass('UDTranslator\Diagnostics', array('@cacheStorage', $this->prefix('@DBStorage')))
                ->addSetup('setDiagnosticsTime', $config['diagnostics']['time'])
                ->addSetup('setDiagnosticsPath', $config['diagnostics']['path'])
                ->addSetup('setDiagnosticsLimit', $config['diagnostics']['limit']);

        $builder->addDefinition($this->prefix('Translator'))
                ->setClass('UDTranslator\NetteTranslator', array($this->prefix('@stringstorage'), $this->prefix('@diagnostics')))
                ->addSetup('setDebugMode', $config['debugMode']);

        $builder->addDefinition($this->prefix('UDTAuthorization'))
                ->setClass('UDTranslator\Services\Authorization', array('@user', '@database'))
                ->addSetup('setAdministratorRole', $config['adminRole']);

        $builder->addDefinition($this->prefix('editor'))
                ->setClass('UDTranslator\Services\Editor', array($this->prefix('@stringstorage'), $this->prefix('@DBStorage'), $this->prefix('@UDTAuthorization'), $this->prefix('@diagnostics')));
    }

    /**
     * @param \Nette\Configurator $config
     */
    public static function register(Nette\Configurator $config) {
        $config->onCompile[] = function ($config, $compiler) {
                    $compiler->addExtension('UDTranslator', new UDTranslator);
                };
    }

}

class InvalidConfigException extends Nette\InvalidStateException {
    
}
