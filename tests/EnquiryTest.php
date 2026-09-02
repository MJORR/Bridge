<?php

/**
 * Bridge — the contact form's three decisions.
 *
 * What a submission is checked against, what makes one look like spam, and
 * whether the form that produced it was really drawn by this site. All three
 * are pure — an array in, an array out — which is why they can be tested at
 * all without a WordPress install, and why they are the parts worth testing:
 * a regression in any of them ships either a form nobody can submit or a form
 * every script can.
 *
 * Nothing here touches the storing, the emails or the admin screen. Those talk
 * to the database, and a double for them would be a test of the double.
 *
 * @package Bridge
 */

declare(strict_types=1);

final class EnquiryTest extends BridgeTestCase
{
	/**
	 * A submission with nothing wrong with it.
	 *
	 * @param array<string,string> $overrides Fields to change.
	 * @return array<string,string>
	 */
	private function submission(array $overrides = array()): array
	{
		return array_merge(
			array(
				'name'    => 'Jane Smith',
				'email'   => 'jane@example.com',
				'phone'   => '01234 567890',
				'message' => 'We are looking for a new website and would like to talk it through.',
			),
			$overrides
		);
	}

	// ---- Validation -------------------------------------------------------

	public function test_a_complete_submission_has_no_errors(): void
	{
		$result = bridge_enquiry_validate($this->submission());

		$this->assertSame(array(), $result['errors']);
		$this->assertSame('Jane Smith', $result['values']['name']);
		$this->assertSame('jane@example.com', $result['values']['email']);
	}

	public function test_every_required_field_reports_itself(): void
	{
		$result = bridge_enquiry_validate(
			$this->submission(
				array(
					'name'    => '',
					'email'   => '',
					'message' => '',
				)
			)
		);

		$this->assertArrayHasKey('name', $result['errors']);
		$this->assertArrayHasKey('email', $result['errors']);
		$this->assertArrayHasKey('message', $result['errors']);
	}

	public function test_a_missing_field_is_not_reported_twice(): void
	{
		// One message per field. An empty box that says both "this is required"
		// and "this is too short" is a box arguing with itself.
		$result = bridge_enquiry_validate($this->submission(array('name' => '')));

		$this->assertCount(1, $result['errors']);
	}

	public function test_an_address_that_is_not_one_is_refused(): void
	{
		$result = bridge_enquiry_validate($this->submission(array('email' => 'jane.example.com')));

		$this->assertArrayHasKey('email', $result['errors']);
	}

	/**
	 * The whole point of not validating a phone number by pattern: every one of
	 * these is how somebody writes their number, and none of them is a mistake.
	 *
	 * @dataProvider provide_phone_numbers
	 */
	public function test_a_phone_number_is_judged_on_its_digits(string $phone, bool $valid): void
	{
		$result = bridge_enquiry_validate($this->submission(array('phone' => $phone)), true);

		$this->assertSame($valid, ! isset($result['errors']['phone']), $phone);
	}

	/**
	 * @return array<string, array{0:string,1:bool}>
	 */
	public static function provide_phone_numbers(): array
	{
		return array(
			'spaced'        => array('01234 567890', true),
			'bracketed'     => array('(01234) 567 890', true),
			'international' => array('+44 1234 567890', true),
			'dotted'        => array('01234.567.890', true),
			'run together'  => array('01234567890', true),
			'too short'     => array('12345', false),
			'too long'      => array('0123456789012345678', false),
			'not a number'  => array('call me', false),
		);
	}

	public function test_a_phone_number_is_optional_unless_the_form_asked_for_one(): void
	{
		$without = bridge_enquiry_validate($this->submission(array('phone' => '')), false);
		$with    = bridge_enquiry_validate($this->submission(array('phone' => '')), true);

		$this->assertArrayNotHasKey('phone', $without['errors']);
		$this->assertArrayHasKey('phone', $with['errors']);
	}

	public function test_a_name_carrying_a_link_is_refused(): void
	{
		// Not a judgement about the visitor: it is the commonest shape of link
		// spam there is, and refusing it here means it never reaches the score.
		$result = bridge_enquiry_validate($this->submission(array('name' => 'Buy at https://example.com')));

		$this->assertArrayHasKey('name', $result['errors']);
	}

	public function test_a_value_is_cut_to_the_length_the_field_allows(): void
	{
		$fields = bridge_enquiry_fields();
		$result = bridge_enquiry_validate($this->submission(array('name' => str_repeat('a', 500))));

		$this->assertSame($fields['name']['maxlength'], mb_strlen($result['values']['name']));
	}

	public function test_a_field_nobody_sent_is_an_empty_string(): void
	{
		// The handler passes whatever arrived. A submission missing a key
		// entirely must be the same thing as one that sent it empty, not a
		// notice about an undefined index.
		$result = bridge_enquiry_validate(array());

		$this->assertSame('', $result['values']['message']);
		$this->assertArrayHasKey('message', $result['errors']);
	}

	// ---- The score --------------------------------------------------------

	/**
	 * The signals a browser sends when a person is filling the form in.
	 *
	 * @param array<string,mixed> $overrides Signals to change.
	 * @return array<string,mixed>
	 */
	private function signals(array $overrides = array()): array
	{
		return array_merge(
			array(
				'honeypot' => false,
				'elapsed'  => 40,
				'js'       => true,
			),
			$overrides
		);
	}

	private function held(array $values, array $signals): bool
	{
		$report = bridge_enquiry_spam_report($values, $signals);

		return (int) $report['score'] >= bridge_enquiry_spam_threshold();
	}

	public function test_an_ordinary_enquiry_is_delivered(): void
	{
		$this->assertFalse($this->held($this->submission(), $this->signals()));
	}

	public function test_the_honeypot_is_conclusive_on_its_own(): void
	{
		$this->assertTrue($this->held($this->submission(), $this->signals(array('honeypot' => true))));
	}

	public function test_a_form_filled_in_instantly_is_held(): void
	{
		$this->assertTrue($this->held($this->submission(), $this->signals(array('elapsed' => 1))));
	}

	/**
	 * The guarantee that makes the score safe to run at all: a visitor with
	 * scripts blocked is a visitor, not a bot, and nothing about them alone may
	 * hold their message back.
	 */
	public function test_a_browser_without_javascript_is_still_delivered(): void
	{
		$this->assertFalse($this->held($this->submission(), $this->signals(array('js' => false))));
	}

	public function test_one_link_is_ordinary_and_three_are_not(): void
	{
		$one = $this->submission(
			array('message' => 'Our current site is at https://example.com and we would like to replace it.')
		);

		$many = $this->submission(
			array('message' => 'See https://a.example https://b.example and https://c.example for our offers.')
		);

		$this->assertFalse($this->held($one, $this->signals()));
		$this->assertTrue($this->held($many, $this->signals()));
	}

	public function test_link_markup_in_a_plain_text_message_is_held(): void
	{
		$values = $this->submission(array('message' => 'Great site [url=https://example.com]click here[/url] thanks'));

		$this->assertTrue($this->held($values, $this->signals()));
	}

	public function test_a_held_submission_says_why(): void
	{
		// The reasons are printed on the admin screen. A held enquiry with no
		// explanation asks a client to trust a number they cannot see.
		$report = bridge_enquiry_spam_report($this->submission(), $this->signals(array('honeypot' => true)));

		$this->assertNotEmpty($report['reasons']);
		$this->assertIsString($report['reasons'][0]);
	}

	// ---- The signed clock -------------------------------------------------

	public function test_a_stamp_this_site_minted_reads_back(): void
	{
		$stamp = bridge_enquiry_stamp(true);
		$read  = bridge_enquiry_read_stamp($stamp['stamp'], $stamp['signature']);

		$this->assertIsArray($read);
		$this->assertTrue($read['p']);
		$this->assertEqualsWithDelta(time(), $read['t'], 5);
	}

	public function test_the_phone_requirement_travels_with_the_stamp(): void
	{
		// Otherwise it would have to travel as a field, and a field is a thing
		// whoever is posting can change.
		$stamp = bridge_enquiry_stamp(false);
		$read  = bridge_enquiry_read_stamp($stamp['stamp'], $stamp['signature']);

		$this->assertIsArray($read);
		$this->assertFalse($read['p']);
	}

	public function test_a_back_dated_stamp_does_not_verify(): void
	{
		$stamp = bridge_enquiry_stamp(false);

		// The whole attack the signature exists for: re-date the form so the
		// three-second floor is cleared without waiting three seconds.
		$forged = base64_encode((string) wp_json_encode(array('t' => time() - 600, 'p' => 0)));

		$this->assertNull(bridge_enquiry_read_stamp($forged, $stamp['signature']));
	}

	public function test_an_unsigned_stamp_does_not_verify(): void
	{
		$stamp = bridge_enquiry_stamp(false);

		$this->assertNull(bridge_enquiry_read_stamp($stamp['stamp'], ''));
		$this->assertNull(bridge_enquiry_read_stamp('', $stamp['signature']));
		$this->assertNull(bridge_enquiry_read_stamp($stamp['stamp'], 'not-a-signature'));
	}

	public function test_a_signed_stamp_that_is_not_a_stamp_does_not_verify(): void
	{
		// Correctly signed rubbish. The signature says it came from us; the
		// contents still have to be readable, or the elapsed time is measured
		// against nothing.
		$junk = base64_encode('not json at all');

		$this->assertNull(bridge_enquiry_read_stamp($junk, wp_hash($junk, 'nonce')));
	}
}
