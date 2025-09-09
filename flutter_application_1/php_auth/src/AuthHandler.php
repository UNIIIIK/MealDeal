<?php

namespace MealDeal\Auth;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Exception\Auth\UserNotFound;
use Kreait\Firebase\Exception\AuthException;
use Kreait\Firebase\Contract\Firestore;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;
//

class AuthHandler {
    private $auth;
    private $firestore;
    private $config;
    
    public function __construct(array $config) {
        $this->config = $config;
        
        // Set environment variable to disable SSL verification (for development only)
        putenv('FIREBASE_VERIFY_SSL_CERTS=false');
        
        try {
            // Set shorter default socket timeout (dev convenience). Adjust as needed.
            @ini_set('default_socket_timeout', '10');

            $factory = (new Factory)
                ->withServiceAccount($config['firebase_credentials']);
                
            $this->auth = $factory->createAuth();
            $this->firestore = $factory->createFirestore();
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to initialize Firebase services: ' . $e->getMessage());
        }
    }

    /**
     * Get the Firestore database instance
     */
    public function getFirestore() {
        return $this->firestore;
    }
    
    /**
     * Get the Auth instance
     */
    public function getAuth() {
        return $this->auth;
    }
    
    
    /**
     * Register a new user with email and password
     */
    public function registerUser(string $email, string $password, array $userData = []) {
        try {
            // Create the user in Firebase Auth
            $userProperties = [
                'email' => $email,
                'password' => $password,
                'emailVerified' => false,
                'disabled' => false
            ];
            
            $user = $this->auth->createUser($userProperties);
            
            // Prepare user data for Firestore
            $userRecord = [
                'uid' => $user->uid,
                'email' => $email,
                'createdAt' => new \DateTime(),
                'verified' => false,
                'role' => $userData['role'] ?? 'consumer',
                'displayName' => $userData['displayName'] ?? '',
                'photoURL' => $userData['photoURL'] ?? ''
            ];
            
            // Add custom claims for role-based access
            $this->auth->setCustomUserClaims($user->uid, ['role' => $userRecord['role']]);
            
            // Save user data to Firestore
            $this->firestore->database()
                ->collection($this->config['firestore']['users_collection'])
                ->document($user->uid)
                ->set($userRecord);
            
            // Try to send verification email, but do not fail registration if it times out
            try {
                $this->sendVerificationEmail($email);
            } catch (\Throwable $mailEx) {
                error_log('Non-blocking: verification email send failed: ' . $mailEx->getMessage());
            }
            
            return [
                'success' => true,
                'userId' => $user->uid,
                'email' => $email,
                'message' => 'Registration successful. Please check your email to verify your account.'
            ];
            
        } catch (\Exception $e) {
            // Clean up if user was created but something else failed
            if (isset($user) && $user instanceof \Kreait\Firebase\Auth\UserRecord) {
                try {
                    $this->auth->deleteUser($user->uid);
                } catch (\Exception $deleteEx) {
                    // Log the error but don't expose it to the user
                    error_log('Failed to clean up user after registration error: ' . $deleteEx->getMessage());
                }
            }
            
            return [
                'success' => false,
                'message' => 'Registration failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Send verification email with the verification link
     */
    public function sendVerificationEmail(string $email): array {
        try {
            // Get user info first
            $user = $this->auth->getUserByEmail($email);
            
            // Generate verification link with continue URL
            $continueUrl = rtrim($this->config['app']['base_url'], '/') . 
                         $this->config['app']['verification_success_url'] . 
                         '?userId=' . urlencode($user->uid) . 
                         '&email=' . urlencode($email);
            
            $verificationLink = $this->auth->getEmailVerificationLink(
                $email,
                [
                    'continueUrl' => $continueUrl,
                    'handleCodeInApp' => true
                ],
                null
            );
            
            // Create a new PHPMailer instance
            $mail = new PHPMailer(true);
            
            // Server settings
            $mail->isSMTP();
            $mail->Host = $this->config['smtp']['host'];
            $mail->SMTPAuth = $this->config['smtp']['auth'];
            $mail->Username = $this->config['smtp']['username'];
            $mail->Password = $this->config['smtp']['password'];
            $mail->SMTPSecure = $this->config['smtp']['smtp_secure'];
            $mail->Port = $this->config['smtp']['port'];
            $mail->CharSet = 'UTF-8';
            $mail->Timeout = 10; // seconds
            $mail->SMTPKeepAlive = false;
            
            // Disable SSL verification for development
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];
            
            // Debug settings
            $mail->SMTPDebug = $this->config['smtp']['debug'];
            $mail->Debugoutput = function($str, $level) {
                file_put_contents('php://stderr', "SMTP debug ($level): $str\n");
            };
            
            // Recipients
            $mail->setFrom($this->config['smtp']['from_email'], $this->config['smtp']['from_name']);
            $mail->addAddress($email);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Verify your email for ' . $this->config['app']['name'];
            
            // Email body with improved styling and instructions
            $emailBody = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                    <div style='background-color: #4CAF50; padding: 20px; text-align: center;'>
                        <h1 style='color: white; margin: 0;'>Welcome to {$this->config['app']['name']}!</h1>
                    </div>
                    
                    <div style='padding: 20px; background-color: #f9f9f9;'>
                        <p>Hello,</p>
                        <p>Thank you for registering with {$this->config['app']['name']}. To complete your registration and verify your email address, please click the button below:</p>
                        
                        <div style='text-align: center; margin: 30px 0;'>
                            <a href='{$verificationLink}' style='background-color: #4CAF50; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-weight: bold; display: inline-block;'>
                                Verify Email Address
                            </a>
                        </div>
                        
                        <p>Or copy and paste this link into your browser:</p>
                        <div style='word-break: break-all; background-color: #f0f0f0; padding: 10px; border-radius: 4px; margin: 10px 0; font-size: 14px;'>
                            {$verificationLink}
                        </div>
                        
                        <p>This link will expire in 1 hour for security reasons.</p>
                        
                        <p>If you didn't create an account with us, you can safely ignore this email.</p>
                        
                        <p>Best regards,<br>The {$this->config['app']['name']} Team</p>
                    </div>
                    
                    <div style='background-color: #f0f0f0; padding: 15px; text-align: center; font-size: 12px; color: #666;'>
                        <p>&copy; " . date('Y') . " {$this->config['app']['name']}. All rights reserved.</p>
                    </div>
                </div>
            ";
            
            $mail->Body = $emailBody;
            $mail->AltBody = strip_tags(str_replace(['<br>', '<p>', '</p>'], ["\n", "\n", "\n"], $emailBody));
            
            // Send email
            $mail->send();
            
            return [
                'success' => true,
                'message' => 'Verification email sent successfully.',
                'debug' => [
                    'email_sent_to' => $email,
                    'verification_link' => $verificationLink
                ]
            ];
            
        } catch (AuthException $e) {
            return [
                'success' => false,
                'message' => 'Authentication error: ' . $e->getMessage(),
                'debug' => ['error_type' => 'auth_exception']
            ];
        } catch (MailException $e) {
            return [
                'success' => false,
                'message' => 'Failed to send email: ' . $e->getMessage(),
                'debug' => [
                    'error_type' => 'mail_exception',
                    'smtp_error' => $mail->ErrorInfo ?? 'No SMTP error info available'
                ]
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage(),
                'debug' => [
                    'error_type' => 'general_exception',
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            ];
        }
    }

    /**
     * Check if the user’s email is verified.
     */
    public function isEmailVerified(string $email): bool {
        try {
            $user = $this->auth->getUserByEmail($email);
            return $user->emailVerified;
        } catch (UserNotFound $e) {
            return false; // user doesn’t exist
        } catch (\Throwable $e) {
            return false; // treat errors as "not verified"
        }
    }
    
}
