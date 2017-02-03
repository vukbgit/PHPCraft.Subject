<?php
namespace PHPCraft\Subject;

trait SubjectWithSideMenuTrait {
    
    /**
     * gets menu opening state by checking cookie 'side_menu_opening_state'
     * @return boolean true if openend, false if closed
     */
    public function isSideMenuOpened() {
        $state = $this->cookie->get('side_menu_opening_state');
        return (!$state || $state == 'opened');
    }    
}