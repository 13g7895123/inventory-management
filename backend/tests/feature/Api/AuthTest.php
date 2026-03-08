<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Auth API Feature 測試
 *
 * 需要連接測試資料庫（phpunit.xml 中設定 database.tests.*）
 *
 * 執行前先確保 migration + seed 已跑過：
 *   php spark migrate --env=testing
 *   php spark db:seed DatabaseSeeder --env=testing
 */
class AuthTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $seed        = 'DatabaseSeeder';

    // ── POST /api/v1/auth/login ───────────────────────────────────────

    public function testLoginWithValidCredentialsReturnsTokens(): void
    {
        $result = $this->withBodyFormat('json')
            ->post('/api/v1/auth/login', [
                'username' => 'admin',
                'password' => 'Admin@12345',
            ]);

        $result->assertStatus(200);
        $result->assertJSONFragment(['success' => true]);

        $body = json_decode($result->getBody(), true);
        $this->assertArrayHasKey('access_token', $body['data']);
        $this->assertArrayHasKey('refresh_token', $body['data']);
        $this->assertNotEmpty($body['data']['access_token']);
    }

    public function testLoginWithWrongPasswordReturns401(): void
    {
        $result = $this->withBodyFormat('json')
            ->post('/api/v1/auth/login', [
                'username' => 'admin',
                'password' => 'wrongpassword',
            ]);

        $result->assertStatus(401);
        $result->assertJSONFragment(['success' => false]);
    }

    public function testLoginWithNonExistentUserReturns401(): void
    {
        $result = $this->withBodyFormat('json')
            ->post('/api/v1/auth/login', [
                'username' => 'nobody',
                'password' => 'anything',
            ]);

        $result->assertStatus(401);
    }

    public function testLoginWithMissingFieldsReturns422(): void
    {
        $result = $this->withBodyFormat('json')
            ->post('/api/v1/auth/login', [
                'username' => 'admin',
                // password 缺少
            ]);

        $result->assertStatus(422);
        $result->assertJSONFragment(['success' => false]);
    }

    // ── POST /api/v1/auth/refresh ─────────────────────────────────────

    public function testRefreshWithInvalidTokenReturns401(): void
    {
        $result = $this->withBodyFormat('json')
            ->post('/api/v1/auth/refresh', [
                'refresh_token' => 'totally-invalid-token',
            ]);

        $result->assertStatus(401);
    }

    // ── GET /api/v1/auth/me ───────────────────────────────────────────

    public function testMeWithoutTokenReturns401(): void
    {
        $result = $this->get('/api/v1/auth/me');

        $result->assertStatus(401);
    }

    public function testMeWithValidTokenReturnsUser(): void
    {
        // 先登入取得 token
        $loginResult = $this->withBodyFormat('json')
            ->post('/api/v1/auth/login', [
                'username' => 'admin',
                'password' => 'Admin@12345',
            ]);

        $body  = json_decode($loginResult->getBody(), true);
        $token = $body['data']['access_token'];

        $result = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->get('/api/v1/auth/me');

        $result->assertStatus(200);
        $result->assertJSONFragment(['success' => true]);

        $me = json_decode($result->getBody(), true);
        $this->assertSame('admin', $me['data']['username']);
    }
}
