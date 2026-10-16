<?php
namespace GhoroaHat\Rewards;

/** Optional HTTPS JSON SMS transport, with write-only encrypted credentials. */
final class SMS {
	public static function hooks(): void {
		add_filter(
			'ghr_sms_adapter',
			static function ( $adapter ) {
				if ( is_callable( $adapter ) ) {
					return $adapter; }
				return Settings::get( 'sms_endpoint' ) ? array( self::class, 'send' ) : null;
			},
			5
		);
	}
	public static function save_secret( string $secret ): void {
		if ( ! $secret ) {
			return; }
		if ( strlen( $secret ) > 4096 || preg_match( '/[\r\n\x00]/', $secret ) ) {
			throw new \DomainException( 'Invalid gateway credential.' ); }
		$nonce  = random_bytes( 12 );
		$tag    = '';
		$cipher = openssl_encrypt( $secret, 'aes-256-gcm', hash( 'sha256', wp_salt(), true ), OPENSSL_RAW_DATA, $nonce, $tag );
		if ( false === $cipher ) {
			throw new \RuntimeException( 'Credential encryption failed.' ); }
		update_option( 'ghr_sms_credential', base64_encode( $nonce . $tag . $cipher ), false );
		DB::audit( 'sms.credential_updated', 'settings' );
	}
	public static function send( string $phone, string $message, string $reference ): bool {
		if ( ! Settings::get( 'sms_enabled' ) ) {
			return false; }
		$url = Settings::get( 'sms_endpoint' );
		if ( ! $url || 'https' !== wp_parse_url( $url, PHP_URL_SCHEME ) || ! wp_http_validate_url( $url ) ) {
			throw new \DomainException( 'Configure an approved public HTTPS gateway endpoint.' ); }
		$headers = array(
			'Content-Type'    => 'application/json',
			'Idempotency-Key' => $reference,
		);
		$stored  = get_option( 'ghr_sms_credential', '' );
		if ( $stored ) {
			$bytes = base64_decode( $stored, true );
			if ( ! $bytes || strlen( $bytes ) < 29 ) {
				throw new \RuntimeException( 'Gateway credential is unavailable.' ); }
			$secret = openssl_decrypt( substr( $bytes, 28 ), 'aes-256-gcm', hash( 'sha256', wp_salt(), true ), OPENSSL_RAW_DATA, substr( $bytes, 0, 12 ), substr( $bytes, 12, 16 ) );
			if ( false === $secret ) {
				throw new \RuntimeException( 'Re-enter the gateway credential after rotating WordPress salts.' ); }
			$headers['Authorization'] = 'Bearer ' . $secret;
		}
		$body     = array(
			Settings::get( 'sms_phone_field' )   => $phone,
			Settings::get( 'sms_message_field' ) => $message,
		);
		$response = wp_safe_remote_post(
			$url,
			array(
				'headers'     => $headers,
				'body'        => wp_json_encode( $body ),
				'timeout'     => 15,
				'redirection' => 0,
			)
		);
		return ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) >= 200 && wp_remote_retrieve_response_code( $response ) < 300;
	}
}
