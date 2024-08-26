<?php
require_once 'vendor/autoload.php'; // Autoload all dependencies

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Firebase\JWT\BeforeValidException;

//funcions basiques per token
class JWTHandler {
    private $secretKey;

    public function __construct($key) {
        $this->secretKey = $key;
    }

    public function generateToken($payload) {
        return JWT::encode($payload, $this->secretKey, 'HS256');
    }

    public function decodeToken($token) {
        try {
            return JWT::decode($token, new Key($this->secretKey, 'HS256'));
        } catch (ExpiredException $e) {
            header('HTTP/1.1 401 Unauthorized');
            echo json_encode(['error' => 'Token expired']);
            exit;
        } catch (SignatureInvalidException $e) {
            header('HTTP/1.1 401 Unauthorized');
            echo json_encode(['error' => 'Invalid signature']);
            exit;
        } catch (\Exception $e) {
            header('HTTP/1.1 401 Unauthorized');
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
    }
}

$secretKey = "your_secret_key";
$jwtHandler = new JWTHandler($secretKey);
?>