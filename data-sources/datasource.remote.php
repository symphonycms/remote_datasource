<?php

	require_once TOOLKIT . '/class.datasource.php';
	require_once FACE . '/interface.datasource.php';

	Class RemoteDatasource extends DataSource implements iDatasource {

		private static $url_result = null;

		public static function getName() {
			return __('Remote Datasource');
		}

		public static function getClass() {
			return __CLASS__;
		}

		public function getSource() {
			return self::getClass();
		}

		public static function getTemplate(){
			return EXTENSIONS . '/remote_datasource/templates/blueprints.datasource.tpl';
		}

		public function settings() {
			$settings = array();

			$settings[self::getClass()]['namespaces'] = $this->dsParamNAMESPACES;
			$settings[self::getClass()]['url'] = $this->dsParamURL;
			$settings[self::getClass()]['xpath'] = isset($this->dsParamXPATH) ? $this->dsParamXPATH : '/';
			$settings[self::getClass()]['cache'] = isset($this->dsParamCACHE) ? $this->dsParamCACHE : 30;
			$settings[self::getClass()]['format'] = isset($this->dsParamFORMAT) ? $this->dsParamFORMAT : 'xml';
			$settings[self::getClass()]['timeout'] = isset($this->dsParamTIMEOUT) ? $this->dsParamTIMEOUT : 6;

			return $settings;
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
		public function getSourceColumn($handle) {
			$datasource = DatasourceManager::create($handle, array(), false);

			if(isset($datasource->dsParamURL)) {
				return Widget::Anchor(str_replace('http://www.', '', $datasource->dsParamURL), $datasource->dsParamURL);
			}
			else {
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
		public static function isValidURL($url, $timeout = 6, $format = 'xml', $fetch_URL = false) {
			// Check that URL was provided
			if(trim($url) == '') {
				return __('This is a required field');
			}
			// Check to see the URL works.
			else if ($fetch_URL === true) {
				$gateway = new Gateway;
				$gateway->init($url);
				$gateway->setopt('TIMEOUT', $timeout);

				// Set the approtiate Accept: headers depending on the format of the URL.
				if($format == 'xml') {
					$gateway->setopt('HTTPHEADER', array('Accept: text/xml, */*'));
				}
				else if($format == 'json') {
					$gateway->setopt('HTTPHEADER', array('Accept: application/json, */*'));
				}

				$data = $gateway->exec();
				$info = $gateway->getInfoLast();

				// 28 is CURLE_OPERATION_TIMEOUTED
				if($info['curl_error'] == 28) {
					return __('Request timed out. %d second limit reached.', array($timeout));
				}
				else if($data === false || $info['http_code'] != 200) {
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
		public static function injectNamespaces(array $namespaces, &$template) {
			if(empty($namespaces)) return;

			$placeholder = '<!-- NAMESPACES -->';
			$string = 'public $dsParamNAMESPACES = array(' . PHP_EOL;

			foreach($namespaces as $key => $val){
				if(trim($val) == '') continue;
				$string .= "\t\t\t'$key' => '" . addslashes($val) . "'," . PHP_EOL;
			}

			$string .= "\t\t);" . PHP_EOL . "\t\t" . $placeholder;
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
		public static function buildCacheID($settings) {
			$cache_id = null;

			if(is_object($settings)) {
				$cache_id = md5(
					$settings->dsParamURL .
					serialize($settings->dsParamNAMESPACES) .
					$settings->dsParamXPATH .
					$settings->dsParamFORMAT
				);
			}
			else if(is_array($settings)) {
				$cache_id = md5(
					$settings['url'] .
					serialize($settings['namespaces']) .
					$settings['xpath'] .
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
		public static function buildCacheInformation(XMLElement $wrapper, Cacheable $cache, $cache_id) {
			$cachedData = $cache->check($cache_id);

			if(is_array($cachedData) && !empty($cachedData) && (time() < $cachedData['expiry'])) {
				$a = Widget::Anchor(__('Clear now'), SYMPHONY_URL . getCurrentPage() . 'clear_cache/');
				$wrapper->appendChild(
					new XMLElement('p', __('Cache expires in %d minutes. %s', array(
						($cachedData['expiry'] - time()) / 60,
						$a->generate(false)
					)), array('class' => 'help'))
				);
			}
			else {
				$wrapper->appendChild(
					new XMLElement('p', __('Cache has expired or does not exist.'), array('class' => 'help'))
				);
			}
		}

	/*-------------------------------------------------------------------------
		Editor
	-------------------------------------------------------------------------*/

		public static function buildEditor(XMLElement $wrapper, array &$errors = array(), array $settings = null, $handle = null) {
			if(!is_null($handle)) {
				$cache = new Cacheable(Symphony::Database());
				$cache_id = self::buildCacheID($settings[self::getClass()]);
			}

			// If `clear_cache` is set, clear it..
			if(isset($cache_id) && in_array('clear_cache', Administration::instance()->Page->getContext())) {
				$cache->forceExpiry($cache_id);
				Administration::instance()->Page->pageAlert(
					__('Data source cache cleared at %s.', array(DateTimeObj::getTimeAgo()))
					. '<a href="' . SYMPHONY_URL . '/blueprints/datasources/" accesskey="a">'
					. __('View all Data sources')
					. '</a>'
					, Alert::SUCCESS);
			}

			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings contextual ' . __CLASS__);
			$fieldset->appendChild(new XMLElement('legend', self::getName()));

			// URL
			$group = new XMLElement('div');
			$group->setAttribute('class', 'two columns');

			$primary = new XMLElement('div', null, array('class' => 'primary column'));
			$label = Widget::Label(__('URL'));
			$label->appendChild(Widget::Input('fields[' . self::getClass() . '][url]', General::sanitize($settings[self::getClass()]['url']), 'text', array('placeholder' => 'http://')));
			$p = new XMLElement('p',
				__('Use %s syntax to specify dynamic portions of the URL.', array(
					'<code>{' . __('$param') . '}</code>'
				))
			);
			$p->setAttribute('class', 'help');
			$label->appendChild($p);

			if(isset($errors[self::getClass()]['url'])) {
				$primary->appendChild(Widget::Error($label, $errors[self::getClass()]['url']));
			}
			else {
				$primary->appendChild($label);
			}

			$group->appendChild($primary);

			$secondary = new XMLElement('div', null, array('class' => 'secondary column'));
			$label = Widget::Label(__('Format'));
			$label->appendChild(
				Widget::Select('fields[' . self::getClass() . '][format]', array(
					array('xml', $settings[self::getClass()]['format'] == 'xml', 'XML'),
					array('json', $settings[self::getClass()]['format'] == 'json', 'JSON')
				), array(
					'class' => 'picker'
				))
			);
			if(isset($errors[self::getClass()]['format'])) {
				$secondary->appendChild(Widget::Error($label, $errors[self::getClass()]['format']));
			}
			else {
				$secondary->appendChild($label);
			}

			$group->appendChild($secondary);
			$fieldset->appendChild($group);

			// Namespaces
			$div = new XMLElement('div', false, array(
				'id' => 'xml',
				'class' => 'pickable'
			));
			$p = new XMLElement('p', __('Namespace Declarations'));
			$p->appendChild(new XMLElement('i', __('Optional')));
			$p->setAttribute('class', 'label');
			$div->appendChild($p);

			$ol = new XMLElement('ol');
			$ol->setAttribute('class', 'filters-duplicator');
			$ol->setAttribute('data-add', __('Add namespace'));
			$ol->setAttribute('data-remove', __('Remove namespace'));

			if(is_array($settings[self::getClass()]['namespaces']) && !empty($settings[self::getClass()]['namespaces'])){
				$ii = 0;
				foreach($settings[self::getClass()]['namespaces'] as $name => $uri) {
					// Namespaces get saved to the file as $name => $uri, however in
					// the $_POST they are represented as $index => array. This loop
					// patches the difference.
					if(is_array($uri)) {
						$name = $uri['name'];
						$uri = $uri['uri'];
					}

					$li = new XMLElement('li');
					$li->setAttribute('class', 'instance');
					$header = new XMLElement('header');
					$header->appendChild(
						new XMLElement('h4', __('Namespace'))
					);
					$li->appendChild($header);

					$group = new XMLElement('div');
					$group->setAttribute('class', 'two columns');

					$label = Widget::Label(__('Name'));
					$label->setAttribute('class', 'column');
					$label->appendChild(Widget::Input("fields[" . self::getClass() . "][namespaces][$ii][name]", General::sanitize($name)));
					$group->appendChild($label);

					$label = Widget::Label(__('URI'));
					$label->setAttribute('class', 'column');
					$label->appendChild(Widget::Input("fields[" . self::getClass() . "][namespaces][$ii][uri]", General::sanitize($uri)));
					$group->appendChild($label);

					$li->appendChild($group);
					$ol->appendChild($li);
					$ii++;
				}
			}

			$li = new XMLElement('li');
			$li->setAttribute('class', 'template');
			$li->setAttribute('data-type', 'namespace');
			$header = new XMLElement('header');
			$header->appendChild(
				new XMLElement('h4', __('Namespace'))
			);
			$li->appendChild($header);

			$group = new XMLElement('div');
			$group->setAttribute('class', 'two columns');

			$label = Widget::Label(__('Name'));
			$label->setAttribute('class', 'column');
			$label->appendChild(Widget::Input('fields[' . self::getClass() . '][namespaces][-1][name]'));
			$group->appendChild($label);

			$label = Widget::Label(__('URI'));
			$label->setAttribute('class', 'column');
			$label->appendChild(Widget::Input('fields[' . self::getClass() . '][namespaces][-1][uri]'));
			$group->appendChild($label);

			$li->appendChild($group);
			$ol->appendChild($li);

			$div->appendChild($ol);
			$div->appendChild(
				new XMLElement('p', __('Namespaces will automatically be discovered when saving this datasource if it does not include any dynamic portions.'), array('class' => 'help'))
			);

			$fieldset->appendChild($div);

			// Included Elements
			$label = Widget::Label(__('Included Elements'));
			$label->appendChild(Widget::Input('fields[' . self::getClass() . '][xpath]', $settings[self::getClass()]['xpath']));
			if(isset($errors[self::getClass()]['xpath'])) $fieldset->appendChild(Widget::Error($label, $errors[self::getClass()]['xpath']));
			else $fieldset->appendChild($label);

			$p = new XMLElement('p', __('Use an XPath expression to select which elements from the source XML to include.'));
			$p->setAttribute('class', 'help');
			$fieldset->appendChild($p);

			// Caching
			$label = Widget::Label();
			$input = Widget::Input('fields[' . self::getClass() . '][cache]', (string)max(1, intval($settings[self::getClass()]['cache'])), NULL, array('size' => '6', 'type' => 'text'));
			$label->setValue(__('Update cached result every %s minutes', array($input->generate(false))));
			if(isset($errors[self::getClass()]['cache'])) $fieldset->appendChild(Widget::Error($label, $errors[self::getClass()]['cache']));
			else $fieldset->appendChild($label);

			// Check for existing Cache objects
			if(isset($cache_id)) {
				self::buildCacheInformation($fieldset, $cache, $cache_id);
			}

			// Timeout
			$label = Widget::Label();
			$input = Widget::Input('fields[' . self::getClass() . '][timeout]', (string)max(1, intval($settings[self::getClass()]['timeout'])), NULL, array('type' => 'hidden'));
			$label->appendChild($input);
			$fieldset->appendChild($label);

			$wrapper->appendChild($fieldset);
		}

		public static function validate(array &$settings, array &$errors) {
			// Use the TIMEOUT that was specified by the user for a real world indication
			$timeout = isset($settings[self::getClass()]['timeout'])
				? (int)$settings[self::getClass()]['timeout']
				: 6;

			// Check cache value is numeric and greater than 1
			if(!is_numeric($settings[self::getClass()]['cache'])) {
				$errors[self::getClass()]['cache'] = __('Must be a valid number');
			}
			else if($settings[self::getClass()]['cache'] < 1) {
				$errors[self::getClass()]['cache'] = __('Must be greater than zero');
			}

			// Make sure that XPath has been filled out
			if(trim($settings[self::getClass()]['xpath']) == '') {
				$errors[self::getClass()]['xpath'] = __('This is a required field');
			}

			// Ensure we have a URL
			if(trim($settings[self::getClass()]['url']) == '') {
				$errors[self::getClass()]['url'] = __('This is a required field');
			}
			// If there is a parameter in the URL, we can't validate the existence of the URL
			// as we don't have the environment details of where this datasource is going
			// to be executed.
			else if(!preg_match('@{([^}]+)}@i', $settings[self::getClass()]['url'])) {
				$valid_url = self::isValidURL($settings[self::getClass()]['url'], $timeout, $settings[self::getClass()]['format'], true);

				// If url was valid, `isValidURL` will return an array of data
				if(is_array($valid_url)) {
					self::$url_result = $valid_url['data'];
				}
				// Otherwise it'll return a string, which is an error
				else {
					$errors[self::getClass()]['url'] = $valid_url;
				}
			}

			return empty($errors[self::getClass()]);
		}

		public static function prepare(array $settings, array $params, $template) {
			$settings = $settings[self::getClass()];

			// Automatically detect namespaces
			if(!is_null(self::$url_result)) {
				preg_match_all('/xmlns:([a-z][a-z-0-9\-]*)="([^\"]+)"/i', self::$url_result, $matches);

				if(!is_array($settings['namespaces'])) {
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

						if (in_array($name, $detected_namespaces) or in_array($uri, $detected_namespaces)) continue;

						$detected_namespaces[] = $name;
						$detected_namespaces[] = $uri;

						$settings['namespaces'][] = array(
							'name' => $name,
							'uri' => $uri
						);
					}
				}
			}

			$namespaces = array();
			if(is_array($settings['namespaces'])) {
				foreach($settings['namespaces'] as $index => $data) {
					$namespaces[$data['name']] = $data['uri'];
				}
			}
			self::injectNamespaces($namespaces, $template);

			$timeout = isset($settings['timeout'])
				? (int)$settings['timeout']
				: 6;

			// If there is valid data, save it to cache so that it is available
			// immediately to the frontend
			if(!is_null(self::$url_result)) {
				$settings['namespaces'] = $namespaces;
				$cache = new Cacheable(Symphony::Database());
				$cache_id = self::buildCacheID($settings);
				$cache->write($cache_id, self::$url_result, $settings['cache']);
			}

			return sprintf($template,
				$params['rootelement'], // rootelement
				$settings['url'], // url
				$settings['format'], // format
				$settings['xpath'], // xpath
				$settings['cache'], // cache
				$timeout// timeout
			);
		}

	/*-------------------------------------------------------------------------
		Execution
	-------------------------------------------------------------------------*/

		public function grab(array &$param_pool = null) {
			$result = new XMLElement($this->dsParamROOTELEMENT);

			try {
				require_once(TOOLKIT . '/class.gateway.php');
				require_once(TOOLKIT . '/class.xsltprocess.php');
				require_once(CORE . '/class.cacheable.php');

				$this->dsParamURL = $this->parseParamURL($this->dsParamURL);

				if(isset($this->dsParamXPATH)) $this->dsParamXPATH = $this->__processParametersInString($this->dsParamXPATH, $this->_env);

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
				if(isset($this->dsParamNAMESPACES) && is_array($this->dsParamNAMESPACES)){
					foreach($this->dsParamNAMESPACES as $name => $uri) {
						$instruction->setAttribute('xmlns' . ($name ? ":{$name}" : NULL), $uri);
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
				$cache = new Cacheable(Symphony::Database());
				$cachedData = $cache->check($cache_id);
				$writeToCache = false;
				$isCacheValid = true;
				$creation = DateTimeObj::get('c');

				// Execute if the cache doesn't exist, or if it is old.
				if(
					(!is_array($cachedData) || empty($cachedData)) // There's no cache.
					|| (time() - $cachedData['creation']) > ($this->dsParamCACHE * 60) // The cache is old.
				){
					if(Mutex::acquire($cache_id, $this->dsParamTIMEOUT, TMP)) {
						$ch = new Gateway;
						$ch->init($this->dsParamURL);
						$ch->setopt('TIMEOUT', $this->dsParamTIMEOUT);

						// Set the approtiate Accept: headers depending on the format of the URL.
						if($this->dsParamFORMAT == 'xml') {
							$ch->setopt('HTTPHEADER', array('Accept: text/xml, */*'));
						}
						else {
							$ch->setopt('HTTPHEADER', array('Accept: application/json, */*'));
						}

						$data = $ch->exec();
						$info = $ch->getInfoLast();

						Mutex::release($cache_id, TMP);

						$data = trim($data);
						$writeToCache = true;

						// Handle any response that is not a 200, or the content type does not include XML, JSON, plain or text
						if((int)$info['http_code'] != 200 || !preg_match('/(xml|json|plain|text)/i', $info['content_type'])){
							$writeToCache = false;

							$result->setAttribute('valid', 'false');

							// 28 is CURLE_OPERATION_TIMEOUTED
							if($info['curl_error'] == 28) {
								$result->appendChild(
									new XMLElement('error',
										sprintf('Request timed out. %d second limit reached.', $timeout)
									)
								);
							}
							else{
								$result->appendChild(
									new XMLElement('error',
										sprintf('Status code %d was returned. Content-type: %s', $info['http_code'], $info['content_type'])
									)
								);
							}

							return $result;
						}

						// Handle where there is `$data`
						else if(strlen($data) > 0) {
							// If it's JSON, convert it to XML
							if($this->dsParamFORMAT == 'json') {
								try {
									require_once TOOLKIT . '/class.json.php';
									$data = JSON::convertToXML($data);
								}
								catch (Exception $ex) {
									$writeToCache = false;
									$errors = array(
										array('message' => $ex->getMessage())
									);
								}
							}
							// If the XML doesn't validate..
							else if(!General::validateXML($data, $errors, false, new XsltProcess)) {
								$writeToCache = false;
							}

							// If the `$data` is invalid, return a result explaining why
							if($writeToCache === false) {
								$result = new XMLElement('errors');

								$result->setAttribute('valid', 'false');

								$result->appendChild(new XMLElement('error', __('Data returned is invalid.')));

								foreach($errors as $e) {
									if(strlen(trim($e['message'])) == 0) continue;
									$result->appendChild(new XMLElement('item', General::sanitize($e['message'])));
								}

								$result->appendChild($result);

								return $result;
							}
						}
						// If `$data` is empty, set the `force_empty_result` to true.
						else if(strlen($data) == 0){
							$this->_force_empty_result = true;
						}
					}

					// Failed to acquire a lock
					else {
						$result->appendChild(
							new XMLElement('error', __('The %s class failed to acquire a lock.', array('<code>Mutex</code>')))
						);
					}
				}

				// The cache is good, use it!
				else {
					$data = trim($cachedData['data']);
					$creation = DateTimeObj::get('c', $cachedData['creation']);
				}

				// If `$writeToCache` is set to false, invalidate the old cache if it existed.
				if(is_array($cachedData) && !empty($cachedData) && $writeToCache === false) {
					$data = trim($cachedData['data']);
					$isCacheValid = false;
					$creation = DateTimeObj::get('c', $cachedData['creation']);

					if(empty($data)) $this->_force_empty_result = true;
				}

				// If `force_empty_result` is false and `$result` is an instance of
				// XMLElement, build the `$result`.
				if(!$this->_force_empty_result && is_object($result)) {
					$proc = new XsltProcess;
					$ret = $proc->process($data, $xsl);

					if($proc->isErrors()) {
						$result->setAttribute('valid', 'false');
						$error = new XMLElement('error', __('Transformed XML is invalid.'));
						$result->appendChild($error);
						$errors = new XMLElement('errors');
						foreach($proc->getError() as $e) {
							if(strlen(trim($e['message'])) == 0) continue;
							$errors->appendChild(new XMLElement('item', General::sanitize($e['message'])));
						}
						$result->appendChild($errors);
					}

					else if(strlen(trim($ret)) == 0) {
						$this->_force_empty_result = true;
					}

					else {
						if($writeToCache) $cache->write($cache_id, $data, $this->dsParamCACHE);

						$result->setValue(PHP_EOL . str_repeat("\t", 2) . preg_replace('/([\r\n]+)/', "$1\t", $ret));
						$result->setAttribute('status', ($isCacheValid === true ? 'fresh' : 'stale'));
						$result->setAttribute('cache-id', $cache_id);
						$result->setAttribute('creation', $creation);
					}
				}
			}
			catch(Exception $e){
				$result->appendChild(new XMLElement('error', $e->getMessage()));
			}

			if($this->_force_empty_result) $result = $this->emptyXMLSet();

			return $result;
		}
	}

	return 'RemoteDatasource';