# Remote Datasource

#### Version 2.1

The Remote Datasource allows you to consume XML, JSON, CSV and TXT sources in Symphony. This extension aims to build upon the Dynamic XML datasource functionality provided in Symphony to allow better cache control, the automatic discovery of namespaces and more flexibility.

## Installation

1. Install this extension by copying `/remote_datasource` folder to your `/extensions` folder. Then navigate to the System > Extensions page in the Symphony backend page, select the Remote Datasource extension and then apply the "Enable/Install".

2. Create a new Remote Datasource via the Datasource Editor, choosing Remote Datasource from the Source dropdown (it's under __From extensions)

## API

If you need to add custom php code in your Data Source, there is two methods that you can override in your DataSource sub-class:

````php
/**
 * This methods allows custom remote data source to set other
 * properties on the HTTP gateway, like Authentication or other
 * parameters. This method is call just before the `exec` method.
 *
 * @param Gateway $gateway
 *  the Gateway object that will be use for the current HTTP request
 *  passed by reference
 */
public static function prepareGateway(&$gateway) {}

/**
 * This methods allows custom remote data source to read the returned
 * data before it becomes only available in the XML.
 *
 * @since Remote Datasource 2.0
 * @param string $data
 *  the parsed xml string data returned by the Gateway by reference
 */
public function exposeData(&$data) {}
````
