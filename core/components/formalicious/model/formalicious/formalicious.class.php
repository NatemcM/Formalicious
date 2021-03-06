<?php

/**
 * Formalicious
 *
 * Copyright 2019 by Sterc <modx@sterc.nl>
 */

class Formalicious
{
    /**
     * @access public.
     * @var modX.
     */
    public $modx;

    /**
     * @access public.
     * @var Array.
     */
    public $config = [];

    /**
     * @access public.
     * @param modX $modx.
     * @param Array $config.
     */
    public function __construct(modX &$modx, array $config = [])
    {
        $this->modx =& $modx;

        $corePath   = $this->modx->getOption('formalicious.core_path', $config, $this->modx->getOption('core_path') . 'components/formalicious/');
        $assetsUrl  = $this->modx->getOption('formalicious.assets_url', $config, $this->modx->getOption('assets_url') . 'components/formalicious/');
        $assetsPath = $this->modx->getOption('formalicious.assets_path', $config, $this->modx->getOption('assets_path') . 'components/formalicious/');

        $this->config = array_merge([
            'namespace'             => 'formalicious',
            'lexicons'              => ['formalicious:default'],
            'base_path'             => $corePath,
            'core_path'             => $corePath,
            'model_path'            => $corePath . 'model/',
            'processors_path'       => $corePath . 'processors/',
            'elements_path'         => $corePath . 'elements/',
            'chunks_path'           => $corePath . 'elements/chunks/',
            'plugins_path'          => $corePath . 'elements/plugins/',
            'snippets_path'         => $corePath . 'elements/snippets/',
            'templates_path'        => $corePath . 'templates/',
            'assets_path'           => $assetsPath,
            'js_url'                => $assetsUrl . 'js/',
            'css_url'               => $assetsUrl . 'css/',
            'assets_url'            => $assetsUrl,
            'connector_url'         => $assetsUrl . 'connector.php',
            'version'               => '2.0.3',
            'branding_url'          => $this->modx->getOption('formalicious.branding_url'),
            'branding_help_url'     => $this->modx->getOption('formalicious.branding_url_help'),
            'save_forms'            => $this->modx->getOption('formalicious.saveforms'),
            'save_forms_prefix'     => $this->modx->getOption('formalicious.saveforms_prefix'),
            'disallowed_hooks'      => explode(',', $this->modx->getOption('formalicious.disallowed_hooks')),
            'preview_css'           => $this->modx->getOption('formalicious.preview_css'),
            'formit_manager_link'   => $this->getFormItManagerLink(),
            'permissions'           => [
                'admin'                 => $this->modx->hasPermission('formalicious_admin'),
                'tab_fields'            => $this->modx->hasPermission('formalicious_tab_fields'),
                'tab_advanced'          => $this->modx->hasPermission('formalicious_tab_advanced')
            ]
        ], $config);

        $this->modx->addPackage('formalicious', $this->config['model_path']);

        if (is_array($this->config['lexicons'])) {
            foreach ($this->config['lexicons'] as $lexicon) {
                $this->modx->lexicon->load($lexicon);
            }
        } else {
            $this->modx->lexicon->load($this->config['lexicons']);
        }
    }

    /**
     * @access public.
     * @return String|Boolean.
     */
    public function getHelpUrl()
    {
        $url = $this->getOption('branding_url_help');

        if (!empty($url)) {
            return $url . '?v=' . $this->config['version'];
        }

        return false;
    }

    /**
     * @access public.
     * @return String|Boolean.
     */
    public function getBrandingUrl()
    {
        $url = $this->getOption('branding_url');

        if (!empty($url)) {
            return $url;
        }

        return false;
    }

    /**
     * @access public.
     * @param String $key.
     * @param Array $options.
     * @param Mixed $default.
     * @return Mixed.
     */
    public function getOption($key, array $options = [], $default = null)
    {
        if (isset($options[$key])) {
            return $options[$key];
        }

        if ($this->config[$key]) {
            return $this->config[$key];
        }

        return $this->modx->getOption($this->config['namespace'] . '.' . $key, $options, $default);
    }

    /**
     * @access public.
     * @return String.
     */
    public function getFormItManagerLink()
    {
        $menu = $this->modx->getObject('modMenu', [
            'text' => 'formit'
        ]);

        if ($menu) {
            return $this->modx->getOption('manager_url', null, MODX_MANAGER_URL) . '?' . http_build_query([
                'a' => $menu->get('action'),
                'namespace' => $menu->get('namespace')
            ]);
        }

        return '';
    }

    public function f() {
        // Only run if we're in the manager
        if (!$this->modx->context || $this->modx->context->get('key') !== 'mgr') {
            return;
        }

        $c = $this->modx->newQuery('transport.modTransportPackage', array('package_name' => __CLASS__));
        $c->innerJoin('transport.modTransportProvider', 'modTransportProvider', 'modTransportProvider.id = modTransportPackage.provider');
        $c->select('modTransportProvider.service_url');
        $c->sortby('modTransportPackage.created', 'desc');
        $c->limit(1);
        if ($c->prepare() && $c->stmt->execute()) {
            $url = $c->stmt->fetchColumn();
            if (stripos($url, 'modstore')) {
                $this->ms();
                return;
            }
        }

        $this->mm();
    }


    /**
     * @return bool
     */
    protected function ms() {
        $result = true;
        $key = strtolower(__CLASS__);
        /** @var modDbRegister $registry */
        $registry = $this->modx->getService('registry', 'registry.modRegistry')
            ->getRegister('user', 'registry.modDbRegister');
        $registry->connect();
        $registry->subscribe('/modstore/' . md5($key));
        if ($res = $registry->read(array('poll_limit' => 1, 'remove_read' => false))) {
            return $res[0];
        }
        $c = $this->modx->newQuery('transport.modTransportProvider', array('service_url:LIKE' => '%modstore%'));
        $c->select('username,api_key');
        /** @var modRest $rest */
        $rest = $this->modx->getService('modRest', 'rest.modRest', '', array(
            'baseUrl' => 'https://modstore.pro/extras',
            'suppressSuffix' => true,
            'timeout' => 1,
            'connectTimeout' => 1,
            'format' => 'xml',
        ));

        if ($rest) {
            $level = $this->modx->getLogLevel();
            $this->modx->setLogLevel(modX::LOG_LEVEL_FATAL);
            /** @var RestClientResponse $response */
            $response = $rest->get('stat', array(
                'package' => $key,
                'host' => @$_SERVER['HTTP_HOST'],
                'keys' => $c->prepare() && $c->stmt->execute()
                    ? $c->stmt->fetchAll(PDO::FETCH_ASSOC)
                    : array(),
            ));
            $result = $response->process() == 'true';
            $this->modx->setLogLevel($level);
        }
        $registry->subscribe('/modstore/');
        $registry->send('/modstore/', array(md5($key) => $result), array('ttl' => 3600 * 24));

        return $result;
    }


    protected function mm() {
        // Get the public key from the .pubkey file contained in the package directory
        $pubKeyFile = $this->config['corePath'] . '.pubkey';
        $key = file_exists($pubKeyFile) ? file_get_contents($pubKeyFile) : '';
        $domain = $this->modx->getOption('http_host');
        if (strpos($key, '@@') !== false) {
            $pos = strpos($key, '@@');
            $domain = substr($key, 0, $pos);
            $key = substr($key, $pos + 2);
        }
        $check = false;
        // No key? That's a really good reason to check :)
        if (empty($key)) {
            $check = true;
        }
        // Doesn't the domain in the key file match the current host? Then we should get that sorted out.
        if ($domain !== $this->modx->getOption('http_host')) {
            $check = true;
        }
        // the .pubkey_c file contains a unix timestamp saying when the pubkey was last checked
        $modified = file_exists($pubKeyFile . '_c') ? file_get_contents($pubKeyFile . '_c') : false;
        if (!$modified ||
            $modified < (time() - (60 * 60 * 24 * 7)) ||
            $modified > time()) {
            $check = true;
        }
        if ($check) {
            $provider = false;
            $c = $this->modx->newQuery('transport.modTransportPackage');
            $c->where(array(
                'signature:LIKE' => 'formalicious-%',
            ));
            $c->sortby('installed', 'DESC');
            $c->limit(1);
            $package = $this->modx->getObject('transport.modTransportPackage', $c);
            if ($package instanceof modTransportPackage) {
                $provider = $package->getOne('Provider');
            }
            if (!$provider) {
                $provider = $this->modx->getObject('transport.modTransportProvider', array(
                    'service_url' => 'https://rest.modmore.com/'
                ));
            }
            if ($provider instanceof modTransportProvider) {
                $this->modx->setOption('contentType', 'default');
                // The params that get sent to the provider for verification
                $params = array(
                    'key' => $key,
                    'package' => 'formalicious',
                );
                // Fire it off and see what it gets back from the XML..
                $response = $provider->request('license', 'GET', $params);
                $xml = $response->toXml();
                $valid = (int)$xml->valid;
                // If the key is found to be valid, set the status to true
                if ($valid) {
                    // It's possible we've been given a new public key (typically for dev licenses or when user has unlimited)
                    // which we will want to update in the pubkey file.
                    $updatePublicKey = (bool)$xml->update_pubkey;
                    if ($updatePublicKey > 0) {
                        file_put_contents($pubKeyFile,
                            $this->modx->getOption('http_host') . '@@' . (string)$xml->pubkey);
                    }
                    file_put_contents($pubKeyFile . '_c', time());
                    return;
                }
                // If the key is not valid, we have some more work to do.
                $message = (string)$xml->message;
                $age = (int)$xml->case_age;
                $url = (string)$xml->case_url;
                $warning = false;
                if ($age >= 7) {
                    $warning = <<<HTML
    var warning = '<div style="width: 100%;border: 1px solid #dd0000;background-color: #F9E3E3;padding: 1em;margin-top: 1em; font-weight: bold; box-sizing: border-box;">';
    warning += '<a href="$url" style="float:right; margin-left: 1em;" target="_blank" class="x-btn">Fix the license</a>The Formalicious license on this site is invalid. Please click the button on the right to correct the problem. Error: {$message}';
    warning += '</div>';
HTML;
                } elseif ($age >= 2) {
                    $warning = <<<HTML
    var warning = '<div style="width: 100%;border: 1px solid #dd0000;background-color: #F9E3E3;padding: 1em;margin-top: 1em; box-sizing: border-box;">';
    warning += '<a href="$url" style="float:right; margin-left: 1em;" target="_blank" class="x-btn">Fix the license</a>Oops, there is an issue with the Formalicious license. Perhaps your site recently moved to a new domain, or the license was reset? Either way, please click the button on the right or contact your development team to correct the problem.';
    warning += '</div>';
HTML;
                }
                if ($warning) {
                    $output = <<<HTML
    <script type="text/javascript">
    {$warning}
    function showFormaliciousWarning() {
        setTimeout(function() {
            var fAdded = false,
                homePanel = Ext.getCmp('formalicious-panel-home'),
                adminPanel = Ext.getCmp('formalicious-panel-admin'),
                updatePanel = Ext.getCmp('formalicious-panel-update');
            
            if (homePanel) {
                homePanel.insert(1,{xtype: 'panel', html: warning, bodyStyle: 'margin-bottom: 1em'});
                fAdded = true;
            }
            else if (adminPanel) {
                adminPanel.insert(1,{xtype: 'panel', html: warning, bodyStyle: 'margin-bottom: 1em'});
                fAdded = true;
            }
            else if (updatePanel) {
                updatePanel.insert(1,{xtype: 'panel', html: warning, bodyStyle: 'margin-bottom: 1em'});
                fAdded = true;
            }
            
            if (!fAdded) {
                setTimeout(showFormaliciousWarning, 300);
            }
        }, 300);
    }
    showFormaliciousWarning();
    </script>
HTML;
                    if ($this->modx->controller instanceof modManagerController) {
                        $this->modx->controller->addHtml($output);
                    } else {
                        $this->modx->regClientHTMLBlock($output);
                    }
                }
            }
            else {
                $this->modx->log(modX::LOG_LEVEL_ERROR, 'UNABLE TO VERIFY MODMORE LICENSE - PROVIDER NOT FOUND!');
            }
        }
    }
}
