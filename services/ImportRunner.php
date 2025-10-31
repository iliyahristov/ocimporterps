<?php
class ImportRunner
{
    private $conn; private $batch; private $id_lang;
    private $imgMode; private $imgBase; private $imgPath;

    public function __construct()
    {
        $this->conn = new OpenCartConnection();
        $this->batch = max(1, (int)Configuration::get('OCIMP_BATCH'));
        $this->id_lang = (int)Configuration::get('PS_LANG_DEFAULT');
        $this->imgMode = Configuration::get('OCIMP_IMG_MODE') ?: 'url';
        $this->imgBase = Configuration::get('OCIMP_IMG_BASEURL') ?: '';
        $this->imgPath = Configuration::get('OCIMP_IMG_BASEPATH') ?: '';
    }

    public function dispatch($entity, $offset=0)
    {
        switch ($entity) {
            case 'category': return $this->importCategories($offset);
            case 'product':  return $this->importProducts($offset);
            case 'customer': return $this->importCustomers($offset);
            case 'order':    return $this->importOrders($offset);
            default: throw new Exception('Unknown entity');
        }
    }

    public function totals()
    {
        return [
            'category' => $this->conn->count('category'),
            'product'  => $this->conn->count('product'),
            'customer' => $this->conn->count('customer'),
            'order'    => $this->conn->count('order'),
        ];
    }

    public function ensureManufacturer($name)
    {
        if (!$name) { return 0; }
        $id = (int)Db::getInstance()->getValue('SELECT id_manufacturer FROM '._DB_PREFIX_."manufacturer WHERE name='".pSQL($name)."' LIMIT 1");
        if ($id) return $id;
        $m = new Manufacturer();
        $m->name = $name; $m->active = 1;
        if ($m->add()) { return (int)$m->id; }
        return 0;
    }

    private function attachImages(Product $prod, array $images)
    {
        foreach ($images as $imgRelPath) {
            if (!$imgRelPath) continue;
            $tmp = tempnam(_PS_TMP_IMG_DIR_, 'ocimg');
            $source = $this->imgMode === 'fs' ? rtrim($this->imgPath,'/').'/'.ltrim($imgRelPath,'/') : rtrim($this->imgBase,'/').'/'.ltrim($imgRelPath,'/');
            $bin = @Tools::file_get_contents($source);
            if ($bin === false) { continue; }
            file_put_contents($tmp, $bin);
            $image = new Image();
            $image->id_product = (int)$prod->id;
            $image->position = Image::getHighestPosition($prod->id) + 1;
            $image->cover = !Image::getCover($prod->id);
            if ($image->add()) {
                $path = $image->getPathForCreation().'.jpg';
                if (!file_exists(dirname($path))) { @mkdir(dirname($path), 0775, true); }
                ImageManager::resize($tmp, $path);
                foreach (ImageType::getImagesTypes('products') as $type) {
                    ImageManager::resize($tmp, $image->getPathForCreation().'-'.$type['name'].'.jpg');
                }
            }
            @unlink($tmp);
        }
    }

    private function importCategories($offset)
    {
        $rows = $this->conn->fetchChunk($this->conn->categories(1), [':lang'=>1], $this->batch, $offset);
        forEach ($rows as $r) {
            $cat = new Category(OcMappers::resolveMappedId('category', (int)$r['category_id']));
            $isNew = !Validate::isLoadedObject($cat);
            if ($isNew) { $cat = new Category(); }
            $mapped = OcMappers::mapCategory($r, $this->id_lang);
            foreach ($mapped as $k=>$v) { $cat->$k = $v; }
            if ($isNew && empty($mapped['id_parent'])) { $cat->id_parent = (int)Category::getRootCategory()->id; }
            if ($cat->save()) {
                OcMappers::rememberMap('category', (int)$r['category_id'], (int)$cat->id);
            }
        }
        return count($rows);
    }

    private function importProducts($offset)
    {
        $rows = $this->conn->fetchChunk($this->conn->products(1), [':lang'=>1], $this->batch, $offset);
        $extraImgs = $this->conn->fetchChunk($this->conn->productImages(), [], 100000, 0);
        $extraCat  = $this->conn->fetchChunk($this->conn->productCategories(), [], 100000, 0);

        foreach ($rows as $r) {
            $id_manufacturer = $this->ensureManufacturer($r['manufacturer']);

            $prod = new Product(OcMappers::resolveMappedId('product', (int)$r['product_id']));
            $isNew = !Validate::isLoadedObject($prod);
            if ($isNew) { $prod = new Product(); }
            $mapped = OcMappers::mapProduct($r, $this->id_lang);
            foreach ($mapped as $k=>$v) { if ($k!=='quantity') { $prod->$k = $v; } }
            if ($id_manufacturer) { $prod->id_manufacturer = $id_manufacturer; }

            if ($prod->save()) {
                StockAvailable::setQuantity((int)$prod->id, 0, (int)$mapped['quantity']);
                OcMappers::rememberMap('product', (int)$r['product_id'], (int)$prod->id);

                $imgs = []
                if (!empty($r['main_image'])) { $imgs[] = $r['main_image']; }
                foreach ($extraImgs as $ex) { if ((int)$ex['product_id']===(int)$r['product_id']) { $imgs.append($ex['image']) } }
                $this->attachImages($prod, $imgs);

                $ids = [];
                foreach ($extraCat as $row) {
                    if ((int)$row['product_id']===(int)$r['product_id']) {
                        $cid = OcMappers::resolveMappedId('category', (int)$row['category_id']);
                        if ($cid) { $ids[] = (int)$cid; }
                    }
                }
                if ($ids) { $prod->addToCategories(array_unique($ids)); }
            }
        }
        return count($rows);
    }

    private function importCustomers($offset)
    {
        $rows = $this->conn->fetchChunk($this->conn->customers(), [], $this->batch, $offset);
        foreach ($rows as $r) {
            $id = OcMappers::resolveMappedId('customer', (int)$r['customer_id']);
            $cust = new Customer($id);
            $isNew = !Validate::isLoadedObject($cust);
            if ($isNew) { $cust = new Customer(); $cust->passwd = Tools::encrypt(Tools::passwdGen(12)); }
            $cust->email = $r['email'];
            $cust->firstname = $r['firstname'];
            $cust->lastname = $r['lastname'];
            $cust->newsletter = (int)$r['newsletter'];
            $cust->active = (int)$r['status'] === 1;
            if ($cust->save()) {
                OcMappers::rememberMap('customer', (int)$r['customer_id'], (int)$cust->id);
            }
        }
        return count($rows);
    }

    private function importOrders($offset)
    {
        $rows = $this->conn->fetchChunk($this->conn->orders(), [], $this->batch, $offset);
        $op = $this->conn->fetchChunk($this->conn->orderProducts(), [], 100000, 0);
        foreach ($rows as $r) {
            $existing = OcMappers::resolveMappedId('order', (int)$r['order_id']);
            if ($existing) { continue; }

            $id_customer = OcMappers::resolveMappedId('customer', (int)$r['customer_id']);
            if (!$id_customer) {
                $c = new Customer();
                $c->email = $r['email'] ?: ('guest'.(int)$r['order_id'].'@example.tld');
                $c->firstname = $r['firstname'] ?: 'OC';
                $c->lastname = $r['lastname'] ?: 'Customer';
                $c->passwd = Tools::encrypt(Tools::passwdGen(12));
                $c->active = 1; $c->add();
                $id_customer = (int)$c->id;
            }

            $id_country = Country::getByIso($r['shipping_country']) ?: (int)Configuration::get('PS_COUNTRY_DEFAULT');
            $addr = new Address();
            $addr->id_customer = $id_customer;
            $addr->address1 = $r['shipping_address_1'] ?: $r['payment_address_1'];
            $addr->city = $r['shipping_city'] ?: $r['payment_city'];
            $addr->alias = 'OC Order #'.$r['order_id'];
            $addr->id_country = $id_country;
            $addr->firstname = $r['firstname'] ?: 'OC';
            $addr->lastname = $r['lastname'] ?: 'Customer';
            $addr->phone = $r['telephone'];
            $addr->add();

            $id_currency = (int)Currency::getIdByIsoCode($r['currency_code']) ?: (int)Configuration::get('PS_CURRENCY_DEFAULT');

            $cart = new Cart();
            $cart->id_customer = $id_customer;
            $cart->id_address_delivery = (int)$addr->id;
            $cart->id_address_invoice = (int)$addr->id;
            $cart->id_currency = $id_currency;
            $cart->id_lang = $this->id_lang;
            $cart->add();

            foreach ($op as $line) {
                if ((int)$line['order_id'] !== (int)$r['order_id']) continue;
                $pid = OcMappers::resolveMappedId('product', (int)$line['product_id']);
                if (!$pid) {
                    $p = new Product();
                    $p->name = [$this->id_lang => $line['name'] ?: ('OC Item '.$line['product_id'])];
                    $p->price = (float)$line['price'];
                    $p->link_rewrite = [$this->id_lang => Tools::link_rewrite($line['name'] ?: ('oc-item-'.$line['product_id']))];
                    $p->active = 0; $p->add();
                    $pid = (int)$p->id;
                }
                $cart->updateQty((int)$line['quantity'], (int)$pid);
            }

            $moduleName = 'ps_checkpayment';
            $paymentModule = Module::getInstanceByName($moduleName);
            if (!Validate::isLoadedObject($paymentModule)) {
                $moduleName = 'ps_wirepayment';
                $paymentModule = Module::getInstanceByName($moduleName);
            }
            $currentState = (int)Configuration::get('PS_OS_PAYMENT');
            $paymentModule->validateOrder(
                (int)$cart->id,
                $currentState,
                (float)$r['total'],
                'OpenCart import',
                'OC order #'.(int)$r['order_id'],
                [],
                (int)$id_currency,
                false,
                (int)$id_customer
            );
            $orderId = (int)$paymentModule->currentOrder;
            if ($orderId) { OcMappers::rememberMap('order', (int)$r['order_id'], $orderId); }
        }
        return count($rows);
    }
}
