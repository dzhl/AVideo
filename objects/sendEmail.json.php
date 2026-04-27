<?php
require_once __DIR__ . DIRECTORY_SEPARATOR . 'autoload.php';

global $global, $config;
if (!isset($global['systemRootPath'])) {
    require_once '../videos/configuration.php';
}
require_once $global['systemRootPath'] . 'objects/captcha.php';
$config = new AVideoConf();
$valid = Captcha::validation(@$_POST['captcha']);
if(User::isAdmin()){
    $valid = true;
}

// Reject the arbitrary-recipient (share) path for unauthenticated callers.
// Without this guard an unauthenticated attacker can force the site's own
// SMTP infrastructure to send attacker-composed mail to any recipient:
// User::getEmail_() returns '' when not logged in, so $replyTo falls back
// to $config->getContactEmail(), and $mail->setFrom($replyTo) makes the
// message appear to originate From the site's own legitimate address --
// passing SPF/DKIM/DMARC and enabling targeted phishing / brand impersonation.
if (empty($_POST['contactForm']) && !User::isLogged()) {
    $obj = new stdClass();
    $obj->error = __('Authentication required');
    header('Content-Type: application/json');
    echo json_encode($obj);
    exit;
}

$obj = new stdClass();
$obj->error = '';
if ($valid) {
    // Sanitize user inputs to prevent HTML injection (email spoofing/phishing)
    $safeEmail = htmlspecialchars($_POST['email'], ENT_QUOTES, 'UTF-8');
    $safeComment = htmlspecialchars($_POST['comment'], ENT_QUOTES, 'UTF-8');
    // Convert newlines to <br> for proper display in email after sanitization
    $safeComment = nl2br($safeComment);
    $msg = "<b>Email:</b> {$safeEmail}<br><br>{$safeComment}";
    //Create a new PHPMailer instance
    $mail = new \PHPMailer\PHPMailer\PHPMailer();
    setSiteSendMessage($mail);
    //$mail->SMTPDebug = 1; // debugging: 1 = errors and messages, 2 = messages only
    //var_dump($mail->SMTPAuth, $mail);
    //Set who the message is to be sent from

    $replyTo = User::getEmail_();
    if (empty($replyTo)) {
        $replyTo = $config->getContactEmail();
    }

    $sendTo = $_POST['email'];

    // if it is from contact form send the message to the siteowner and the sender is the email on the form field
    if (!empty($_POST['contactForm'])) {
        $replyTo = $_POST['email'];
        $sendTo = $config->getContactEmail();
    }

    if (filter_var($sendTo, FILTER_VALIDATE_EMAIL)) {
        $mail->AddReplyTo($replyTo);
        // For the share (non-contactForm) path always send From the site's own
        // address so the site's SPF/DKIM/DMARC record is never overridden by a
        // user-supplied address. The caller's email remains reachable via
        // Reply-To. For the contact-form path the submitter's address stays in
        // From so the site owner can reply directly to the enquirer.
        $siteFrom = empty($_POST['contactForm']) ? $config->getContactEmail() : $replyTo;
        $mail->setFrom($siteFrom);
        //Set who the message is to be sent to
        $mail->addAddress($sendTo);
        //Set the subject line
        $safeFirstName = htmlspecialchars($_POST['first_name'], ENT_QUOTES, 'UTF-8');
        $mail->Subject = 'Message From Site ' . $config->getWebSiteTitle() . " ({$safeFirstName})";
        $mail->msgHTML($msg);

        _error_log("Send email now to {$sendTo}");
        //send the message, check for errors
        if (!$mail->send()) {
            $obj->error = __("Message could not be sent") . " (" . $mail->ErrorInfo.")";
        } else {
            $obj->success = __("Message sent");
        }
    } else {
        $obj->error = __("The email is invalid")." {$sendTo}";
    }
} else {
    $obj->error = __("Your code is not valid");
}
_error_log("sendEmail: ".$obj->error);
header('Content-Type: application/json');
echo json_encode($obj);
