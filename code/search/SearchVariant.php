<?php

/**
 * A Search Variant handles decorators and other situations where the items to reindex or search through are modified
 * from the default state - for instance, dealing with Versioned or Subsite
 */
abstract class SearchVariant {

	function __construct() {}

	/*** OVERRIDES start here */

	/**
	 * Variants can provide any functions they want, but they _must_ override these functions
	 * with specific ones
	 */

	/**
	 * Return true if this variant applies to the passed class & subclass
	 */
	abstract function appliesTo($class, $includeSubclasses);

	/**
	 * Return the current state
	 */
	abstract function currentState();
	/**
	 * Return all states to step through to reindex all items
	 */
	abstract function reindexStates();
	/**
	 * Activate the passed state
	 */
	abstract function activateState($state);

	/** Holds the class dependencies of each variant **/
	protected static $dependentClasses = array(
		'Subsite',
		'Versioned'
	);

	/*** OVERRIDES end here*/

	/** Holds a cache of all variants */
	protected static $variants = null;
	/** Holds a cache of the variants keyed by "class!" "1"? (1 = include subclasses) */
	protected static $class_variants = array();

	/**
	 * Returns an array of variants.
	 *
	 * With no arguments, returns all variants
	 *
	 * With a classname as the first argument, returns the variants that apply to that class
	 * (optionally including subclasses)
	 *
	 * @static
	 * @param string $class - The class name to get variants for
	 * @param bool $includeSubclasses - True if variants should be included if they apply to at least one subclass of $class
	 * @return array - An array of (string)$variantClassName => (Object)$variantInstance pairs
	 */
	public static function variants($class = null, $includeSubclasses = true) {
		if (!$class) {
			if (self::$variants === null) {
				$classes = ClassInfo::subclassesFor('SearchVariant');

				$concrete = array();
				foreach ($classes as $variantclass) {
					foreach(self::$dependentClasses as $dependency) {
						// Rather relies on variants being named similarly to their dependencies
						if(preg_match("#$dependency#i",$variantclass) && class_exists($dependency)) {
							$ref = new ReflectionClass($variantclass);
							if ($ref->isInstantiable()) $concrete[$variantclass] = singleton($variantclass);
						}
					}
				}
				self::$variants = $concrete;
			}

			return self::$variants;
		}
		else {
			$key = $class . '!' . $includeSubclasses;

			if (!isset(self::$class_variants[$key])) {
				self::$class_variants[$key] = array();

				foreach (self::variants() as $variantclass => $instance) {
					if ($instance->appliesTo($class, $includeSubclasses)) self::$class_variants[$key][$variantclass] = $instance;
				}
			}

			return self::$class_variants[$key];
		}
	}

	/** Holds a cache of SearchVariant_Caller instances, one for each class/includeSubclasses setting */
	protected static $call_instances = array();

	/**
	 * Lets you call any function on all variants that support it, in the same manner as "Object#extend" calls
	 * a method from extensions.
	 *
	 * Usage: SearchVariant::with(...)->call($method, $arg1, ...);
	 *
	 * @static
	 *
	 * @param string $class - (Optional) a classname. If passed, only variants that apply to that class will be checked / called
	 *
	 * @param bool $includeSubclasses - (Optional) If false, only variants that apply strictly to the passed class or its super-classes
	 * will be checked. If true (the default), variants that apply to any sub-class of the passed class with also be checked
	 *
	 * @return An object with one method, call()
	 */
	static function with($class = null, $includeSubclasses = true) {
		// Make the cache key
		$key = $class ? $class . '!' . $includeSubclasses : '!';
		// If no SearchVariant_Caller instance yet, create it
		if (!isset(self::$call_instances[$key])) self::$call_instances[$key] = new SearchVariant_Caller(self::variants($class, $includeSubclasses));
		// Then return it
		return self::$call_instances[$key];
	}

	/**
	 * A shortcut to with when calling without passing in a class,
	 *
	 * SearchVariant::call(...) ==== SearchVariant::with()->call(...);
	 */
	static function call($method, &$a1=null, &$a2=null, &$a3=null, &$a4=null, &$a5=null, &$a6=null, &$a7=null) {
		return self::with()->call($method, $a1, $a2, $a3, $a4, $a5, $a6, $a7);
	}

	/**
	 * Get the current state of every variant
	 * @static
	 * @return array
	 */
	static function current_state($class = null, $includeSubclasses = true) {
		$state = array();
		foreach (self::variants($class, $includeSubclasses) as $variant => $instance) {
			$state[$variant] = $instance->currentState();
		}
		return $state;
	}

	/**
	 * Activate all the states in the passed argument
	 * @static
	 * @param  (array) $state. A set of (string)$variantClass => (any)$state pairs , e.g. as returned by
	 * SearchVariant::current_state()
	 * @return void
	 */
	static function activate_state($state) {
		foreach (self::variants() as $variant => $instance) {
			if (isset($state[$variant])) $instance->activateState($state[$variant]);
		}
	}

	/**
	 * Return an iterator that, when used in a for loop, activates one combination of reindex states per loop, and restores
	 * back to the original state at the end
	 * @static
	 * @param string $class - The class name to get variants for
	 * @param bool $includeSubclasses - True if variants should be included if they apply to at least one subclass of $class
	 * @return SearchVariant_ReindexStateIteratorRet - The iterator to foreach loop over
	 */
	static function reindex_states($class = null, $includeSubclasses = true) {
		$allstates = array();

		foreach (self::variants($class, $includeSubclasses) as $variant => $instance) {
			if ($states = $instance->reindexStates()) $allstates[$variant] = $states;
		}

		return $allstates ? new CombinationsArrayIterator($allstates) : array(array());
	}
}

/**
 * Internal utility class used to hold the state of the SearchVariant::with call
 */
class SearchVariant_Caller {
	protected $variants = null;

	function __construct($variants) {
		$this->variants = $variants;
	}

	function call($method, &$a1=null, &$a2=null, &$a3=null, &$a4=null, &$a5=null, &$a6=null, &$a7=null) {
		$values = array();

		foreach ($this->variants as $variant) {
			if (method_exists($variant, $method)) {
				$value = $variant->$method($a1, $a2, $a3, $a4, $a5, $a6, $a7);
				if ($value !== null) $values[] = $value;
			}
		}

		return $values;
	}
}

