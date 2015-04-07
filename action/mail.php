<?php
/**
 * @license GNU General Public License, version 2
 */


if (!defined('DOKU_INC')) die();


/**
 * Class action_plugin_publish_mail
 *
 * @author Michael Große <grosse@cosmocode.de>
 */
class action_plugin_publish_mail extends DokuWiki_Action_Plugin {

    /**
     * @var helper_plugin_publish
     */
    private $hlp;

    function __construct() {
        $this->hlp = plugin_load('helper','publish');
    }

    public function register(Doku_Event_Handler $controller) {

        $controller->register_hook('IO_WIKIPAGE_WRITE', 'AFTER', $this, 'send_change_mail', array());
    }

    // Funktion versendet eine Änderungsmail
    function send_change_mail(&$event, $param) {
        global $ID;
        global $ACT;
        global $REV;
        global $INFO;
        global $conf;
        $data = pageinfo();

        if ($ACT != 'save') {
            return true;
        }

        // IO_WIKIPAGE_WRITE is always called twice when saving a page. This makes sure to only send the mail once.
        if (!$event->data[3]) {
            return true;
        }

        // Does the publish plugin apply to this page?
        if (!$this->hlp->isActive($ID)) {
            return true;
        }

        //are we supposed to send change-mails at all?
        if (!$this->getConf('send_mail_on_change')) {
            return true;
        }

        // get mail receiver
        $receiver = $this->getConf('apr_mail_receiver');

        // get mail sender
        $sender = $data['userinfo']['mail'];

        if ($sender == $receiver) {
            dbglog('[publish plugin]: Mail not send. Sender and receiver are identical.');
            return true;
        }

        if ($INFO['isadmin'] == '1') {
            dbglog('[publish plugin]: Mail not send. Sender is admin.');
            return true;
        }

        // get mail subject
        $timestamp = $data['lastmod'];
        $datum = date("d.m.Y",$timestamp);
        $uhrzeit = date("H:i",$timestamp);
        $subject = $this->getLang('apr_mail_subject') . ': ' . $ID . ' - ' . $datum . ' ' . $uhrzeit;
        dbglog($subject);

        $body = $this->create_approve_mail_body($ID, $data);


        dbglog('mail_send?');
        $returnStatus = mail_send($receiver, $subject, $body, $sender);
        dbglog($returnStatus);
        dbglog($body);
        return $returnStatus;
    }

    public function create_change_mail_body($id, $pageinfo) {
        global $conf;
        // get mail text
        $body = $this->getLang('mail_greeting') . "\n";
        $body .= $this->getLang('mail_new_suggestiopns') . "\n\n";

        $rev = $pageinfo['lastmod'];

        //If there is no approved revision show the diff to the revision before. Otherwise show the diff to the last approved revision.
        if ($this->hlp->hasApprovals($pageinfo['meta'])) {
            $body .= $this->getLang('mail_changes_to_approved_rev') . "\n\n";
            $difflink = $this->hlp->getDifflink($id, $this->hlp->getLatestApprovedRevision($id), $rev);
        } else {
            $body .= $this->getLang('mail_changes_to_previous_rev') . "\n\n";
            $changelog = new PageChangelog($id);
            $prevrev = $changelog->getRelativeRevision($rev,-1);
            $difflink = $this->hlp->getDifflink($id, $prevrev, $rev);
        }

        $body .= $this->getLang('mail_dw_signature');

        $body = str_replace('@CHANGES@', $difflink, $body);
        $apprejlink = $this->apprejlink($id, $rev);
        $body = str_replace('@APPREJ@', $apprejlink, $body);

        $body = str_replace('@DOKUWIKIURL@', DOKU_URL, $body);
        $body = str_replace('@FULLNAME@', $pageinfo['userinfo']['name'], $body);
        $body = str_replace('@TITLE@', $conf['title'], $body);

        return $body;
    }



    /**
     * Send approve-mail to editor of the now approved revision
     *
     * @return mixed
     */
    public function send_approve_mail() {
        dbglog('send_approve_mail()');
        global $ID;
        global $ACT;
        global $REV;

        /** @var DokuWiki_Auth_Plugin $auth */
        global $auth;
        global $conf;
        $data = pageinfo();

        if ($ACT != 'save') {
           // return true;
        }

        // get mail receiver
        $changelog = new PageChangelog($ID);
        $revinfo = $changelog->getRevisionInfo($REV);
        $userinfo = $auth->getUserData($revinfo['user']);
        $receiver = $userinfo['mail'];
        dbglog('$receiver: ' . $receiver);
        // get mail sender
        $sender = $data['userinfo']['mail'];
        dbglog('$sender: ' . $sender);
        // get mail subject
        $subject = $this->getLang('apr_mail_app_subject');
        dbglog('$subject: ' . $subject);
        // get mail text
        $body = $this->getLang('apr_approvemail_text');
        $body = str_replace('@DOKUWIKIURL@', DOKU_URL, $body);
        $body = str_replace('@FULLNAME@', $data['userinfo']['name'], $body);
        $body = str_replace('@TITLE@', $conf['title'], $body);

        $url = wl($ID, array('rev'=>$this->hlp->getLatestApprovedRevision($ID)), true, '&');
        $url = '"' . $url . '"';
        $body = str_replace('@URL@', $url, $body);
        dbglog('$body: ' . $body);

        return mail_send($receiver, $subject, $body, $sender);
    }

    /**
     * erzeugt den Link auf die edit-Seite
     *
     * @param $id
     * @param $rev
     * @return string
     */
    function apprejlink($id, $rev) {

        $options = array(
             'rev'=> $rev,
        );
        $apprejlink = wl($id, $options, true, '&');

        return $apprejlink;
    }

}
