<?php

class moduleloadedRoute {
    private readonly array $config;
    private readonly globalHTML $globalHTML;
    private readonly staffAccountFromSession $staffSession;
    private moduleEngine $moduleEngine;

    public function __construct(
        array $config,
        globalHTML $globalHTML,
        staffAccountFromSession $staffSession,
        moduleEngine $moduleEngine
    ) {
        $this->config = $config;
        $this->globalHTML = $globalHTML;
        $this->staffSession = $staffSession;
        $this->moduleEngine = $moduleEngine;
    }

    /* Displays loaded module information */
    public function listModules(): void {
        $dat = '';
        
        $this->globalHTML->head($dat);

        $roleLevel = $this->staffSession->getRoleLevel();
        $links = '[<a href="' . $this->config['PHP_SELF2'] . '?' . time() . '">' . _T('return') . '</a>]';
        $this->moduleEngine->useModuleMethods('LinksAboveBar', array(&$links, 'modules', $roleLevel));

        $dat .= $links.'<h2 class="theading2">'._T('module_info_top').'</h2>
</div>

<div id="modules">
';

        /* Module Loaded */
        $dat .= _T('module_loaded') . '<ul>';
        foreach ($this->moduleEngine->getLoadedModules() as $m) {
            $dat .= '<li>' . $m . "</li>\n";
        }
        $dat .= "</ul><hr>\n";

        /* Module Information */
        $dat .= _T('module_info') . '<ul>';
        foreach ($this->moduleEngine->moduleInstance as $m) {
            $dat .= '<li>' . $m->getModuleName() . '<div>' . $m->getModuleVersionInfo() . "</div></li>\n";
        }
        $dat .= '</ul><hr>
        </div>
        ';
        $this->globalHTML->foot($dat);
        echo $dat;
    }
}
