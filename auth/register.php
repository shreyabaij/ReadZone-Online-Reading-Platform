<?php
// auth/register.php
header("Content-Type: application/json");
require_once '../config/db.php';
require_once '../vendor/autoload.php';
$mailConfig = require_once '../config/mail.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['username'], $data['email'], $data['password'], $data['security_question'], $data['security_answer'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

$username = trim($data['username']);
$email = trim($data['email']);
$password = password_hash($data['password'], PASSWORD_DEFAULT);
$security_question = trim($data['security_question']);
$security_answer = password_hash(trim($data['security_answer']), PASSWORD_DEFAULT);
$role = 'reader';

try {
    // checking if the user already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
    $stmt->execute([$email, $username]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => 'User already exists']);
        exit;
    }

    $pdo->beginTransaction();

    // when user doesn't exist, create new user
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, security_question, security_answer,
is_verified, created_at) VALUES (?, ?, ?, ?, ?, ?, 0, NOW())");
    $stmt->execute([$username, $email, $password, $role, $security_question, $security_answer]);
    $user_id = $pdo->lastInsertId();

    // otp generation through random
    $otp = rand(100000, 999999);

    // Clear any existing OTPs for this user before generating a new one
    $stmt = $pdo->prepare("DELETE FROM otps WHERE user_id = ?");
    $stmt->execute([$user_id]);

    // Use DB time for consistency (3 minutes)
    $stmt = $pdo->prepare("INSERT INTO otps (user_id, otp, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 3 MINUTE))");
    $stmt->execute([$user_id, $otp]);


    $pdo->commit();

    // Send email using PHPMailer
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
        $mail->addAddress($email, $username);

        $mail->isHTML(true);
        $mail->Subject = 'Verify your ReadZone Account';
        $mail->Body = "Hello $username,<br><br>Your OTP for registration is: <b>$otp</b><br>It will expire in 3
minutes.<br><br>Regards,<br>ReadZone Team";

        $mail->send();

        echo json_encode([
            'message' => 'Registration successful. Please check your email for the OTP.'
        ]);
    } catch (Exception $e) {
        // Log mail error but user is still registered (they can request resend or use forgot password if needed,
// though ideally we'd handle it better. For now we notify success with warning or just error?)
// Let's return a partial success but mention email fail
        echo json_encode([
            'message' => 'Registration successful, but failed to send email. Please contact support.',
            'mail_error' => $mail->ErrorInfo
        ]);
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => 'Registration failed: ' . $e->getMessage()]);
}