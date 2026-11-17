<?php
declare(strict_types = 1);
namespace RCS\WP\Cookies;

use RuntimeException;
use Psr\Log\LoggerInterface;

/**
 * Class used to manage cookies within a WordPress environment.
 *
 * The value used for the HTTP cookie is a unique hash value. The hash value
 * is then used as the key for the actual value which is stored as WordPress
 * transient data. This prevents any internal data from being used in the
 * cookie value and keeps the HTTP cookie value to a fixed length.
 */
class WordPressCookie
{
    /**
     * Pattern a cookie value must match to be accepted as a hash value.
     * Matches the output of hash('sha256', ...): 64 lowercase hex characters.
     */
    private const HASH_PATTERN = '/^[0-9a-f]{64}$/D';

    private string $hashValue;

    /**
     * Constructs an instance of WordPressCookie.
     *
     * If the cookie has previously been set and its value is a valid hash
     * value, the value of the cookie is used as the hash value. Otherwise
     * (the cookie is missing, or its value is malformed or has been tampered
     * with), assuming no data has been sent to the browser, a new hash value
     * is created and the cookie is set to that value, replacing any invalid
     * value.
     *
     * @param string $cookieName The name of the cookie.
     * @param string $hashKey A key to be used in creating the hash value.
     * @param int $lifetime The time, in seconds, before the cookie should expire. Defaults to 24 hours.
     * @param string $path The HTTP path the cookie should apply to. Defaults to '/'.
     *
     * @throws WordPressCookieException Thrown if a valid cookie isn't
     *     currently set and cannot be set because data has already been sent
     *     to the browser. This would generally indicate that the object was
     *     instantiated at the wrong place in the code.
     */
    public function __construct(
        protected readonly LoggerInterface $logger,
        private string $cookieName,
        string $hashKey,
        private int $lifetime = 86400,
        string $path = '/'
        )
    {
        $this->cookieName = $cookieName;
        $this->lifetime = $lifetime;

        if (self::isValidHash($_COOKIE[$this->cookieName] ?? null)) {
            $this->hashValue = $_COOKIE[$this->cookieName];
        }
        else {
            $file = '';
            $line = 0;

            if (\headers_sent($file, $line)) {
                $msg = \sprintf('Unable to create cookie, headers already sent, line %d in %s', $line, $file);
                $this->logger->warning($msg);
                throw new WordPressCookieException($msg);
            }
            else {
                $this->hashValue = \hash('sha256', $hashKey . '-' . \uniqid());

                \setcookie($this->cookieName, $this->hashValue, \time() + $this->lifetime, $path, '', false, true);
            }
        }
    }

    /**
     * Determines whether a value, such as one read from $_COOKIE, is a
     * well-formed hash value.
     *
     * Cookie values come from the client and cannot be trusted. $_COOKIE
     * entries can also be arrays (e.g. "name[]=x"), so the type is checked
     * before the format.
     */
    private static function isValidHash(mixed $value): bool
    {
        return \is_string($value) && \preg_match(self::HASH_PATTERN, $value) === 1;
    }

    /**
     * Gets the value for the cookie.
     *
     * @return NULL|mixed Returns the value or null if the value does not exist.
     */
    public function getCookieValue(): mixed
    {
        $value = null;

        $transient = \get_transient($this->hashValue);

        if ($transient !== false) {
            $value = $transient;
        }

        return $value;
    }

    /**
     * Sets the value for the cookie.
     *
     * @param mixed $value The value to be associated with the cookie.
     *
     * @return boolean Returns true if the value is saved, otherwise returns false.
     */
    public function setCookieValue(mixed $value): bool
    {
        return \set_transient($this->hashValue, $value, $this->lifetime);
    }

    /**
     * Delete the cookie value.
     *
     * Removes the value associated with the cookie.
     */
    public function deleteCookieValue(): void
    {
        \delete_transient($this->hashValue);
    }
}

/**
 * Exception thrown if a WordPressCookie object is instantiated but the cookie
 * is not currently set (or is invalid) and cannot be set because data has
 * already been sent to the browser.
 */
class WordPressCookieException extends RuntimeException {

}
