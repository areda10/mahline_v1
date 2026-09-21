<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Identity\Password\Http\Requests;

use App\Domains\Identity\Password\Http\Requests\ResetPasswordRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

final class ResetPasswordRequestTest extends TestCase
{
    public function test_token_is_required(): void
    {
        $request = new ResetPasswordRequest();

        $validator = Validator::make(
            [],
            $request->rules(),
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey(
            'token',
            $validator->errors()->toArray(),
        );
    }

    public function test_token_must_be_a_string(): void
    {
        $request = new ResetPasswordRequest();

        $validator = Validator::make(
            [
                'token' => 123456,
            ],
            $request->rules(),
        );

        $this->assertTrue($validator->fails());
    }

    public function test_password_is_required(): void
    {
        $request = new ResetPasswordRequest();

        $validator = Validator::make(
            [
                'token' => 'valid-token',
            ],
            $request->rules(),
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey(
            'password',
            $validator->errors()->toArray(),
        );
    }

    public function test_password_confirmation_is_required(): void
    {
        $request = new ResetPasswordRequest();

        $validator = Validator::make(
            [
                'token' => 'valid-token',
                'password' => 'ValidPassword123',
            ],
            $request->rules(),
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey(
            'password_confirmation',
            $validator->errors()->toArray(),
        );
    }

    public function test_password_confirmation_must_match(): void
    {
        $request = new ResetPasswordRequest();

        $validator = Validator::make(
            [
                'token' => 'valid-token',
                'password' => 'ValidPassword123',
                'password_confirmation' => 'DifferentPassword123',
            ],
            $request->rules(),
        );

        $this->assertTrue($validator->fails());
    }

    public function test_valid_password_reset_request_is_accepted(): void
    {
        $request = new ResetPasswordRequest();

        $validator = Validator::make(
            [
                'token' => 'valid-token',
                'password' => 'ValidPassword123',
                'password_confirmation' => 'ValidPassword123',
            ],
            $request->rules(),
        );

        $this->assertFalse($validator->fails());
    }
}