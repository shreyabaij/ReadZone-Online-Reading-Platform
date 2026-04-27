<?php
//api calls to: auth/verify_otp.php
header("Content-Type: application/json");
require_once '../config/db.php';

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['email'], $data['otp'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing email or OTP']);
    exit;
}

$email = trim($data['email']);
$otp_input = trim($data['otp']);

try {
    // Get user id
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
        exit;
    }

    $user_id = $user['id'];

    //checking OTP validity
    // Ensure we are checking against DB time NOW()
    $stmt = $pdo->prepare("SELECT * FROM otps WHERE user_id = ? AND otp = ? AND expires_at > NOW()");
    $stmt->execute([$user_id, $otp_input]);
    $otp_record = $stmt->fetch();

    if ($otp_record) {
        // Verify user
        $stmt = $pdo->prepare("UPDATE users SET is_verified = 1 WHERE id = ?");
        $stmt->execute([$user_id]);

        // Delete used OTP
        $stmt = $pdo->prepare("DELETE FROM otps WHERE id = ?");
        $stmt->execute([$otp_record['id']]);

        echo json_encode(['message' => 'Account verified successfully']);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid or expired OTP']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Verification failed: ' . $e->getMessage()]);
}
