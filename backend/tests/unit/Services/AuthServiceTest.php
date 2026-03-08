<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Entities\User;
use App\Libraries\JWT\JWTService;
use App\Models\RefreshTokenModel;
use App\Models\UserModel;
use App\Services\AuthService;
use CodeIgniter\Test\CIUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * AuthService 單元測試
 *
 * 使用 Mock 物件隔離 DB，只測試業務邏輯。
 */
class AuthServiceTest extends CIUnitTestCase
{
    private AuthService $authService;

    /** @var UserModel&MockObject */
    private UserModel $userModel;

    /** @var RefreshTokenModel&MockObject */
    private RefreshTokenModel $refreshTokenModel;

    /** @var JWTService&MockObject */
    private JWTService $jwtService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userModel         = $this->createMock(UserModel::class);
        $this->refreshTokenModel = $this->createMock(RefreshTokenModel::class);
        $this->jwtService        = $this->createMock(JWTService::class);

        $this->authService = new AuthService(
            $this->userModel,
            $this->refreshTokenModel,
            $this->jwtService,
        );
    }

    // ── login ────────────────────────────────────────────────────────

    public function testLoginThrowsWhenUserNotFound(): void
    {
        $this->userModel
            ->method('findActiveByUsername')
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('帳號或密碼錯誤');

        $this->authService->login('notexist', 'anypass');
    }

    public function testLoginThrowsWhenPasswordWrong(): void
    {
        $user = $this->makeUser();

        $this->userModel
            ->method('findActiveByUsername')
            ->willReturn($user);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('帳號或密碼錯誤');

        $this->authService->login('admin', 'wrongpassword');
    }

    public function testLoginSuccessReturnsTokens(): void
    {
        $user = $this->makeUser(['password' => password_hash('correct', PASSWORD_BCRYPT)]);

        $this->userModel->method('findActiveByUsername')->willReturn($user);
        $this->userModel->method('touchLastLogin')->willReturn(null);
        $this->jwtService->method('generateAccessToken')->willReturn('access-token');
        $this->jwtService->method('generateRefreshToken')->willReturn('refresh-token');
        $this->jwtService->method('getTtl')->willReturn(3600);
        $this->jwtService->method('getRefreshTtl')->willReturn(604800);
        $this->refreshTokenModel->method('store')->willReturn(null);

        $result = $this->authService->login('admin', 'correct');

        $this->assertSame('access-token', $result['access_token']);
        $this->assertSame('refresh-token', $result['refresh_token']);
        $this->assertSame(3600, $result['expires_in']);
        $this->assertArrayHasKey('user', $result);
    }

    // ── refresh ──────────────────────────────────────────────────────

    public function testRefreshThrowsWhenTokenInvalid(): void
    {
        $this->refreshTokenModel
            ->method('findValid')
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Refresh Token 無效或已過期');

        $this->authService->refresh('invalid-token');
    }

    public function testRefreshRevokeOldAndIssueNew(): void
    {
        $tokenRecord = (object) ['user_id' => 1];
        $user = $this->makeUser(['password' => password_hash('x', PASSWORD_BCRYPT)]);

        $this->refreshTokenModel->method('findValid')->willReturn($tokenRecord);
        $this->refreshTokenModel->expects($this->once())->method('revoke');
        $this->userModel->method('find')->willReturn($user);
        $this->jwtService->method('generateAccessToken')->willReturn('new-access');
        $this->jwtService->method('generateRefreshToken')->willReturn('new-refresh');
        $this->jwtService->method('getTtl')->willReturn(3600);
        $this->jwtService->method('getRefreshTtl')->willReturn(604800);
        $this->refreshTokenModel->method('store')->willReturn(null);

        $result = $this->authService->refresh('old-token');

        $this->assertSame('new-access', $result['access_token']);
        $this->assertSame('new-refresh', $result['refresh_token']);
    }

    // ── logout ───────────────────────────────────────────────────────

    public function testLogoutRevokesToken(): void
    {
        $this->refreshTokenModel
            ->expects($this->once())
            ->method('revoke')
            ->with('some-token');

        $this->authService->logout('some-token');
    }

    // ── helpers ──────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $overrides
     */
    private function makeUser(array $overrides = []): User
    {
        $data = array_merge([
            'id'        => 1,
            'role_id'   => 1,
            'username'  => 'admin',
            'name'      => 'Test User',
            'password'  => password_hash('password', PASSWORD_BCRYPT),
            'is_active' => 1,
        ], $overrides);

        $user = new User();
        foreach ($data as $key => $value) {
            $user->$key = $value;
        }

        return $user;
    }
}
