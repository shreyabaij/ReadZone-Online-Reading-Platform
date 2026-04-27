<?php
// auth/login.php
header("Content-Type: application/json");
session_start();
require_once '../config/db.php';
require_once '../vendor/autoload.php';
$mailConfig = require_once '../config/mail.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['username'], $data['password'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Please provide all field details']);
    exit;
}

$input = trim($data['username']); //user can use email or username
$password = $data['password'];

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$input, $input]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid credentials']);
        exit;
    }

    // verify password first to prevent unauthorized OTP triggers
    if (!password_verify($password, $user['password'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid credentials']);
        exit;
    }

    // checking if the reader is in a timeout
    if ($user['timeout_until']) {
        if (strtotime($user['timeout_until']) > time()) {
            http_response_code(403);
            echo json_encode([
                'error' => 'Account is temporarily suspended for misusing report feature until ' .
                    $user['timeout_until']
            ]);
            exit;
        } else {
            // passive cleanup: suspension expired, clear it in DB
            $stmt = $pdo->prepare("UPDATE users SET timeout_until = NULL WHERE id = ?");
            $stmt->execute([$user['id']]);
        }
    }

    // user only allowed to login if verified(otp verified)
    if ($user['is_verified'] == 0) {
        // Check for existing valid OTP
        $stmt = $pdo->prepare("SELECT * FROM otps WHERE user_id = ? AND expires_at > NOW()");
        $stmt->execute([$user['id']]);
        $existing_otp = $stmt->fetch();

        $new_otp_sent = false;
        if (!$existing_otp) {
            // Clear any existing expired/invalid OTPs for this user
            $stmt = $pdo->prepare("DELETE FROM otps WHERE user_id = ?");
            $stmt->execute([$user['id']]);

            // Generate and send NEW OTP
            $otp = rand(100000, 999999);
            $stmt = $pdo->prepare("INSERT INTO otps (user_id, otp, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 3 MINUTE))");
            $stmt->execute([$user['id'], $otp]);

            // Send email
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
                $mail->addAddress($user['email'], $user['username']);

                $mail->isHTML(true);
                $mail->Subject = 'Verify your ReadZone Account';
                $mail->Body = "Hello " . $user['username'] . ",<br><br>Your verification OTP is: <b>$otp</b><br>It will expire in 3
minutes.<br><br>Regards,<br>ReadZone Team";

                $mail->send();
                $new_otp_sent = true;
            } catch (Exception $e) {
                // Email failed, but still tell frontend to show OTP view
            }
        }

        echo json_encode([
            'status' => 'NOT_VERIFIED',
            'email' => $user['email'],
            'message' => $new_otp_sent ? 'Your OTP was expired. A new one has been sent to your email.' : 'Please verify your
account with the OTP sent to your email.',
            'new_otp_sent' => $new_otp_sent
        ]);
        exit;
    }

    // Login successful
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['username'] = $user['username'];

    echo json_encode([
        'message' => 'Login successful',
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'role' => $user['role'],
            'is_verified' => $user['is_verified']
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Login failed: ' . $e->getMessage()]);
}
