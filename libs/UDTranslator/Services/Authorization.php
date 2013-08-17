<?php

namespace UDTranslator\Services;

use Nette\Security\User,
    Nette;

/**
 * 
 * @uses UDTranslator\Service\Editor
 */
class Authorization extends Nette\Object {

    /** @var Nette\Security\User */
    private $user;

    /** @var Nette\Database\Connection */
    private $database;

    /** @var string */
    protected $lang;

    /** @var string */
    private $adminRole;

    /** @var bool */
    private $DBAuth;

    public function __construct(User $user, Nette\Database\Connection $database) {
        $this->user = $user;
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
     * Get user id
     * @return int
     */
    public function getUserID() {
        return $this->user->id;
    }

    /**
     * Set authorization for translation
     * @param type $adminRole
     */
    public function setAdministratorRole($adminRole) {
        $this->adminRole = $adminRole;
    }

    /**
     * Get user role for UD Trenslator
     * @return array
     */
    public function getRole() {
        return $this->roles();
    }

    /**
     * Set of allowed elements
     * @return array
     */
    private function permission($role) {
        $permison['administrator'] = 'all';
        $permison['translator'] = array('editor', 'save-string');
        return $permison[$role];
    }

    /**
     * Return is element allowed for logged user
     * @param string $element
     * @return bool
     */
    public function isAllowed($element) {
        $role = $this->roles();
        $allowed = FALSE;
        foreach ($role AS $r) {
            if ($this->permission($r) === 'all') {
                $allowed = TRUE;
            } elseif (in_array($element, $this->permission($r))) {
                $allowed = TRUE;
            }
        }
        return $allowed;
    }

    /**
     * Roles for UD Translator
     * @return array
     */
    private function roles() {
        $netteRole = $this->user->roles;
        if ($netteRole != 'guest') {
            if ($this->getAuthorization($this->user->id)) {
                $role[] = 'translator';
            }
            if ($this->adminRole === NULL OR $this->adminRole === $netteRole[0]) {
                $role[] = 'administrator';
            }
        }
        return isset($role) ? $role : array();
    }

    /**
     * Check authorization for user and lang
     * @param int $uzivatel_id
     * @return bool
     */
    private function getAuthorization($uzivatel_id) {
        return (bool) $this->DBAuth ? : $this->DBAuth = $this->database->table('prekladopravneni')->where('uzivatel_id', $uzivatel_id)->where('sysjazyk.zkratka', $this->lang)->count();
    }

}
