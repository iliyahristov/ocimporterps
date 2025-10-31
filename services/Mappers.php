<?php
class OcMappers
{
    public static function mapProduct(array $row, $id_lang)
    {
        return [
            'name' => [$id_lang => $row['name'] ?: ('Product '.$row['product_id'])],
            'link_rewrite' => [$id_lang => Tools::link_rewrite($row['name'] ?: $row['model'])],
            'description' => [$id_lang => $row['description'] ?: ''],
            'meta_title' => [$id_lang => $row['meta_title'] ?: ''],
            'meta_description' => [$id_lang => $row['meta_description'] ?: ''],
            'price' => (float)$row['price'],
            'reference' => $row['sku'] ?: $row['model'],
            'active' => (int)$row['status'] === 1,
            'quantity' => (int)$row['quantity'],
        ];
    }

    public static function mapCategory(array $row, $id_lang)
    {
        return [
            'name' => [$id_lang => $row['name'] ?: ('Category '.$row['category_id'])],
            'link_rewrite' => [$id_lang => Tools::link_rewrite($row['name'] ?: 'cat-'.$row['category_id'])],
            'description' => [$id_lang => $row['description'] ?: ''],
            'meta_title' => [$id_lang => $row['meta_title'] ?: ''],
            'meta_description' => [$id_lang => $row['meta_description'] ?: ''],
            'active' => (int)$row['status'] === 1,
            'id_parent' => (int)$row['parent_id'] ? self::resolveMappedId('category', (int)$row['parent_id']) : (int)Category::getRootCategory()->id,
        ];
    }

    public static function resolveMappedId($entity, $source_id)
    {
        $sql = 'SELECT ps_id FROM '._DB_PREFIX_.'ocimp_map WHERE entity="'.pSQL($entity).'" AND source_id='.(int)$source_id;
        $id = (int)Db::getInstance()->getValue($sql);
        return $id ?: 0;
    }

    public static function rememberMap($entity, $source_id, $ps_id)
    {
        Db::getInstance()->insert('ocimp_map', [
            'entity' => pSQL($entity),
            'source_id' => (int)$source_id,
            'ps_id' => (int)$ps_id,
        ], false, true, Db::ON_DUPLICATE_KEY);
    }
}
