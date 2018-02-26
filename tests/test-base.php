<?php
/**
 * Stripe_Connect_For_WooCommerce.
 *
 * @since   0.1.0
 * @package Stripe_Connect_For_WooCommerce
 */
class Stripe_Connect_For_WooCommerce_Test extends WP_UnitTestCase {

	/**
	 * Test if our class exists.
	 *
	 * @since  0.1.0
	 */
	function test_class_exists() {
		$this->assertTrue( class_exists( 'Stripe_Connect_For_WooCommerce') );
	}

	/**
	 * Test that our main helper function is an instance of our class.
	 *
	 * @since  0.1.0
	 */
	function test_get_instance() {
		$this->assertInstanceOf(  'Stripe_Connect_For_WooCommerce', stripe_connect_for_woocommerce() );
	}

	/**
	 * Replace this with some actual testing code.
	 *
	 * @since  0.1.0
	 */
	function test_sample() {
		$this->assertTrue( true );
	}
}
