 # BruteForceDefender

A PHP class for protecting your applications against brute force attacks by tracking failed login attempts and temporarily blocking suspicious IP addresses.

## Features

- **Automatic Table Creation**: Creates necessary database tables on initialization
- **IP-based Tracking**: Monitors failed attempts per IP address
- **Automatic Blocking**: Blocks IPs after 10 failed attempts for 2 hours
- **Block Expiration**: Automatically resets attempts after block period expires
- **Easy Integration**: Simple methods to record attempts and check block status

## Installation

1. Include the class in your project:
```php
require_once 'BruteforceDefender.php';
```

2. Make sure you have the `Database` class available with a proper MySQL connection.

## Database Schema

The class automatically creates a `brute_force_attempts` table with the following structure:
- `id`: Auto-increment primary key
- `ip_address`: Unique IP address (supports IPv6)
- `attempts`: Number of failed attempts
- `last_attempt`: Timestamp of last failed attempt
- `blocked_until`: When the IP will be unblocked (NULL if not blocked)

## Usage Examples

### Basic Implementation

```php
<?php
require_once 'BruteforceDefender.php';

$defender = new BruteForceDefender();
$userIP = $_SERVER['REMOTE_ADDR'];

// Check if IP is blocked before processing login
if ($defender->isBlocked($userIP)) {
    die('Too many failed attempts. Please try again in 2 hours.');
}

// Process login attempt
if ($loginFailed) {
    // Record failed attempt
    $result = $defender->recordFailedAttempt($userIP);
    
    if ($result['success']) {
        $attempts = $result['data']['attempts'];
        $remaining = 10 - $attempts;
        echo "Login failed! {$remaining} attempts remaining.";
    }
}
?>
```

### Advanced Implementation with Login System

```php
<?php
class LoginSystem {
    private $defender;
    
    public function __construct() {
        $this->defender = new BruteForceDefender();
    }
    
    public function attemptLogin($username, $password) {
        $userIP = $_SERVER['REMOTE_ADDR'];
        
        // Check if IP is blocked
        if ($this->defender->isBlocked($userIP)) {
            return [
                'success' => false,
                'message' => 'IP temporarily blocked due to too many failed attempts. Please try again in 2 hours.'
            ];
        }
        
        // Verify credentials (your authentication logic here)
        if ($this->verifyCredentials($username, $password)) {
            // Reset attempts on successful login
            $this->defender->resetAttempts($userIP);
            return ['success' => true, 'message' => 'Login successful!'];
        } else {
            // Record failed attempt
            $result = $this->defender->recordFailedAttempt($userIP);
            $attempts = $result['data']['attempts'];
            
            $message = "Invalid credentials. ";
            if ($attempts >= 7) {
                $remaining = 10 - $attempts;
                $message .= "Warning: {$remaining} attempts remaining before temporary block.";
            }
            
            return ['success' => false, 'message' => $message];
        }
    }
}
?>
```

### Administrative Functions

```php
<?php
$defender = new BruteForceDefender();

// Check attempt count for an IP
$attemptInfo = $defender->getAttemptCount('192.168.1.100');
if ($attemptInfo['success']) {
    echo "Attempts: " . $attemptInfo['data']['attempts'];
    echo "Blocked until: " . $attemptInfo['data']['blocked_until'];
}

// Manually reset attempts for an IP (useful for admin panel)
$resetResult = $defender->resetAttempts('192.168.1.100');
if ($resetResult['success']) {
    echo "IP has been unblocked successfully.";
}
?>
```

### API Protection Example

```php
<?php
class APIController {
    private $defender;
    
    public function __construct() {
        $this->defender = new BruteForceDefender();
    }
    
    public function handleAPIRequest() {
        $clientIP = $this->getClientIP();
        
        if ($this->defender->isBlocked($clientIP)) {
            http_response_code(429); // Too Many Requests
            echo json_encode([
                'error' => 'Rate limit exceeded. Please try again later.'
            ]);
            return;
        }
        
        // Process API request
        if (!$this->validateAPIRequest()) {
            $this->defender->recordFailedAttempt($clientIP);
            http_response_code(401);
            echo json_encode(['error' => 'Invalid API key']);
            return;
        }
        
        // Valid request
        echo json_encode(['data' => 'Your API response here']);
    }
    
    private function getClientIP() {
        // Your IP detection logic here
        return $_SERVER['REMOTE_ADDR'];
    }
}
?>
```

## Methods

### `recordFailedAttempt(string $ip)`
Records a failed login attempt for the given IP address.

### `isBlocked(string $ip)`
Checks if the given IP address is currently blocked.

### `resetAttempts(string $ip)`
Resets the attempt counter and removes block for the given IP.

### `getAttemptCount(string $ip)`
Retrieves the current attempt count and block status for an IP.

## Configuration

The class uses the following default settings:
- **Block threshold**: 10 failed attempts
- **Block duration**: 2 hours (7200 seconds)

To modify these settings, edit the `recordFailedAttempt` method in the class.

## Requirements

- PHP 7.4 or higher
- MySQL database
- `Database` class with MySQLi connection

## Security Notes

- The class silently fails when database errors occur to avoid exposing internal information
- IPv6 addresses are supported (VARCHAR(45))
- Automatic cleanup of expired blocks happens when checking block status
- Consider implementing additional security measures like CAPTCHA after several failed attempts

## License

MIT License - Feel free to use in your projects.