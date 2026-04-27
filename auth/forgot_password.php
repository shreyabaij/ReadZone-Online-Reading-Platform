<?php
// auth/forgot_password.php
header("Content-Type: application/json");
require_once '../config/db.php';
require_once '../vendor/autoload.php';
$mailConfig = require_once '../config/mail.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['action'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing action (initiate or reset)']);
    exit;
}

$action = $data['action'];

try {
    if ($action === 'initiate') {
        if (!isset($data['email'])) {
            throw new Exception("Email required");
        }
        $email = trim($data['email']);

        $stmt = $pdo->prepare("SELECT id, username, security_question FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            exit;
        }

        // if previous otp exists, delete it to avoid confusion and clean database
        $stmt = $pdo->prepare("DELETE FROM otps WHERE user_id = ?");
        $stmt->execute([$user['id']]);

        // generate new OTP and store in database with expiry
        $otp = rand(100000, 999999);
        $stmt = $pdo->prepare("INSERT INTO otps (user_id, otp, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 3 MINUTE))");
        $stmt->execute([$user['id'], $otp]);

        // send email using PHPMailer
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = $mailConfig['host'];
            $mail->SMTPAuth = $mailConfig['smtp_auth'];
            $mail->Username = $mailConfig['username'];
            $mail->Password = $mailConfig['password'];
            $mail->SMTPSecure = $mailConfig['smtp_secure'];
            $mail->Port = $mailConfig['port'];

            $mail->setFrom($mailConfig['from_email'], $mailConfig['from_name']);
            $mail->addAddress($email, $user['username']);

            $mail->isHTML(true);
            $mail->Subject = 'Reset your ReadZone Password';
            $mail->Body = "Hello " . $user['username'] . ",<br><br>Your OTP for password reset is: <b>$otp</b><br>It will expire in 5 minutes.<br><br>Regards,<br>ReadZone Team";

            $mail->send();

            echo json_encode([
                'security_question' => $user['security_question'],
                'message' => 'OTP sent to your email. Please check and enter it below.'
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'security_question' => $user['security_question'],
                'message' => 'OTP generated but failed to send email. You may use your security question.',
                'mail_error' => $mail->ErrorInfo
            ]);
        }
    } elseif ($action === 'reset') {

        if (!isset($data['email'], $data['new_password'])) {
            throw new Exception("Missing required fields");
        }

        $email = trim($data['email']);
        $new_pass = password_hash($data['new_password'], PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("SELECT id, security_answer FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            exit;
        }

        $verified = false;

        // verify via OTP
        if (isset($data['otp'])) {
            $otp_input = trim($data['otp']);
            $stmt = $pdo->prepare("SELECT * FROM otps WHERE user_id = ? AND otp = ? AND expires_at > NOW()");
            $stmt->execute([$user['id'], $otp_input]);
            $otp_record = $stmt->fetch();

            if ($otp_record) {
                $verified = true;
                // cleanup used OTP
                $pdo->prepare("DELETE FROM otps WHERE id = ?")->execute([$otp_record['id']]);
            }
        }

        // vefirication via security answer if OTP not verified or not provided
        if (!$verified && isset($data['security_answer'])) {
            if (password_verify(trim($data['security_answer']), $user['security_answer'])) {
                $verified = true;
            }
        }

        if ($verified) {
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$new_pass, $user['id']]);
            echo json_encode(['message' => 'Password reset successful']);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid OTP or Security Answer']);
        }
    } else {
        throw new Exception("Invalid action");
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
