<?php
// app/Services/TamaraDebugAuthenticator.php

namespace App\Services;

use Tamara\Notification\Authenticator as TamaraAuthenticator;
use Symfony\Component\HttpFoundation\Request;
use Illuminate\Support\Facades\Log;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Throwable;

class TamaraDebugAuthenticator extends TamaraAuthenticator
{
    private $tokenKey;

    public function __construct(string $tokenKey)
    {
        $this->tokenKey = $tokenKey;
        parent::__construct($tokenKey);
    }

    public function authenticate($request): bool
    {
        // Convert Laravel request to Symfony request if needed
        if ($request instanceof \Illuminate\Http\Request) {
            Log::info('Converting Laravel Request to Symfony Request for Tamara authentication');
            
            // Create a new Symfony request from Laravel's request
            $symfonyRequest = new Request(
                $request->query->all(),
                $request->request->all(),
                [],
                $request->cookies->all(),
                $request->files->all(),
                $request->server->all(),
                $request->getContent()
            );
            
            // Copy headers
            foreach ($request->headers->all() as $key => $value) {
                $symfonyRequest->headers->set($key, $value);
            }
            
            $request = $symfonyRequest;
        }
        
        // Check for Authorization header
        $hasAuthHeader = $request->headers->has('Authorization');
        Log::info('Authorization header present: ' . ($hasAuthHeader ? 'YES' : 'NO'));
        
        if ($hasAuthHeader) {
            Log::info('Authorization header value: ' . $request->headers->get('Authorization'));
        }
        
        // Check for tamaraToken query param
        $hasTokenParam = $request->query->has('tamaraToken');
        Log::info('tamaraToken param present: ' . ($hasTokenParam ? 'YES' : 'NO'));
        
        if ($hasTokenParam) {
            Log::info('tamaraToken param value: ' . $request->query->get('tamaraToken'));
        }
        
        // If neither is present, authentication fails
        if (!$hasAuthHeader && !$hasTokenParam) {
            Log::error('Tamara auth failed: Neither Authorization header nor tamaraToken param present');
            return false;
        }
        
        // Extract token
        $token = null;
        
        if ($hasAuthHeader) {
            $authHeader = $request->headers->get('Authorization');
            if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
                $token = $matches[1];
                Log::info('Extracted token from Authorization header');
            } else {
                Log::error('Failed to extract token from Authorization header: ' . $authHeader);
                return false;
            }
        } else {
            $token = $request->query->get('tamaraToken');
            Log::info('Using token from tamaraToken param');
        }
        
        Log::info('Token length: ' . strlen($token));
        Log::info('Token: ' . $token);
        
        // Decode JWT token for diagnostics
        try {
            $parts = explode('.', $token);
            if (count($parts) === 3) {
                $header = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[0])), true);
                $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1])), true);
                
                Log::info('JWT header:', $header ?: []);
                Log::info('JWT payload:', $payload ?: []);
                
                // Verify times
                if (isset($payload['exp'])) {
                    $expTime = date('Y-m-d H:i:s', $payload['exp']);
                    $serverTime = date('Y-m-d H:i:s');
                    $isExpired = time() > $payload['exp'];
                    
                    Log::info("JWT Expiration check: Token expires at $expTime, server time is $serverTime, Token is " . ($isExpired ? 'EXPIRED' : 'VALID'));
                }
            }
        } catch (\Exception $e) {
            Log::warning('Error decoding JWT for diagnostics: ' . $e->getMessage());
        }
        
        // Verify the token
        try {
            Log::info('Attempting to verify token with notification token (first 10 chars): ' . substr($this->tokenKey, 0, 10) . '...');
            Log::info('Notification token length: ' . strlen($this->tokenKey));
            
            // Try the decode using Firebase JWT
            $decoded = JWT::decode($token, new Key($this->tokenKey, 'HS256'));
            
            Log::info('JWT verification successful!', (array) $decoded);
            return true;
        } catch (Throwable $exception) {
            Log::error('JWT verification failed: ' . $exception->getMessage());
            Log::error('Exception trace: ' . $exception->getTraceAsString());
            return false;
        }
    }
}
