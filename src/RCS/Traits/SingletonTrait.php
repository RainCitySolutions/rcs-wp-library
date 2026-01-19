<?php
declare(strict_types=1);
namespace RCS\Traits;

// If this file is called directly, abort.
//defined('ABSPATH') || exit;

/**
 * Trait Singleton
 */
trait SingletonTrait {

    /**
     * Collection of instance class.
     *
     * @var object[]
     */
    protected static $instance = array();

    /**
     * Constructor which can be overwritten by child classes.
     *
     * Care should be taken in the constructor to avoid doing anything that
     * might call init() on the singleton as this will lead to a
     * recursive loop. It is preferred to do any initialization of the
     * instance in the initializeInstance() method.
     */
    final protected function __construct()
    {
        $self = get_class();

        if (get_parent_class($self)) {
            parent::__construct();  // @phpstan-ignore class.noParent
        }
    }

    /**
     * Called after a new instance of the singleton has been created. It is
     * preferred to do any initialization of the instance in this method and
     * not in the constructor.
     */
    protected function initializeInstance(): void
    {
    }

    protected static function triggerIncorrectUseWarning(string $function): void
    {
        trigger_error($function . ' should not be called on singleton class', E_USER_WARNING);
    }

    /**
     * A dummy magic method to prevent from being cloned
     */
    final public function __clone() {
        self::triggerIncorrectUseWarning(__FUNCTION__);
    }

    /**
     * A dummy magic method to prevent from being unserialized
     */
    final public function __wakeup() {
        self::triggerIncorrectUseWarning(__FUNCTION__);
    }

    /**
     * Initiator method.
     *
     * @param mixed ...$args
     *
     * @return object Instance of the called class.
     */
    public static function init(...$args): object
    {
        // Get name of the class where the method being called.
        $called_class = get_called_class();

        if ( !isset( static::$instance[ $called_class ] ) ) {
            $newObj = new $called_class(...$args);
            $newObj->initializeInstance();

            static::$instance[ $called_class ] = $newObj;
        }

        return static::$instance[ $called_class ];
    }
}
