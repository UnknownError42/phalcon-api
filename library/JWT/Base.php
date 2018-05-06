<?php

namespace Niden\JWT;

use const JSON_BIGINT_AS_STRING;
use const JSON_ERROR_NONE;
use function array_keys;
use function json_decode;
use function json_encode;
use function json_last_error;
use function json_last_error_msg;
use function strtoupper;
use function ucfirst;

class Base
{
    /** @var int */
    private $timeDrift = 0;
    /** @var int */
    private $timestamp = 0;

    /**
     * Checks if the cipher supplied is supported or not
     *
     * @param string $name
     *
     * @return bool
     */
    public function isAlgorithmSupported(string $name): bool
    {
        $cipher  = strtoupper($name);
        $ciphers = Claims::JWT_CIPHERS;

        return isset($ciphers[$cipher]);
    }

    /**
     * Returns an array of all supported algoritms
     *
     * @return array
     */
    public function getSupportedAlgorithms(): array
    {
        return array_keys(Claims::JWT_CIPHERS);
    }

    /**
     * Decode a JSON string to a PHP object/array
     *
     * @param string $input
     * @param bool   $assoc
     * @param int    $depth
     * @param int    $options
     *
     * @return mixed
     * @throws Exception
     */
    public function jsonDecode(
        string $input,
        bool $assoc = true,
        int $depth = 512,
        int $options = JSON_BIGINT_AS_STRING
    ) {
        $result = json_decode($input, $assoc, $depth, $options);
        $this->processJsonError();

        return $result;
    }

    /**
     * Encode a PHP object into a JSON string
     *
     * @param object|array $input
     * @param int          $options
     * @param int          $depth
     *
     * @return string
     * @throws Exception
     */
    public function jsonEncode($input, int $options = JSON_BIGINT_AS_STRING, int $depth = 512): string
    {
        $result = json_encode($input, $options, $depth);
        $this->processJsonError();

        return $result;
    }

    /**
     * Sign a string given a key and an cipher
     *
     * @param string $message
     * @param string $key
     * @param string $cipher
     *
     * @return string|null
     * @throws Exception
     */
    public function sign(string $message, string $key, string $cipher = Claims::JWT_CIPHER_HS256)
    {
        if (true !== $this->isAlgorithmSupported($cipher)) {
            throw new Exception('Cipher not supported');
        }

        list($function, $signCipher) = Claims::JWT_CIPHERS[$cipher];

        $method = 'sign' . ucfirst($function);

        return $this->{$method}($signCipher, $message, $key);
    }

    /**
     * Decodes a string encoded with the urlSafeBase64Encode function
     *
     * @param string $string
     *
     * @return string
     */
    public function urlSafeBase64Decode(string $string): string
    {
        return base64_decode(strtr($string, ['-_|' => '+/=']));
    }

    /**
     * Encodes a string with base64 keeping it URL safe
     *
     * @param string $string
     *
     * @return string
     */
    public function urlSafeBase64Encode(string $string): string
    {
        return strtr(base64_encode($string), ['+/=' => '-_|']);
    }

    /**
     * If there was an error, throw an exception with the message
     *
     * @throws Exception
     */
    private function processJsonError()
    {
        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new Exception(
                'json_decode error: ' . json_last_error_msg()
            );
        }
    }

    /**
     * Sign with hash_hmac
     *
     * @param string $cipher
     * @param string $message
     * @param string $key
     *
     * @return string
     */
    private function signHmac(string $cipher, string $message, string $key)
    {
        return hash_hmac($cipher, $message, $key, true);
    }

    /**
     * Sign with openssl
     *
     * @param string $cipher
     * @param string $message
     * @param string $key
     *
     * @return string
     * @throws Exception
     */
    private function signOpenssl(string $cipher, string $message, string $key)
    {
        $signature = '';
        $success   = openssl_sign($message, $signature, $key, $this->cipherToOpenssl($cipher));
        if (true !== $success) {
            throw new Exception('OpenSSL unable to sign data');
        }

        return $signature;
    }

    /**
     * Converts a passed string cipher to the OPENSSL numeric constant
     *
     * @param string $cipher
     *
     * @return int
     */
    private function cipherToOpenssl(string $cipher): int
    {
        $ciphers = [
            'SHA1'   => OPENSSL_ALGO_SHA1,
            'MD5'    => OPENSSL_ALGO_MD5,
            'MD4'    => OPENSSL_ALGO_MD4,
            'MD2'    => OPENSSL_ALGO_MD2,
            'DSS1'   => OPENSSL_ALGO_DSS1,
            'SHA224' => OPENSSL_ALGO_SHA224,
            'SHA256' => OPENSSL_ALGO_SHA256,
            'SHA384' => OPENSSL_ALGO_SHA384,
            'SHA512' => OPENSSL_ALGO_SHA512,
            'RMD160' => OPENSSL_ALGO_RMD160,
        ];

        return $ciphers[$cipher] ?? OPENSSL_ALGO_SHA256;
    }
}
