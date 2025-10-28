<?php
require_once('Models/User.php');
require_once('Models/BruteForceDefender.php');

class AuthController {
    private User $user;
    private BruteForceDefender $bruteForceDefender;

    public function __construct() {
        $this->user = new User();
        $this->bruteForceDefender = new BruteForceDefender();
    }

    public function login(string $identifier, string $password, string $ipAddress): array {
        try {
            // Check if IP is blocked
            if ($this->bruteForceDefender->isBlocked($ipAddress)) {
                $attemptInfo = $this->bruteForceDefender->getAttemptCount($ipAddress);
                $blockedUntil = $attemptInfo['data']['blocked_until'] ?? null;
                $message = $blockedUntil 
                    ? "Too many failed attempts. Blocked until " . $blockedUntil
                    : "IP is blocked due to excessive failed attempts";
                    
            //if the user is blocked show message on a view
                return ['success' => false, 'message' => $message];
            }

            // Attempt login
            $loginResult = $this->user->login($identifier, $password);

            if ($loginResult['success']) {
                // On successful login, reset brute force attempts
                $this->bruteForceDefender->resetAttempts($ipAddress);
                
    //redirect to two factor auth
                return [
                    'success' => true,
                    'message' => $loginResult['message'],
                    'data' => $loginResult['data']
                ];
            } else {
                // On failed login, record attempt
                $this->bruteForceDefender->recordFailedAttempt($ipAddress);
                $attemptInfo = $this->bruteForceDefender->getAttemptCount($ipAddress);
                $remainingAttempts = 10 - ($attemptInfo['data']['attempts'] ?? 0);
                
      if ($attemptInfo['data']['blocked_until'] !== null) {
          
    //redirection to blocked view
                    return [
                        'success' => false,
                        'message' => "Login failed. Too many attempts. Blocked until " . $attemptInfo['data']['blocked_until']
                    ];
                }

 //show s how many attempts
                return [
                    'success' => false,
                    'message' => $loginResult['message'] . ". {$remainingAttempts} attempts remaining."
                ];
            }
        } catch (Exception $e) {
            
    //handling error
            return ['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()];
        }
    }

    // Example API endpoint method
    public function handleLoginRequest(): void {
        try {
            // Assuming data comes from POST request
            $identifier = filter_input(INPUT_POST, 'identifier', FILTER_SANITIZE_STRING) ?? '';
            $password = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_STRING) ?? '';
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

            $result = $this->login($identifier, $password, $ipAddress);
            
            // Output JSON response
            header('Content-Type: application/json');
            echo json_encode($result);
        } catch (Exception $e) {
            header('Content-Type: application/json', true, 500);
            echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
        }
    }
}
?>