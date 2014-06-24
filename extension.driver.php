<?php

require_once EXTENSIONS . '/remote_datasource/data-sources/datasource.remote.php';

class Extension_Remote_Datasource extends Extension
{

    private static $provides = array();

    public static function registerProviders()
    {
        self::$provides = array(
            'data-sources' => array(
                'RemoteDatasource' => RemoteDatasource::getName()
            )
        );

        return true;
    }

    public static function providerOf($type = null)
    {
        self::registerProviders();

        if (is_null($type)) {
            return self::$provides;
        }

        if (!isset(self::$provides[$type])) {
            return array();
        }

        return self::$provides[$type];
    }

    public function getSubscribedDelegates()
    {
        return array(
            array(
                'page'		=> '/system/preferences/',
                'delegate'	=> 'AddCachingOpportunity',
                'callback'	=> 'addCachingOpportunity'
            )
        );
    }

    public function addCachingOpportunity($context)
    {
        $current_cache = Symphony::Configuration()->get('remotedatasource', 'caching');
        $label = Widget::Label(__('Remote Datasource'));

        $options = array();
        foreach ($context['available_caches'] as $handle => $cache_name) {
            $options[] = array($handle, ($current_cache == $handle || (!isset($current_cache) && $handle === 'database')), $cache_name);
        }

        $select = Widget::Select('settings[caching][remotedatasource]', $options, array('class' => 'picker'));
        $label->appendChild($select);

        $context['wrapper']->appendChild($label);
    }
}
