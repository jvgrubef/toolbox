<?php 
class AesEncryption{
    const AES_METHOD = 'aes-256-cbc';

    private $password;

    public function __construct($password) {
        if (OPENSSL_VERSION_NUMBER <= 268443727) {
            throw new RuntimeException('OpenSSL Version too old, vulnerability to Heartbleed');
        }

        $this->password = $password;
    }

    public function encrypt(string $message) {
        $iv_size        = openssl_cipher_iv_length(self::AES_METHOD);
        $iv             = openssl_random_pseudo_bytes($iv_size);
        $ciphertext     = openssl_encrypt($message, self::AES_METHOD, $this->password, OPENSSL_RAW_DATA, $iv);
        $ciphertext_hex = bin2hex($ciphertext);
        $iv_hex         = bin2hex($iv);
        return "$iv_hex:$ciphertext_hex";
    }

    public function decrypt(string $ciphered) {
        $iv_size    = openssl_cipher_iv_length(self::AES_METHOD);
        $data       = explode(":", $ciphered);
        $iv         = hex2bin($data[0]);
        $ciphertext = hex2bin($data[1]);
        return openssl_decrypt($ciphertext, self::AES_METHOD, $this->password, OPENSSL_RAW_DATA, $iv);
    }
}
?>