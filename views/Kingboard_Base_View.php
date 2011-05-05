<?php
class Kingboard_Base_View extends King23_TemplateView
{
    public function __construct($loginrequired = false)
    {
        if($loginrequired && !Kingboard_Auth::isLoggedIn())
            $this->redirect("/login");
        parent::__construct();
        $reg = King23_Registry::getInstance();
        $this->_context['images'] = $reg->imagePaths;
        $this->_context['baseHost'] = $reg->baseHost;
        
        // when user is logged in we provide user object to all pages, false otherwise
        $this->_context['user'] = Kingboard_Auth::getUser();

        // make sure all views have the XSRF Token available
        $this->_context['XSRF'] = Kingboard_Form::getXSRFToken();
    }
}