<?php

use SSRS\Object;

class ExecutionParameters extends ArrayIterator {

    public $iteratorKey = 'Parameters';

    public function __construct(array $parameters = array()) {
        parent::__construct([]);
        $this->setParameters($parameters);
    }

    public function init() {
        $this->data['Parameters'] = array();
    }

    public function setParameters(array $parameters) {
        $this->data['Parameters'] = array();

        foreach ($parameters AS $key => $parameter) {
            if (($parameter instanceof Object\ReportParameter) === false) {
                $values = (array) $parameter;
                foreach ($values AS $value) {
                    $this->data['Parameters'][] = new Object\ReportParameter($key, $value);
                }
            } else {
                $this->data['Parameters'][] = $parameter;
            }
        }
    }

    public function getParameters() {
        return $this->data['Parameters'];
    }

    public function getParameterArrayForSoapCall() {
        $execParams = array();
        foreach ($this->getParameters() AS $parameter) {
            $execParams[] = array(
                'Name' => $parameter->name,
                'Value' => $parameter->value,
            );
        }

        return $execParams;
    }

}
