<?php
/* vim: set noai expandtab tabstop=4 softtabstop=4 shiftwidth=4: */
/**
 * System_Daemon turns PHP-CLI scripts into daemons.
 * 
 * PHP version 5
 *
 * @category  System
 * @package   System_Daemon
 * @author    Kevin van Zonneveld <kevin@vanzonneveld.net>
 * @copyright 2008 Kevin van Zonneveld (http://kevin.vanzonneveld.net)
 * @license   http://www.opensource.org/licenses/bsd-license.php New BSD Licence
 * @version   SVN: Release: $Id$
 * @link      http://trac.plutonia.nl/projects/system_daemon
 */

/**
 * Operating System focussed functionality.
 *
 * @category  System
 * @package   System_Daemon
 * @author    Kevin van Zonneveld <kevin@vanzonneveld.net>
 * @copyright 2008 Kevin van Zonneveld (http://kevin.vanzonneveld.net)
 * @license   http://www.opensource.org/licenses/bsd-license.php New BSD Licence
 * @version   SVN: Release: $Id$
 * @link      http://trac.plutonia.nl/projects/system_daemon
 * 
 */
class System_Daemon_OS
{

    /**
     * Holds errors
     *
     * @var array
     */
    public $errors = array();
    
    
        
    /**
     * Template path
     *
     * @var string
     */
    protected $autoRunTemplatePath = "";    
        
    /**
     * Replace the following keys with values to convert a template into
     * a read autorun script
     *
     * @var array
     */
    protected $autoRunTemplateReplace = array();
    
    
    
    /**
     * Hold OS information
     *
     * @var array
     */
    private $_osDetails = array();
    
    
    
    /**
     * Constructor
     * Only run by instantiated OS Drivers
     */
    public function __construct() 
    {
        // Up to date filesystem information
        clearstatcache();

        // Get ancestors
        $ancs = System_Daemon_OS::_getAncestors($this);
        foreach ($ancs as $i=>$anc) {
            $ancs[$i] = System_Daemon_OS::_getShortHand($anc);
        }        
        
        // Set OS Details
        $this->_osDetails["shorthand"] = $this->_getShortHand(get_class($this));
        $this->_osDetails["ancestors"] = $ancs;
    }

    /**
     * Loads all the drivers and returns the one for the most specifc OS
     *
     * @return unknown
     */
    public function &factory()
    {
        
        $drivers      = array();
        $driversValid = array();
        $class_prefix = "System_Daemon_OS_";
        
        // Load all drivers
        $driver_dir = realpath(dirname(__FILE__)."/OS");
        foreach (glob($driver_dir."/*.php") as $driver_path) {
            // Set names
            $driver = basename($driver_path, ".php");
            $class  = $class_prefix.$driver;
            
            // Only do this for real drivers
            if ($driver == "Exception" || !is_file($driver_path)) {
                continue;
            }
            
            // Let SPL include & load the driver or Report errors
            if (!class_exists($class, true)) {
                $this->errors[] = "Class ".$class." does not exist";
                return false;
            }

            // Save in drivers array
            $drivers[$class] = new $class;            
        }
        
        
        // What OSes are valid for this system?
        // e.g. Debian makes Linux valid as well
        foreach ($drivers as $class=>$obj) {
            // Save in Installed container
            if (call_user_func(array($obj, "isInstalled"))) {
                $driversValid[$class] = $obj;         
            }
        }
        
        // What's the most specific OS?
        // e.g. Ubuntu > Debian > Linux    
        $use_name = System_Daemon_OS::_mostSpecific($driversValid);
        $obj      = $driversValid[$use_name];
                        
        return $obj;
    }//end &factory()
        
    /**
     * Determines wether the system is compatible with this OS
     *
     * @return boolean
     */    
    public function isInstalled() 
    {
        $this->errors[] = "Not implemented for OS";
        return false;
    }//end isInstalled
    
    /**
     * Returns array with all the specific details of the loaded OS
     *
     * @return array
     */
    public function getDetails()
    {
        return $this->_osDetails;
    }//end getDetails
    
    /**
     * Returns OS specific path to autoRun file
     * 
     * @param string $appName Unix-proof name of daemon
     *
     * @return string
     */
    public function getAutoRunPath($appName) 
    {
        if (!$this->autoRunDir) {
            $this->errors[] = "autoRunDir is not set";
            return false;
        }
        
        $path = $this->autoRunDir."/".$appName;
        
        // Path exists
        if (!is_dir($dir = dirname($path))) {
            $this->errors[] = "Directory: '".$dir."' does not exist. ".
                "How can this be a correct path?";
            return false;
        }
        
        // Is writable?
        if (!is_writable($dir)) {
            $this->errors[] = "Directory: '".$dir."' is not writable. ".
                "Maybe run as root?";
            return false;
        }
        
        return $path;
    }//end getAutoRunPath
    
    /**
     * Returns a template to base the autuRun script on.
     * Uses $autoRunTemplatePath if possible. 
     *
     * @return unknown
     * @see autoRunTemplatePath
     */
    public function getAutoRunTemplate() 
    {
        if (!$this->autoRunTemplatePath) {
            $this->errors[] = "No autoRunTemplatePath found";
            return false;
        }
        
        if (!file_exists($this->autoRunTemplatePath)) {
            $this->errors[] = "No autoRunTemplatePath: ".
                $this->autoRunTemplatePath." does not exist";
            return false;
        }
        
        return file_get_contents($this->autoRunTemplatePath);
    }//end getAutoRunTemplate    
    
    /**
     * Uses properties to enrich the autoRun Template
     *
     * @param array $properties Contains the daemon properties
     * 
     * @return mixed string or boolean on failure
     */
    public function getAutoRunScript($properties)
    {
        $this->errors[] = "Not implemented for OS";
        return false;
    }//end getAutoRunScript()
    
    /**
     * Writes an: 'init.d' script on the filesystem
     * combining
     * 
     * @param array   $properties Contains the daemon properties
     * @param boolean $overwrite  Wether to overwrite when the file exists 
     * 
     * @return mixed string or boolean on failure
     * @see getAutoRunScript()
     * @see getAutoRunPath()
     */
    public function writeAutoRun($properties, $overwrite = false)
    {
        // Check properties
        if ($this->_testAutoRunProperties($properties) === false) {
            // Explaining errors should have been generated by 
            // previous function already
            return false;
        }
        
        // Get script body
        if (($body = $this->getAutoRunScript($properties)) === false) {
            // Explaining errors should have been generated by 
            // previous function already
            return false;
        }
        
        // Get script path
        if (($path = $this->getAutoRunPath($properties["appName"])) === false) {
            // Explaining errors should have been generated by 
            // previous function already            
            return false;
        }
        
        // Overwrite?
        if (file_exists($path) && !$overwrite) {
            return true;
        }
        
        // Write
        if (!file_put_contents($path, $body)) {
            $this->errors[] =  "startup file: '".
                $path."' cannot be ".
                "written to. Check the permissions";
            return false;
        }

        // Chmod
        if (!chmod($path, 0777)) {
            $this->errors[] =  "startup file: '".
                $path."' cannot be ".
                "chmodded. Check the permissions";
            return false;
        } 
        
        
        return $path;
    }//end writeAutoRun() 
            
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    /**
     * Sets daemon specific properties
     *  
     * @param array $properties Contains the daemon properties
     * 
     * @return array
     */       
    private function _testAutoRunProperties($properties = false) 
    {
        $required_props = array("appName", "appExecutable", 
            "appDescription", "appDir", "authorName", "authorEmail");
        
        // Valid array?
        if (!is_array($properties) || !count($properties)) {
            $this->errors[] = "No properties to ".
                "forge init.d script";
            return false; 
        }
                
        // Check if all required properties are available
        $success = true;
        foreach ($required_props as $required_prop) {
            if (!isset($properties[$required_prop])) {
                $this->errors[] = "Cannot forge an ".
                    "init.d script without a valid ".
                    "daemon property: ".$required_prop;
                $success        = false;
                continue;
            }            
        }
        
        // Path to daemon
        $daemon_filepath = $properties["appDir"]."/".$properties["appExecutable"];
        
        // Path to daemon exists?
        if (!file_exists($daemon_filepath)) {
            $this->errors[] = "unable to forge startup script for non existing ".
                "daemon_filepath: ".$daemon_filepath.", try setting a valid ".
                "appDir or appExecutable";
            $success        = false;
        }
        
        // Path to daemon is executable? 
        if (!is_executable($daemon_filepath)) {
            $this->errors[] = "unable to forge startup script. ".
                "daemon_filepath: ".$daemon_filepath.", needs to be executable ".
                "first";
            $success        = false;
        }
        
        return $success;
        
    } //end _testAutoRunProperties    
    
    
    
    /**
     * Determines how specific an operating system is.
     * e.g. Ubuntu is more specific than Debian is more 
     * specific than Linux is more specfic than Common.
     * Determined based on class hierarchy.
     *
     * @param array $classes Array with keys with classnames
     * 
     * @return string
     */
    private function _mostSpecific($classes) 
    {
        $weights = array_map(array("System_Daemon_OS", "_getAncestorCount"), 
            $classes);
        arsort($weights);        
        return reset(array_keys($weights));
    }//end _mostSpecific
    
    /**
     * Extracts last part of a classname. e.g. System_Daemon_OS_Ubuntu -> Ubuntu
     *
     * @param string $class Full classname
     * 
     * @return string
     */
    private function _getShortHand($class) 
    {
        if (!is_string($class) || ! $class ) {
            return false;
        }
        $parts = explode("_", $class);
        return end($parts);
    } //end _getShortHand
    
    /**
     * Get the total parent count of a class
     *
     * @param string $class Full classname or instance
     * 
     * @return integer
     */
    private function _getAncestorCount($class) 
    {
        return count(System_Daemon_OS::_getAncestors($class));        
    }//end _getAncestorCount
    
    /**
     * Get an array of parent classes
     *
     * @param string $class Full classname or instance
     * 
     * @return array
     */
    private function _getAncestors($class)
    {
        $classes = array();
        while ($class = get_parent_class($class)) { 
            $classes[] = $class; 
        }
        return $classes;
    }//end _getAncestors
    
}//end class
?>