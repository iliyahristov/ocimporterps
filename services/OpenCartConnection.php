<?php
class OpenCartConnection
{
    private $pdo; private $prefix;
    public function __construct()
    {
        $host = Configuration::get('OCIMP_DB_HOST');
        $db   = Configuration::get('OCIMP_DB_NAME');
        $user = Configuration::get('OCIMP_DB_USER');
        $pass = Configuration::get('OCIMP_DB_PASS');
        $this->prefix = Configuration::get('OCIMP_DB_PREFIX');
        $dsn = "mysql:host={$host};dbname={$db};charset=utf8mb4";
        $this->pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }
    public function prefix(){ return $this->prefix; }

    public function count($table, $where = '1')
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM `{$this->prefix}{$table}` WHERE {$where}");
        return (int)$stmt->fetchColumn();
    }

    public function fetchChunk($sql, $params, $limit, $offset)
    {
        $sql .= " LIMIT :limit OFFSET :offset";
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k=>$v) { $stmt->bindValue($k, $v); }
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function products($lang_id = 1){
        $p = $this->prefix;
        return "SELECT p.product_id, p.model, p.sku, p.price, p.quantity, p.status, p.image as main_image,
                       d.name, d.description, d.meta_title, d.meta_description, d.meta_keyword,
                       m.name AS manufacturer
                FROM `{$p}product` p
                JOIN `{$p}product_description` d ON d.product_id=p.product_id AND d.language_id=:lang
                LEFT JOIN `{$p}manufacturer` m ON m.manufacturer_id=p.manufacturer_id
                ORDER BY p.product_id ASC";
    }
    public function productImages(){
        $p = $this->prefix;
        return "SELECT product_id, image FROM `{$p}product_image` WHERE image IS NOT NULL ORDER BY sort_order";
    }
    public function productImagesFor(array $productIds)
    {
        if (!$productIds) { return []; }
        $p = $this->prefix;
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $sql = "SELECT product_id, image FROM `{$p}product_image` WHERE image IS NOT NULL AND product_id IN ($placeholders) ORDER BY sort_order";
        $stmt = $this->pdo->prepare($sql);
        foreach (array_values($productIds) as $index => $id) {
            $stmt->bindValue($index + 1, (int)$id, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function productCategories(){
        $p = $this->prefix;
        return "SELECT product_id, category_id FROM `{$p}product_to_category`";
    }
    public function productCategoriesFor(array $productIds)
    {
        if (!$productIds) { return []; }
        $p = $this->prefix;
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $sql = "SELECT product_id, category_id FROM `{$p}product_to_category` WHERE product_id IN ($placeholders)";
        $stmt = $this->pdo->prepare($sql);
        foreach (array_values($productIds) as $index => $id) {
            $stmt->bindValue($index + 1, (int)$id, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function categories($lang_id = 1){
        $p = $this->prefix;
        return "SELECT c.category_id, cd.name, cd.description, cd.meta_title, cd.meta_description,
                       c.parent_id, c.status
                FROM `{$p}category` c
                JOIN `{$p}category_description` cd ON cd.category_id=c.category_id AND cd.language_id=:lang
                ORDER BY c.category_id ASC";
    }
    public function customers(){
        $p = $this->prefix;
        return "SELECT customer_id, email, firstname, lastname, telephone, newsletter, status, date_added FROM `{$p}customer` ORDER BY customer_id ASC";
    }
    public function orders(){
        $p = $this->prefix;
        return "SELECT o.order_id, o.customer_id, o.firstname, o.lastname, o.email, o.telephone,
                       o.payment_company, o.payment_address_1, o.payment_address_2, o.payment_city, o.payment_postcode, o.payment_zone, o.payment_country,
                       o.payment_iso_code_2, o.payment_zone_id,
                       o.shipping_company, o.shipping_address_1, o.shipping_address_2, o.shipping_city, o.shipping_postcode, o.shipping_zone, o.shipping_country,
                       o.shipping_iso_code_2, o.shipping_zone_id,
                       o.total, o.currency_code, o.date_added, o.order_status_id
                FROM `{$p}order` o ORDER BY o.order_id ASC";
    }
    public function orderProducts(){
        $p = $this->prefix;
        return "SELECT order_id, product_id, name, model, quantity, price, total, tax
                FROM `{$p}order_product` ORDER BY order_product_id ASC";
    }
}
