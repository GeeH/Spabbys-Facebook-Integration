<?php
class SFB_BaseController extends Zend_Controller_Action
{

    /**
     * Sets property from an array
     * @param array $array An array of properties to set
     * @return obj
     */
    public function setFromArray(array $array)
    {
        foreach ($array as $key => $value) {
            try {
            $this->setProperty($key, $value);
            } Catch (InvalidArgumentException $e) {
                //Skip missing properties (lets this work easily with form data)
                continue;
            }
        }
        return $this;
    }

    /**
     * Gets value of property (also invoked on $Player->variable
     * @param string $name  name of the property
     * @return string
     */
    public function __get($name)
    {
        $method = 'get' . ucfirst($name);
        if (method_exists($this, $method)) {
            return $this->{$method}();
        }
        $property = '_' . ucfirst($name);
        if (property_exists($this, $property)) {
            return $this->{$property};
        }

        throw new Zend_Controller_Exception("Trying to access undefined offset {$name}");
    }

    /**
     * Sets a property
     * @param string $name  private variable name
     * @param string $value  value to set variable to
     */
    public function __set($name, $value)
    {
        $method = 'set' . ucfirst($name);
        if (method_exists($this, $method)) {
            $this->{$method}($value);
            return $this;
        }
        $name = ucfirst($name);
        $property = '_' . $name;
        if (property_exists($this, $property)) {
            $this->{$property} = $value;
            return $this;
        }
        throw new InvalidArgumentException("Property by name {$name} does " .
                                           "not exist");
    }

}
?>
