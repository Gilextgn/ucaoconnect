<?php
// config/email.php
require_once __DIR__ . '/../vendor/phpmailer/src/Exception.php';
require_once __DIR__ . '/../vendor/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailSender {
    private static $config = [
        'smtp_host' => 'smtp-relay.brevo.com',
        'smtp_port' => 587,
        'smtp_username' => '9f1ba7001@smtp-brevo.com',
        'smtp_password' => 'Qqx1psG476LBRWaK',
        'from_email' => 'gilchristtognisse@gmail.com',
        'from_name' => 'UCAO STUDENTS MARKETPLACE'
    ];
    
    public static function sendConfirmation($toEmail, $userId, $firstName) {
        $mail = new PHPMailer(true);
        
        try {
            // Configuration SMTP
            $mail->isSMTP();
            $mail->Host = self::$config['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = self::$config['smtp_username'];
            $mail->Password = self::$config['smtp_password'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = self::$config['smtp_port'];
            
            // Expéditeur
            $mail->setFrom(self::$config['from_email'], self::$config['from_name']);
            $mail->addAddress($toEmail, $firstName);
            
            // Générer token
            $token = bin2hex(random_bytes(32));
            $_SESSION['confirm_' . $userId] = [
                'token' => $token,
                'email' => $toEmail,
                'expires' => time() + 3600
            ];
            
            // URL de confirmation
            $protocol = isset($_SERVER['HTTPS']) ? 'https://' : 'http://';
            $confirmUrl = $protocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . 
                         '/auth/confirm.php?token=' . $token . '&uid=' . $userId;
            
            // Contenu email
            $mail->isHTML(true);
            $mail->Subject = 'Confirmez votre inscription';
            $mail->Body = self::getConfirmationTemplate($firstName, $confirmUrl);
            $mail->AltBody = "Bonjour $firstName, confirmez ici: $confirmUrl";
            
            if ($mail->send()) {
                // Log succès
                self::logEmail($toEmail, $userId, true);
                return ['success' => true, 'token' => $token];
            }
            
        } catch (Exception $e) {
            self::logEmail($toEmail, $userId, false, $mail->ErrorInfo);
        }
        
        // Fallback: mail() simple
        return self::sendSimpleMail($toEmail, $userId, $firstName);
    }
    
    private static function sendSimpleMail($toEmail, $userId, $firstName) {
        $token = bin2hex(random_bytes(32));
        $_SESSION['confirm_' . $userId] = [
            'token' => $token,
            'email' => $toEmail,
            'expires' => time() + 3600
        ];
        
        $protocol = isset($_SERVER['HTTPS']) ? 'https://' : 'http://';
        $confirmUrl = $protocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . 
                     '/auth/confirm.php?token=' . $token . '&uid=' . $userId;
        
        $subject = 'Confirmez votre inscription';
        $message = "Bonjour $firstName,\n\nConfirmez ici: $confirmUrl";
        $headers = "From: " . self::$config['from_email'] . "\r\n";
        
        if (mail($toEmail, $subject, $message, $headers)) {
            self::logEmail($toEmail, $userId, true, 'mail() fallback');
            return ['success' => true, 'token' => $token];
        }
        
        self::logEmail($toEmail, $userId, false, 'mail() failed');
        return ['success' => false, 'error' => 'Impossible d\'envoyer l\'email', 'debug_link' => $confirmUrl];
    }
    
    private static function getConfirmationTemplate($firstName, $confirmUrl) {
        return "
            <h2>Bonjour $firstName !</h2>
            <p>Cliquez ici pour confirmer :</p>
            <p><a href='$confirmUrl'>$confirmUrl</a></p>
        ";
    }
    
    private static function logEmail($toEmail, $userId, $success, $error = '') {
        $log = date('Y-m-d H:i:s') . " | $toEmail | $userId | " . 
               ($success ? 'SUCCESS' : 'FAILED') . " | $error\n";
        file_put_contents(__DIR__ . '/../logs/emails.log', $log, FILE_APPEND);
    }
    
    public static function configure($config) {
        self::$config = array_merge(self::$config, $config);
    }
}