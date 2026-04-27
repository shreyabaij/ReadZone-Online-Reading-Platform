<?php
// reader/profile.php
header("Content-Type: application/json");
session_start();
require_once '../config/db.php';
require_once '../vendor/autoload.php';
$mailConfig = require_once '../config/mail.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        // Get User Info
        $stmt = $pdo->prepare("SELECT username, email, created_at, role FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Stats: Earned Books (Saved) & Completed Books
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM saved_books WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $total_owned = $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM saved_books WHERE user_id = ? AND progress_percentage = 100");
        $stmt->execute([$user_id]);
        $total_read = $stmt->fetchColumn();

        echo json_encode([
            'user' => $user,
            'stats' => [
                'owned' => $total_owned,
                'read' => $total_read
            ]
        ]);
    } elseif ($method === 'POST') {
        $data = json_decode(file_get_contents("php://input"), true);
        $action = $_GET['action'] ?? $data['action'];

        if ($action === 'verify_password') {
            // Verify current password for UI checks
            $pwd = $data['password'];
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $hash = $stmt->fetchColumn();

            if (password_verify($pwd, $hash)) {
                echo json_encode(['verified' => true]);
            } else {
                http_response_code(401);
                echo json_encode(['error' => 'Incorrect password']);
            }
        } elseif ($action === 'verify_security_answer') {
            // Verify security answer
            $answer = $data['answer'];
            $stmt = $pdo->prepare("SELECT security_answer FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $hash = $stmt->fetchColumn();

            if (password_verify($answer, $hash)) {
                $_SESSION['ver_token'] = time(); // Simple session based verification time
                echo json_encode(['verified' => true]);
            } else {
                http_response_code(401);
                echo json_encode(['error' => 'Incorrect answer']);
            }
        } elseif ($action === 'send_otp') {
            // Re-use OTP logic for verification
            $stmt = $pdo->prepare("SELECT email, username FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user_info = $stmt->fetch(PDO::FETCH_ASSOC);
            $email = $user_info['email'];
            $username = $user_info['username'];

            $otp = rand(100000, 999999);

            // Clear old
            $stmt = $pdo->prepare("DELETE FROM otps WHERE user_id = ?");
            $stmt->execute([$user_id]);

            // Insert New with 3 Minute Expiry (Using DB Time to avoid mismatch)
            $stmt = $pdo->prepare("INSERT INTO otps (user_id, otp, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 3 MINUTE))");
            $stmt->execute([$user_id, $otp]);

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
                $mail->Subject = 'Verify your Account Security Change';
                $mail->Body = "Hello $username,<br><br>Your OTP for verifying your security settings change is: <b>$otp</b><br>It will
expire in 3 minutes.<br><br>Regards,<br>ReadZone Team";

                $mail->send();

                echo json_encode([
                    'message' => 'OTP sent to your email. Please check your inbox.'
                ]);
            } catch (Exception $e) {
                echo json_encode([
                    'message' => 'Failed to send OTP via email. Please contact support.',
                    'mail_error' => $mail->ErrorInfo
                ]);
            }
        } elseif ($action === 'update_password') {

            // Requires verification proof
            // Method: 'password' (old supplied), 'otp' (code supplied), 'security' (session token?)

            if (!isset($data['new_password']))
                throw new Exception("New password required");

            $valid = false;

            if ($data['method'] === 'old_password') {
                $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                if (password_verify($data['proof'], $stmt->fetchColumn()))
                    $valid = true;
            } elseif ($data['method'] === 'otp') {
                $stmt = $pdo->prepare("SELECT id FROM otps WHERE user_id = ? AND otp = ? AND expires_at > NOW()");
                $stmt->execute([$user_id, $data['proof']]);
                if ($stmt->fetch()) {
                    $valid = true;
                    // Consume OTP
                    $stmt = $pdo->prepare("DELETE FROM otps WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                }
            } elseif ($data['method'] === 'security_question') {
                // Check answer proof directly
                $stmt = $pdo->prepare("SELECT security_answer FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                if (password_verify($data['proof'], $stmt->fetchColumn()))
                    $valid = true;
            }

            if (!$valid) {
                http_response_code(403);
                echo json_encode(['error' => 'Verification failed']);
                exit;
            }

            $new_hash = password_hash($data['new_password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$new_hash, $user_id]);
            echo json_encode(['message' => 'Password updated']);
        } elseif ($action === 'update_security') {
            // Update Question & Answer
            if (!isset($data['question'], $data['answer']))
                throw new Exception("Missing fields");

            // Verify first (similar logic)
            $valid = false;
            // Only verify via Password or OTP as requested
            if ($data['method'] === 'password') {
                $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                if (password_verify($data['proof'], $stmt->fetchColumn()))
                    $valid = true;
            } elseif ($data['method'] === 'otp') {
                $stmt = $pdo->prepare("SELECT id FROM otps WHERE user_id = ? AND otp = ? AND expires_at > NOW()");
                $stmt->execute([$user_id, $data['proof']]);
                if ($stmt->fetch()) {
                    $valid = true;
                    $stmt = $pdo->prepare("DELETE FROM otps WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                }
            }

            if (!$valid) {
                http_response_code(403);
                echo json_encode(['error' => 'Verification failed']);
                exit;
            }

            $ans_hash = password_hash($data['answer'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET security_question = ?, security_answer = ? WHERE id = ?");
            $stmt->execute([$data['question'], $ans_hash, $user_id]);
            echo json_encode(['message' => 'Security settings updated']);
        }
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
