<?php

namespace Tests\Unit;

use App\Models\Utils;
use Tests\TestCase;

class UtilsMailSenderTest extends TestCase
{
    public function test_mail_sender_rejects_missing_recipient_email(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Recipient email is missing or invalid.');

        Utils::mail_sender([
            'email' => null,
            'name' => 'Tester',
            'subject' => 'Password Reset',
            'body' => '<p>Body</p>',
        ]);
    }

    public function test_mail_sender_rejects_invalid_recipient_email(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Recipient email is missing or invalid.');

        Utils::mail_sender([
            'email' => 'not-an-email',
            'name' => 'Tester',
            'subject' => 'Password Reset',
            'body' => '<p>Body</p>',
        ]);
    }

    public function test_mail_sender_sends_with_valid_addresses(): void
    {
        config([
            'mail.default' => 'array',
            'mail.from.address' => 'sender@example.com',
            'mail.from.name' => 'Katogo Test',
        ]);

        Utils::mail_sender([
            'email' => 'recipient@example.com',
            'name' => 'Tester',
            'subject' => 'Password Reset',
            'body' => '<p>Your code is 123456</p>',
        ]);

        $this->assertTrue(true);
    }
}
