<?php
/**
 * template trait for a PHPCraft subject
 * traits optional automatic called methods:
 *      setTraitDependencies[trait-name](): calls $this->setTraitDependencies
 *      setTraitInjections[trait-name](): calls $this->setTraitInjections
 *      processRouteTrait[trait-name](): processes route
 *      processConfigurationTrait[trait-name](): processes configuration
 *      initTrait[trait-name](): performs any task needed by trait BEFORE subject action is performed
 * @author vuk <http://vuk.bg.it>
 */
 
namespace PHPCraft\Subject\Traits;

use PHPCraft\PDF\TemplateInterface;

trait Mail{
    
    /**
    * included trait flag 
    **/
    protected $hasMail = true;
    
    /**
    * Mailer instance
    **/
    protected $mailer;
    
    /**
     * Sets trait needed injections
     **/
    protected function setTraitInjectionsMail()
    {
        $this->setTraitInjections('Mail', ['mailer']);
    }
    
    /**
     * injects mailer
     * @param \PHPMailer\PHPMailer\PHPMailer $mailer
     */
    public function injectMailer(\PHPMailer\PHPMailer\PHPMailer $mailer)
    {
        $this->mailer = $mailer;
    }
    
    /**
     * Inits trait
     **/
    protected function initTraitMail()
    {
        $this->mailer->CharSet = 'UTF-8';
        $this->mailer->isHTML(true);
    }
    
    /**
     * Sets SMTP
     * @param string $host
     * @param int $port
     * @param string $username
     * @param string $password
     * @param string $security: tls|ssl
     **/
    protected function setSMTP($host, $port, $username = false, $password = false, $security = false)
    {
        $this->mailer->IsSMTP();
        $this->mailer->Host = $host;
        if($username && $password) {
            $this->mailer->SMTPAuth = true;
            //$this->mailer->SMTPAutoTLS = false;
            $this->mailer->Username = $username;
            $this->mailer->Password = $password;
            if($security) {
                $this->mailer->SMTPSecure = $security;
            }
            $this->mailer->Port = $port;
        }
    }
    
    /**
     * Turns on email debug
     **/
    protected function debugEmail()
    {
        $this->mailer->SMTPDebug = 2;
        echo '<pre>';
    }
    
    /**
     * Disables called host SSL certificate verification
     **/
    protected function allowInsecureSMTPConnection()
    {
        $this->mailer->SMTPOptions = array(
                'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
    }
    
    /**
     * Sends email
     **/
    protected function sendEmail()
    {
        try{
            $r = $this->mailer->send();
        } catch (Exception $e) {
            echo 'Message could not be sent.';
            echo 'Mailer Error: ' . $this->mailer->ErrorInfo;
        }
    }
    
    /**
     * Tests SMTP
     * @param string $host
     * @param int $port
     **/
    protected function testSMTP($host, $port)
    {
        echo '<pre>';
        $smtp = new \PHPMailer\PHPMailer\SMTP;
        //Enable connection-level debug output
        $smtp->do_debug = \PHPMailer\PHPMailer\SMTP::DEBUG_CONNECTION;
        try {
            //Connect to an SMTP server
            if (!$smtp->connect($host, $port)) {
                throw new \PHPMailer\PHPMailer\Exception('Connect failed');
            }
            //Say hello
            if (!$smtp->hello(gethostname())) {
                throw new \PHPMailer\PHPMailer\Exception('EHLO failed: ' . $smtp->getError()['error']);
            }
            //Get the list of ESMTP services the server offers
            $e = $smtp->getServerExtList();
            //If server can do TLS encryption, use it
            if (is_array($e) && array_key_exists('STARTTLS', $e)) {
                $tlsok = $smtp->startTLS();
                if (!$tlsok) {
                    throw new \PHPMailer\PHPMailer\Exception('Failed to start encryption: ' . $smtp->getError()['error']);
                }
                //Repeat EHLO after STARTTLS
                if (!$smtp->hello(gethostname())) {
                    throw new \PHPMailer\PHPMailer\Exception('EHLO (2) failed: ' . $smtp->getError()['error']);
                }
                //Get new capabilities list, which will usually now include AUTH if it didn't before
                $e = $smtp->getServerExtList();
            }
            //If server supports authentication, do it (even if no encryption)
            if (is_array($e) && array_key_exists('AUTH', $e)) {
                if ($smtp->authenticate('username', 'password')) {
                    echo "Connected ok!";
                } else {
                    throw new \PHPMailer\PHPMailer\Exception('Authentication failed: ' . $smtp->getError()['error']);
                }
            }
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            echo 'SMTP error: ' . $e->getMessage(), "\n";
        }
        //Whatever happened, close the connection.
        $smtp->quit(true);
        echo '</pre>';
    }
}