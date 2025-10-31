<?php
if (!defined('_PS_VERSION_')) { exit; }

class Ocimporterps extends Module
{
    public function __construct()
    {
        $this->name = 'ocimporterps';
        $this->tab = 'migration_tools';
        $this->version = '0.2.1';
        $this->author = 'kaielectric.com';
        $this->need_instance = 0;
        $this->bootstrap = true;
        parent::__construct();
        $this->displayName = $this->l('OpenCart 3 → PrestaShop Importer');
        $this->description = $this->l('Импорт на категории, продукти, клиенти и поръчки от OpenCart 3.0.3.3');
    }

    public function install()
    {
        if (!parent::install()) { return false; }
        if (!$this->installSql()) { return false; }
        Configuration::updateValue('OCIMP_DB_HOST', 'localhost');
        Configuration::updateValue('OCIMP_DB_NAME', 'opencart');
        Configuration::updateValue('OCIMP_DB_USER', 'root');
        Configuration::updateValue('OCIMP_DB_PASS', '');
        Configuration::updateValue('OCIMP_DB_PREFIX', 'oc_');
        Configuration::updateValue('OCIMP_BATCH', 50);
        Configuration::updateValue('OCIMP_IMG_MODE', 'url');
        Configuration::updateValue('OCIMP_IMG_BASEURL', '');
        Configuration::updateValue('OCIMP_IMG_BASEPATH', '');
        $this->installTab('AdminParentModulesSf', 'AdminOcimporter', 'OC Importer');
        return true;
    }

    public function uninstall()
    {
        $this->uninstallTab('AdminOcimporter');
        $this->uninstallSql();
        return parent::uninstall();
    }

    protected function installSql()
    {
        $sql = str_replace('PREFIX_', _DB_PREFIX_, file_get_contents(__DIR__.'/sql/install.sql'));
        return Db::getInstance()->executeMultiple($sql);
    }

    protected function uninstallSql()
    {
        $sql = str_replace('PREFIX_', _DB_PREFIX_, file_get_contents(__DIR__.'/sql/uninstall.sql'));
        return Db::getInstance()->executeMultiple($sql);
    }

    protected function installTab($parent, $className, $name)
    {
        $tab = new Tab();
        $tab->class_name = $className;
        $tab->module = $this->name;
        $tab->id_parent = (int)Tab::getIdFromClassName($parent);
        if (!$tab->id_parent) {
            // fallback към главно меню "IMPROVE" ако AdminParentModulesSf липсва
            $tab->id_parent = 0;
        }
        foreach (Language::getLanguages(false) as $lang) {
            $tab->name[$lang['id_lang']] = $name;
        }
        return $tab->add();
    }

    protected function uninstallTab($className)
    {
        $idTab = (int)Tab::getIdFromClassName($className);
        if ($idTab) { $tab = new Tab($idTab); return $tab->delete(); }
        return true;
    }
}
