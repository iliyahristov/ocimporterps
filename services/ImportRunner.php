<?php
class ImportRunner
{
    private $conn; private $batch; private $id_lang; private $ocLangId;
    private $imgMode; private $imgBase; private $imgPath;
    private $context; private $shopId; private $shopGroupId;

    public function __construct()
    {
        $this->context = Context::getContext();
        $shop = $this->context->shop;
        $this->shopId = $shop ? (int)$shop->id : (int)Configuration::get('PS_SHOP_DEFAULT');
        $this->shopGroupId = $shop ? (int)$shop->id_shop_group : (int)Configuration::get('PS_SHOP_GROUP_DEFAULT');
        $this->conn = new OpenCartConnection();
        $this->batch = max(1, (int)Configuration::get('OCIMP_BATCH'));
        $this->id_lang = (int)Configuration::get('PS_LANG_DEFAULT');
        $this->ocLangId = (int)Configuration::get('OCIMP_OC_LANG') ?: 1;
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

    private function mapOrderStatus($ocStatusId)
    {
        $ocStatusId = (int)$ocStatusId;
        $mapping = [
            1 => (int)Configuration::get('PS_OS_PREPARATION'),
            2 => (int)Configuration::get('PS_OS_PAYMENT'),
            3 => (int)Configuration::get('PS_OS_SHIPPING'),
            5 => (int)Configuration::get('PS_OS_DELIVERED'),
            7 => (int)Configuration::get('PS_OS_CANCELED'),
            9 => (int)Configuration::get('PS_OS_REFUND'),
        ];
        $state = $mapping[$ocStatusId] ?? (int)Configuration::get('PS_OS_PAYMENT');
        if (!$state) {
            $state = (int)Configuration::get('PS_OS_PAYMENT');
        }
        return $state;
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

    private function attachImages(Product $prod, array $images, $replaceExisting = false)
    {
        if ($replaceExisting) {
            $existing = $prod->getImages($this->id_lang);
            foreach ($existing as $img) {
                $image = new Image((int)$img['id_image']);
                $image->delete();
            }
        }

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
                if (method_exists($image, 'associateTo')) {
                    $shopIds = Shop::getContextListShopID();
                    if (!$shopIds) {
                        $defaultShopId = $this->shopId ?: (int)$prod->id_shop_default;
                        $shopIds = $defaultShopId ? [$defaultShopId] : [];
                    }
                    $image->associateTo($shopIds);
                }
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
        $rows = $this->conn->fetchChunk($this->conn->categories($this->ocLangId), [':lang'=>$this->ocLangId], $this->batch, $offset);
        foreach ($rows as $r) {
            $cat = new Category(OcMappers::resolveMappedId('category', (int)$r['category_id']));
            $isNew = !Validate::isLoadedObject($cat);
            if ($isNew) {
                $cat = new Category();
                if ($this->shopId) {
                    $cat->id_shop_default = $this->shopId;
                }
            }
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
        $rows = $this->conn->fetchChunk($this->conn->products($this->ocLangId), [':lang'=>$this->ocLangId], $this->batch, $offset);
        if (!$rows) {
            return 0;
        }
        $productIds = array_map('intval', array_column($rows, 'product_id'));
        $extraImgs = $this->conn->productImagesFor($productIds);
        $extraCat  = $this->conn->productCategoriesFor($productIds);

        $imagesByProduct = [];
        foreach ($extraImgs as $ex) {
            $pid = (int)$ex['product_id'];
            if (!isset($imagesByProduct[$pid])) {
                $imagesByProduct[$pid] = [];
            }
            if (!empty($ex['image'])) {
                $imagesByProduct[$pid][] = $ex['image'];
            }
        }

        $categoriesByProduct = [];
        foreach ($extraCat as $row) {
            $pid = (int)$row['product_id'];
            if (!isset($categoriesByProduct[$pid])) {
                $categoriesByProduct[$pid] = [];
            }
            $categoriesByProduct[$pid][] = (int)$row['category_id'];
        }

        foreach ($rows as $r) {
            $id_manufacturer = $this->ensureManufacturer($r['manufacturer']);

            $prod = new Product(OcMappers::resolveMappedId('product', (int)$r['product_id']));
            $isNew = !Validate::isLoadedObject($prod);
            if ($isNew) { $prod = new Product(); }
            $mapped = OcMappers::mapProduct($r, $this->id_lang);
            foreach ($mapped as $k=>$v) {
                if ($k !== 'quantity') {
                    $prod->$k = $v;
                }
            }
            if ($id_manufacturer) { $prod->id_manufacturer = $id_manufacturer; }

            $mappedCategoryIds = [];
            $ocCategoryIds = $categoriesByProduct[(int)$r['product_id']] ?? [];
            foreach ($ocCategoryIds as $ocCatId) {
                $cid = OcMappers::resolveMappedId('category', $ocCatId);
                if ($cid) {
                    $mappedCategoryIds[] = (int)$cid;
                }
            }
            $mappedCategoryIds = array_values(array_unique($mappedCategoryIds));
            $defaultCategoryId = $mappedCategoryIds ? (int)$mappedCategoryIds[0] : (int)Configuration::get('PS_HOME_CATEGORY');
            $prod->id_category_default = $defaultCategoryId ?: (int)Category::getRootCategory()->id;
            if ($this->shopId) {
                $prod->id_shop_default = $this->shopId;
            }

            if ($prod->save()) {
                StockAvailable::setQuantity((int)$prod->id, 0, (int)$mapped['quantity'], $this->shopId ?: null);
                OcMappers::rememberMap('product', (int)$r['product_id'], (int)$prod->id);

                if ($mappedCategoryIds) {
                    $prod->setWsCategories($mappedCategoryIds);
                } else {
                    $prod->setWsCategories([(int)$prod->id_category_default]);
                }

                $imgs = [];
                if (!empty($r['main_image'])) { $imgs[] = $r['main_image']; }
                if (!empty($imagesByProduct[(int)$r['product_id']])) {
                    $imgs = array_merge($imgs, $imagesByProduct[(int)$r['product_id']]);
                }
                if ($imgs) {
                    $currentImages = $prod->getImages($this->id_lang);
                    $replaceImages = !$isNew && !empty($currentImages);
                    $this->attachImages($prod, $imgs, $replaceImages);
                }
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

            $isoCountry = $r['shipping_iso_code_2'] ?: $r['payment_iso_code_2'];
            $id_country = $isoCountry ? (int)Country::getByIso($isoCountry) : 0;
            if (!$id_country) {
                $id_country = (int)Configuration::get('PS_COUNTRY_DEFAULT');
            }
            $addr = new Address();
            $addr->id_customer = $id_customer;
            $addr->address1 = $r['shipping_address_1'] ?: $r['payment_address_1'];
            $addr->address2 = $r['shipping_address_2'] ?: $r['payment_address_2'];
            $addr->city = $r['shipping_city'] ?: $r['payment_city'];
            $addr->alias = 'OC Order #'.$r['order_id'];
            $addr->id_country = $id_country;
            $addr->postcode = $r['shipping_postcode'] ?: $r['payment_postcode'];
            $addr->firstname = $r['firstname'] ?: 'OC';
            $addr->lastname = $r['lastname'] ?: 'Customer';
            $addr->company = $r['shipping_company'] ?: $r['payment_company'];
            $addr->phone = $r['telephone'];
            $stateName = $r['shipping_zone'] ?: $r['payment_zone'];
            if ($stateName && method_exists('State', 'getIdByName')) {
                $stateId = (int)State::getIdByName($stateName);
                if ($stateId) {
                    $addr->id_state = $stateId;
                }
            }
            $addr->add();

            $id_currency = (int)Currency::getIdByIsoCode($r['currency_code']) ?: (int)Configuration::get('PS_CURRENCY_DEFAULT');

            $cart = new Cart();
            $cart->id_customer = $id_customer;
            $cart->id_address_delivery = (int)$addr->id;
            $cart->id_address_invoice = (int)$addr->id;
            $cart->id_currency = $id_currency;
            $cart->id_lang = $this->id_lang;
            $cart->id_shop = $this->shopId;
            $cart->id_shop_group = $this->shopGroupId;
            $cart->id_carrier = (int)Configuration::get('PS_CARRIER_DEFAULT');
            $customer = new Customer($id_customer);
            $cart->secure_key = $customer->secure_key;
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
            $currentState = $this->mapOrderStatus($r['order_status_id']);
            $this->context->cart = $cart;
            $this->context->customer = $customer;
            $this->context->currency = new Currency($id_currency);
            $this->context->shop = new Shop($cart->id_shop);

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
            if ($orderId) {
                $order = new Order($orderId);
                if (Validate::isLoadedObject($order) && !empty($r['date_added'])) {
                    $order->date_add = pSQL($r['date_added']);
                    $order->date_upd = pSQL($r['date_added']);
                    $order->update();
                }
                OcMappers::rememberMap('order', (int)$r['order_id'], $orderId);
            }
        }
        return count($rows);
    }
}
