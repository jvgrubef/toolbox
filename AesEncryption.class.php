<?php 
/**
 * AesEncryption class for encrypting and decrypting messages using AES encryption.
 *
 * @property string $password The encryption password.
 * @property string $method The AES encryption method used.
 */
class AesEncryption {
    private const SUPPORTED_METHODS = ['aes-128-cbc', 'aes-192-cbc', 'aes-256-cbc'];

    private $password;
    private $method;

    /**
     * Constructor for the AesEncryption class.
     *
     * @param string $password The encryption password.
     * @param string $method The AES encryption method (default: aes-256-cbc).
     * @throws RuntimeException If OpenSSL version is vulnerable to Heartbleed or if the method is not supported.
     */
    public function __construct($password, $method = 'aes-256-cbc') {
        if (OPENSSL_VERSION_NUMBER <= 268443727) {
            throw new RuntimeException('OpenSSL Version too old, vulnerability to Heartbleed');
        }

        if (!in_array($method, self::SUPPORTED_METHODS)) {
            throw new RuntimeException('Unsupported AES encryption method');
        }

        $this->password = $password;
        $this->method = $method;
    }

    /**
     * Encrypts a message using AES encryption.
     *
     * @param string $message The message to be encrypted.
     * @return string The encrypted message.
     */
    public function encrypt(string $message): string {
        $iv_size        = openssl_cipher_iv_length(self::AES_METHOD);
        $iv             = openssl_random_pseudo_bytes($iv_size);
        $ciphertext     = openssl_encrypt($message, self::AES_METHOD, $this->password, OPENSSL_RAW_DATA, $iv);
        $ciphertext_hex = bin2hex($ciphertext);
        $iv_hex         = bin2hex($iv);
        return "$iv_hex:$ciphertext_hex";
    }

    /**
     * Decrypts an AES-encrypted message.
     *
     * @param string $ciphered The AES-encrypted message.
     * @return string The decrypted message.
     */
    public function decrypt(string $ciphered: string {
        $iv_size    = openssl_cipher_iv_length(self::AES_METHOD);
        $data       = explode(":", $ciphered);
        $iv         = hex2bin($data[0]);
        $ciphertext = hex2bin($data[1]);
        return openssl_decrypt($ciphertext, self::AES_METHOD, $this->password, OPENSSL_RAW_DATA, $iv);
    }
}
?>
