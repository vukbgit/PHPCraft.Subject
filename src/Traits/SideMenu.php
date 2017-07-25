<?php
/**
 * side menu trait for a PHPCraft subject
 * traits optional automatic called methods:
 *      setTraitDependencies[trait-name](): calls $this->setTraitDependencies
 *      setTraitInjections[trait-name](): calls $this->setTraitInjections
 *      processRouteTrait[trait-name](): processes route
 *      processConfigurationTrait[trait-name](): processes configuration
 *      initTrait[trait-name](): performs any task needed by trait BEFORE subject action is performed
 * @author vuk <http://vuk.bg.it>
 */
 
namespace PHPCraft\Subject\Traits;

trait SideMenu{
    
    /**
    * included trait flag 
    **/
    protected $hasSideMenu = true;
    
    /**
     * Sets trait dependencies from other traits
     **/
    public function setTraitDependenciesSideMenu()
    {
        $this->setTraitDependencies('SideMenu', ['Template', 'Cookies']);
    }
    
    /**
     * Inits trait
     **/
    protected function initTraitSideMenu()
    {
        $this->templateParameters['sideMenuOpenend'] = $this->isSideMenuOpened();
    }
    
    /**
     * gets menu opening state by checking cookie 'side_menu_opening_state'
     * @return boolean true if openend, false if closed
     */
    protected function isSideMenuOpened() {
        $state = $this->cookies->get('side_menu_opening_state');
        return (!$state || $state == 'opened');
    }
}