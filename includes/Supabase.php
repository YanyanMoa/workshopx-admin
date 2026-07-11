<?php
/**
 * WorkshopX Admin - Supabase REST & Auth Client
 * -----------------------------------------------
 * Lightweight wrapper around Supabase's auto-generated REST API (PostgREST)
 * and GoTrue Auth API using cURL. No external packages required.
 */

require_once __DIR__ . '/../config.php';

class Supabase
{
    /**
     * Sign in a user with email + password against Supabase Auth.
     * Returns the decoded JSON response (contains access_token, user, etc.)
     * or throws an Exception with the error message on failure.
     */
    public static function signIn(string $email, string $password): array
    {
        $url = rtrim(SUPABASE_URL, '/') . '/auth/v1/token?grant_type=password';

        $response = self::curl($url, 'POST', [
            'email'    => $email,
            'password' => $password,
        ], [
            'apikey: ' . SUPABASE_ANON_KEY,
            'Content-Type: application/json',
        ]);

        if (isset($response['error']) || isset($response['error_description'])) {
            throw new Exception($response['error_description'] ?? $response['msg'] ?? 'Login failed.');
        }

        if (empty($response['access_token'])) {
            throw new Exception('Invalid login response from Supabase.');
        }

        return $response;
    }

    /**
     * Generic SELECT from a table via PostgREST.
     * $filters example: ['status' => 'eq.pending', 'order' => 'created_at.desc']
     */
    public static function select(string $table, array $filters = [], ?string $token = null): array
    {
        $query = http_build_query($filters);
        $url = rtrim(SUPABASE_URL, '/') . '/rest/v1/' . $table . ($query ? '?' . $query : '');

        $result = self::curl($url, 'GET', null, self::headers($token));

        if (isset($result['message']) && isset($result['code'])) {
            // PostgREST error shape
            throw new Exception($result['message']);
        }

        return $result;
    }

    /** Insert a row. Returns the inserted row(s). */
    public static function insert(string $table, array $data, ?string $token = null): array
    {
        $url = rtrim(SUPABASE_URL, '/') . '/rest/v1/' . $table;
        $headers = self::headers($token);
        $headers[] = 'Prefer: return=representation';
        return self::curl($url, 'POST', $data, $headers);
    }

    /** Update rows matching filters. e.g. update('invoices', ['status'=>'paid'], ['id'=>'eq.123']) */
    public static function update(string $table, array $data, array $filters, ?string $token = null): array
    {
        $query = http_build_query($filters);
        $url = rtrim(SUPABASE_URL, '/') . '/rest/v1/' . $table . '?' . $query;
        $headers = self::headers($token);
        $headers[] = 'Prefer: return=representation';
        return self::curl($url, 'PATCH', $data, $headers);
    }

    /** Delete rows matching filters. */
    public static function delete(string $table, array $filters, ?string $token = null): array
    {
        $query = http_build_query($filters);
        $url = rtrim(SUPABASE_URL, '/') . '/rest/v1/' . $table . '?' . $query;
        return self::curl($url, 'DELETE', null, self::headers($token));
    }

    /** Count rows in a table (used for dashboard summary cards). */
    public static function count(string $table, array $filters = [], ?string $token = null): int
    {
        $query = http_build_query($filters);
        $url = rtrim(SUPABASE_URL, '/') . '/rest/v1/' . $table . ($query ? '?' . $query : '');
        $headers = self::headers($token);
        $headers[] = 'Prefer: count=exact';
        $headers[] = 'Range: 0-0';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => 'GET',
        ]);
        $response = curl_exec($ch);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        // Fallback: just fetch and count array length if Content-Range parsing fails
        $rows = self::select($table, $filters, $token);
        return is_array($rows) ? count($rows) : 0;
    }

    private static function headers(?string $token): array
    {
        // Use the Service Role Key if defined to bypass RLS and token expiration
        $key = (defined('SUPABASE_SERVICE_KEY') && SUPABASE_SERVICE_KEY !== 'YOUR_SUPABASE_SERVICE_ROLE_KEY') 
            ? SUPABASE_SERVICE_KEY 
            : SUPABASE_ANON_KEY;

        return [
            'apikey: ' . $key,
            'Authorization: Bearer ' . $key,
            'Content-Type: application/json',
        ];
    }

    private static function curl(string $url, string $method, ?array $body, array $headers): array
    {
        $ch = curl_init($url);
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 20,
        ];
        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = json_encode($body);
        }
        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new Exception('Connection error: ' . $err);
        }
        curl_close($ch);

        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : [];
    }
}
