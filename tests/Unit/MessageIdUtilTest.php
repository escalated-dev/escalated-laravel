<?php

namespace Escalated\Laravel\Tests\Unit;

use Escalated\Laravel\Mail\MessageIdUtil;
use Escalated\Laravel\Tests\TestCase;

/**
 * Pure-function tests for MessageIdUtil. Mirrors the NestJS / Spring /
 * WordPress / .NET / Phoenix reference test suites.
 */
class MessageIdUtilTest extends TestCase
{
    private const DOMAIN = 'support.example.com';

    private const SECRET = 'test-secret-long-enough-for-hmac';

    public function test_build_message_id_initial_ticket(): void
    {
        $this->assertEquals(
            '<ticket-42@support.example.com>',
            MessageIdUtil::buildMessageId(42, null, self::DOMAIN)
        );
    }

    public function test_build_message_id_reply_form(): void
    {
        $this->assertEquals(
            '<ticket-42-reply-7@support.example.com>',
            MessageIdUtil::buildMessageId(42, 7, self::DOMAIN)
        );
    }

    public function test_parse_ticket_id_round_trips(): void
    {
        $initial = MessageIdUtil::buildMessageId(42, null, self::DOMAIN);
        $reply = MessageIdUtil::buildMessageId(42, 7, self::DOMAIN);

        $this->assertEquals(42, MessageIdUtil::parseTicketIdFromMessageId($initial));
        $this->assertEquals(42, MessageIdUtil::parseTicketIdFromMessageId($reply));
    }

    public function test_parse_ticket_id_accepts_value_without_brackets(): void
    {
        $this->assertEquals(99, MessageIdUtil::parseTicketIdFromMessageId('ticket-99@example.com'));
    }

    public function test_parse_ticket_id_returns_null_for_unrelated_input(): void
    {
        $this->assertNull(MessageIdUtil::parseTicketIdFromMessageId(null));
        $this->assertNull(MessageIdUtil::parseTicketIdFromMessageId(''));
        $this->assertNull(MessageIdUtil::parseTicketIdFromMessageId('<random@mail.com>'));
        $this->assertNull(MessageIdUtil::parseTicketIdFromMessageId('ticket-abc@example.com'));
    }

    public function test_build_reply_to_is_stable(): void
    {
        $first = MessageIdUtil::buildReplyTo(42, self::SECRET, self::DOMAIN);
        $again = MessageIdUtil::buildReplyTo(42, self::SECRET, self::DOMAIN);
        $this->assertEquals($first, $again);
        $this->assertMatchesRegularExpression(
            '/^reply\+42\.[a-f0-9]{8}@support\.example\.com$/',
            $first
        );
    }

    public function test_build_reply_to_different_tickets_differ(): void
    {
        $a = MessageIdUtil::buildReplyTo(42, self::SECRET, self::DOMAIN);
        $b = MessageIdUtil::buildReplyTo(43, self::SECRET, self::DOMAIN);
        $this->assertNotEquals(
            substr($a, 0, strpos($a, '@')),
            substr($b, 0, strpos($b, '@'))
        );
    }

    public function test_verify_reply_to_round_trips(): void
    {
        $address = MessageIdUtil::buildReplyTo(42, self::SECRET, self::DOMAIN);
        $this->assertEquals(42, MessageIdUtil::verifyReplyTo($address, self::SECRET));
    }

    public function test_verify_reply_to_accepts_local_part_only(): void
    {
        $address = MessageIdUtil::buildReplyTo(42, self::SECRET, self::DOMAIN);
        $local = substr($address, 0, strpos($address, '@'));
        $this->assertEquals(42, MessageIdUtil::verifyReplyTo($local, self::SECRET));
    }

    public function test_verify_reply_to_rejects_tampered_signature(): void
    {
        $address = MessageIdUtil::buildReplyTo(42, self::SECRET, self::DOMAIN);
        $at = strpos($address, '@');
        $local = substr($address, 0, $at);
        $last = $local[strlen($local) - 1];
        $tampered = substr($local, 0, -1).($last === '0' ? '1' : '0').substr($address, $at);
        $this->assertNull(MessageIdUtil::verifyReplyTo($tampered, self::SECRET));
    }

    public function test_verify_reply_to_rejects_wrong_secret(): void
    {
        $address = MessageIdUtil::buildReplyTo(42, self::SECRET, self::DOMAIN);
        $this->assertNull(MessageIdUtil::verifyReplyTo($address, 'different-secret'));
    }

    public function test_verify_reply_to_rejects_malformed_input(): void
    {
        $this->assertNull(MessageIdUtil::verifyReplyTo(null, self::SECRET));
        $this->assertNull(MessageIdUtil::verifyReplyTo('', self::SECRET));
        $this->assertNull(MessageIdUtil::verifyReplyTo('alice@example.com', self::SECRET));
        $this->assertNull(MessageIdUtil::verifyReplyTo('reply@example.com', self::SECRET));
        $this->assertNull(MessageIdUtil::verifyReplyTo('reply+abc.deadbeef@example.com', self::SECRET));
    }

    public function test_verify_reply_to_case_insensitive_hex(): void
    {
        $address = MessageIdUtil::buildReplyTo(42, self::SECRET, self::DOMAIN);
        $this->assertEquals(42, MessageIdUtil::verifyReplyTo(strtoupper($address), self::SECRET));
    }
}
