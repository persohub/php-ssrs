<?php

require_once('Soap/NTLM.php');
require_once('Soap/Exception.php');
require_once('Object/Abstract.php');
require_once('Object/ArrayIterator.php');
require_once('Object/CatalogItems.php');
require_once('Object/CatalogItem.php');
require_once('Object/ItemDefinition.php');
require_once('Object/ExecutionParameter.php');
require_once('Object/ExecutionParameters.php');
require_once('Object/ExecutionInfo.php');
require_once('Object/Extensions.php');
require_once('Object/Extension.php');
require_once('Object/ReportParameter.php');
require_once('Object/ReportParameters.php');
require_once('Object/Report.php');
require_once('Object/ReportOutput.php');
require_once('Report/Exception.php');

/**
 * Description of SSRSReport
 *
 * @author Andrew Lowe
 */
class SSRS_Report {

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
        $this->_baseUri = rtrim($baseUri, '/');

        if (array_key_exists('username', $options)) {
            $this->setUsername($options['username']);
        }

        if (array_key_exists('password', $options)) {
            $this->setPassword($options['password']);
        }
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
            'Recursive' => $recursive
        );

        $result = $this->getSoapService()->ListChildren($params);
        return new SSRS_Object_CatalogItems($result);
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
     * Loads all details relating to a report including all available search parameters
     *
     * @param string $Report
     * @param string $HistoryId
     * @return SSRS_Object_Report
     */
    public function loadReport($Report, $HistoryId = null) {
        $params = array(
            'Report' => $Report,
            'HistoryID' => $HistoryId
        );

        $result = $this->getSoapExecution()->LoadReport($params);
        return new SSRS_Object_Report($result);
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
    public function render($format, $PaginationMode='Estimate') {
        $this->checkSessionId();

        $renderParams = array(
            'Format' => $format,
            'DeviceInfo' => '<DeviceInfo><Toolbar>False</Toolbar></DeviceInfo>',
            'PaginationMode' => $PaginationMode
        );

        $result = $this->getSoapExecution()->Render2($renderParams);
        return new SSRS_Object_ReportOutput($result);
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
     *
     */
    public function hasValidSessionId() {
        return (!empty($this->_sessionId));
    }

}