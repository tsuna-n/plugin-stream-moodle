<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Contains unit tests for mod_livestream\local\realtime.
 *
 * @package   mod_livestream
 * @category  test
 * @copyright 2026 Your Name
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_livestream\local;

use advanced_testcase;

/**
 * Class for unit testing mod_livestream\local\realtime.
 *
 * The signing scheme in sign()/base64url_encode() must produce byte-identical
 * output to the Node gateway's verification code (see realtime/server.js and
 * CLAUDE.md section 4.1) -- this is checked against a fixed known vector so
 * any accidental drift between the two implementations fails loudly here
 * instead of as a silent "every token gets rejected" bug in production.
 *
 * @copyright 2026 Your Name
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class realtime_test extends advanced_testcase {

    /**
     * A fixed payload/secret/expected-signature vector. If this assertion
     * ever fails after touching sign()/base64url_encode(), the Node gateway's
     * verification code (realtime/server.js) needs the matching update too.
     */
    public function test_sign_matches_known_vector(): void {
        $payload = [
            'uid' => 42,
            'room' => 'cm-7',
            'sk' => 'abc123',
            'cid' => 3,
            'cm' => 7,
            'mod' => 1,
            'exp' => 1780000000,
        ];
        $secret = 'testsecret';

        $token = realtime::sign($payload, $secret);
        [$b64, $sig] = explode('.', $token, 2);

        // Known vector: json_encode() of the payload above, base64url-encoded,
        // HMAC-SHA256 signed with 'testsecret'. Computed independently of the
        // production code path (a plain hash_hmac call) so this test would
        // catch a regression in either half of sign().
        $expectedjson = '{"uid":42,"room":"cm-7","sk":"abc123","cid":3,"cm":7,"mod":1,"exp":1780000000}';
        $this->assertSame($expectedjson, json_encode($payload));

        $expectedb64 = rtrim(strtr(base64_encode($expectedjson), '+/', '-_'), '=');
        $this->assertSame($expectedb64, $b64);

        $expectedsig = hash_hmac('sha256', $expectedb64, $secret);
        $this->assertSame($expectedsig, $sig);

        // No '=' padding, no '+' or '/' -- must be safe to ride a query string
        // unencoded (EventSource cannot set headers, so the token goes in the
        // SSE URL's ?token= param).
        $this->assertStringNotContainsString('=', $b64);
        $this->assertStringNotContainsString('+', $b64);
        $this->assertStringNotContainsString('/', $b64);
    }

    /**
     * token() round-trips uid/room/streamkey/course/cm/moderate/exp through
     * the real get_config()-backed secret, decodable back into the same
     * payload -- the path get_realtime_token.php actually calls.
     */
    public function test_token_round_trip(): void {
        $this->resetAfterTest();
        set_config('realtimesecret', 'round-trip-secret', 'mod_livestream');

        $before = time();
        $token = realtime::token(99, 'course-5', '', 5, 0, false);
        $after = time();

        [$b64, $sig] = explode('.', $token, 2);
        $expectedsig = hash_hmac('sha256', $b64, 'round-trip-secret');
        $this->assertSame($expectedsig, $sig);

        $json = base64_decode(strtr($b64, '-_', '+/'), true);
        $payload = json_decode($json, true);

        $this->assertSame(99, $payload['uid']);
        $this->assertSame('course-5', $payload['room']);
        $this->assertSame('', $payload['sk']);
        $this->assertSame(5, $payload['cid']);
        $this->assertSame(0, $payload['cm']);
        $this->assertSame(0, $payload['mod']);
        $this->assertGreaterThanOrEqual($before + 60, $payload['exp']);
        $this->assertLessThanOrEqual($after + 60, $payload['exp']);
    }

    /**
     * enabled() is the single on/off switch every AMD module's fallback
     * decision depends on -- must track realtimeurl exactly.
     */
    public function test_enabled_reflects_realtimeurl(): void {
        $this->resetAfterTest();

        set_config('realtimeurl', '', 'mod_livestream');
        $this->assertFalse(realtime::enabled());

        set_config('realtimeurl', 'https://rt.example.com', 'mod_livestream');
        $this->assertTrue(realtime::enabled());
    }
}
