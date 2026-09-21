<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Identity\Password\Http\Requests;

use App\Domains\Identity\Password\Http\Requests\ForgotPasswordRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

final class ForgotPasswordRequestTest extends TestCase
{
    public function test_email_is_required(): void
    {
        $request = new ForgotPasswordRequest();

        $validator = Validator::make(
            [],
            $request->rules(),
        );

        $this->assertTrue(
            $validator->fails(),
        );

        $this->assertArrayHasKey(
            'email',
            $validator->errors()->toArray(),
        );
    }

    public function test_email_must_be_valid(): void
    {
        $request = new ForgotPasswordRequest();

        $validator = Validator::make(
            [
                'email' => 'invalid-email',
            ],
            $request->rules(),
        );

        $this->assertTrue(
            $validator->fails(),
        );
    }

    public function test_valid_email_is_accepted(): void
    {
        $request = new ForgotPasswordRequest();

        $validator = Validator::make(
            [
                'email' => 'user@example.com',
            ],
            $request->rules(),
        );

        $this->assertFalse(
            $validator->fails(),
        );
    }

    public function test_email_has_maximum_length_of_255_characters(): void
    {
        $request = new ForgotPasswordRequest();

        $validator = Validator::make(
            [
                'email' => str_repeat('a', 250) . '@example.com',
            ],
            $request->rules(),
        );

        $this->assertTrue(
            $validator->fails(),
        );
    }
}