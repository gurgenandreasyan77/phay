<?php

namespace Tests\Unit;

use App\Security\PhoneNumber;
use App\Security\Totp;
use PHPUnit\Framework\TestCase;

class TotpTest extends TestCase
{
    /** RFC 6238 appendix B (SHA-1), last 6 digits. */
    public function test_codes_match_the_rfc_test_vectors(): void
    {
        $secret = Totp::base32Encode('12345678901234567890');

        $this->assertSame('287082', Totp::code($secret, 59));
        $this->assertSame('081804', Totp::code($secret, 1111111109));
        $this->assertSame('005924', Totp::code($secret, 1234567890));
        $this->assertSame('353130', Totp::code($secret, 20000000000));
    }

    public function test_a_code_is_accepted_one_step_either_way_and_only_once(): void
    {
        $secret = Totp::generateSecret();
        $now = 1_700_000_000;

        $step = Totp::verify($secret, Totp::code($secret, $now - 30), null, $now);
        $this->assertSame(intdiv($now, 30) - 1, $step);
        $this->assertNull(Totp::verify($secret, Totp::code($secret, $now - 90), null, $now));
        $this->assertNull(Totp::verify($secret, Totp::code($secret, $now), intdiv($now, 30), $now));
        $this->assertNull(Totp::verify($secret, 'abcdef', null, $now));
        $this->assertNotNull(Totp::verify($secret, '  '.Totp::code($secret, $now), null, $now));
    }

    public function test_the_uri_is_what_authenticator_apps_read(): void
    {
        $uri = Totp::uri('JBSWY3DPEHPK3PXP', 'ani@shop.am', 'Servio');

        $this->assertStringStartsWith('otpauth://totp/Servio:ani%40shop.am?secret=JBSWY3DPEHPK3PXP&issuer=Servio', $uri);
    }

    public function test_armenian_phone_numbers_are_normalized(): void
    {
        $this->assertSame('+37455123456', PhoneNumber::normalize('055 12 34 56'));
        $this->assertSame('+37455123456', PhoneNumber::normalize('55-12-34-56'));
        $this->assertSame('+37455123456', PhoneNumber::normalize('+374 (55) 123456'));
        $this->assertSame('+37455123456', PhoneNumber::normalize('0037455123456'));
        $this->assertSame('+79161234567', PhoneNumber::normalize('+7 916 123 45 67'));
        $this->assertNull(PhoneNumber::normalize('12345'));
        $this->assertNull(PhoneNumber::normalize('call me'));
    }
}
