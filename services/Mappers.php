<?php
class OcMappers
{
    public static function mapProduct(array $row, $id_lang)
    {
        $name = $row['name'] ?: ('Product '.$row['product_id']);
        $linkRewrite = Tools::link_rewrite($row['name'] ?: $row['model']);
        if (!$linkRewrite) {
            $linkRewrite = 'product-'.$row['product_id'];
        }
        $shortDescription = Tools::truncate(strip_tags($row['description'] ?? ''), 512);
        return [
            'name' => [$id_lang => $name],
            'link_rewrite' => [$id_lang => $linkRewrite],
            'description' => [$id_lang => $row['description'] ?: ''],
            'description_short' => [$id_lang => $shortDescription],
            'meta_title' => [$id_lang => $row['meta_title'] ?: ''],
            'meta_description' => [$id_lang => $row['meta_description'] ?: ''],
            'meta_keywords' => [$id_lang => $row['meta_keyword'] ?: ''],
            'price' => max(0, (float)$row['price']),
            'reference' => $row['sku'] ?: $row['model'],
            'active' => (int)$row['status'] === 1,
            'quantity' => (int)$row['quantity'],
            'id_tax_rules_group' => 0,
            'visibility' => 'both',
            'available_for_order' => 1,
            'show_price' => 1,
        ];
    }

    public static function mapCategory(array $row, $id_lang)
    {
        $name = $row['name'] ?: ('Category '.$row['category_id']);
        $linkRewrite = Tools::link_rewrite($row['name'] ?: 'cat-'.$row['category_id']);
        if (!$linkRewrite) {
            $linkRewrite = 'cat-'.$row['category_id'];
        }
        $parentId = 0;
        if ((int)$row['parent_id'] > 0) {
            $parentId = self::resolveMappedId('category', (int)$row['parent_id']);
        }
        if (!$parentId) {
            $parentId = (int)Category::getRootCategory()->id;
        }
        return [
            'name' => [$id_lang => $name],
            'link_rewrite' => [$id_lang => $linkRewrite],
            'description' => [$id_lang => $row['description'] ?: ''],
            'meta_title' => [$id_lang => $row['meta_title'] ?: ''],
            'meta_description' => [$id_lang => $row['meta_description'] ?: ''],
            'active' => (int)$row['status'] === 1,
            'id_parent' => $parentId,
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
