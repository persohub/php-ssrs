<?php

/**
 * php-ssrs http://www.apache.org/licenses/LICENSE-2.0
 *
 * @author Arron Woods <arron@idealwebsites.co.uk>
 * @link http://code.idealwebsites.co.uk/php-ssrs/
 * @copyright Copyright &copy; 2011 Ideal Websites Ltd
 * @license 
 * @version 0.1
 */

namespace SSRS;

class Report {

    public $servicePath = 'ReportService2010.asmx';
    public $executionPath = 'ReportExecution2005.asmx';
    protected $_baseUri;
    protected $_username;
    protected $_passwd;
    protected $_soapService;
    protected $_soapExecution;
    protected $_executionNameSpace = 'http://schemas.microsoft.com/sqlserver/2005/06/30/reporting/reportingservices';
    protected $_headerExecutionLayout = '<ExecutionHeader xmlns="%s"><ExecutionID>%s</ExecutionID></ExecutionHeader>';
    protected $_sessionId;

    /**
     *
     * @param string $baseUri
     * @param array $options
     */
    public function __construct($baseUri, $options = array()) {
        $this->setBaseUri($baseUri);

        if (array_key_exists('username', $options)) {
            $this->setUsername($options['username']);
        }

        if (array_key_exists('password', $options)) {
            $this->setPassword($options['password']);
        }
    }

    public function setBaseUri($uri) {
        $this->_baseUri = rtrim($uri, '/');
    }

    public function getBaseUri() {
        return $this->_baseUri;
    }

    /**
     * Sets the Soap client class with the Execution Uri so that the connection to the web service can be made.
     * Should be the custom SOAP NTLM class to bypass NTLM security.
     *
     * @param SoapClient $client 
     */
    public function setSoapExecution(SoapClient $client) {
        $this->_soapExecution = $client;
        return $this;
    }

    /**
     * Sets the Soap client class with the Service Uri so that the connection to the web service can be made.
     * Should be the custom SOAP NTLM class to bypass NTLM security.
     *
     * @param SoapClient $client
     */
    public function setSoapService(SoapClient $client) {
        $this->_soapService = $client;
        return $this;
    }

    /**
     * Returns the SOAP client Execution object so that methods of the web service can be run.
     * If the SOAP Execution object is undefined then it will be set.
     *
     * @return SoapClient
     */
    public function getSoapExecution($runInit = true) {
        if ($this->_soapExecution === null) {
            $options = array('username' => $this->_username, 'password' => $this->_passwd);
            $client = new SSRS_Soap_NTLM($this->_baseUri . '/' . $this->executionPath, $options);
            if ($runInit) {
                $client->init();
            }

            $this->setSoapExecution($client);
        }

        return $this->_soapExecution;
    }

    /**
     * Returns the SOAP client Service object so that methods of the web service can be run.
     * If the SOAP Service object is undefined then it will be set.
     *
     * @return SoapClient
     */
    public function getSoapService($runInit = true) {
        if ($this->_soapService === null) {
            $options = array('username' => $this->_username, 'password' => $this->_passwd);
            $client = new SSRS_Soap_NTLM($this->_baseUri . '/' . $this->servicePath, $options);
            if ($runInit) {
                $client->init();
            }

            $this->setSoapService($client);
        }

        return $this->_soapService;
    }

    /**
     * Sets username property
     *
     * @param string $username
     * @return SSRS_Report
     */
    public function setUsername($username) {
        $this->_username = (string) $username;
        return $this;
    }

    /**
     * Sets password property
     *
     * @param string $password
     * @return SSRS_Report
     */
    public function setPassword($password) {
        $this->_passwd = (string) $password;
        return $this;
    }

    /**
     * Returns username property value
     *
     * @return string
     */
    public function getUsername() {
        return $this->_username;
    }

    /**
     * Returns password property value
     * 
     * @return string
     */
    public function getPassword() {
        return $this->_passwd;
    }

    /**
     * Sets Session ID, taken from the LoadReport method under property 'ExecutionID'.
     * Required for later methods to produce report.
     * Adds to the main SOAP header through the SOAP Execution object.
     *
     * @param string $id
     */
    public function setSessionId($id) {
        $client = $this->getSoapExecution();
        $parameters = array(array('name' => 'ExecutionID', 'value' => $id));

        $headerStr = sprintf($this->_headerExecutionLayout, $this->_executionNameSpace, $id);
        $soapVar = new SoapVar($headerStr, XSD_ANYXML, null, null, null);

        $soapHeader = new SoapHeader($this->_executionNameSpace, 'ExecutionHeader', $soapVar);
        $client->__setSoapHeaders(array($soapHeader));

        $this->_sessionId = $id;
        return $this;
    }

    /**
     * Returns a list of all child items from a specified location.
     * Used to show all reports available.
     *
     * @param string $itemPath
     * @param boolean $recursive
     * @return SSRS_Object_CatalogItems
     */
    public function listChildren($itemPath, $recursive = false) {
        $params = array(
            'ItemPath' => $itemPath,
            'Recursive' => (bool) $recursive
        );

        $result = $this->getSoapService()->ListChildren($params);
        return new SSRS_Object_CatalogItems($result);
    }

    /**
     * Returns item properties
     * 
     * @param string $path
     * @return \SSRS_Object_Properties
     */
    public function getProperties($itemPath) {
        $params = array(
            'ItemPath' => $itemPath,
        );

        $result = $this->getSoapService()->GetProperties($params);
        return new SSRS_Object_Properties($result->Values->Property);
    }

    /**
     * Returns item definition details in a XML string.
     * Used to backup report definitions into a XML based RDL file.
     *
     * @param string $itemPath
     * @return SSRS_Object_ItemDefinition
     */
    public function getItemDefinition($itemPath) {
        $params = array(
            'ItemPath' => $itemPath,
        );
        $result = $this->getSoapService()->GetItemDefinition($params);
        return new SSRS_Object_ItemDefinition($result);
    }

    /**
     * Returns a list of all render types to output reports to, such as XML, HTML & PDF.
     *
     * @return SSRS_Object_Extensions
     */
    public function listRenderingExtensions() {
        return new SSRS_Object_Extensions($this->getSoapExecution()->ListRenderingExtensions());
    }

    /**
     * 
     * @param type $toggleId
     * @return type
     */
    public function toggleItem($toggleId) {
        $params = array(
            'ToggleID' => $toggleId
        );
        return $this->getSoapExecution()->ToggleItem($params);
    }

    public function sort($sortId, $direction, $clear) {
        $params = array(
            'SortItem' => $sortId,
            'Direction' => $direction,
            'Clear' => $clear,
        );
        return $this->getSoapExecution()->Sort($params);
    }

    /**
     * Loads all details relating to a report including all available search parameters
     *
     * @param string $Report
     * @param string $HistoryId
     * @return SSRS_Object_ExecutionInfo
     */
    public function loadReport($Report, $HistoryId = null) {
        $params = array(
            'Report' => $Report,
            'HistoryID' => $HistoryId
        );

        $result = $this->getSoapExecution()->LoadReport($params);
        return new SSRS_Object_ExecutionInfo($result);
    }

    /**
     * Get current execution info
     * 
     * @return \SSRS_Object_ExecutionInfo
     */
    public function getExecutionInfo() {
        $result = $this->getSoapExecution()->GetExecutionInfo2();
        return new SSRS_Object_ExecutionInfo($result);
    }

    /**
     * Sets all search parameters for the report to render.
     * Pass details from 'LoadReport' method to set the search parameters.
     * Requires the Session/Execution ID to be set.
     *
     * @param SSRS_Object_ExecutionParameters $request
     * @param string $id
     * @return SSRS_Object_ExecutionInfo
     */
    public function setExecutionParameters(SSRS_Object_ExecutionParameters $parameters, $parameterLanguage = 'en-us') {
        $this->checkSessionId();

        $options = array(
            'Parameters' => $parameters->getParameterArrayForSoapCall(),
            'ParameterLanguage' => $parameterLanguage,
        );

        $result = $this->getSoapExecution()->SetExecutionParameters($options);
        return new SSRS_Object_ExecutionInfo($result);
    }

    /**
     * Renders and outputs report depending on $format variable.
     *
     * @param string $format
     * @param string $PaginationMode
     * @return SSRS_Object_ReportOutput
     */
    public function render($format, $deviceInfo = array(), $PaginationMode = 'Estimate') {
        $this->checkSessionId();
        $deviceInfo = array('DeviceInfo' => $deviceInfo);

        $renderParams = array(
            'Format' => $format,
            'DeviceInfo' => $this->renderDeviceInfo($deviceInfo),
            'PaginationMode' => $PaginationMode
        );

        $result = $this->getSoapExecution()->Render2($renderParams);
        return new SSRS_Object_ReportOutput($result);
    }

    /**
     * 
     * @param string $format
     * @param string $streamId
     * @param array $deviceInfo
     * @return \SSRS_Object_RenderStream
     */
    public function renderStream($format, $streamId, $deviceInfo = array()) {
        $this->checkSessionId();
        $deviceInfo = array('DeviceInfo' => array_merge(array('Toolbar' => 'false'), $deviceInfo));

        $renderParams = array(
            'Format' => $format,
            'StreamID' => $streamId,
            'DeviceInfo' => $this->renderDeviceInfo($deviceInfo),
        );

        $result = $this->getSoapExecution()->RenderStream($renderParams);
        return new SSRS_Object_RenderStream($result);
    }

    /**
     * 
     * @param string $format
     * @param string $streamId
     * @param array $deviceInfo
     * @return \SSRS_Object_RenderStream
     */
    public function getRenderResource($format, $deviceInfo = array()) {
        $this->checkSessionId();

        $deviceInfo = array('DeviceInfo' => $deviceInfo);

        $renderParams = array(
            'Format' => (string) $format,
            'DeviceInfo' => $this->renderDeviceInfo($deviceInfo),
        );

        $result = $this->getSoapExecution()->GetRenderResource($renderParams);
        return new SSRS_Object_RenderStream($result);
    }

    /**
     * Checks if there is a valid Session ID set.
     *
     */
    public function checkSessionId() {
        if ($this->hasValidSessionId() === false) {
            throw new SSRS_Report_Exception('Session ID not set');
        }
    }

    /**
     * Checks to see if the Session ID is not empty and returns boolean value
     * @return bool
     */
    public function hasValidSessionId() {
        return (!empty($this->_sessionId));
    }

    /**
     * Translate deviceInfo array into XML
     * Look out for translation entities
     * @param array $deviceInfo
     */
    public function renderDeviceInfo(array $deviceInfo) {
        $translations = array(
            '_SID_' => $this->_sessionId,
            '_TIME_' => time(),
        );
        return $this->renderXmlOptions($deviceInfo, $translations);
    }

    /**
     * Takes an array of options and converts them to an XML string recursively
     * @param array $options
     * @param array $translations
     * @return string $xml
     */
    public function renderXmlOptions(array $options, $translations = array()) {
        $xml = '';
        foreach ($options AS $key => $value) {
            switch (true) {
                case is_array($value):
                    $value = $this->renderXmlOptions($value, $translations);
                    break;
                case is_bool($value):
                    $value = ($value) ? 'true' : 'false';
                    break;
                default:
                    $value = strtr($value, $translations);
                    $value = htmlentities($value);
            }

            $key = preg_replace('/[^a-z0-9_\-]+/i', '_', $key);
            $xml .= sprintf('<%s>%s</%s>', $key, $value, $key);
        }

        return $xml;
    }

}
