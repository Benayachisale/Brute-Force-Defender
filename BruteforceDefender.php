<?php
require_once('Database/Database.php');

class BruteForceDefender {
    public Database $database;
    public mysqli $conn;

    public function __construct() {
        $this->database = new Database();
        $this->conn = $this->database->getConnection();
        $this->initializeTables();
    }

    public function initializeTables(): array {
        $tables = [
            "-- Create brute_force_attempts table
            CREATE TABLE IF NOT EXISTS brute_force_attempts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ip_address VARCHAR(45) NOT NULL UNIQUE,
                attempts INT NOT NULL DEFAULT 0,
                last_attempt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                blocked_until DATETIME DEFAULT NULL,
                INDEX idx_ip_address (ip_address)
            ) ENGINE=InnoDB CHARACTER SET utf8mb4;"
        ];

        $errors = [];
        foreach ($tables as $sql) {
            try {
                if (!$this->conn->query($sql)) {
                    $errors[] = $this->conn->error;
                }
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        }

        if (empty($errors)) {
            return ['success' => true, 'message' => 'Table initialized successfully'];
        } else {
            return ['success' => false, 'message' => 'Initialization errors: ' . implode('; ', $errors)];
        }
    }

    public function recordFailedAttempt(string $ip): array {
        try {
            if (empty($ip)) {
                return ['success' => false, 'message' => 'IP address is required'];
            }

            // Check if IP exists
            $checkStmt = $this->conn->prepare("SELECT attempts, blocked_until FROM brute_force_attempts WHERE ip_address = ?");
            $checkStmt->bind_param("s", $ip);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                $checkStmt->close();
                
                // If currently blocked, don't increment further
                if ($row['blocked_until'] !== null && strtotime($row['blocked_until']) > time()) {
                    return ['success' => true, 'message' => 'Attempt recorded (already blocked)'];
                }
                
                // Reset if block expired
                if ($row['blocked_until'] !== null && strtotime($row['blocked_until']) <= time()) {
                    $attempts = 1; // Reset and start new count
                    $blockedUntil = null;
                } else {
                    $attempts = $row['attempts'] + 1;
                    $blockedUntil = ($attempts >= 10) ? date('Y-m-d H:i:s', time() + 7200) : null; // 2 hours = 7200 seconds
                }
                
                // Update existing record
                $updateStmt = $this->conn->prepare("UPDATE brute_force_attempts SET attempts = ?, last_attempt = CURRENT_TIMESTAMP, blocked_until = ? WHERE ip_address = ?");
                $updateStmt->bind_param("iss", $attempts, $blockedUntil, $ip);
                if ($updateStmt->execute()) {
                    $updateStmt->close();
                    return ['success' => true, 'message' => 'Failed attempt recorded', 'data' => ['attempts' => $attempts, 'blocked' => ($blockedUntil !== null)]];
                } else {
                    $updateStmt->close();
                    return ['success' => false, 'message' => 'Failed to update attempt record'];
                }
            } else {
                $checkStmt->close();
                
                // Insert new record
                $insertStmt = $this->conn->prepare("INSERT INTO brute_force_attempts (ip_address, attempts) VALUES (?, 1)");
                $insertStmt->bind_param("s", $ip);
                if ($insertStmt->execute()) {
                    $insertStmt->close();
                    return ['success' => true, 'message' => 'Failed attempt recorded', 'data' => ['attempts' => 1, 'blocked' => false]];
                } else {
                    $insertStmt->close();
                    return ['success' => false, 'message' => 'Failed to insert attempt record'];
                }
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()];
        }
    }

    public function isBlocked(string $ip): bool {
        try {
            if (empty($ip)) {
                return false;
            }

            $stmt = $this->conn->prepare("SELECT blocked_until FROM brute_force_attempts WHERE ip_address = ?");
            $stmt->bind_param("s", $ip);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                $stmt->close();
                
                if ($row['blocked_until'] !== null) {
                    $blockedUntilTime = strtotime($row['blocked_until']);
                    if ($blockedUntilTime > time()) {
                        return true;
                    } else {
                        // Block expired, reset attempts and blocked_until
                        $this->resetAttempts($ip);
                        return false;
                    }
                }
                return false;
            }
            
            $stmt->close();
            return false;
        } catch (Exception $e) {
            // Silently fail to not expose errors, assume not blocked
            error_log('Error checking block status: ' . $e->getMessage());
            return false;
        }
    }

    public function resetAttempts(string $ip): array {
        try {
            if (empty($ip)) {
                return ['success' => false, 'message' => 'IP address is required'];
            }

            $stmt = $this->conn->prepare("UPDATE brute_force_attempts SET attempts = 0, blocked_until = NULL WHERE ip_address = ?");
            $stmt->bind_param("s", $ip);
            if ($stmt->execute()) {
                $affected = $stmt->affected_rows;
                $stmt->close();
                if ($affected > 0) {
                    return ['success' => true, 'message' => 'Attempts reset successfully'];
                } else {
                    return ['success' => false, 'message' => 'No record found for this IP'];
                }
            } else {
                $stmt->close();
                return ['success' => false, 'message' => 'Failed to reset attempts'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()];
        }
    }

    public function getAttemptCount(string $ip): array {
        try {
            if (empty($ip)) {
                return ['success' => false, 'message' => 'IP address is required'];
            }

            $stmt = $this->conn->prepare("SELECT attempts, blocked_until FROM brute_force_attempts WHERE ip_address = ?");
            $stmt->bind_param("s", $ip);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                $stmt->close();
                return ['success' => true, 'message' => 'Attempt count retrieved', 'data' => ['attempts' => (int)$row['attempts'], 'blocked_until' => $row['blocked_until']]];
            }
            
            $stmt->close();
            return ['success' => false, 'message' => 'No record found', 'data' => ['attempts' => 0, 'blocked_until' => null]];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()];
        }
    }
}
?>