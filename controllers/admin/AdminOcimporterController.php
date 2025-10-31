<?php
class AdminOcimporterController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        $this->display = 'view';
        parent::__construct();
    }

    public function initContent()
    {
        parent::initContent();
        $this->context->smarty->assign($this->getTemplateVars());
        $this->setTemplate('admin/configure.tpl');
    }

    protected function getTemplateVars()
    {
        $runner = new ImportRunner();
        $totals = $runner->totals();
        return [
            'form_action' => self::$currentIndex.'&configure='.$this->module->name.'&token='.$this->token,
            'ajax_url' => $this->context->link->getAdminLink('AdminOcimporter', true, [], ['ajax'=>1]),
            'config' => [
                'host' => Configuration::get('OCIMP_DB_HOST'),
                'db'   => Configuration::get('OCIMP_DB_NAME'),
                'user' => Configuration::get('OCIMP_DB_USER'),
                'pass' => Configuration::get('OCIMP_DB_PASS'),
                'pref' => Configuration::get('OCIMP_DB_PREFIX'),
                'batch'=> (int)Configuration::get('OCIMP_BATCH'),
                'img_mode' => Configuration::get('OCIMP_IMG_MODE') ?: 'url',
                'img_baseurl' => Configuration::get('OCIMP_IMG_BASEURL'),
                'img_basepath' => Configuration::get('OCIMP_IMG_BASEPATH'),
            ],
            'totals' => $totals,
        ];
    }

    public function postProcess()
    {
        if (Tools::isSubmit('saveOcimp')) {
            Configuration::updateValue('OCIMP_DB_HOST', Tools::getValue('ocimp_host'));
            Configuration::updateValue('OCIMP_DB_NAME', Tools::getValue('ocimp_db'));
            Configuration::updateValue('OCIMP_DB_USER', Tools::getValue('ocimp_user'));
            Configuration::updateValue('OCIMP_DB_PASS', Tools::getValue('ocimp_pass'));
            Configuration::updateValue('OCIMP_DB_PREFIX', Tools::getValue('ocimp_pref'));
            Configuration::updateValue('OCIMP_BATCH', (int)Tools::getValue('ocimp_batch'));
            Configuration::updateValue('OCIMP_IMG_MODE', Tools::getValue('ocimp_img_mode'));
            Configuration::updateValue('OCIMP_IMG_BASEURL', Tools::getValue('ocimp_img_baseurl'));
            Configuration::updateValue('OCIMP_IMG_BASEPATH', Tools::getValue('ocimp_img_basepath'));
            $this->confirmations[] = $this->l('Настройките са записани.');
        }

        if (Tools::getValue('ajax')) {
            $this->ajaxProcess();
        }
    }

    protected function ajaxProcess()
    {
        header('Content-Type: application/json');
        try {
            $entity = Tools::getValue('entity');
            $offset = (int)Tools::getValue('offset', 0);
            $runner = new ImportRunner();
            $count = $runner->dispatch($entity, $offset);
            die(json_encode(['ok'=>true,'count'=>$count]));
        } catch (Exception $e) {
            http_response_code(500);
            die(json_encode(['ok'=>false,'error'=>$e->getMessage()]));
        }
    }
}
