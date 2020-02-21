<?php

require_once TOOLKIT . '/class.datasource.php';
require_once FACE . '/interface.datasource.php';

class RemoteDatasource extends DataSource implements iDatasource
{

    private static $transformer = array();
    private static $url_result = null;
    private static $cacheable = null;

    public static function getName()
    {
        return __('Remote Datasource');
    }

    public static function getClass()
    {
        return __CLASS__;
    }

    public function getSource()
    {
        return self::getClass();
    }

    public static function getTemplate()
    {
        return EXTENSIONS . '/remote_datasource/templates/blueprints.datasource.tpl';
    }

    public function settings()
    {
        $settings = array();

        $settings[self::getClass()]['namespaces'] = $this->dsParamNAMESPACES;
        $settings[self::getClass()]['paramoutput'] = $this->dsParamOUTPUTPARAM;
        $settings[self::getClass()]['url'] = $this->dsParamURL;
        $settings[self::getClass()]['xpath'] = isset($this->dsParamXPATH) ? $this->dsParamXPATH : '/';
        $settings[self::getClass()]['cache'] = isset($this->dsParamCACHE) ? $this->dsParamCACHE : 30;
        $settings[self::getClass()]['format'] = isset($this->dsParamFORMAT) ? $this->dsParamFORMAT : 'xml';
        $settings[self::getClass()]['timeout'] = isset($this->dsParamTIMEOUT) ? $this->dsParamTIMEOUT : 6;

        return $settings;
    }

    /**
     * This methods allows custom remote data source to set other
     * properties on the HTTP gateway, like Authentication or other
     * parameters. This method is call just before the `exec` method.
     *
     * @param Gateway $gateway
     *  the Gateway object that will be use for the current HTTP request
     *  passed by reference
     */
    public static function prepareGateway(&$gateway)
    {

    }

    /**
     * This methods allows custom remote data source to read the returned
     * data before it becomes only available in the XML.
     *
     * @since Remote Datasource 2.0
     * @param string $data
     *  the parsed xml string data returned by the Gateway by reference
     * @param array $param_pool
     *  the pool of parameters
     */
    public function exposeData(&$data, array &$param_pool = null)
    {

    }

    /**
     * This method is called when their is an http error
     * or when content type is unsupported
     *
     * @since Remote Datasource 2.0
     * @param array $info
     *  info of the http request
     */
    public function httpError(&$info)
    {

    }

/*-------------------------------------------------------------------------
    Utilities
-------------------------------------------------------------------------*/

    /**
     * Returns the source value for display in the Datasources index
     *
     * @param string $file
     *  The path to the Datasource file
     * @return string
     */
    public static function getSourceColumn($handle)
    {
        $datasource = DatasourceManager::create($handle, array(), false);

        if (isset($datasource->dsParamURL)) {
            return Widget::Anchor(str_replace('http://www.', '', $datasource->dsParamURL), $datasource->dsParamURL);
        } else {
            return 'Remote Datasource';
        }
    }

    /**
     * Given a `$url` and `$timeout`, this function will use the `Gateway`
     * class to determine that it is a valid URL and returns successfully
     * before the `$timeout`. If it does not, an error message will be
     * returned, otherwise true.
     *
     * @todo This function is a bit messy, could be revisited.
     * @param string $url
     * @param integer $timeout
     *  If not provided, this will default to 6 seconds
     * @param boolean $fetch_URL
     *  Defaults to false, but when set to true, this function will use the
     *  `Gateway` class to attempt to validate the URL's existence and it
     *  returns before the `$timeout`
     * @param string $format
     *  The format that the URL will return, either JSON or XML. Defaults
     *  to 'xml' which will send the appropriate ACCEPTs header.
     * @return string|array
     *  Returns an array with the 'data' if it is a valid URL, otherwise a string
     *  containing an error message.
     */
    public static function isValidURL($url, $timeout = 6, $format = 'xml', $fetch_URL = false)
    {
        if (trim($url) == '') {
            return __('This is a required field');
        } elseif ($fetch_URL === true) {
            list($data, $info) = self::fetch($url, $format, $timeout);

            // 28 is CURLE_OPERATION_TIMEOUTED
            $isTimeout = (isset($info['curl_errno']) && $info['curl_errno'] === 28) ||
                         (isset($info['curl_error']) && $info['curl_error'] === 28);
            if ($isTimeout) {
                return __('Request timed out. %d second limit reached.', array($timeout));
            } elseif ($data === false || $info['http_code'] != 200) {
                return __('Failed to load URL, status code %d was returned.', array($info['http_code']));
            }
        }

        return array('data' => $data);
    }

    /**
     * Builds the namespaces out to be saved in the Datasource file
     *
     * @param array $namespaces
     *  An associative array of where the key is the namespace prefix
     *  and the value is the namespace URL.
     * @param string $template
     *  The template file, as defined by `getTemplate()`
     * @return string
     *  The template injected with the Namespaces (if any).
     */
    public static function injectNamespaces(array $namespaces, &$template)
    {
        if (empty($namespaces)) {
            return;
        }

        $placeholder = '<!-- NAMESPACES -->';
        $string = 'public $dsParamNAMESPACES = array(' . PHP_EOL;

        foreach ($namespaces as $key => $val) {
            if (trim($val) == '') {
                continue;
            }

            $string .= str_repeat(' ', 8) . "'$key' => '" . addslashes($val) . "'," . PHP_EOL;
        }

        $string .= str_repeat(' ', 4) . ");" . PHP_EOL . str_repeat(' ', 4) . $placeholder;
        $template = str_replace($placeholder, trim($string), $template);
    }

    /**
     * Builds the output parameters out to be saved in the Datasource file
     *
     * @param array $paramoutput
     *  An associative array of where the key is the parameter name
     *  and the value is the xpath
     * @param string $template
     *  The template file, as defined by `getTemplate()`
     * @return string
     *  The template injected with the Namespaces (if any).
     */
    public static function injectOutputParameters(array $paramoutput, &$template)
    {
        if (empty($paramoutput)) {
            return;
        }

        $placeholder = '<!-- PARAMOUTPUT -->';
        $string = 'public $dsParamOUTPUTPARAM = array(' . PHP_EOL;

        foreach ($paramoutput as $key => $val) {
            if (trim($val) == '') {
                continue;
            }

            $string .= str_repeat(' ', 8) . "'$key' => '" . addslashes($val) . "'," . PHP_EOL;
        }

        $string .= str_repeat(' ', 4) . ");" . PHP_EOL . str_repeat(' ', 4) . $placeholder;
        $template = str_replace($placeholder, trim($string), $template);
    }

    /**
     * Given either the Datasource object or an array of settings for a
     * Remote Datasource, this function will return it's cache ID, which
     * is stored in tbl_cache.
     *
     * @since 1.1
     * @param array|object $settings
     */
    public static function buildCacheID($settings)
    {
        $cache_id = null;

        if (is_object($settings)) {
            $cache_id = md5(
                $settings->dsParamURL .
                serialize($settings->dsParamNAMESPACES) .
                serialize($settings->dsParamPARAMOUTPUT) .
                $settings->dsParamXPATH .
                $settings->dsParamFORMAT
            );
        } elseif (is_array($settings)) {
            // Namespaces come through empty, or as an array, so normalise
            // to ensure the cache key stays the same.
            if (is_array($settings['namespaces']) && empty($settings['namespaces'])) {
                $settings['namespaces'] = null;
            }

            // Same deal with output parameters
            if (is_array($settings['paramoutput']) && empty($settings['paramoutput'])) {
                $settings['paramoutput'] = null;
            }

            $cache_id = md5(
                $settings['url'] .
                serialize($settings['namespaces']) .
                serialize($settings['paramoutput']) .
                stripslashes($settings['xpath']) .
                $settings['format']
            );
        }

        return $cache_id;
    }

    /**
     * Helper function to build Cache information block
     *
     * @param XMLElement $wrapper
     * @param Cacheable $cache
     * @param string $cache_id
     */
    public static function buildCacheInformation(XMLElement $wrapper, Cacheable $cache, $cache_id)
    {
        $cachedData = $cache->read($cache_id);

        if (is_array($cachedData) && !empty($cachedData) && (time() < $cachedData['expiry'])) {
            $a = Widget::Anchor(__('Clear now'), SYMPHONY_URL . getCurrentPage() . 'clear_cache/');
            $wrapper->appendChild(
                new XMLElement('p', __('Cache expires in %d minutes. %s', array(
                    ($cachedData['expiry'] - time()) / 60,
                    $a->generate(false)
                )), array('class' => 'help'))
            );
        } else {
            $wrapper->appendChild(
                new XMLElement('p', __('Cache has expired or does not exist.'), array('class' => 'help'))
            );
        }
    }

    /**
     * Returns an instance of the cache for the Remote Datasource
     *
     * @since Remote Datasource 2.4.0
     * @return Cacheable
     */
    public static function getCache() {
      return Symphony::ExtensionManager()->getCacheProvider('remotedatasource');
    }

/*-------------------------------------------------------------------------
    Editor
-------------------------------------------------------------------------*/

    public static function buildEditor(XMLElement $wrapper, array &$errors = array(), array $settings = null, $handle = null)
    {
        if (!is_null($handle) && isset($settings[self::getClass()])) {
            $cache = self::getCache();
            $cache_id = self::buildCacheID($settings[self::getClass()]);
        }

        // If `clear_cache` is set, clear it..
        if (isset($cache_id) && in_array('clear_cache', Administration::instance()->Page->getContext())) {
            $cache->delete($cache_id, $handle);
            Administration::instance()->Page->pageAlert(
                __('Data source cache cleared at %s.', array(Widget::Time()->generate()))
                . '<a href="' . SYMPHONY_URL . '/blueprints/datasources/" accesskey="a">'
                . __('View all Data sources')
                . '</a>',
                Alert::SUCCESS
            );
        }

        $fieldset = new XMLElement('fieldset');
        $fieldset->setAttribute('class', 'settings contextual ' . __CLASS__);
        $fieldset->setAttribute('data-context', Lang::createHandle(self::getName()));
        $fieldset->appendChild(new XMLElement('legend', self::getName()));
        $p = new XMLElement(
            'p',
            __('Use %s syntax to specify dynamic portions of the URL.', array(
                '<code>{' . __('$param') . '}</code>'
            ))
        );
        $p->setAttribute('class', 'help');
        $fieldset->appendChild($p);

        // URL
        $label = Widget::Label(__('URL'));
        $url = isset($settings[self::getClass()]['url'])
            ? General::sanitize($settings[self::getClass()]['url'])
            : null;

        $label->appendChild(Widget::Input('fields[' . self::getClass() . '][url]', $url, 'text', array('placeholder' => 'http://')));

        if (isset($errors[self::getClass()]['url'])) {
            $fieldset->appendChild(Widget::Error($label, $errors[self::getClass()]['url']));
        } else {
            $fieldset->appendChild($label);
        }

        // Namespaces
        static::addNamespaces($fieldset, $errors, $settings, $handle);

        // Included Elements
        $label = Widget::Label(__('Included Elements'));

        $help = new XMLElement('i', __('xPath expression'));
        $label->appendChild($help);

        $xpath = isset($settings[self::getClass()]['xpath'])
            ? stripslashes($settings[self::getClass()]['xpath'])
            : null;

        $label->appendChild(
            Widget::Input('fields[' . self::getClass() . '][xpath]', $xpath, 'text', array('placeholder' => '/'))
        );
        if (isset($errors[self::getClass()]['xpath'])) {
            $fieldset->appendChild(Widget::Error($label, $errors[self::getClass()]['xpath']));
        } else {
            $fieldset->appendChild($label);
        }

        $group = new XMLElement('div', null, array('class' => 'three columns'));
        $fieldset->appendChild($group);

        // Caching
        $label = Widget::Label(__('Cache expiration'));
        $label->setAttribute('class', 'column');

        $help = new XMLElement('i', __('in minutes'));
        $label->appendChild($help);

        $cache_time = isset($settings[self::getClass()]['cache'])
            ? max(0, intval($settings[self::getClass()]['cache']))
            : 5;

        $input = Widget::Input('fields[' . self::getClass() . '][cache]', (string) $cache_time);
        $label->appendChild($input);
        if (isset($errors[self::getClass()]['cache'])) {
            $group->appendChild(Widget::Error($label, $errors[self::getClass()]['cache']));
        } else {
            $group->appendChild($label);
        }

        // Timeout
        $label = Widget::Label(__('Timeout'));
        $label->setAttribute('class', 'column');

        $help = new XMLElement('i', __('in seconds'));
        $label->appendChild($help);

        $timeout_time = isset($settings[self::getClass()]['timeout'])
            ? max(1, intval($settings[self::getClass()]['timeout']))
            : 6;

        $label->appendChild(
            Widget::Input('fields[' . self::getClass() . '][timeout]', (string) $timeout_time, 'text')
        );
        if (isset($errors[self::getClass()]['timeout'])) {
            $group->appendChild(Widget::Error($label, $errors[self::getClass()]['timeout']));
        } else {
            $group->appendChild($label);
        }

        // Format
        $label = Widget::Label(__('Format'));
        $label->setAttribute('class', 'column');

        $format = isset($settings[self::getClass()]['format'])
            ? $settings[self::getClass()]['format']
            : null;

        $label->appendChild(
            Widget::Select('fields[' . self::getClass() . '][format]', array(
                array('xml', $settings[self::getClass()]['format'] == 'xml', 'XML'),
                array('json', $settings[self::getClass()]['format'] == 'json', 'JSON'),
                array('csv', $settings[self::getClass()]['format'] == 'csv', 'CSV'),
                array('txt', $settings[self::getClass()]['format'] == 'txt', 'TEXT')
            ), array(
                'class' => 'picker'
            ))
        );
        if (isset($errors[self::getClass()]['format'])) {
            $group->appendChild(Widget::Error($label, $errors[self::getClass()]['format']));
        } else {
            $group->appendChild($label);
        }

        // Check for existing Cache objects
        if (isset($cache_id)) {
            static::buildCacheInformation($fieldset, $cache, $cache_id);
        }

        $wrapper->appendChild($fieldset);

        // Parameters
        $fieldset = new XMLElement('fieldset');
        $fieldset->setAttribute('class', 'settings contextual ' . __CLASS__);
        $fieldset->setAttribute('data-context', Lang::createHandle(self::getName()));
        $fieldset->appendChild(new XMLElement('legend', 'Parameters'));
        $p = new XMLElement(
            'p',
            __('Appended to datasource handle, eg. %s', array(
                '<code>$ds-handle.parameter-name</code>'
            ))
        );
        $p->setAttribute('class', 'help');
        $fieldset->appendChild($p);

        // Output Parameters
        static::addOutputParameters($fieldset, $errors, $settings, $handle);

        $wrapper->appendChild($fieldset);

    }

    /**
     * Creates the markup for a Namespaces duplicator in the Remote Datasource editor.
     *
     * @param XMLElement $wrapper
     * @param array $errors
     * @param array $settings
     * @param string $handle
     */
    protected static function addNamespaces(XMLElement $wrapper, array &$errors = array(), array $settings = null, $handle = null) {
        $div = new XMLElement('div', false, array(
            'id' => 'xml',
            'class' => 'pickable'
        ));
        $p = new XMLElement('p', __('Namespace Declarations'));
        $p->appendChild(new XMLElement('i', __('optional')));
        $p->setAttribute('class', 'label');
        $div->appendChild($p);

        $frame = new XMLElement('div', null, array('class' => 'frame filters-duplicator'));
        $frame->setAttribute('data-interactive', 'data-interactive');

        $ol = new XMLElement('ol');
        $ol->setAttribute('data-add', __('Add namespace'));
        $ol->setAttribute('data-remove', __('Remove namespace'));

        if (isset($settings[self::getClass()], $settings[self::getClass()]['namespaces']) && is_array($settings[self::getClass()]['namespaces']) && !empty($settings[self::getClass()]['namespaces'])) {
            $ii = 0;
            foreach ($settings[self::getClass()]['namespaces'] as $name => $uri) {
                // Namespaces get saved to the file as $name => $uri, however in
                // the $_POST they are represented as $index => array. This loop
                // patches the difference.
                if (is_array($uri)) {
                    $name = $uri['name'];
                    $uri = $uri['uri'];
                }

                $name = General::sanitize($name);
                $uri = General::sanitize($uri);

                $li = static::addNamespaceDuplicatorItem(false, $ii, $name, $uri);
                $ol->appendChild($li);
                $ii++;
            }
        }

        // Add the template
        $li = static::addNamespaceDuplicatorItem();
        $ol->appendChild($li);

        $frame->appendChild($ol);
        $div->appendChild($frame);

        $wrapper->appendChild($div);
    }

    /**
     * Creates the duplicator item, both the template or the actual instance.
     *
     * @param boolean $template
     *  If this is the template, set to `true` (which it is by default)
     * @param string|integer $key
     *  Used with instances, helps maintain the position of the duplicator item
     * @param string $name
     *  The namespace name (by default null)
     * @param string $uri
     *  The matching URI for this namespace
     * @return XMLElement
     */
    protected static function addNamespaceDuplicatorItem($template = true, $key = '-1', $name = null, $uri = null) {

        $li = new XMLElement('li');

        // Is this a template or the real deal?
        if ($template) {
            $li->setAttribute('class', 'template');
            $li->setAttribute('data-type', 'namespace');
        }
        else {
            $li->setAttribute('class', 'instance');
        }

        // Header
        $header = new XMLElement('header');
        $header->appendChild(
            new XMLElement('h4', __('Namespace'))
        );
        $li->appendChild($header);

        // Actual duplicator content (visible only when expanded)
        $group = new XMLElement('div');
        $group->setAttribute('class', 'two columns');

        $label = Widget::Label(__('Name'));
        $label->setAttribute('class', 'column');
        $label->appendChild(Widget::Input('fields[' . self::getClass() . '][namespaces][' . $key . '][name]', $name));
        $group->appendChild($label);

        $label = Widget::Label(__('URI'));
        $label->setAttribute('class', 'column');
        $label->appendChild(Widget::Input('fields[' . self::getClass() . '][namespaces][' . $key . '][uri]', $uri));
        $group->appendChild($label);

        $li->appendChild($group);

        return $li;
    }

    /**
     * Creates the markup for the Output Parameters duplicator in the Remote Datasource editor.
     *
     * @param XMLElement $wrapper
     * @param array $errors
     * @param array $settings
     * @param string $handle
     */
    protected static function addOutputParameters(XMLElement $wrapper, array &$errors = array(), array $settings = null, $handle = null) {
        $div = new XMLElement('div', false, array(
            'id' => 'xml',
            'class' => 'pickable'
        ));

        $frame = new XMLElement('div', null, array('class' => 'frame filters-duplicator'));
        $frame->setAttribute('data-interactive', 'data-interactive');

        $ol = new XMLElement('ol');
        $ol->setAttribute('data-add', __('Add parameter'));
        $ol->setAttribute('data-remove', __('Remove parameter'));

        if (isset($settings[self::getClass()], $settings[self::getClass()]['paramoutput']) && is_array($settings[self::getClass()]['paramoutput']) && !empty($settings[self::getClass()]['paramoutput'])) {
            $ii = 0;
            foreach ($settings[self::getClass()]['paramoutput'] as $param => $xpath) {
                if (is_array($param)) {
                    $param = $xpath['param'];
                    $xpath = $xpath['xpath'];
                }

                $param = General::sanitize($param);
                $xpath = General::sanitize($xpath);

                $li = static::addOutputParameterDuplicatorItem(false, $ii, $param, $xpath);
                $ol->appendChild($li);
                $ii++;
            }
        }

        // Add the template
        $li = static::addOutputParameterDuplicatorItem();
        $ol->appendChild($li);

        $frame->appendChild($ol);
        $div->appendChild($frame);

        $wrapper->appendChild($div);
    }

    /**
     * Creates the duplicator item, both the template or the actual instance.
     *
     * @param boolean $template
     *  If this is the template, set to `true` (which it is by default)
     * @param string|integer $key
     *  Used with instances, helps maintain the position of the duplicator item
     * @param string $param
     *  The namespace name (by default null)
     * @param string $xpath
     *  The matching URI for this namespace
     * @return XMLElement
     */
    protected static function addOutputParameterDuplicatorItem($template = true, $key = '-1', $param = null, $xpath = null) {

        $li = new XMLElement('li');

        // Is this a template or the real deal?
        if ($template) {
            $li->setAttribute('class', 'template');
            $li->setAttribute('data-type', 'paramoutput');
        }
        else {
            $li->setAttribute('class', 'instance');
        }

        // Header
        $header = new XMLElement('header');
        $header->appendChild(
            new XMLElement('h4', __('Parameter'))
        );
        $li->appendChild($header);

        // Actual duplicator content (visible only when expanded)
        $group = new XMLElement('div');
        $group->setAttribute('class', 'two columns');

        $label = Widget::Label(__('Parameter name'));
        $label->setAttribute('class', 'column');
        $label->appendChild(Widget::Input('fields[' . self::getClass() . '][paramoutput][' . $key . '][name]', $param));
        $group->appendChild($label);

        $label = Widget::Label(__('XPath'));
        $label->setAttribute('class', 'column');
        $label->appendChild(Widget::Input('fields[' . self::getClass() . '][paramoutput][' . $key . '][xpath]', $xpath));
        $group->appendChild($label);

        $li->appendChild($group);

        return $li;
    }

    public static function validate(array &$settings, array &$errors)
    {
        // Use the TIMEOUT that was specified by the user for a real world indication
        $timeout = isset($settings[self::getClass()]['timeout'])
            ? (int) $settings[self::getClass()]['timeout']
            : 6;

        // Check cache value is numeric
        if (!is_numeric($settings[self::getClass()]['cache'])) {
            $errors[self::getClass()]['cache'] = __('Must be a valid number');
        }

        // Make sure that XPath has been filled out
        if (trim($settings[self::getClass()]['xpath']) == '') {
            $errors[self::getClass()]['xpath'] = __('This is a required field');
        }

        // Ensure we have a URL
        if (trim($settings[self::getClass()]['url']) == '') {
            $errors[self::getClass()]['url'] = __('This is a required field');
        } elseif (!preg_match('@{([^}]+)}@i', $settings[self::getClass()]['url'])) {

            // If there is a parameter in the URL, we can't validate the existence of the URL
            // as we don't have the environment details of where this datasource is going
            // to be executed.
            $valid_url = self::isValidURL($settings[self::getClass()]['url'], $timeout, $settings[self::getClass()]['format'], true);

            // If url was valid, `isValidURL` will return an array of data
            // Otherwise it'll return a string, which is an error
            if (is_array($valid_url)) {
                self::$url_result = $valid_url['data'];
            } else {
                $errors[self::getClass()]['url'] = $valid_url;
            }
        }

        return empty($errors[self::getClass()]);
    }

    public static function prepare(array $settings, array $params, $template)
    {
        $settings = $settings[self::getClass()];

        // Timeout
        $timeout = isset($settings['timeout'])
            ? (int) $settings['timeout']
            : 6;

        // Automatically detect namespaces
        if (!is_null(self::$url_result)) {
            preg_match_all('/xmlns:([a-z][a-z-0-9\-]*)="([^\"]+)"/i', self::$url_result, $matches);

            if (!is_array($settings['namespaces'])) {
                $settings['namespaces'] = array();
            }

            if (isset($matches[2][0])) {
                $detected_namespaces = array();

                foreach ($settings['namespaces'] as $index => $namespace) {
                    $detected_namespaces[] = $namespace['name'];
                    $detected_namespaces[] = $namespace['uri'];
                }

                foreach ($matches[2] as $index => $uri) {
                    $name = $matches[1][$index];

                    if (in_array($name, $detected_namespaces) || in_array($uri, $detected_namespaces)) {
                        continue;
                    }

                    $detected_namespaces[] = $name;
                    $detected_namespaces[] = $uri;

                    $settings['namespaces'][] = array(
                        'name' => $name,
                        'uri' => $uri
                    );
                }
            }
        }

        // Namespace
        $namespaces = array();
        if (is_array($settings['namespaces'])) {
            foreach ($settings['namespaces'] as $index => $data) {
                $namespaces[$data['name']] = $data['uri'];
            }
        }
        self::injectNamespaces($namespaces, $template);

        // Output params
        $outputparams = array();
        if (is_array($settings['paramoutput'])) {
            foreach ($settings['paramoutput'] as $index => $data) {
                $outputparams[$data['name']] = $data['xpath'];
            }
        }
        self::injectOutputParameters($outputparams, $template);

        // If there is valid data, save it to cache so that it is available
        // immediately to the frontend
        if (!is_null(self::$url_result)) {
            $settings = array_merge($settings, array(
                'namespaces' => $namespaces,
                'paramoutput' => $outputparams
            ));
            $data = self::transformResult(self::$url_result, $settings['format']);
            $cache_id = self::buildCacheID($settings);
            self::getCache()->write($cache_id, $data, $settings['cache'], $params['rootelement']);
        }

        return sprintf(
            $template,
            $params['rootelement'], // rootelement
            $settings['url'], // url
            $settings['format'], // format
            addslashes($settings['xpath']), // xpath
            $settings['cache'], // cache
            $timeout// timeout
        );
    }

/*-------------------------------------------------------------------------
    Execution
-------------------------------------------------------------------------*/

    public function execute(array &$param_pool = null)
    {
        $result = new XMLElement($this->dsParamROOTELEMENT);

        // When DS is called out of the Frontend context, this will enable
        // {$root} and {$workspace} parameters to be evaluated
        if (empty($this->_env)) {
            $this->_env['env']['pool'] = array(
                'root' => URL,
                'workspace' => URL . '/workspace'
            );
        }

        try {
            require_once(TOOLKIT . '/class.gateway.php');
            require_once(TOOLKIT . '/class.xsltprocess.php');
            require_once(CORE . '/class.cacheable.php');

            $this->dsParamURL = $this->parseParamURL($this->dsParamURL);

            if (isset($this->dsParamXPATH)) {
                $this->dsParamXPATH = $this->__processParametersInString(stripslashes($this->dsParamXPATH), $this->_env);
            }

            // Builds a Default Stylesheet to transform the resulting XML with
            $stylesheet = new XMLElement('xsl:stylesheet');
            $stylesheet->setAttributeArray(array('version' => '1.0', 'xmlns:xsl' => 'http://www.w3.org/1999/XSL/Transform'));

            $output = new XMLElement('xsl:output');
            $output->setAttributeArray(array('method' => 'xml', 'version' => '1.0', 'encoding' => 'utf-8', 'indent' => 'yes', 'omit-xml-declaration' => 'yes'));
            $stylesheet->appendChild($output);

            $template = new XMLElement('xsl:template');
            $template->setAttribute('match', '/');

            $instruction = new XMLElement('xsl:copy-of');

            // Namespaces
            if (isset($this->dsParamNAMESPACES) && is_array($this->dsParamNAMESPACES)) {
                foreach ($this->dsParamNAMESPACES as $name => $uri) {
                    $instruction->setAttribute('xmlns' . ($name ? ":{$name}" : null), $uri);
                }
            }

            // XPath
            $instruction->setAttribute('select', $this->dsParamXPATH);

            $template->appendChild($instruction);
            $stylesheet->appendChild($template);
            $stylesheet->setIncludeHeader(true);

            $xsl = $stylesheet->generate(true);

            // Check for an existing Cache for this Datasource
            $cache_id = self::buildCacheID($this);
            $cachedData = self::getCache()->read($cache_id, $this->dsParamROOTELEMENT);
            $writeToCache = null;
            $isCacheValid = true;
            $creation = DateTimeObj::get('c');

            // Execute if the cache doesn't exist, or if it is old.
            if (
                (!is_array($cachedData) || empty($cachedData)) // There's no cache.
                || (time() - $cachedData['creation']) > ($this->dsParamCACHE * 60) // The cache is old.
            ) {
                if (Mutex::acquire($cache_id, $this->dsParamTIMEOUT, TMP)) {
                    list($data, $info) = self::fetch($this->dsParamURL, $this->dsParamFORMAT, $this->dsParamTIMEOUT);
                    Mutex::release($cache_id, TMP);
                    $writeToCache = true;

                    // Handle any response that is not a 200, or the content type does not include XML, JSON, plain or text
                    if ((int) $info['http_code'] != 200 || !preg_match('/(xml|json|csv|plain|text)/i', $info['content_type'])) {
                        $writeToCache = false;

                        $result->setAttribute('valid', 'false');
                        $this->httpError($info);

                        // 28 is CURLE_OPERATION_TIMEOUTED
                        if ($info['curl_error'] == 28) {
                            $result->appendChild(
                                new XMLElement(
                                    'error',
                                    sprintf('Request timed out. %d second limit reached.', $this->dsParamTIMEOUT)
                                )
                            );
                        } else {
                            $result->appendChild(
                                new XMLElement(
                                    'error',
                                    sprintf('Status code %d was returned. Content-type: %s', $info['http_code'], $info['content_type'])
                                )
                            );
                        }

                        return $result;

                    // Handle where there is `$data`
                    } else if (strlen($data) > 0) {
                        try {
                            $data = self::transformResult($data, $this->dsParamFORMAT);
                        }
                        // If the `$data` is invalid, return a result explaining why
                        catch (TransformException $ex) {
                            $writeToCache = false;
                            $error = new XMLElement('errors');
                            $error->setAttribute('valid', 'false');

                            $error->appendChild(new XMLElement('error', __('Data returned is invalid.')));

                            foreach ($ex->getErrors() as $e) {
                                if (strlen(trim($e['message'])) == 0) {
                                    continue;
                                }

                                $error->appendChild(new XMLElement('item', General::sanitize($e['message'])));
                            }

                            $result->appendChild($error);

                            return $result;
                        }

                    // If `$data` is empty, set the `force_empty_result` to true.
                    } elseif (strlen($data) == 0) {
                        $this->_force_empty_result = true;
                    }

                // Failed to acquire a lock
                } else {
                    $result->appendChild(
                        new XMLElement('error', __('The %s class failed to acquire a lock.', array('<code>Mutex</code>')))
                    );
                }

            // The cache is good, use it!
            } else {
                $data = trim($cachedData['data']);
                $creation = DateTimeObj::get('c', $cachedData['creation']);
            }

            // Visit the data
            $this->exposeData($data, $param_pool);

            // If `$writeToCache` is set to false, invalidate the old cache if it existed.
            if (is_array($cachedData) && !empty($cachedData) && $writeToCache === false) {
                $data = trim($cachedData['data']);
                $isCacheValid = false;
                $creation = DateTimeObj::get('c', $cachedData['creation']);

                if (empty($data)) {
                    $this->_force_empty_result = true;
                }
            }

            // If `force_empty_result` is false and `$result` is an instance of
            // XMLElement, build the `$result`.
            if (!$this->_force_empty_result && is_object($result)) {
                $proc = new XsltProcess;
                $ret = $proc->process($data, $xsl);

                if ($proc->isErrors()) {
                    $result->setAttribute('valid', 'false');
                    $error = new XMLElement('error', __('Transformed XML is invalid.'));
                    $result->appendChild($error);
                    $errors = new XMLElement('errors');
                    foreach ($proc->getError() as $e) {
                        if (strlen(trim($e['message'])) == 0) {
                            continue;
                        }

                        $errors->appendChild(new XMLElement('item', General::sanitize($e['message'])));
                    }
                    $result->appendChild($errors);
                    $result->appendChild(
                        new XMLElement('raw-data', General::wrapInCDATA($data))
                    );
                } elseif (strlen(trim($ret)) == 0) {
                    $this->_force_empty_result = true;
                } else {
                    if ($this->dsParamCACHE > 0 && $writeToCache) {
                        self::getCache()->write($cache_id, $data, $this->dsParamCACHE, $this->dsParamROOTELEMENT);
                    }

                    if (!empty($this->dsParamOUTPUTPARAM)) {
                        $this->processOutputParameters($data, $param_pool);
                    }

                    $result->setValue(PHP_EOL . str_repeat("\t", 2) . preg_replace('/([\r\n]+)/', "$1\t", $ret));
                    $result->setAttribute('status', ($isCacheValid === true ? 'fresh' : 'stale'));
                    $result->setAttribute('cache-id', $cache_id);
                    $result->setAttribute('cache-namespace', $this->dsParamROOTELEMENT);
                    $result->setAttribute('creation', $creation);
                }
            }
        } catch (FrontendPageNotFoundException $e) {
            // Work around. This ensures the 404 page is displayed and
            // is not picked up by the default catch() statement below
            throw $e;
        } catch (Exception $e) {
            $result->appendChild(new XMLElement('error', $e->getMessage()));
        }

        if ($this->_force_empty_result) {
            $result = $this->emptyXMLSet();
        }

        $result->setAttribute('url', General::sanitize($this->dsParamURL));

        return $result;
    }

    /**
     * Using the data return, apply any XPath expressions to generate
     * parameters for this datasource.
     *
     * @param string $data
     * @param array $param_pool
     */
    public function processOutputParameters($data, &$param_pool)
    {
        $dom = new DOMDocument('1.0', 'utf-8');
        $dom->loadXML($data, LIBXML_NONET | LIBXML_DTDLOAD | LIBXML_DTDATTR | defined('LIBXML_COMPACT') ? LIBXML_COMPACT : 0);
        $xpath = new DOMXPath($dom);
        $key = 'ds-' . $this->dsParamROOTELEMENT;

        // Apply the namespaces to XPath
        if (isset($this->dsParamNAMESPACES) && is_array($this->dsParamNAMESPACES)) {
            foreach ($this->dsParamNAMESPACES as $name => $uri) {
                $xpath->registerNamespace($name, $uri);
            }
        }

        // Query each expression for the value
        foreach ($this->dsParamOUTPUTPARAM as $name => $expression) {
            $matches = array();

            // As this is passed the full data node, prepend the DS Xpath
            // to the expression.
            $expression = $this->dsParamXPATH . $expression;

            foreach($xpath->evaluate($expression) as $match) {
                $matches[] = $match->nodeValue;
            }

            $param_key = $key .  '.' . str_replace(':', '-', $name);
            $param_pool[$param_key] = $matches;
        }
    }

    /**
     * Given a URL, Format and Timeout, this function will initalise
     * Symphony's Gateway class to retrieve the contents of the URL.
     *
     * @param string $url
     * @param string $format
     * @param integer $timeout
     * @return array
     */
    public static function fetch($url, $format, $timeout)
    {
        $ch = new Gateway;
        $ch->init($url);
        $ch->setopt('TIMEOUT', $timeout);

        // Set the approtiate Accept: headers depending on the format of the URL.
        if ($transformer = self::getTransformer($format)) {
            $accepts = $transformer->accepts();
            $ch->setopt('HTTPHEADER', array('Accept: ' . $accepts));
        }

        // use static here to allow late static binding
        // see http://php.net/manual/en/language.oop5.late-static-bindings.php
        static::prepareGateway($ch);

        return array(
            trim($ch->exec()),
            $ch->getInfoLast()
        );
    }

    /**
     * Given the result (a string), and a desired format, this
     * function will transform it to the desired format and return
     * it.
     * @param string $data
     * @param string $format
     * @return string
     */
    public static function transformResult($data, $format)
    {
        if ($transformer = self::getTransformer($format)) {
            $data = $transformer->transform($data);
        } else {
            $data = '';
        }

        return $data;
    }

    /**
     * Given the format, this function will look for the file
     * and create a new transformer.
     *
     * @param string $format
     * @return Transformer
     */
    public static function getTransformer($format)
    {
        $transformer = EXTENSIONS . '/remote_datasource/lib/class.' . strtolower($format) . '.php';

        if (!isset(self::$transformer[$format])) {
            if (file_exists($transformer)) {
                $classname = require_once $transformer;
                self::$transformer[$format] = new $classname;
            }
        }

        return self::$transformer[$format];
    }
}

return 'RemoteDatasource';
