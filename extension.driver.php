<?php

	require_once EXTENSIONS . '/remote_datasource/data-sources/datasource.remote.php';

	Class Extension_Remote_Datasource extends Extension {

		private static $provides = array();

		public static function registerProviders() {
			self::$provides = array(
				'data-sources' => array(
					'RemoteDatasource' => RemoteDatasource::getName()
				)
			);

			return true;
		}

		public static function providerOf($type = null) {
			self::registerProviders();

			if(is_null($type)) return self::$provides;

			if(!isset(self::$provides[$type])) return array();

			return self::$provides[$type];
		}

	}
