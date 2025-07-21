<?php
class Encryptor {
    private $key;
    private $iv;

    public function __construct($key, $iv) {
        $this->key = hash('sha256', $key, true); // 256-bit key
        $this->iv = substr(hash('sha256', $iv), 0, 16); // 16-byte IV
    }

    public function encrypt($data) {
        return base64_encode(openssl_encrypt($data, 'AES-256-CBC', $this->key, OPENSSL_RAW_DATA, $this->iv));
    }

    public function decrypt($data) {
        return openssl_decrypt(base64_decode($data), 'AES-256-CBC', $this->key, OPENSSL_RAW_DATA, $this->iv);
    }
}
